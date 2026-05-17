<?php

namespace App\Http\Requests\Recipes;

use App\Enums\TestType;
use App\Enums\TestVerdict;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRecipeTestRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Route-level authorization is handled by the controller's Gate check on the test.
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
            'type' => ['sometimes', 'required', Rule::enum(TestType::class)],
            'recipe_version_id' => ['sometimes', 'required', 'integer', 'exists:recipe_versions,id'],
            'tested_at' => ['sometimes', 'required', 'date'],
            'overall_rating' => ['sometimes', 'required', 'integer', 'min:1', 'max:10'],
            'tasting_notes' => ['nullable', 'string', 'max:5000'],
            'ratings' => ['nullable', 'array', 'max:20'],
            'ratings.*.dimension' => ['required_with:ratings.*', 'string', 'max:100'],
            'ratings.*.score' => ['nullable', 'integer', 'min:1', 'max:10'],
            'ratings.*.is_custom' => ['boolean'],
            'hypothesis' => ['nullable', 'string', 'max:5000', Rule::requiredIf(fn () => $this->input('type') === 'experiment')],
            'outcome_narrative' => ['nullable', 'string', 'max:5000'],
            'verdict' => ['nullable', Rule::enum(TestVerdict::class)],
            'change_rows' => ['nullable', 'array', 'max:20'],
            'change_rows.*.what_changed' => ['required_with:change_rows.*', 'string', 'max:500'],
            'change_rows.*.expected_effect' => ['nullable', 'string', 'max:500'],
            'change_rows.*.actual_effect' => ['nullable', 'string', 'max:500'],
            'photos' => ['nullable', 'array', 'max:8'],
            'photos.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'deleted_photo_ids' => ['nullable', 'array'],
            'deleted_photo_ids.*' => ['integer', 'exists:recipe_test_photos,id'],
        ];
    }
}
