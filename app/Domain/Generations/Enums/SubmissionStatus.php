<?php

namespace App\Domain\Generations\Enums;

enum SubmissionStatus: string
{
    case Pending = 'pending';
    case Submitting = 'submitting';
    case Submitted = 'submitted';
    case Uncertain = 'uncertain';
    case Rejected = 'rejected';
}
