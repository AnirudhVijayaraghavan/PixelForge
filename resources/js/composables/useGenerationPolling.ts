import { useHttp } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref, shallowRef, watch } from 'vue';
import { statuses } from '@/routes/generations';
import type { Generation } from '@/types/generation';

export function useGenerationPolling<T extends Generation>(
    initial: () => T[],
    detail = false,
) {
    const records = shallowRef<T[]>([]);
    const refreshing = ref(false);
    const error = ref('');
    const http = useHttp<
        { ids: string[]; detail: number },
        { generations: T[] }
    >({ ids: [], detail: detail ? 1 : 0 });
    let timer: ReturnType<typeof setTimeout> | undefined;
    let mounted = false;
    let sequence = 0;

    function stop() {
        clearTimeout(timer);
        sequence++;
        http.cancel();
        refreshing.value = false;
    }

    function schedule() {
        clearTimeout(timer);
        if (
            mounted &&
            !document.hidden &&
            records.value.some((record) => record.active)
        ) {
            timer = setTimeout(() => {
                void refresh();
            }, 4000);
        }
    }

    async function refresh(force = false) {
        if (!mounted || document.hidden || refreshing.value) return;
        const ids = records.value
            .filter((record) => force || record.active)
            .map((record) => record.id);
        if (!ids.length) return;
        const current = ++sequence;
        http.ids = ids;
        refreshing.value = true;
        try {
            const response = await http.get(statuses.url(), {
                onHttpException: () => false,
                onNetworkError: () => false,
            });
            if (current !== sequence || !mounted) return;
            const updates = new Map(
                response.generations.map((record) => [record.id, record]),
            );
            records.value = records.value.map(
                (record) => updates.get(record.id) ?? record,
            );
            error.value = '';
        } catch {
            if (current === sequence && mounted && !document.hidden) {
                error.value =
                    'Live updates are temporarily unavailable. Your last known status is preserved.';
            }
        } finally {
            if (current === sequence) {
                refreshing.value = false;
                schedule();
            }
        }
    }

    function visibilityChanged() {
        if (document.hidden) stop();
        else void refresh(true);
    }

    watch(
        initial,
        (value) => {
            stop();
            records.value = [...value];
            error.value = '';
            schedule();
        },
        { immediate: true },
    );
    onMounted(() => {
        mounted = true;
        document.addEventListener('visibilitychange', visibilityChanged);
        schedule();
    });
    onUnmounted(() => {
        mounted = false;
        stop();
        document.removeEventListener('visibilitychange', visibilityChanged);
    });

    return { records, refreshing, error, refresh: () => refresh(true) };
}
