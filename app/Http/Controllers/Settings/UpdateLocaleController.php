<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateLocaleRequest;
use Illuminate\Http\RedirectResponse;

class UpdateLocaleController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(UpdateLocaleRequest $request): RedirectResponse
    {
        $locale = $request->validated('locale');

        if ($request->user()) {
            $request->user()->update(['locale' => $locale]);
        }

        $request->session()->put('locale', $locale);

        return back();
    }
}
