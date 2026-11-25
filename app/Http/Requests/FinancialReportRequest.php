<?php

namespace App\Http\Requests;

use App\Domain\Billing\ReportPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

class FinancialReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewFinancialReports') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->isMethod('GET')) {
            $this->mergeIfMissing([
                'start_date' => now('UTC')->subDays(29)->toDateString(),
                'end_date' => now('UTC')->toDateString(),
            ]);
        }
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'users_page' => ['sometimes', 'integer', 'min:1', 'max:1000000'],
            'breakdown_page' => ['sometimes', 'integer', 'min:1', 'max:1000000'],
            'generations_page' => ['sometimes', 'integer', 'min:1', 'max:1000000'],
        ];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            try {
                $this->period();
            } catch (InvalidArgumentException $exception) {
                $validator->errors()->add('end_date', $exception->getMessage());
            }
        }];
    }

    public function period(): ReportPeriod
    {
        return new ReportPeriod($this->string('start_date')->toString(), $this->string('end_date')->toString());
    }
}
