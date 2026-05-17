<?php

namespace App\Support\Recipes;

use App\Models\Ingredient;
use App\Models\Unit;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Applies an agent edit `action` to a recipe working draft.
 *
 * CORE PRINCIPLE: an action is always applied ON TOP of the CURRENT full draft.
 * The proposal `data` is treated as the action-specific delta (parameters),
 * never as a replacement draft. The output is always a complete, valid draft
 * of the same shape the recipe builder persists.
 *
 * On an unresolvable reference, a missing target, or an unknown action this
 * throws DraftActionException so the caller can fail the proposal gracefully
 * WITHOUT mutating the draft.
 */
class DraftActionApplier
{
    /**
     * The action names this applier understands. Mirrors the enum declared in
     * AgentOrchestrator::buildTools()'s propose_recipe_edit tool.
     *
     * @var list<string>
     */
    public const SUPPORTED_ACTIONS = [
        'update_metadata',
        'add_ingredient_line',
        'remove_ingredient_line',
        'update_ingredient_line',
        'update_section',
        'add_step',
        'update_step',
        'apply_scale',
        'add_sub_recipe',
    ];

    /**
     * Top-level metadata keys an `update_metadata` action may set.
     *
     * @var list<string>
     */
    private const METADATA_KEYS = [
        'name',
        'yield_amount',
        'portions',
        'prep_time_minutes',
        'cook_time_minutes',
        'difficulty',
        'cuisine_id',
        'notes',
        'selling_price',
    ];

    /**
     * Apply the given action to the current draft data and return a complete
     * new draft array.
     *
     * @param  array<string, mixed>  $currentDraft  The draft's existing `data`.
     * @param  array<string, mixed>  $delta  The proposal's action-specific parameters.
     * @return array<string, mixed> A complete new draft.
     *
     * @throws DraftActionException On unknown action, unresolvable reference, or missing target.
     */
    public function apply(array $currentDraft, string $action, array $delta): array
    {
        if (! in_array($action, self::SUPPORTED_ACTIONS, true)) {
            throw new DraftActionException("Unknown edit action: {$action}.");
        }

        // Always clone from the current draft — never replace it wholesale.
        $draft = $this->normalizeDraft($currentDraft);

        return match ($action) {
            'update_metadata' => $this->updateMetadata($draft, $delta),
            'add_ingredient_line' => $this->addIngredientLine($draft, $delta),
            'remove_ingredient_line' => $this->removeIngredientLine($draft, $delta),
            'update_ingredient_line' => $this->updateIngredientLine($draft, $delta),
            'update_section' => $this->updateSection($draft, $delta),
            'add_step' => $this->addStep($draft, $delta),
            'update_step' => $this->updateStep($draft, $delta),
            'apply_scale' => $this->applyScale($draft, $delta),
            'add_sub_recipe' => $this->addSubRecipe($draft, $delta),
        };
    }

    /**
     * Ensure the draft has a usable `sections` array so mutations are safe.
     *
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>
     */
    private function normalizeDraft(array $draft): array
    {
        if (! isset($draft['sections']) || ! is_array($draft['sections'])) {
            $draft['sections'] = [];
        }

        return $draft;
    }

    /**
     * Merge only the provided top-level metadata fields into the draft.
     *
     * @param  array<string, mixed>  $draft
     * @param  array<string, mixed>  $delta
     * @return array<string, mixed>
     */
    private function updateMetadata(array $draft, array $delta): array
    {
        foreach (self::METADATA_KEYS as $key) {
            if (array_key_exists($key, $delta)) {
                $draft[$key] = $delta[$key];
            }
        }

        return $draft;
    }

    /**
     * Append a new ingredient line to a target section.
     *
     * @param  array<string, mixed>  $draft
     * @param  array<string, mixed>  $delta
     * @return array<string, mixed>
     */
    private function addIngredientLine(array $draft, array $delta): array
    {
        $sectionIndex = $this->resolveSectionIndex($draft, $delta);

        $section = $draft['sections'][$sectionIndex];
        $lines = is_array($section['lines'] ?? null) ? $section['lines'] : [];

        $line = [
            'id' => $this->nextNegativeId($draft),
            'ingredient_id' => $this->resolveIngredientId($delta),
            'sub_recipe_version_id' => $delta['sub_recipe_version_id'] ?? null,
            'name' => $this->resolveLineName($delta),
            'quantity' => isset($delta['quantity']) ? (string) $delta['quantity'] : '0',
            'unit_id' => $this->resolveUnitId($delta),
            'prep_note' => $delta['prep_note'] ?? null,
            'yield_pct' => isset($delta['yield_pct']) ? (string) $delta['yield_pct'] : '100',
            'is_flour_base' => (bool) ($delta['is_flour_base'] ?? false),
            'order' => count($lines),
        ];

        $lines[] = $line;
        $draft['sections'][$sectionIndex]['lines'] = $lines;

        return $draft;
    }

