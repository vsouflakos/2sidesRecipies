<?php

use App\Http\Controllers\Admin\IngredientReviewController;
use App\Http\Controllers\Admin\IngredientVerificationController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Dev\StyleguideController;
use App\Http\Controllers\Ingredients\IngredientController;
use App\Http\Controllers\Ingredients\IngredientPriceController;
use App\Http\Controllers\Ingredients\PrivateIngredientController;
use App\Http\Controllers\Recipes\RecipeController;
use App\Http\Controllers\Recipes\RecipeDraftController;
use App\Http\Controllers\Recipes\RecipeDuplicateController;
use App\Http\Controllers\Recipes\RecipeSearchController;
use App\Http\Controllers\Recipes\RecipeVersionController;
use App\Http\Controllers\Settings\UpdateLocaleController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
    Route::get('ingredients', [IngredientController::class, 'index'])->name('ingredients.index');
    Route::get('ingredients/create', [PrivateIngredientController::class, 'create'])->name('ingredients.create');
    Route::post('ingredients', [PrivateIngredientController::class, 'store'])->name('ingredients.store');
    Route::get('ingredients/{ingredient}/edit', [PrivateIngredientController::class, 'edit'])->name('ingredients.edit');
    Route::put('ingredients/{ingredient}', [PrivateIngredientController::class, 'update'])->name('ingredients.update');
    Route::delete('ingredients/{ingredient}', [PrivateIngredientController::class, 'destroy'])->name('ingredients.destroy');
    Route::post('ingredients/{ingredient}/prices', [IngredientPriceController::class, 'store'])->name('ingredients.prices.store');
    Route::get('ingredients/{ingredient}', [IngredientController::class, 'show'])->name('ingredients.show');

    // Recipe routes — static segments before the {recipe} wildcard
    Route::get('recipes', [RecipeController::class, 'index'])->name('recipes.index');
    Route::get('recipes/create', [RecipeController::class, 'create'])->name('recipes.create');
    Route::post('recipes', [RecipeController::class, 'store'])->name('recipes.store');
    Route::get('recipes/{recipe}/versions/compare', [RecipeVersionController::class, 'compare'])->name('recipes.versions.compare');
    Route::get('recipes/{recipe}/versions/{version}', [RecipeVersionController::class, 'show'])->name('recipes.versions.show');
    Route::get('recipes/{recipe}', [RecipeController::class, 'show'])->name('recipes.show');
    Route::delete('recipes/{recipe}', [RecipeController::class, 'destroy'])->name('recipes.destroy');
    Route::put('recipes/{recipe}/draft', [RecipeDraftController::class, 'update'])->name('recipes.draft.update');
    Route::post('recipes/{recipe}/draft/recall', [RecipeDraftController::class, 'recall'])->name('recipes.draft.recall');
    Route::post('recipes/{recipe}/versions', [RecipeVersionController::class, 'store'])->name('recipes.versions.store');
    Route::post('recipes/{recipe}/duplicate', [RecipeDuplicateController::class, 'store'])->name('recipes.duplicate');

    // Unified component search
    Route::get('search/components', [RecipeSearchController::class, 'index'])->name('search.components');
});

Route::middleware('auth')
    ->put('locale', UpdateLocaleController::class)
    ->name('locale.update');

Route::middleware(['auth', 'verified'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index')->middleware('permission:manage-users');
        Route::put('users/{user}/role', [UserController::class, 'assignRole'])->name('users.role');
        Route::put('users/{user}/status', [UserController::class, 'toggleStatus'])->name('users.status');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });

Route::middleware(['auth', 'verified', 'permission:review-ingredients'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('ingredients', [IngredientReviewController::class, 'index'])->name('ingredients.index');
    });

Route::middleware(['auth', 'verified', 'permission:verify-ingredients'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::post('ingredients/{ingredient}/verify', [IngredientVerificationController::class, 'store'])->name('ingredients.verify');
    });

require __DIR__.'/settings.php';

if (app()->isLocal() || app()->runningUnitTests()) {
    Route::middleware('auth')
        ->get('dev/styleguide', [StyleguideController::class, 'index'])
        ->name('dev.styleguide');
}
