<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardContent, CardTitle } from '@/components/ui/card';
import { index, purchase } from '@/routes/wallet';
import { show } from '@/routes/generations';
import { studio } from '@/routes';
import type { WalletBalances } from '@/types/generation';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Credit wallet', href: index() }] },
});
const props = defineProps<{
    balances: WalletBalances;
    denominations: string[];
    activity: {
        current_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
        data: {
            id: string;
            amount: string;
            generation_id: string | null;
            created_at: string;
        }[];
    };
}>();
const form = useForm({
    denomination: props.denominations[0],
    intent: '',
    confirmed: false,
});
const storageKey = 'pixelforge:purchase:' + usePage().props.auth.user.id;
const descriptions = {
    available: 'Ready for a new reservation.',
    reserved: 'Held for open requests until their outcome is known.',
    settled: 'Purchased credits less completed generation charges.',
};
const feedback = ref('');
function buy() {
    if (form.processing || !form.confirmed) return;
    const key = storageKey + ':' + form.denomination;
    try {
        form.intent = sessionStorage.getItem(key) || crypto.randomUUID();
        sessionStorage.setItem(key, form.intent);
    } catch {
        form.intent ||= crypto.randomUUID();
    }
    form.post(purchase.url(), {
        preserveScroll: true,
        onSuccess: () => {
            try {
                sessionStorage.removeItem(key);
            } catch {
                /* Storage is optional. */
            }
            feedback.value =
                'Purchase confirmed. Your credits are ready to use.';
            form.reset('intent', 'confirmed');
        },
    });
}
</script>
<template>
    <main class="mx-auto w-full max-w-4xl space-y-6 p-4 sm:p-8">
        <Head title="Credit wallet" />
        <header>
            <h1 class="text-3xl font-semibold">Credit wallet</h1>
            <p class="text-muted-foreground">
                Available credits fund your next generation.
            </p>
        </header>
        <dl class="grid gap-4 rounded-xl border bg-card p-5 sm:grid-cols-3">
            <div v-for="(amount, label) in balances" :key="label">
                <dt class="text-muted-foreground capitalize">{{ label }}</dt>
                <dd class="font-mono break-all tabular-nums">${{ amount }}</dd>
                <dd class="mt-1 text-xs text-muted-foreground">
                    {{ descriptions[label] }}
                </dd>
            </div>
        </dl>
        <p
            v-if="balances.available.startsWith('-')"
            role="status"
            class="rounded-lg border p-3 text-sm"
        >
            Your available balance is below zero. Accepted provider pricing can
            exceed an earlier hold. Review the generation charge in your
            activity and add simulated credits before submitting again.
        </p>
        <Card
            ><CardHeader><CardTitle>Buy credits</CardTitle></CardHeader
            ><CardContent>
                <form class="space-y-4" @submit.prevent="buy">
                    <p>
                        Simulated purchase. No payment information is needed and
                        no real money is charged.
                    </p>
                    <fieldset :disabled="form.processing" class="space-y-3">
                        <legend>Choose a USD credit pack</legend>
                        <div class="flex flex-wrap gap-4">
                            <label
                                v-for="pack in denominations"
                                :key="pack"
                                class="rounded-lg border p-3"
                                ><input
                                    type="radio"
                                    v-model="form.denomination"
                                    :value="pack"
                                    @change="
                                        form.confirmed = false;
                                        form.intent = '';
                                        feedback = '';
                                    "
                                />
                                ${{ pack }}</label
                            >
                        </div>
                        <label class="flex items-center gap-2"
                            ><input type="checkbox" v-model="form.confirmed" />
                            Confirm adding ${{ form.denomination }} in simulated
                            credits</label
                        >
                    </fieldset>
                    <div class="flex flex-wrap gap-3">
                        <Button
                            :disabled="form.processing || !form.confirmed"
                            >{{
                                form.processing
                                    ? 'Adding credits...'
                                    : 'Buy credits'
                            }}</Button
                        ><Button as-child variant="outline"
                            ><Link :href="studio()"
                                >Back to Studio</Link
                            ></Button
                        >
                    </div>
                    <p role="status">{{ feedback }}</p>
                    <p
                        v-for="error in form.errors"
                        :key="error"
                        role="alert"
                        class="text-destructive"
                    >
                        {{ error }}
                    </p>
                </form></CardContent
            ></Card
        >
        <section class="space-y-3">
            <h2 class="text-xl font-semibold">Credit activity</h2>
            <p v-if="!activity.data.length">
                Your first simulated purchase will appear here.
            </p>
            <ul class="divide-y">
                <li
                    v-for="entry in activity.data"
                    :key="entry.id"
                    class="flex flex-wrap justify-between gap-2 py-3"
                >
                    <div>
                        <Link
                            v-if="entry.generation_id"
                            class="underline"
                            :href="show(entry.generation_id)"
                            >Generation charge</Link
                        ><span v-else>Simulated purchase</span>
                        <p class="text-xs text-muted-foreground">
                            {{ entry.created_at }} UTC
                        </p>
                    </div>
                    <span class="font-mono tabular-nums"
                        >{{ entry.generation_id ? '-' : '+' }}${{
                            entry.amount
                        }}</span
                    >
                </li>
            </ul>
            <nav aria-label="Credit activity pages" class="flex gap-4">
                <Link
                    v-if="activity.prev_page_url"
                    :href="
                        index({ query: { page: activity.current_page - 1 } })
                    "
                    >Previous</Link
                ><Link
                    v-if="activity.next_page_url"
                    :href="
                        index({ query: { page: activity.current_page + 1 } })
                    "
                    >Next</Link
                >
            </nav>
        </section>
    </main>
</template>