    /**
     * Remove an ingredient line by id from whatever section contains it.
     *
     * @param  array<string, mixed>  $draft
     * @param  array<string, mixed>  $delta
     * @return array<string, mixed>
     */
    private function removeIngredientLine(array $draft, array $delta): array
    {
        $lineId = $this->extractLineId($delta);
        $located = $this->locateLine($draft, $lineId);

        [$sectionIndex, $lineIndex] = $located;

        $lines = $draft['sections'][$sectionIndex]['lines'];
        array_splice($lines, $lineIndex, 1);

        // Re-number the remaining lines so `order` stays contiguous.
        foreach ($lines as $i => $line) {
            $lines[$i]['order'] = $i;
        }

        $draft['sections'][$sectionIndex]['lines'] = $lines;

        return $draft;
    }

    /**
     * Update only the provided fields of an existing ingredient line.
     *
     * @param  array<string, mixed>  $draft
     * @param  array<string, mixed>  $delta
     * @return array<string, mixed>
     */
    private function updateIngredientLine(array $draft, array $delta): array
    {
        $lineId = $this->extractLineId($delta);
        [$sectionIndex, $lineIndex] = $this->locateLine($draft, $lineId);

        $line = $draft['sections'][$sectionIndex]['lines'][$lineIndex];

        if (array_key_exists('quantity', $delta)) {
            $line['quantity'] = (string) $delta['quantity'];
        }
        if (array_key_exists('prep_note', $delta)) {
            $line['prep_note'] = $delta['prep_note'];
        }
        if (array_key_exists('yield_pct', $delta)) {
            $line['yield_pct'] = (string) $delta['yield_pct'];
        }
        if (array_key_exists('is_flour_base', $delta)) {
            $line['is_flour_base'] = (bool) $delta['is_flour_base'];
        }
        if (array_key_exists('name', $delta)) {
            $line['name'] = (string) $delta['name'];
        }
        if (array_key_exists('unit_id', $delta) || array_key_exists('unit', $delta)) {
            $line['unit_id'] = $this->resolveUnitId($delta);
        }
        if (array_key_exists('ingredient_id', $delta) || array_key_exists('ingredient_name', $delta)) {
            $line['ingredient_id'] = $this->resolveIngredientId($delta);
        }

        $draft['sections'][$sectionIndex]['lines'][$lineIndex] = $line;

        return $draft;
    }

    /**
     * Update a section's metadata (currently its name).
     *
     * @param  array<string, mixed>  $draft
     * @param  array<string, mixed>  $delta
     * @return array<string, mixed>
     */
    private function updateSection(array $draft, array $delta): array
    {
        $sectionIndex = $this->resolveSectionIndex($draft, $delta);

        if (array_key_exists('name', $delta)) {
            $draft['sections'][$sectionIndex]['name'] = $delta['name'];
        }
        if (array_key_exists('section_name', $delta)) {
            $draft['sections'][$sectionIndex]['name'] = $delta['section_name'];
        }
        if (array_key_exists('order', $delta)) {
            $draft['sections'][$sectionIndex]['order'] = (int) $delta['order'];
        }

        return $draft;
    }

    /**
     * Append a step to a target section.
     *
     * @param  array<string, mixed>  $draft
     * @param  array<string, mixed>  $delta
     * @return array<string, mixed>
     */
    private function addStep(array $draft, array $delta): array
    {
        $sectionIndex = $this->resolveSectionIndex($draft, $delta);

        $section = $draft['sections'][$sectionIndex];
        $steps = is_array($section['steps'] ?? null) ? $section['steps'] : [];

        $steps[] = [
            'id' => $this->nextNegativeId($draft),
            'instruction' => $delta['instruction'] ?? null,
            'order' => count($steps),
            'step_image_path' => $delta['step_image_path'] ?? null,
        ];

        $draft['sections'][$sectionIndex]['steps'] = $steps;

        return $draft;
    }

