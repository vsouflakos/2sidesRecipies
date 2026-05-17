<?php

namespace App\Support\Recipes;

use RuntimeException;

/**
 * Thrown when an agent edit action cannot be applied to a draft — an unknown
 * action, an unresolvable ingredient/unit reference, or a missing target
 * line/section/step.
 *
 * The caller (SuggestionApplier) catches this, marks the proposal as failed,
 * and leaves the draft untouched.
 */
class DraftActionException extends RuntimeException {}
