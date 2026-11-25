<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ChartColumn, ShieldCheck } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import FinancialTable from '@/components/generations/FinancialTable.vue';
import FinancialGenerationTable from '@/components/generations/FinancialGenerationTable.vue';
import ProviderReconciliationPanel from '@/components/generations/ProviderReconciliationPanel.vue';
import ReportPagination from '@/components/generations/ReportPagination.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index, refresh } from '@/routes/admin/financials';
import type {
    FinancialReport,
    Reconciliation,
    ReportFilters,
} from '@/types/financial-report';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Financial reports', href: index() }] },
});
const props = defineProps<{
    filters: ReportFilters;
    report: FinancialReport;
    reconciliation: Reconciliation;
    refreshed: boolean;
}>();
const filtersForm = useForm({ ...props.filters });
const refreshForm = useForm({ ...props.filters });
const section = ref<'daily' | 'users' | 'breakdown' | 'generations'>('daily');
const navigating = ref(false);
const refreshError = computed(() => {
    const errors: Record<string, string | undefined> = refreshForm.errors;
    return errors.reconciliation;
});
const sections = [
    { id: 'daily', label: 'Daily' },
    { id: 'users', label: 'By user' },
    { id: 'breakdown', label: 'Daily by user' },
    { id: 'generations', label: 'Generations' },
] as const;
const kpis = computed(() => [
    {
        label: 'Customer charges',
        value: `$${props.report.summary.customer_charge_usd}`,
    },
    {
        label: 'Provider costs',
        value: `$${props.report.summary.provider_cost_usd}`,
    },
    {
        label: 'Gross profit',
        value: `$${props.report.summary.gross_profit_usd}`,
    },
    { label: 'Settled successes', value: props.report.summary.generations },
]);
function applyFilters() {
    filtersForm.get(index.url(), { preserveState: true, preserveScroll: true });
}
function refreshUsage() {
    refreshForm
        .transform(() => props.filters)
        .post(refresh.url(), { preserveScroll: true });
}
function paginate(
    name: 'users_page' | 'breakdown_page' | 'generations_page',
    page: number,
) {
    router.get(
        index.url(),
        { ...props.filters, [name]: page },
        {
            preserveState: true,
            preserveScroll: true,
            onStart: () => {
                navigating.value = true;
            },
            onFinish: () => {
                navigating.value = false;
            },
        },
    );
}
</script>

<template>
    <div class="mx-auto w-full max-w-7xl min-w-0 space-y-6 p-4 sm:p-6 lg:p-8">
        <Head title="Financial reports" />
        <header>
            <p
                class="mb-2 flex items-center gap-2 text-xs font-medium tracking-widest text-primary uppercase"
            >
                <ShieldCheck class="size-4" /> Admin workspace
            </p>
            <h1 class="text-3xl font-semibold tracking-tight">
                Financial reports
            </h1>
            <p class="mt-2 max-w-3xl text-sm text-muted-foreground">
                Actual settled charges, provider costs, and gross profit. All
                dates use provider completion in UTC; every amount is exact USD.
            </p>
        </header>

        <form
            class="flex flex-wrap items-end gap-4 rounded-xl border bg-card p-4"
            @submit.prevent="applyFilters"
        >
            <div class="max-w-full min-w-0 space-y-2">
                <Label for="report-start">Start date · UTC</Label
                ><Input
                    id="report-start"
                    v-model="filtersForm.start_date"
                    type="date"
                    required
                    :aria-invalid="!!filtersForm.errors.start_date"
                    aria-describedby="report-start-error"
                /><InputError
                    id="report-start-error"
                    :message="filtersForm.errors.start_date"
                />
            </div>
            <div class="max-w-full min-w-0 space-y-2">
                <Label for="report-end">End date · UTC</Label
                ><Input
                    id="report-end"
                    v-model="filtersForm.end_date"
                    type="date"
                    required
                    :aria-invalid="!!filtersForm.errors.end_date"
                    aria-describedby="report-end-error report-range-help"
                /><InputError
                    id="report-end-error"
                    :message="filtersForm.errors.end_date"
                />
            </div>
            <Button type="submit" :disabled="filtersForm.processing">{{
                filtersForm.processing ? 'Loading report…' : 'Apply dates'
            }}</Button>
            <p id="report-range-help" class="text-xs text-muted-foreground">
                Inclusive range · maximum 92 days
            </p>
        </form>

        <Alert
            v-if="report.summary.missing_settlements"
            class="border-amber-500/50"
            role="alert"
            ><AlertDescription
                >{{ report.summary.missing_settlements }} succeeded
                generation(s) have no settlement. Their actual amounts are
                unavailable; reconciliation needs
                investigation.</AlertDescription
            ></Alert
        >

        <div
            class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
            :aria-busy="filtersForm.processing"
        >
            <Card
                v-for="kpi in kpis"
                :key="kpi.label"
                class="min-w-0 py-5 shadow-none"
                ><CardContent
                    ><p class="text-xs text-muted-foreground">
                        {{ kpi.label }}
                    </p>
                    <p
                        class="mt-3 font-mono text-xl font-semibold break-all tabular-nums"
                    >
                        {{ kpi.value }}
                    </p></CardContent
                ></Card
            >
        </div>

        <section
            class="space-y-4"
            aria-labelledby="settlement-reports-title"
            :aria-busy="navigating || filtersForm.processing"
        >
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2
                    id="settlement-reports-title"
                    class="flex items-center gap-2 text-lg font-semibold"
                >
                    <ChartColumn class="size-5 text-primary" /> Settlement
                    ledger
                </h2>
                <nav
                    class="flex flex-wrap gap-2"
                    aria-label="Financial report sections"
                >
                    <Button
                        v-for="item in sections"
                        :key="item.id"
                        :variant="section === item.id ? 'default' : 'outline'"
                        size="sm"
                        :aria-pressed="section === item.id"
                        @click="section = item.id"
                        >{{ item.label }}</Button
                    >
                </nav>
            </div>
            <p class="text-xs text-muted-foreground">
                Applied period: {{ filters.start_date }} through
                {{ filters.end_date }} UTC. Estimates are excluded; failed
                generations contribute zero.
            </p>
            <p
                v-if="navigating || filtersForm.processing"
                role="status"
                class="text-sm text-muted-foreground"
            >
                Loading financial records…
            </p>
            <FinancialTable
                v-if="section === 'daily'"
                :rows="report.daily"
                grouping="date"
                caption="Daily financial report"
                show-tokens
            />
            <div v-else-if="section === 'users'">
                <FinancialTable
                    :rows="report.users.data"
                    grouping="user"
                    caption="Per-user financial report"
                /><ReportPagination
                    :page="report.users"
                    label="Users"
                    :loading="navigating"
                    @change="paginate('users_page', $event)"
                />
            </div>
            <div v-else-if="section === 'breakdown'">
                <FinancialTable
                    :rows="report.breakdown.data"
                    grouping="date-user"
                    caption="Daily per-user financial report"
                /><ReportPagination
                    :page="report.breakdown"
                    label="Daily user groups"
                    :loading="navigating"
                    @change="paginate('breakdown_page', $event)"
                />
            </div>
            <div v-else>
                <FinancialGenerationTable :rows="report.generations.data" />
                <ReportPagination
                    :page="report.generations"
                    label="Generations"
                    :loading="navigating"
                    @change="paginate('generations_page', $event)"
                />
            </div>
        </section>

        <ProviderReconciliationPanel
            :reconciliation="reconciliation"
            :refreshed="refreshed"
            :processing="refreshForm.processing"
            :error="refreshError"
            @refresh="refreshUsage"
        />
    </div>
</template>
