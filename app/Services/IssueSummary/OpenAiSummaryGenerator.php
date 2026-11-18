<?php

namespace App\Services\IssueSummary;

use App\Interfaces\SummaryGenerator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use UnexpectedValueException;

class OpenAiSummaryGenerator implements SummaryGenerator
{
    public function generate(string $title, string $description, string $category, string $priority): GeneratedSummary
    {
        $response = Http::withToken(config('services.openai.key'))
            ->connectTimeout(3)
            ->timeout(12)
            ->post(config('services.openai.url'), [
                'model'        => config('services.openai.model'),
                'instructions' => File::get(resource_path('prompts/issue-summary-template.txt')),
                'input'        => json_encode(compact('title', 'description', 'category', 'priority'), JSON_THROW_ON_ERROR),
                'text'         => [
                    'format' => [
                        'type'   => 'json_schema',
                        'name'   => 'issue_summary',
                        'strict' => true,
                        'schema' => [
                            'type'       => 'object',
                            'properties' => [
                                'summary'               => ['type' => 'string'],
                                'suggested_next_action' => ['type' => 'string'],
                            ],
                            'required'             => ['summary', 'suggested_next_action'],
                            'additionalProperties' => false,
                        ],
                    ],
                ],
            ])
            ->throw()
            ->json();

        if (($response['status'] ?? null) !== 'completed') {
            throw new UnexpectedValueException('OpenAI did not complete the response.');
        }

        $parts = collect($response['output'] ?? [])
            ->flatMap(fn (mixed $item): array => is_array($item['content'] ?? null) ? $item['content'] : []);

        if ($parts->contains('type', 'refusal')) {
            throw new UnexpectedValueException('OpenAI refused the request.');
        }

        $text = $parts->firstWhere('type', 'output_text')['text'] ?? null;
        if (!is_string($text)) {
            throw new UnexpectedValueException('OpenAI returned no summary text.');
        }

        $data = json_decode($text, true, flags: JSON_THROW_ON_ERROR);

        if (!is_array($data)) {
            throw new UnexpectedValueException('OpenAI returned an invalid summary.');
        }

        $validated = Validator::make(array_map(
            fn (mixed $value) => is_string($value) ? trim($value) : $value,
            $data,
        ), [
            'summary'               => ['required', 'string', 'max:500'],
            'suggested_next_action' => ['required', 'string'],
        ])->validate();

        return new GeneratedSummary(
            $validated['summary'],
            $validated['suggested_next_action'],
        );
    }
}
