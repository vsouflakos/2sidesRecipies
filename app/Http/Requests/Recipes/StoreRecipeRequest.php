<?php

namespace App\Http\Requests\Recipes;

use App\Concerns\RecipeValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreRecipeRequest extends FormRequest
{
    use RecipeValidationRules;

    /**
     * Determine if the user is authorized to make this request.
     * Any authenticated user may create a recipe.
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
        return $this->recipeMetadataRules();
    }
}
