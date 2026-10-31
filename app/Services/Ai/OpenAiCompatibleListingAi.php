<?php

namespace App\Services\Ai;

use App\Contracts\ListingAi;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Any OpenAI-compatible chat-completions endpoint. Falls back to the offline
 * heuristic on any error, so listing never blocks on the AI provider.
 */
class OpenAiCompatibleListingAi implements ListingAi
{
    public function __construct(private readonly array $config, private readonly FakeListingAi $fallback) {}

    public function enabled(): bool
    {
        return true;
    }

    public function suggestTitles(array $input): array
    {
        if (blank($this->config['api_key'] ?? null)) {
            return $this->fallback->suggestTitles($input);
        }

        $system = 'You write short, clear marketplace product titles for Nigerian buyers. '
            .'Use ONLY facts present in the seller text. Never invent brand names, models, specs, sizes, '
            .'warranty, authenticity or availability. Max 70 characters each. '
            .'Reply with a JSON object: {"titles": ["...", "...", "..."]}.';

        $user = json_encode([
            'description' => mb_substr((string) $input['description'], 0, 1500),
            'category' => $input['category'] ?? null,
            'condition' => $input['condition'] ?? null,
            'city' => $input['city'] ?? null,
        ], JSON_UNESCAPED_UNICODE);

        try {
            $res = Http::timeout($this->config['timeout'] ?? 15)
                ->withToken($this->config['api_key'])
                ->post(rtrim($this->config['base_url'], '/').'/chat/completions', [
                    'model' => $this->config['model'],
                    'temperature' => 0.4,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $user],
                    ],
                ]);

            $content = $res->json('choices.0.message.content');
            $titles = json_decode((string) $content, true)['titles'] ?? [];
            $titles = array_values(array_filter(array_map(
                fn ($t) => is_string($t) ? mb_substr(trim($t), 0, 90) : null,
                $titles
            )));

            return $titles ? array_slice($titles, 0, 3) : $this->fallback->suggestTitles($input);
        } catch (Throwable $e) {
            Log::warning('ListingAi provider failed, using fallback', ['error' => $e->getMessage()]);

            return $this->fallback->suggestTitles($input);
        }
    }

    public function suggestDescription(array $input): ?string
    {
        if (blank($this->config['api_key'] ?? null)) {
            return $this->fallback->suggestDescription($input);
        }
        $system = 'You write clear, friendly marketplace product descriptions for Nigerian buyers. '
            .'Use ONLY facts present in the seller notes, title, category, condition and city. Never invent brands, '
            .'models, specs, sizes, warranty, authenticity, delivery promises or availability. 60-160 words, plain text, '
            .'short paragraphs, optional bullet list with "• ". Reply with JSON: {"description": "..."}.';
        try {
            $res = Http::timeout($this->config['timeout'] ?? 15)
                ->withToken($this->config['api_key'])
                ->post(rtrim($this->config['base_url'], '/').'/chat/completions', [
                    'model' => $this->config['model'],
                    'temperature' => 0.4,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => json_encode([
                            'title' => $input['title'] ?? null,
                            'notes' => mb_substr((string) $input['notes'], 0, 2000),
                            'category' => $input['category'] ?? null,
                            'condition' => $input['condition'] ?? null,
                            'city' => $input['city'] ?? null,
                        ], JSON_UNESCAPED_UNICODE)],
                    ],
                ]);
            $text = json_decode((string) $res->json('choices.0.message.content'), true)['description'] ?? null;

            return is_string($text) && trim($text) !== '' ? mb_substr(trim($text), 0, 4800) : $this->fallback->suggestDescription($input);
        } catch (Throwable $e) {
            Log::warning('ListingAi description failed, using fallback', ['error' => $e->getMessage()]);

            return $this->fallback->suggestDescription($input);
        }
    }
}
