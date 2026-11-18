<?php

namespace App\Http\Requests;

use App\Domain\Generations\Enums\GenerationQuality;
use App\Domain\Generations\Enums\GenerationSize;
use App\Models\Generation;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CreateGenerationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Generation::class) ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'prompt' => ['bail', 'required', 'string', 'max:4000', function (string $attribute, mixed $value, Closure $fail): void {
                if (! mb_check_encoding($value, 'UTF-8')) {
                    $fail('The prompt must contain valid Unicode text.');
                }
            }],
            'size' => ['required', Rule::enum(GenerationSize::class)],
            'quality' => ['required', Rule::enum(GenerationQuality::class)],
            'reference_images' => ['sometimes', 'array', 'list', 'max:4'],
            'reference_images.*' => ['required', 'string', 'url:http,https'],
        ];
    }

    /** @return list<Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (array_diff(array_keys($this->all()), ['prompt', 'size', 'quality', 'reference_images', '_token']) as $key) {
                $validator->errors()->add((string) $key, 'This field is not supported.');
            }
        }];
    }
}
