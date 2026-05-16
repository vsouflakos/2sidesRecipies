<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\VerifyIngredientRequest;
use App\Models\Ingredient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class IngredientVerificationController extends Controller
{
    /**
     * Mark an ingredient as verified by the current moderator/admin.
     */
    public function store(VerifyIngredientRequest $request, Ingredient $ingredient): RedirectResponse
    {
        DB::transaction(function () use ($ingredient) {
            $ingredient->verified = true;
            $ingredient->verified_by = auth()->id();
            $ingredient->verified_at = now();
            $ingredient->save();
        });

        return back()->with('success', __('app.ingredients.verify_toast'));
    }
}
