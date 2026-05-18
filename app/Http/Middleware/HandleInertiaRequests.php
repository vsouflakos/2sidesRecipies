<?php

namespace App\Http\Middleware;

use App\Enums\SubmissionStatus;
use App\Models\Ingredient;
use App\Notifications\IngredientDecisionNotification;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                'permissions' => $user
                    ? $user->getAllPermissions()->pluck('name')
                    : [],
            ],
            'locale' => app()->getLocale(),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'pendingIngredientReviewCount' => $user && $user->can('review-ingredients')
                ? Ingredient::where('submission_status', SubmissionStatus::Submitted->value)->count()
                : null,
            'ingredientNotifications' => $user
                ? $user->unreadNotifications()
                    ->where('type', IngredientDecisionNotification::class)
                    ->latest()
                    ->take(5)
                    ->get(['id', 'data', 'created_at'])
                    ->map(fn ($n) => [
                        'id' => $n->id,
                        'data' => $n->data,
                        'created_at' => $n->created_at?->toISOString(),
                    ])
                    ->values()
                : null,
        ];
    }
}
