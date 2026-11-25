import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

export function useImageExpiry(expiresAt: () => string | null) {
    const expired = ref(false);
    let timer: ReturnType<typeof setTimeout> | undefined;
    function refresh() {
        clearTimeout(timer);
        const iso = expiresAt();
        if (!iso) {
            expired.value = false;
            return;
        }
        const remaining = Date.parse(iso) - Date.now();
        expired.value = !(remaining > 0);
        if (!expired.value) {
            timer = setTimeout(refresh, Math.min(remaining, 2147483647));
        }
    }
    onMounted(() => {
        watch(expiresAt, refresh, { immediate: true });
        document.addEventListener('visibilitychange', refresh);
    });
    onBeforeUnmount(() => {
        clearTimeout(timer);
        document.removeEventListener('visibilitychange', refresh);
    });
    return expired;
}
