<?php

namespace App\Interfaces;

use App\Services\IssueSummary\GeneratedSummary;

interface SummaryGenerator
{
    public function generate(
        string $title,
        string $description,
        string $category,
        string $priority
    ): GeneratedSummary;
}