<?php

namespace App\Services\IssueSummary;

final readonly class GeneratedSummary
{
    public function __construct(public string $summary, public string $suggestedNextAction) {}
}
