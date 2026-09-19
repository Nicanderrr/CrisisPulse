<?php

namespace App\Services;

use App\Models\AssistantChat;
use App\Models\MonitoredMessage;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CrisisAssistantService
{
    public function __construct(private readonly CrisisAssistantActionService $actions) {}

    /**
     * @return array{reply: string, context: array<string, mixed>}
     */
    public function reply(string $sessionId, string $message, ?int $incidentId = null): array
    {
        if (blank(config('services.openai.key'))) {
            throw new RuntimeException('OpenAI API key is not configured yet.');
        }

        try {
            $response = Http::withToken(config('services.openai.key'))
                ->acceptJson()
                ->asJson()
                ->timeout(45)
                ->post('https://api.openai.com/v1/responses', [
                    'model' => config('services.openai.model'),
                    'input' => [
                        ['role' => 'system', 'content' => [['type' => 'input_text', 'text' => $this->systemPrompt($incidentId)]]],
                        ...$this->recentMessages($sessionId),
                        ['role' => 'user', 'content' => [['type' => 'input_text', 'text' => $message]]],
                    ],
                ])
                ->throw()
                ->json();
        } catch (RequestException $exception) {
            throw new RuntimeException($this->openAiError($exception), previous: $exception);
        }

        $reply = $this->extractResponseText($response);

        AssistantChat::create([
            'session_id' => $sessionId,
            'user_message' => $message,
            'assistant_response' => $reply,
        ]);

        return [
            'reply' => $reply,
            'context' => $this->actions->context(),
        ];
    }

    public function systemPrompt(?int $incidentId = null): string
    {
        $context = json_encode($this->actions->context(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $incident = $incidentId ? MonitoredMessage::find($incidentId) : null;
        $incidentContext = $incident ? json_encode([
            'id' => $incident->id,
            'source' => $incident->source,
            'source_url' => $incident->source_url,
            'sentiment' => $incident->sentiment,
            'sentiment_score' => $incident->sentiment_score,
            'crisis_level' => $incident->crisis_level,
            'keywords' => $incident->crisis_keywords,
            'message_text' => $incident->content,
            'summary' => $incident->summary,
            'detailed_analysis' => $incident->detailed_analysis,
            'recommended_response' => $incident->recommended_response,
            'attachments' => collect($incident->attachments ?? [])->map(fn (array $attachment): array => [
                'name' => $attachment['original_name'] ?? 'uploaded evidence',
                'type' => $attachment['type'] ?? 'file',
            ])->all(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : 'No single incident is currently attached to this conversation.';

        return <<<PROMPT
You are CrisisPulse Assistant, a warm, natural, highly capable AI assistant inside the CrisisPulse AI web application.
Speak like a helpful human colleague: clear, calm, friendly, and practical.

You know the CrisisPulse AI system:
- It monitors communication related to Korle Bu Teaching Hospital.
- It analyzes typed messages, source links, uploaded images, video frames, and transcribed video audio.
- It classifies sentiment, crisis level, keywords, detailed findings, and recommended responses.
- It stores monitored messages, evidence, summaries, and dashboard trends.

You can help users operate the system. You can explain pages, summarize dashboard data, interpret records, draft communication responses, and help create text-only monitored records when the user clearly asks.
Never claim you performed an action unless the client or server action result confirms it.
Do not invent database records. If you do not know, say what you can check or ask the user for the missing detail.
Keep responses conversational. Use short paragraphs. When the user asks for a report or draft, be polished and complete.

Current CrisisPulse context:
{$context}

Current incident context:
{$incidentContext}
PROMPT;
    }

    /**
     * @return array<int, array{role: string, content: array<int, array{type: string, text: string}>}>
     */
    private function recentMessages(string $sessionId): array
    {
        return AssistantChat::where('session_id', $sessionId)
            ->latest()
            ->limit(4)
            ->get()
            ->reverse()
            ->flatMap(fn (AssistantChat $chat): array => [
                ['role' => 'user', 'content' => [['type' => 'input_text', 'text' => $chat->user_message]]],
                ['role' => 'assistant', 'content' => [['type' => 'input_text', 'text' => $chat->assistant_response]]],
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function extractResponseText(array $response): string
    {
        if (isset($response['output_text']) && is_string($response['output_text'])) {
            return trim($response['output_text']);
        }

        foreach ($response['output'] ?? [] as $item) {
            foreach ($item['content'] ?? [] as $content) {
                if (($content['type'] ?? null) === 'output_text' && isset($content['text'])) {
                    return trim($content['text']);
                }
            }
        }

        return 'I could not generate a response right now.';
    }

    private function openAiError(RequestException $exception): string
    {
        $message = $exception->response?->json('error.message') ?? $exception->getMessage();
        $message = preg_replace('/sk-[A-Za-z0-9_\\-]+/', 'sk-***', $message) ?? 'OpenAI request failed.';

        return 'OpenAI request failed: '.$message;
    }
}
