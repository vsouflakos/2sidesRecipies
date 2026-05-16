<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a Recall is attempted but the client's expected_sequence does not
 * match the draft's current edit_sequence — indicating a concurrent modification.
 * The controller should map this to a 409 Conflict response.
 */
class DraftSequenceMismatchException extends RuntimeException
{
    public function __construct(int $expected, int $actual)
    {
        parent::__construct(
            "Draft sequence mismatch: expected {$expected}, got {$actual}. Refresh and try again."
        );
    }
}
