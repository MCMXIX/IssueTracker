<?php

namespace App\Services\IssueSummary;

use App\Interfaces\SummaryGenerator;
use Illuminate\Support\Str;

class RuleBasedSummaryGenerator implements SummaryGenerator
{
    /**
     * Generate Rule Based Summary as Fallback
     */
    public function generate(string $title, string $description, string $category, string $priority): GeneratedSummary
    {
        $description = trim($description);
        $firstSentence = preg_split('/(?<=[.!?])\s+/u', $description, 2)[0] ?? '';
        $summary = Str::limit($firstSentence !== '' ? $firstSentence : trim($title), 497, '...');
        $ticket = Str::lower($title.' '.$description);

        $nextAction = match (true) {
            Str::contains($ticket, ['charged twice', 'duplicate charge']) => 'Compare both charge records against the invoice.',
            $category === 'billing' => 'Review the invoice and payment history.',
            $category === 'access'  => 'Ask the reporter for the exact error shown.',
            $category === 'operations' => 'Check the logs for the affected process at the reported time.',
            default => 'Ask the reporter for the steps to reproduce the issue.',
        };

        return new GeneratedSummary($summary, $nextAction);
    }
}
