export type ReportFilters = { start_date: string; end_date: string };
export type FinancialMetrics = {
    generations: string;
    input_text_tokens: string;
    input_image_tokens: string;
    output_tokens: string;
    reasoning_tokens: string;
    provider_cost_usd: string;
    customer_charge_usd: string;
    gross_profit_usd: string;
};
export type FinancialRow = FinancialMetrics & {
    date?: string;
    user_id?: number;
    name?: string;
    email?: string;
    missing_settlements?: number;
};
export type ReportPage<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
};
export type FinancialGeneration = {
    id: string;
    provider_generation_id: string | null;
    user_name: string;
    completed_at: string;
    status: string;
    provider_cost_usd: string | null;
    customer_charge_usd: string | null;
};
export type FinancialReport = {
    summary: FinancialMetrics & { missing_settlements: number };
    daily: FinancialRow[];
    users: ReportPage<FinancialRow>;
    breakdown: ReportPage<FinancialRow>;
    generations: ReportPage<FinancialGeneration>;
};
export type Reconciliation = {
    status: 'MATCHED' | 'MISMATCHED' | 'UNAVAILABLE' | 'NOT_CHECKED';
    provider_total: Omit<
        FinancialMetrics,
        'customer_charge_usd' | 'gross_profit_usd'
    > | null;
    total_cost_difference_usd: string | null;
    days: {
        date: string;
        status: 'MATCHED' | 'MISMATCHED' | 'UNAVAILABLE' | 'NOT_CHECKED';
        local: FinancialMetrics;
        provider: Omit<
            FinancialMetrics,
            'customer_charge_usd' | 'gross_profit_usd'
        > | null;
        differences: Record<string, string>;
    }[];
    last_successful_fetch_at: string | null;
    last_attempt_at: string | null;
    error_code: string | null;
    is_stale: boolean;
    next_refresh_at: string | null;
};
