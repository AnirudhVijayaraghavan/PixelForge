"""Run release checks against an entirely new loopback PostgreSQL cluster."""
import argparse
import base64
import os
from pathlib import Path
import secrets
import shutil
import socket
import subprocess
import tempfile

ROOT = Path(__file__).resolve().parents[2]


def run(binary_dir, mode):
    with tempfile.TemporaryDirectory(prefix="pixelforge-release-") as directory:
        scratch = Path(directory).resolve()
        data = scratch / "cluster"
        nonce = "pf_" + secrets.token_hex(12)
        password = secrets.token_hex(32)
        admin_password = secrets.token_hex(32)
        pwfile = scratch / "init-password"
        pwfile.write_text(admin_password + "\n", encoding="ascii")
        with socket.socket() as available:
            available.bind(("127.0.0.1", 0))
            port = str(available.getsockname()[1])
        if int(port) <= 1024 or port in {"5432", "5433"}:
            raise RuntimeError("Refusing reserved port")
        env = {k: v for k, v in os.environ.items() if k.upper() in {
            "PATH", "SYSTEMROOT", "COMSPEC", "WINDIR", "TEMP", "TMP", "HOME",
            "USERPROFILE", "APPDATA", "LOCALAPPDATA", "PATHEXT", "SYSTEMDRIVE",
        }}
        env.update(PGHOST="127.0.0.1", PGPORT=port, PGUSER=nonce + "_admin",
                   PGPASSWORD=admin_password, PGDATABASE="postgres", PGCONNECT_TIMEOUT="4")
        def pg(name, *args, **kwargs):
            return subprocess.run([str(binary_dir / name), *args], env=env, check=True,
                                  timeout=60, **kwargs)
        pg("initdb", "-D", str(data), "-U", env["PGUSER"], "-A", "scram-sha-256",
           "--pwfile", str(pwfile), "--encoding=UTF8", "--no-locale", stdout=subprocess.DEVNULL)
        pwfile.unlink()
        # No network-facing listener or shared Unix socket directory.
        options = f"-h 127.0.0.1 -p {port} -c cluster_name={nonce}"
        if os.name != "nt":
            options += f" -k '{scratch}'"
        pg("pg_ctl", "-D", str(data), "-l", str(scratch / "server.log"), "-o", options, "-w", "start")
        try:
            def sql(statement):
                return pg("psql", "-XAt", "-v", "ON_ERROR_STOP=1", input=statement,
                          text=True, capture_output=True).stdout.strip()
            identity = sql("SELECT current_setting('data_directory'),current_setting('cluster_name'),current_user,inet_server_addr(),inet_server_port(),current_database();").split("|")
            if Path(identity[0]).resolve() != data or identity[1:] != [nonce, env["PGUSER"], "127.0.0.1", port, "postgres"]:
                raise RuntimeError("Disposable cluster identity mismatch")
            sql(f"CREATE ROLE {nonce} LOGIN PASSWORD '{password}';")
            sql(f"CREATE DATABASE {nonce} OWNER {nonce};")
            print("Isolated", sql("SHOW server_version;"), "on 127.0.0.1:" + port, nonce, flush=True)
            child = dict(env)
            for key in list(child):
                if key.startswith("PG"):
                    del child[key]
            child.update(PF_RELEASE_DIR=str(scratch), PF_RELEASE_CLUSTER=nonce,
                         APP_CONFIG_CACHE=str(scratch / "unused-config.php"),
                         APP_ENV="testing", APP_KEY="base64:" + base64.b64encode(secrets.token_bytes(32)).decode(),
                         DB_CONNECTION="pgsql", DB_HOST="127.0.0.1", DB_PORT=port,
                         DB_DATABASE=nonce, DB_USERNAME=nonce, DB_PASSWORD=password,
                         QUEUE_CONNECTION="database", PIXELFORGE_QUEUE_CONNECTION="database",
                         CACHE_STORE="array", SESSION_DRIVER="array", MAIL_MAILER="array",
                         LOG_CHANNEL="stderr", PIXELFORGE_BASE_URL="https://provider.invalid/v1",
                         PIXELFORGE_API_KEY="fixture")
            # Laravel's console exception handler can emit an error with exit code zero.
            # Require a positive completion marker as well as the process exit status.
            result = subprocess.run(["php", str(ROOT / "tests/Postgres/suite.php"), mode],
                                    cwd=ROOT, env=child, capture_output=True, text=True, timeout=240)
            print(result.stdout, end="", flush=True)
            if result.returncode or "COMPLETE " + mode not in result.stdout.splitlines():
                raise RuntimeError("PostgreSQL suite failed: " + result.stderr[-1500:])
        finally:
            pg("pg_ctl", "-D", str(data), "-m", "fast", "-w", "stop")


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--bin", type=Path, help="PostgreSQL server binaries directory")
    args = parser.parse_args()
    location = args.bin or (Path(shutil.which("initdb")).parent if shutil.which("initdb") else None)
    if location is None:
        parser.error("Specify --bin pointing to a local PostgreSQL installation")
    for mode in ("empty", "upgrade"):
        run(location.resolve(), mode)
