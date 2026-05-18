<?php

namespace App\Enums;

enum SubmissionStatus: string
{
    case Private = 'private';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';
}
