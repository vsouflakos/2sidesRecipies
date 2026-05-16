<?php

namespace App\Http\Requests\Ingredients;

use App\Concerns\IngredientValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreIngredientRequest extends FormRequest
{
    use IngredientValidationRules;

    /**
     * Determine if the user is authorized to make this request.
     * Any authenticated user may create a private ingredient.
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
        return $this->ingredientRules();
    }
}
