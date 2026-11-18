export type GenerationSize = '1024x1024' | '1536x1024' | '1024x1536';
export type GenerationQuality = 'low' | 'medium' | 'high';
export type GenerationInputs = {
    prompt: string;
    size: GenerationSize;
    quality: GenerationQuality;
    reference_images: string[];
};
export type GenerationStatus =
    | 'pending'
    | 'submitting'
    | 'retrying'
    | 'uncertain'
    | 'rejected'
    | 'queued'
    | 'processing'
    | 'succeeded'
    | 'failed';
export type BillingState = 'pending' | 'settled' | 'free' | 'unavailable';
export type TokenCategory =
    | 'input_text_tokens'
    | 'input_image_tokens'
    | 'output_tokens'
    | 'reasoning_tokens';
export type TokenUsage = Record<TokenCategory, number> & {
    total_tokens: number;
};
export type CalculatedPrice = {
    provider_cost_usd: string;
    customer_charge_usd: string;
    provider_breakdown_usd: Record<TokenCategory, string>;
};
export type GenerationQuote = {
    currency: 'usd';
    estimate: CalculatedPrice;
    minimum: CalculatedPrice;
    maximum: CalculatedPrice;
    estimated_usage: TokenUsage;
    rates: Record<TokenCategory, string>;
    provenance: string;
    quoted_at: string;
};
export type Generation = {
    id: string;
    prompt: string;
    prompt_excerpt: string;
    size: GenerationSize;
    quality: GenerationQuality;
    status: GenerationStatus;
    status_label: string;
    status_message: string;
    active: boolean;
    billing_state: BillingState;
    estimated_customer_charge_usd: string;
    actual_customer_charge_usd: string | null;
    image_url: string | null;
    image_state: 'pending' | 'available' | 'expired' | 'unavailable';
    image_url_expires_at: string | null;
    created_at: string;
    updated_at: string;
    provider_created_at: string | null;
    started_at: string | null;
    completed_at: string | null;
};
export type GenerationBilling = {
    state: BillingState;
    rows: {
        category: TokenCategory;
        label: string;
        tokens: number;
        rate_usd: string;
        cost_usd: string;
    }[];
    total_tokens: number | null;
    provider_cost_usd: string | null;
    customer_charge_usd: string | null;
    markup_usd: string | null;
    difference_usd: string | null;
    estimated_customer_charge_usd: string;
    markup_percentage: string;
    rate_provenance: string;
};
export type GenerationDetail = Generation & {
    reference_images: string[];
    billing: GenerationBilling;
};
export type GenerationPage = {
    data: Generation[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
};