    /**
     * Update an existing step's instruction by its id.
     *
     * @param  array<string, mixed>  $draft
     * @param  array<string, mixed>  $delta
     * @return array<string, mixed>
     */
    private function updateStep(array $draft, array $delta): array
    {
        $stepId = $delta['step_id'] ?? $delta['id'] ?? null;

        if ($stepId === null) {
            throw new DraftActionException('update_step requires a step id.');
        }

        foreach ($draft['sections'] as $si => $section) {
            foreach ($section['steps'] ?? [] as $ti => $step) {
                if (($step['id'] ?? null) === $stepId) {
                    if (array_key_exists('instruction', $delta)) {
                        $draft['sections'][$si]['steps'][$ti]['instruction'] = $delta['instruction'];
                    }
                    if (array_key_exists('order', $delta)) {
                        $draft['sections'][$si]['steps'][$ti]['order'] = (int) $delta['order'];
                    }
                    if (array_key_exists('step_image_path', $delta)) {
                        $draft['sections'][$si]['steps'][$ti]['step_image_path'] = $delta['step_image_path'];
                    }

                    return $draft;
                }
            }
        }

        throw new DraftActionException("update_step: step #{$stepId} not found in the draft.");
    }

    /**
     * Multiply every ingredient line quantity by the provided scale factor.
     *
     * Accepts either a single `factor`, or `scale_numerator`/`scale_denominator`.
     * Uses BigDecimal (scale 6, HALF_UP) to avoid float drift.
     *
     * @param  array<string, mixed>  $draft
     * @param  array<string, mixed>  $delta
     * @return array<string, mixed>
     */
    private function applyScale(array $draft, array $delta): array
    {
        if (array_key_exists('factor', $delta)) {
            $num = BigDecimal::of((string) $delta['factor']);
            $den = BigDecimal::one();
        } else {
            $num = BigDecimal::of((string) ($delta['scale_numerator'] ?? 1));
            $den = BigDecimal::of((string) ($delta['scale_denominator'] ?? 1));
        }

        if ($den->isZero()) {
            throw new DraftActionException('apply_scale: scale denominator cannot be zero.');
        }

        foreach ($draft['sections'] as $si => $section) {
            foreach ($section['lines'] ?? [] as $li => $line) {
                if (isset($line['quantity']) && is_numeric($line['quantity'])) {
                    $scaled = BigDecimal::of((string) $line['quantity'])
                        ->multipliedBy($num)
                        ->dividedBy($den, 6, RoundingMode::HALF_UP);
                    $draft['sections'][$si]['lines'][$li]['quantity'] = (string) $scaled;
                }
            }
        }

        return $draft;
    }

    /**
     * Append a sub-recipe reference line to a target section.
     *
     * @param  array<string, mixed>  $draft
     * @param  array<string, mixed>  $delta
     * @return array<string, mixed>
     */
    private function addSubRecipe(array $draft, array $delta): array
    {
        $sectionIndex = $this->resolveSectionIndex($draft, $delta);

        $subRecipeVersionId = $delta['sub_recipe_version_id'] ?? null;

        if ($subRecipeVersionId === null) {
            throw new DraftActionException('add_sub_recipe requires a sub_recipe_version_id.');
        }

        $section = $draft['sections'][$sectionIndex];
        $lines = is_array($section['lines'] ?? null) ? $section['lines'] : [];

        $lines[] = [
            'id' => $this->nextNegativeId($draft),
            'ingredient_id' => null,
            'sub_recipe_version_id' => (int) $subRecipeVersionId,
            'name' => (string) ($delta['name'] ?? 'Sub-recipe'),
            'quantity' => isset($delta['quantity']) ? (string) $delta['quantity'] : '0',
            'unit_id' => $this->resolveUnitId($delta),
            'prep_note' => $delta['prep_note'] ?? null,
            'yield_pct' => isset($delta['yield_pct']) ? (string) $delta['yield_pct'] : '100',
            'is_flour_base' => (bool) ($delta['is_flour_base'] ?? false),
            'order' => count($lines),
        ];

        $draft['sections'][$sectionIndex]['lines'] = $lines;

        return $draft;
    }

