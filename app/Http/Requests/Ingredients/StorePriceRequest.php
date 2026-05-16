<?php

namespace App\Http\Requests\Ingredients;

use App\Models\Unit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePriceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Any authenticated user may record a price on any ingredient they can see.
     * Pricing is per-user and private, so no additional authorization is needed.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit_id' => ['required', 'exists:units,id'],
            'currency' => ['required', 'string', 'size:3'],
            'recorded_at' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Add after-validation hook to check that non-weight units have a
     * conversion row defined for the ingredient being priced.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->has('unit_id')) {
                return;
            }

            $unitId = $this->input('unit_id');

            if (! $unitId) {
                return;
            }

            $unit = Unit::find($unitId);

            if (! $unit || $unit->type === 'weight') {
                return;
            }

            // Non-weight unit — check ingredient has a conversion row for it
            $ingredient = $this->route('ingredient');

            if (! $ingredient) {
                return;
            }

            $hasConversion = $ingredient->conversions()
                ->where('from_unit_id', $unit->id)
                ->exists();

            if (! $hasConversion) {
                $validator->errors()->add(
                    'unit_id',
                    __('app.ingredients.price_no_conversion')
                );
            }
        });
    }
}
