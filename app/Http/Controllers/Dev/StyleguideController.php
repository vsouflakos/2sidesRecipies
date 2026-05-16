<?php

namespace App\Http\Controllers\Dev;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class StyleguideController extends Controller
{
    /**
     * Render the dev-only styleguide page.
     */
    public function index(): Response
    {
        return Inertia::render('dev/styleguide');
    }
}
