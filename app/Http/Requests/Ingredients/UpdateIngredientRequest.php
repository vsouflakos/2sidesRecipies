<?php

namespace App\Http\Requests\Ingredients;

use App\Concerns\IngredientValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateIngredientRequest extends FormRequest
{
    use IngredientValidationRules;

    /**
     * Determine if the user is authorized to make this request.
     * Defers to the IngredientPolicy — owner-only check.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('ingredient'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->ingredientRules();
    }
}
