<?php

namespace App\Concerns;

use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

trait AdminUserGuards
{
    /**
     * Determine if the target user is the last remaining Admin.
     */
    protected function isLastAdmin(User $target): bool
    {
        if (! $target->hasRole('Admin')) {
            return false;
        }

        return User::role('Admin')->count() <= 1;
    }

    /**
     * Determine if the authenticated user is acting on themselves.
     */
    protected function isActingOnSelf(User $target): bool
    {
        return auth()->id() === $target->id;
    }

    /**
     * Handle a failed validation attempt.
     *
     * Always returns a 422 JSON response so that tests and Inertia can
     * reliably detect guard failures without requiring Inertia request headers.
     */
    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()->toArray(),
            ], 422)
        );
    }
}
