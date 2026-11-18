<?php

namespace App\Http\Requests;

use App\Services\Pixelforge\PricingCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Mirrors Pixelforge's own validation so a 422 from the provider can't happen
 * after we've already held the user's credits.
 */
class StoreGenerationRequest extends FormRequest
{
    private const DEFAULT_SIZES = ['1024x1024', '1536x1024', '1024x1536'];

    private const DEFAULT_QUALITIES = ['low', 'medium', 'high'];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        [$sizes, $qualities] = $this->options();

        return [
            'prompt' => ['required', 'string', 'max:4000'], // characters, like Pixelforge
            'size' => ['required', Rule::in($sizes)],
            'quality' => ['required', Rule::in($qualities)],
            'reference_images' => ['array', 'max:4'],
            'reference_images.*' => ['required', 'string', 'max:2048', 'url:http,https'],
            // One per form instance (UUID from the browser): a double submit is one generation.
            'idempotency_key' => ['required', 'uuid'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'reference_images.max' => 'Up to 4 reference images.',
            'reference_images.*.url' => 'Reference images must be http(s) URLs.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'prompt' => is_string($this->prompt) ? trim($this->prompt) : $this->prompt,
            'reference_images' => array_values(array_filter(
                array_map(fn ($u) => is_string($u) ? trim($u) : $u, (array) $this->input('reference_images', [])),
                fn ($u) => $u !== '' && $u !== null,
            )),
        ]);
    }

    /** @return array{0: list<string>, 1: list<string>} */
    private function options(): array
    {
        try {
            $table = app(PricingCatalog::class)->current();

            return [$table->sizes() ?: self::DEFAULT_SIZES, $table->qualities() ?: self::DEFAULT_QUALITIES];
        } catch (Throwable) {
            return [self::DEFAULT_SIZES, self::DEFAULT_QUALITIES];
        }
    }
}
