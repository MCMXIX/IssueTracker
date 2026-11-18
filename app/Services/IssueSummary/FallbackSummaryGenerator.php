<?php

namespace App\Services\IssueSummary;

use App\Interfaces\SummaryGenerator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Validation\ValidationException;
use JsonException;
use UnexpectedValueException;

class FallbackSummaryGenerator implements SummaryGenerator
{
    public function __construct(
        private OpenAiSummaryGenerator $openAi,
        private RuleBasedSummaryGenerator $rules,
    ) {}

    public function generate(string $title, string $description, string $category, string $priority): GeneratedSummary
    {
        if (filled(config('services.openai.key'))) {
            try {
                return $this->openAi->generate($title, $description, $category, $priority);
            } catch (ConnectionException|RequestException|ValidationException|JsonException|UnexpectedValueException) {
                // Continue with the local rules when the provider cannot return a usable result.
            }
        }

        return $this->rules->generate($title, $description, $category, $priority);
    }
}
