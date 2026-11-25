import { router, useHttp } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import {
    normalizeInputs,
    unicodeLength,
    validReference,
} from '@/lib/generations';
import { quote as quoteRoute, show, store } from '@/routes/generations';
import type { GenerationInputs, GenerationQuote } from '@/types/generation';

type CreationResponse = {
    id: string;
    submission_status: string;
    estimated_customer_charge_usd: string;
};

function isPricingUnavailable(body: string): boolean {
    try {
        const data: unknown = JSON.parse(body);
        return (
            !!data &&
            typeof data === 'object' &&
            'code' in data &&
            data.code === 'pricing_unavailable'
        );
    } catch {
        return false;
    }
}

export function useGenerationComposer(
    inputs: GenerationInputs,
    userId: number,
    reusing: boolean,
) {
    const form = useHttp<GenerationInputs, CreationResponse>({
        ...inputs,
        reference_images: [...inputs.reference_images],
    });
    const quoteRequest = useHttp<GenerationInputs, GenerationQuote>({
        ...inputs,
        reference_images: [...inputs.reference_images],
    });
    const quote = ref<GenerationQuote | null>(null);
    const quoteLoading = ref(false);
    const quoteError = ref('');
    const message = ref('');
    const uncertain = ref(false);
    const ready = ref(false);
    const draftKey = `pixelforge:studio:${userId}`;
    let intent = '';
    let persist = true;
    let timer: ReturnType<typeof setTimeout> | undefined;
    let sequence = 0;
    let mounted = false;

    const normalized = computed(() => normalizeInputs(form.data()));
    const characterCount = computed(() =>
        unicodeLength(normalized.value.prompt),
    );
    const valid = computed(
        () =>
            characterCount.value > 0 &&
            characterCount.value <= 4000 &&
            form.reference_images.length <= 4 &&
            form.reference_images.every(validReference),
    );
    const fingerprint = computed(() => JSON.stringify(normalized.value));
    const canSubmit = computed(
        () =>
            valid.value &&
            quote.value?.affordable === true &&
            !quoteLoading.value &&
            !form.processing &&
            !uncertain.value,
    );

    function persistDraft() {
        if (!mounted || !persist) return;
        try {
            sessionStorage.setItem(
                draftKey,
                JSON.stringify({
                    inputs: form.data(),
                    uncertain: uncertain.value,
                    intent,
                }),
            );
        } catch {
            /* Storage may be disabled. */
        }
    }

    async function updateQuote(current: number) {
        Object.assign(quoteRequest, normalized.value);
        try {
            const response = await quoteRequest.post(quoteRoute.url(), {
                onHttpException: () => false,
                onNetworkError: () => false,
            });
            if (current === sequence && mounted) quote.value = response;
        } catch {
            if (current === sequence && mounted) {
                form.setError(quoteRequest.errors);
                quoteError.value = quoteRequest.hasErrors
                    ? 'Review the highlighted fields to update your estimate.'
                    : 'Pricing is unavailable right now. Your draft is saved; please check again.';
            }
        } finally {
            if (current === sequence) quoteLoading.value = false;
        }
    }

    function requestQuote(delay = 450) {
        clearTimeout(timer);
        const current = ++sequence;
        quoteRequest.cancel();
        quote.value = null;
        quoteError.value = '';
        quoteLoading.value = valid.value;
        if (valid.value && mounted)
            timer = setTimeout(() => {
                void updateQuote(current);
            }, delay);
    }

    watch(
        fingerprint,
        () => {
            intent = '';
            form.clearErrors();
            message.value = '';
            persistDraft();
            if (ready.value) requestQuote();
        },
        { flush: 'sync' },
    );
    watch(uncertain, persistDraft);

    onMounted(() => {
        mounted = true;
        if (!reusing) {
            try {
                const saved: unknown = JSON.parse(
                    sessionStorage.getItem(draftKey) ?? 'null',
                );
                if (
                    saved &&
                    typeof saved === 'object' &&
                    'inputs' in saved &&
                    saved.inputs &&
                    typeof saved.inputs === 'object'
                ) {
                    const draft = saved.inputs;
                    if (
                        'prompt' in draft &&
                        typeof draft.prompt === 'string' &&
                        'size' in draft &&
                        typeof draft.size === 'string' &&
                        ['1024x1024', '1536x1024', '1024x1536'].includes(
                            String(draft.size),
                        ) &&
                        'quality' in draft &&
                        typeof draft.quality === 'string' &&
                        ['low', 'medium', 'high'].includes(
                            String(draft.quality),
                        ) &&
                        'reference_images' in draft &&
                        Array.isArray(draft.reference_images) &&
                        draft.reference_images.length <= 4 &&
                        draft.reference_images.every(
                            (url) => typeof url === 'string',
                        )
                    ) {
                        Object.assign(form, {
                            prompt: draft.prompt,
                            size: draft.size,
                            quality: draft.quality,
                            reference_images: [...draft.reference_images],
                        });
                        intent =
                            'intent' in saved &&
                            typeof saved.intent === 'string'
                                ? saved.intent
                                : '';
                        uncertain.value =
                            'uncertain' in saved && saved.uncertain === true;
                    }
                }
            } catch {
                /* A malformed or unavailable local draft must not prevent editing. */
            }
        }
        ready.value = true;
        persistDraft();
        requestQuote(0);
    });

    async function submit() {
        if (!canSubmit.value) return;
        intent ||= crypto.randomUUID();
        uncertain.value = true;
        persistDraft();
        let handled = false;
        form.transform(() => normalized.value);
        message.value = '';
        try {
            await form.post(store.url(), {
                headers: { 'Idempotency-Key': intent },
                onSuccess: (response, httpResponse) => {
                    handled = true;
                    if (
                        httpResponse.status !== 202 ||
                        !/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(
                            response.id,
                        )
                    ) {
                        uncertain.value = true;
                        return;
                    }
                    persist = false;
                    try {
                        sessionStorage.removeItem(draftKey);
                    } catch {
                        /* Storage is optional. */
                    }
                    router.visit(show.url(response.id));
                },
                onError: (errors) => {
                    handled = true;
                    uncertain.value = false;
                    message.value = String(
                        (errors as Record<string, string>).wallet ||
                            'Review the highlighted fields. Your draft has been preserved.',
                    );
                    requestQuote();
                },
                onHttpException: (response) => {
                    handled = true;
                    if (
                        response.status === 503 &&
                        isPricingUnavailable(response.data)
                    ) {
                        uncertain.value = false;
                        quote.value = null;
                        quoteError.value =
                            'Pricing changed or became unavailable. Check the estimate again before submitting.';
                    } else if ([401, 403, 419].includes(response.status)) {
                        uncertain.value = false;
                        message.value =
                            'Your session needs refreshing. Sign in again in another tab, then return to your saved draft.';
                    } else {
                        uncertain.value = true;
                    }
                    return false;
                },
                onNetworkError: () => {
                    handled = true;
                    uncertain.value = true;
                    return false;
                },
            });
        } catch {
            if (!handled) uncertain.value = true;
        }
    }

    onUnmounted(() => {
        mounted = false;
        sequence++;
        clearTimeout(timer);
        quoteRequest.cancel();
    });

    return {
        form,
        quote,
        quoteLoading,
        quoteError,
        message,
        uncertain,
        characterCount,
        canSubmit,
        submit,
        refreshQuote: () => requestQuote(0),
    };
}
