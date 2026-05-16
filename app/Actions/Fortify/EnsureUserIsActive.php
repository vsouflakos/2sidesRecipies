<?php

namespace App\Actions\Fortify;

use App\Enums\AccountStatus;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureUserIsActive
{
    public function __invoke(Request $request, Closure $next): mixed
    {
        $user = Auth::user();

        if ($user && $user->account_status === AccountStatus::Deactivated) {
            Auth::logout();

            return redirect()->route('login')->withErrors([
                'email' => __('app.auth.deactivated'),
            ]);
        }

        return $next($request);
    }
}
