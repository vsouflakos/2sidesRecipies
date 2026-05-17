<?php

namespace App\Http\Requests\Recipes;

use App\Models\RecipeVersion;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PublishRecipeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * The authenticated user must own the recipe to publish it.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('recipe')) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'version_id' => [
                'required',
                'integer',
                Rule::exists('recipe_versions', 'id')
                    ->where('recipe_id', $this->route('recipe')->id),
            ],
        ];
    }

    /**
     * Add additional validation after the base rules pass.
     *
     * Walks the chosen version's snapshot sections and rejects publish when
     * any referenced sub-recipe is not yet published itself.
     *
     * Throws HttpResponseException with a 422 JSON body so that the rejection
     * is testable regardless of the request content-type (Inertia or plain HTTP).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->has('version_id')) {
                return;
            }

            $versionId = $this->input('version_id');

            if (! $versionId) {
                return;
            }

            /** @var RecipeVersion|null $version */
            $version = RecipeVersion::find($versionId);

            if (! $version) {
                return;
            }

            $snapshot = $version->snapshot ?? [];
            $sections = $snapshot['sections'] ?? [];

            $subRecipeVersionIds = [];

            foreach ($sections as $section) {
                $lines = $section['lines'] ?? [];
                foreach ($lines as $line) {
                    $subVersionId = $line['sub_recipe_version_id'] ?? null;
                    if ($subVersionId !== null) {
                        $subRecipeVersionIds[] = $subVersionId;
                    }
                }
            }

            if (empty($subRecipeVersionIds)) {
                return;
            }

            $unpublishedSubVersions = RecipeVersion::with('recipe')
                ->whereIn('id', $subRecipeVersionIds)
                ->whereHas('recipe', fn ($q) => $q->where('is_published', false))
                ->get();

            foreach ($unpublishedSubVersions as $subVersion) {
                $name = $subVersion->recipe?->name ?? 'Unknown';
                $message = str_replace(':name', $name, ':name must be published before this recipe can be published.');
                $validator->errors()->add('version_id', $message);
            }

            if ($unpublishedSubVersions->isNotEmpty()) {
                throw new HttpResponseException(
                    response()->json([
                        'message' => 'The given data was invalid.',
                        'errors' => $validator->errors()->toArray(),
                    ], 422)
                );
            }
        });
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'version_id.required' => 'A version must be selected to publish this recipe.',
            'version_id.integer' => 'The version must be a valid integer.',
            'version_id.exists' => 'The selected version does not belong to this recipe.',
        ];
    }
}
