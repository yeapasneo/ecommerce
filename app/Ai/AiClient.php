<?php

namespace App\Ai;

use App\Models\AiLog;
use App\Models\Setting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Throwable;

/**
 * Talks to an AI provider through its OpenAI-compatible chat endpoint.
 * Gemini (Google AI Studio), Groq and OpenRouter all offer free tiers.
 * If the main provider is down or out of quota, the fallback provider is tried.
 */
class AiClient
{
    public const PROVIDERS = [
        'gemini' => [
            'label' => 'Google Gemini (AI Studio)',
            'url' => 'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions',
            'key_url' => 'https://aistudio.google.com/apikey',
        ],
        'groq' => [
            'label' => 'Groq',
            'url' => 'https://api.groq.com/openai/v1/chat/completions',
            'key_url' => 'https://console.groq.com/keys',
        ],
        'openrouter' => [
            'label' => 'OpenRouter',
            'url' => 'https://openrouter.ai/api/v1/chat/completions',
            'key_url' => 'https://openrouter.ai/keys',
        ],
    ];

    /** Providers that have a key, main provider first. */
    public function chain(): array
    {
        $main = Setting::get('ai_provider', 'gemini');
        $fallback = Setting::get('ai_fallback');

        return collect([$main, $fallback])->filter(fn ($p) => isset(self::PROVIDERS[$p]) && filled(Setting::get("ai_{$p}_key")))
            ->unique()->values()->all();
    }

    protected static function busyKey(string $provider, ?string $model): string
    {
        return "ai:busy:{$provider}:".($model ?? 'default');
    }

