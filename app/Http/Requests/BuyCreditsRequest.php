<?php

namespace App\Http\Requests;

use App\Domain\Billing\WalletAccounting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BuyCreditsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'denomination' => ['required', 'string', Rule::in(WalletAccounting::DENOMINATIONS)],
            'intent' => ['required', 'uuid'], 'confirmed' => ['accepted'],
            'user_id' => ['prohibited'], 'wallet_id' => ['prohibited'], 'amount' => ['prohibited'],
        ];
    }
}