    /**
     * Resolve the index of the section a line/step/section action targets.
     *
     * Matches by section `id` first, then by `section_name`/`name`
     * (case-insensitive). Falls back to the first section when nothing is
     * specified. Throws if a named/identified section cannot be found.
     *
     * @param  array<string, mixed>  $draft
     * @param  array<string, mixed>  $delta
     */
    private function resolveSectionIndex(array $draft, array $delta): int
    {
        $sections = $draft['sections'];

        if (empty($sections)) {
            throw new DraftActionException('The draft has no sections to edit.');
        }

        $sectionId = $delta['section_id'] ?? null;
        if ($sectionId !== null) {
            foreach ($sections as $index => $section) {
                if (($section['id'] ?? null) === $sectionId) {
                    return (int) $index;
                }
            }

            throw new DraftActionException("Section #{$sectionId} was not found in the draft.");
        }

        $sectionName = $delta['section_name'] ?? ($delta['section'] ?? null);
        if ($sectionName !== null) {
            foreach ($sections as $index => $section) {
                if (
                    isset($section['name']) &&
                    is_string($section['name']) &&
                    mb_strtolower($section['name']) === mb_strtolower((string) $sectionName)
                ) {
                    return (int) $index;
                }
            }

            throw new DraftActionException("Section \"{$sectionName}\" was not found in the draft.");
        }

        return 0;
    }

    /**
     * Locate an ingredient line by id across all sections.
     *
     * @param  array<string, mixed>  $draft
     * @return array{0: int, 1: int} [sectionIndex, lineIndex]
     *
     * @throws DraftActionException When no line carries that id.
     */
    private function locateLine(array $draft, int|string $lineId): array
    {
        foreach ($draft['sections'] as $si => $section) {
            foreach ($section['lines'] ?? [] as $li => $line) {
                if (($line['id'] ?? null) == $lineId) {
                    return [(int) $si, (int) $li];
                }
            }
        }

        throw new DraftActionException("Ingredient line #{$lineId} was not found in the draft.");
    }

    /**
     * Extract the line id from a delta, accepting `id` or `line_id`.
     *
     * @param  array<string, mixed>  $delta
     */
    private function extractLineId(array $delta): int|string
    {
        $lineId = $delta['id'] ?? $delta['line_id'] ?? null;

        if ($lineId === null) {
            throw new DraftActionException('The proposal must reference a line id from the draft.');
        }

        return $lineId;
    }

    /**
     * Resolve a unit id from `unit_id`, or from a `unit` symbol/name.
     *
     * @param  array<string, mixed>  $delta
     */
    private function resolveUnitId(array $delta): ?int
    {
        if (array_key_exists('unit_id', $delta) && $delta['unit_id'] !== null) {
            return (int) $delta['unit_id'];
        }

        $unit = $delta['unit'] ?? null;
        if ($unit === null || $unit === '') {
            return null;
        }

        $match = Unit::query()
            ->where('symbol', $unit)
            ->orWhere('name', $unit)
            ->first();

        if ($match === null) {
            throw new DraftActionException("Unit \"{$unit}\" could not be resolved.");
        }

        return $match->id;
    }

    /**
     * Resolve an ingredient id from `ingredient_id`, or from `ingredient_name`.
     *
     * Returns null when neither is supplied (a free-text line is allowed).
     *
     * @param  array<string, mixed>  $delta
     */
    private function resolveIngredientId(array $delta): ?int
    {
        if (array_key_exists('ingredient_id', $delta) && $delta['ingredient_id'] !== null) {
            return (int) $delta['ingredient_id'];
        }

        $name = $delta['ingredient_name'] ?? null;
        if ($name === null || $name === '') {
            return null;
        }

        $match = Ingredient::query()
            ->whereHas('translations', fn ($q) => $q->where('name', $name))
            ->first()
            ?? Ingredient::query()->where('name_cache', $name)->first();

        if ($match === null) {
            // A free-text ingredient line is still valid — keep the name, no id.
            return null;
        }

        return $match->id;
    }

    /**
     * Resolve the display name for a new line.
     *
     * @param  array<string, mixed>  $delta
     */
    private function resolveLineName(array $delta): string
    {
        if (isset($delta['name']) && $delta['name'] !== '') {
            return (string) $delta['name'];
        }

        if (isset($delta['ingredient_name']) && $delta['ingredient_name'] !== '') {
            return (string) $delta['ingredient_name'];
        }

        return '';
    }

    /**
     * Compute the next unused negative client-side id for a new line/step.
     *
     * @param  array<string, mixed>  $draft
     */
    private function nextNegativeId(array $draft): int
    {
        $min = 0;

        foreach ($draft['sections'] as $section) {
            foreach ($section['lines'] ?? [] as $line) {
                if (isset($line['id']) && is_numeric($line['id'])) {
                    $min = min($min, (int) $line['id']);
                }
            }
            foreach ($section['steps'] ?? [] as $step) {
                if (isset($step['id']) && is_numeric($step['id'])) {
                    $min = min($min, (int) $step['id']);
                }
            }
        }

        return $min - 1;
    }
}