    /** The models set for a provider, in the order to try them. */
    public static function models(string $provider): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) Setting::get("ai_{$provider}_model")))));
    }

    public function isConfigured(): bool
    {
        return $this->chain() !== [];
    }

    public function usedToday(): int
    {
        return AiLog::where('created_at', '>=', today())->where('status', 'ok')->count();
    }

    public function limitReached(): bool
    {
        $limit = (int) Setting::get('ai_daily_limit', 500);

        return $limit > 0 && $this->usedToday() >= $limit;
    }

    /**
     * Send a chat request. Returns the assistant message
     * (['content' => ?string, 'tool_calls' => ?array]) plus provider and model.
     *
     * @throws AiException
     */
    public function chat(array $messages, string $feature, array $tools = [], int $maxTokens = 1500, float $temperature = 0.4, ?string $only = null): array
    {
        $chain = $only ? array_values(array_intersect($this->chain(), [$only])) : $this->chain();
        if ($chain === []) {
            throw new AiException('AI is not set up. Add an API key in Settings → AI.', 'not_configured');
        }
        if ($this->limitReached()) {
            throw new AiException('The daily AI limit has been reached. It resets at midnight, or raise it in Settings → AI.', 'limit');
        }

        // Each provider can list several models ("gemini-3.8-flash, gemini-3.6-flash"):
        // when one is overloaded or out of quota, the next is tried, then the backup provider.
        $attempts = [];
        foreach ($chain as $provider) {
            foreach (self::models($provider) ?: [null] as $model) {
                $attempts[] = [$provider, $model];
            }
        }

        // Skip models that were overloaded in the last few minutes (but always try at least one).
        $fresh = array_values(array_filter($attempts, fn ($a) => ! Cache::has(self::busyKey(...$a))));
        $attempts = $fresh ?: $attempts;
        $timeout = $feature === 'chat' ? 40 : 60;

        $lastError = null;
        foreach ($attempts as [$provider, $model]) {
            $payload = array_filter([
                'model' => $model,
                'messages' => $messages,
                'temperature' => $temperature,
                'max_tokens' => $maxTokens,
                'tools' => $tools ?: null,
            ], fn ($v) => $v !== null);
            if ($provider === 'gemini') {
                // Gemini models think before answering ("none" isn't accepted by all of them). Thinking
                // counts against max_tokens, so callers leave room for it.
                $payload['reasoning_effort'] = 'low';
            }

            // "High demand" (503) spikes are short, so each model gets one quick retry.
            for ($try = 1; $try <= 2; $try++) {
                $started = microtime(true);
                try {
                    $response = Http::withToken((string) Setting::get("ai_{$provider}_key"))
                        ->withHeaders($provider === 'openrouter' ? ['HTTP-Referer' => config('app.url'), 'X-Title' => (string) Setting::get('site_name')] : [])
                        ->acceptJson()->connectTimeout(5)->timeout($timeout)
                        ->post(self::PROVIDERS[$provider]['url'], $payload);
                } catch (ConnectionException $e) {
                    Cache::put(self::busyKey($provider, $model), true, 300);
                    $this->log($feature, $provider, $model, 'error', $started, error: 'Could not connect: '.$e->getMessage());
                    $lastError = new AiException("Could not reach {$this->label($provider)}.", 'unavailable');

                    continue 2;
                }

                if ($response->successful() && is_array($message = $response->json('choices.0.message'))) {
                    $this->log($feature, $provider, $model, 'ok', $started, (int) $response->json('usage.prompt_tokens'), (int) $response->json('usage.completion_tokens'));

                    return ['content' => $message['content'] ?? null, 'tool_calls' => $message['tool_calls'] ?? null, 'provider' => $provider, 'model' => $model];
                }

                $error = $this->errorText($response->json(), $response->status());
                $this->log($feature, $provider, $model, 'error', $started, error: $error);
                $lastError = new AiException("{$this->label($provider)}: {$error}", $response->status() === 429 ? 'limit' : 'error');
                if ($response->status() !== 503 || $try === 2) {
                    if (in_array($response->status(), [429, 503], true)) {
                        Cache::put(self::busyKey($provider, $model), true, 300);
                    }
                    break;
                }
                Sleep::for(1500)->milliseconds();
            }

            // Move on for quota, overload, auth, unknown-model and server errors; stop on bad requests.
            if (! in_array($response->status(), [401, 403, 404, 408, 429, 500, 502, 503, 504], true)) {
                break;
            }
        }

        throw $lastError ?? new AiException('The AI provider did not answer.', 'error');
    }

    /** Plain-text answer. */
    public function text(array $messages, string $feature, int $maxTokens = 1500, float $temperature = 0.4): string
    {
        return trim((string) $this->chat($messages, $feature, [], $maxTokens, $temperature)['content']);
    }

    /** An answer that must be a JSON object; tolerates ```json fences and text around it. */
    public function json(array $messages, string $feature, int $maxTokens = 2500): array
    {
        $text = $this->text($messages, $feature, $maxTokens, 0.3);
        $data = self::decodeJson($text);
        if (! is_array($data)) {
            throw new AiException('The AI answer was not in the expected format. Try again.', 'format');
        }

        return $data;
    }

    public static function decodeJson(string $text): ?array
    {
        $text = trim(preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($text)));
        $data = json_decode($text, true);
        if (! is_array($data) && preg_match('/\{.*\}/s', $text, $m)) {
            $data = json_decode($m[0], true);
        }

        return is_array($data) ? $data : null;
    }

    /** Quick check from Settings: one short request to the given provider. */
    public function test(string $provider): string
    {
        $reply = $this->chat([['role' => 'user', 'content' => 'Reply with the single word: OK']], 'test', [], 1000, 0, $provider);

        return (trim((string) $reply['content']) ?: '(empty reply)').($reply['model'] ? " (model: {$reply['model']})" : '');
    }

    public function label(string $provider): string
    {
        return self::PROVIDERS[$provider]['label'] ?? $provider;
    }

    protected function errorText(mixed $body, int $status): string
    {
        $body = is_array($body) && array_is_list($body) ? ($body[0] ?? []) : $body;
        $message = is_array($body) ? ($body['error']['message'] ?? $body['message'] ?? null) : null;

        return mb_substr(trim((string) ($message ?: "HTTP {$status}")), 0, 300);
    }

    protected function log(string $feature, string $provider, ?string $model, string $status, float $started, int $in = 0, int $out = 0, ?string $error = null): void
    {
        try {
            AiLog::create([
                'feature' => $feature, 'provider' => $provider, 'model' => $model, 'status' => $status,
                'prompt_tokens' => $in, 'completion_tokens' => $out, 'duration_ms' => (int) ((microtime(true) - $started) * 1000),
                'error' => $error ? mb_substr($error, 0, 500) : null,
            ]);
        } catch (Throwable) {
            // logging must never break the feature
        }
    }
}
