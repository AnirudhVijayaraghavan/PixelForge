import type { GenerationInputs } from '@/types/generation';

// Match Laravel 13 Str::trim, including its invisible boundary characters.
const boundaryCharacters =
    '[\\p{White_Space}\\u0000\\u00ad\\u034f\\u061c\\u115f\\u1160\\u17b4\\u17b5\\u180e\\u200b-\\u200f\\u2060-\\u2065\\u206a-\\u206f\\u2800\\u3164\\ufeff\\uffa0\\u{1d159}\\u{1d173}-\\u{1d17a}\\u{e0020}]';
const boundaryPattern = new RegExp(
    `^${boundaryCharacters}+|${boundaryCharacters}+$`,
    'gu',
);

export function normalizeText(value: string): string {
    return value.replace(boundaryPattern, '');
}

export function normalizeInputs(inputs: GenerationInputs): GenerationInputs {
    return {
        ...inputs,
        prompt: normalizeText(inputs.prompt),
        reference_images: inputs.reference_images.map(normalizeText),
    };
}

export function unicodeLength(value: string): number {
    return Array.from(value).length;
}

export function validReference(value: string): boolean {
    try {
        const url = new URL(normalizeText(value));
        return (
            ['https:', 'http:'].includes(url.protocol) &&
            url.hostname.length > 0
        );
    } catch {
        return false;
    }
}

export function formatGenerationDate(value: string | null): string {
    if (!value) return 'Pending';
    return (
        new Intl.DateTimeFormat('en-US', {
            dateStyle: 'medium',
            timeStyle: 'short',
            timeZone: 'UTC',
        }).format(new Date(value)) + ' UTC'
    );
}

export function signedDifference(value: string): string {
    if (value.startsWith('-')) return `−$${value.slice(1)}`;
    return value === '0.000000000' ? `$${value}` : `+$${value}`;
}
