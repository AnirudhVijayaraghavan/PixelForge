<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ManageAdmin extends Command
{
    protected $signature = 'app:admin {user : Existing user ID} {--revoke : Remove admin access} {--force : Confirm this operational change}';

    protected $description = 'Explicitly grant or revoke financial-report access for an existing user';

    public function handle(): int
    {
        if (! $this->option('force')) {
            $this->error('No change made. Review the user ID and pass --force to confirm.');

            return self::FAILURE;
        }
        $id = filter_var($this->argument('user'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $user = $id === false ? null : User::query()->find($id);
        if ($user === null) {
            $this->error('Existing user not found. No change made.');

            return self::FAILURE;
        }
        $user->forceFill(['is_admin' => ! $this->option('revoke')])->save();
        $this->info('Financial-report access '.($user->is_admin ? 'granted' : 'revoked').' for user '.$user->id.'.');

        return self::SUCCESS;
    }
}
