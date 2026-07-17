<?php

declare(strict_types=1);

namespace App\Model;

enum AnalysisSource: string
{
    case Ai = 'ai';
    case Heuristic = 'heuristic';
}
