<?php

namespace App\Domain\Generations\Enums;

enum GenerationQuality: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
}
