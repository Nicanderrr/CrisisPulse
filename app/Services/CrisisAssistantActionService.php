<?php

namespace App\Services;

use App\Models\MonitoredMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CrisisAssistantActionService
{
    public function __construct(private readonly CrisisAnalysisService $analysis) {}

    /**
     * @return array<int, array{key: string, label: string, url: string}>
     */
    public function allowedPages(): array
    {
        return [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'url' => route('dashboard')],
            ['key' => 'messages', 'label' => 'Message Records', 'url' => route('messages.index')],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return [
            'system' => [
                'name' => 'CrisisPulse AI',
                'purpose' => 'AI-based crisis communication monitoring and sentiment analysis for Korle Bu Teaching Hospital.',
                'main_workflow' => [
                    'Collect or enter public communication records.',
                    'Analyze text, source-link context, images, video frames, and video audio transcripts.',
                    'Classify sentiment and crisis level.',
                    'Store evidence and analysis findings.',
                    'Support communication officers with summaries and recommended responses.',
                ],
            ],
            'allowed_pages' => $this->allowedPages(),
            'dashboard' => $this->dashboardSummary(),
            'latest_messages' => $this->messageList(['limit' => 5])['messages'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function toolDefinitions(): array
    {
        return [
            $this->tool('open_page', 'Open a CrisisPulse AI page.', [
                'page' => ['type' => 'string', 'enum' => ['dashboard', 'messages']],
            ], ['page']),
            $this->tool('explain_system', 'Explain what CrisisPulse AI does and how it works.', []),
            $this->tool('dashboard_summary', 'Summarize the current crisis monitoring dashboard.', []),
            $this->tool('list_messages', 'List recent monitored messages by filter.', [
                'filter' => ['type' => 'string', 'enum' => ['latest', 'high_risk', 'negative', 'medium_risk']],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 10],
            ]),
            $this->tool('draft_response', 'Draft a communication response for a monitored message.', [
                'message_id' => ['type' => 'integer'],
            ], ['message_id']),
            $this->tool('analyze_text', 'Create and analyze a new text-only monitored message.', [
                'content' => ['type' => 'string'],
                'source' => ['type' => 'string'],
                'author' => ['type' => 'string'],
            ], ['content']),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function execute(string $tool, array $arguments, ?Request $request = null): array
    {
        return match ($tool) {
            'open_page' => $this->openPage($arguments),
            'explain_system' => $this->explainSystem(),
            'dashboard_summary' => $this->dashboardSummary(),
            'list_messages' => $this->messageList($arguments),
            'draft_response' => $this->draftResponse($arguments),
            'analyze_text' => $this->analyzeText($arguments),
            default => ['error' => "Unknown assistant action: {$tool}"],
        };
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function openPage(array $arguments): array
    {
        $page = collect($this->allowedPages())->firstWhere('key', $arguments['page'] ?? '');

        if (! $page) {
            return ['error' => 'That page is not available in CrisisPulse AI.'];
        }

        return ['status' => 'opening', 'page' => $page['key'], 'url' => $page['url']];
    }

    /**
     * @return array<string, mixed>
     */
    private function explainSystem(): array
    {
        return [
            'summary' => 'CrisisPulse AI monitors hospital-related communication, analyzes public sentiment, detects possible crisis signals, and helps communication officers respond faster.',
            'steps' => [
                'A user enters a message, source link, image, video, or CSV record.',
                'The analyzer reviews text, fetched link text, image content, video frames, and video audio transcripts when available.',
                'The system assigns sentiment, crisis level, keywords, summary, detailed findings, and recommended response.',
                'The dashboard shows trends, high-risk alerts, records, and evidence for review.',
                'This assistant can explain records, summarize trends, draft responses, and create text-only records.',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function dashboardSummary(): array
    {
        return [
            'total_messages' => MonitoredMessage::count(),
            'positive' => MonitoredMessage::where('sentiment', 'positive')->count(),
            'neutral' => MonitoredMessage::where('sentiment', 'neutral')->count(),
            'negative' => MonitoredMessage::where('sentiment', 'negative')->count(),
            'high_risk' => MonitoredMessage::where('crisis_level', 'high')->count(),
            'medium_risk' => MonitoredMessage::where('crisis_level', 'medium')->count(),
            'latest_recorded_at' => MonitoredMessage::latest()->value('created_at')?->toDateTimeString(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function messageList(array $arguments): array
    {
        $filter = (string) ($arguments['filter'] ?? 'latest');
        $limit = min(max((int) ($arguments['limit'] ?? 5), 1), 10);

        $messages = MonitoredMessage::query()
            ->when($filter === 'high_risk', fn ($query) => $query->where('crisis_level', 'high'))
            ->when($filter === 'medium_risk', fn ($query) => $query->where('crisis_level', 'medium'))
            ->when($filter === 'negative', fn ($query) => $query->where('sentiment', 'negative'))
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (MonitoredMessage $message): array => [
                'id' => $message->id,
                'source' => $message->source,
                'sentiment' => $message->sentiment,
                'crisis_level' => $message->crisis_level,
                'summary' => $message->summary,
                'content_preview' => Str::limit((string) $message->content, 120),
                'url' => route('messages.show', $message),
            ])
            ->values()
            ->all();

        return ['filter' => $filter, 'messages' => $messages];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function draftResponse(array $arguments): array
    {
        $message = MonitoredMessage::find((int) ($arguments['message_id'] ?? 0));

        if (! $message) {
            return ['error' => 'I could not find that monitored message.'];
        }

        $draft = match ($message->crisis_level) {
            'high' => 'We are aware of the concern raised and have escalated it to the appropriate hospital unit for immediate review. We are verifying the facts and will provide an update as soon as confirmed information is available.',
            'medium' => 'Thank you for bringing this concern to our attention. The relevant team is reviewing the matter, and we will share an update after verification.',
            default => 'Thank you for your feedback. We appreciate the public input and will continue monitoring service experience to improve patient care.',
        };

        return [
            'message_id' => $message->id,
            'crisis_level' => $message->crisis_level,
            'sentiment' => $message->sentiment,
            'draft' => $draft,
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function analyzeText(array $arguments): array
    {
        $content = trim((string) ($arguments['content'] ?? ''));

        if ($content === '') {
            return ['error' => 'Please provide the message text to analyze.'];
        }

        $analysis = $this->analysis->analyze($content);
        $message = MonitoredMessage::create([
            'source' => trim((string) ($arguments['source'] ?? 'Assistant Entry')) ?: 'Assistant Entry',
            'author' => trim((string) ($arguments['author'] ?? '')) ?: null,
            'content' => $content,
            'attachments' => [],
            ...$analysis,
            'analyzed_at' => now(),
        ]);

        return [
            'status' => 'created',
            'message_id' => $message->id,
            'sentiment' => $message->sentiment,
            'crisis_level' => $message->crisis_level,
            'url' => route('messages.show', $message),
        ];
    }

    /**
     * @param  array<string, mixed>  $properties
     * @param  array<int, string>  $required
     * @return array<string, mixed>
     */
    private function tool(string $name, string $description, array $properties, array $required = []): array
    {
        return [
            'type' => 'function',
            'name' => $name,
            'description' => $description,
            'parameters' => [
                'type' => 'object',
                'properties' => (object) $properties,
                'required' => $required,
            ],
        ];
    }
}
