<?php

use App\Http\Controllers\Admin\IngredientReviewController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Dev\StyleguideController;
use App\Http\Controllers\Ingredients\IngredientController;
use App\Http\Controllers\Settings\UpdateLocaleController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
    Route::get('ingredients', [IngredientController::class, 'index'])->name('ingredients.index');
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

require __DIR__.'/settings.php';

if (app()->isLocal() || app()->runningUnitTests()) {
    Route::middleware('auth')
        ->get('dev/styleguide', [StyleguideController::class, 'index'])
        ->name('dev.styleguide');
}
