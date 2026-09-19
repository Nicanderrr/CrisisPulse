<?php

namespace App\Http\Controllers;

use App\Services\CrisisAssistantActionService;
use App\Services\CrisisAssistantService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class AssistantController extends Controller
{
    public function boot(CrisisAssistantActionService $actions)
    {
        return response()->json([
            'context' => $actions->context(),
            'tools' => $actions->toolDefinitions(),
        ]);
    }

    public function message(Request $request, CrisisAssistantService $assistant, CrisisAssistantActionService $actions)
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:3000'],
            'incident_id' => ['nullable', 'integer', 'exists:monitored_messages,id'],
        ]);

        try {
            return response()->json($assistant->reply(
                $request->session()->getId(),
                $validated['message'],
                $validated['incident_id'] ?? null,
            ));
        } catch (RuntimeException $exception) {
            return response()->json([
                'reply' => $this->fallbackReply($validated['message']),
                'context' => $actions->context(),
                'offline' => true,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    public function action(Request $request, CrisisAssistantActionService $actions)
    {
        $validated = $request->validate([
            'tool' => ['required', 'string', 'max:80'],
            'arguments' => ['nullable', 'array'],
        ]);

        return response()->json($actions->execute(
            $validated['tool'],
            $validated['arguments'] ?? [],
            $request,
        ));
    }

    public function realtimeCall(Request $request, CrisisAssistantService $assistant, CrisisAssistantActionService $actions)
    {
        $sdp = $request->getContent();

        if (! str_contains($sdp, 'v=0')) {
            return response('Missing SDP offer.', 422);
        }

        if (! config('services.openai.key')) {
            return response('OpenAI API key is not configured on the server.', 500);
        }

        $incidentId = $request->integer('incident_id') ?: $request->header('X-CrisisPulse-Incident') ?: null;
        $session = [
            'type' => 'realtime',
            'model' => config('services.openai.realtime_model'),
            'instructions' => $assistant->systemPrompt($incidentId)
                .'\nThe user is currently viewing: '.str($request->header('X-CrisisPulse-Page', '/'))->limit(120)
                .'\nUse the available tools whenever the user asks about CrisisPulse records, summaries, navigation, response drafts, or new analyses. Be natural and conversational. Ask for confirmation before creating a new record if the user\'s intent is ambiguous.',
            'output_modalities' => ['audio'],
            'audio' => [
                'input' => [
                    'noise_reduction' => ['type' => 'far_field'],
                    'transcription' => [
                        'model' => config('services.openai.realtime_transcription_model'),
                        'prompt' => 'The user is speaking English with a Ghanaian accent about Korle Bu Teaching Hospital, CrisisPulse AI, crisis communication, sentiment analysis, monitored messages, source links, evidence, crisis levels, and recommended responses.',
                    ],
                    'turn_detection' => [
                        'type' => 'semantic_vad',
                        'eagerness' => 'auto',
                        'create_response' => true,
                        'interrupt_response' => true,
                    ],
                ],
                'output' => ['voice' => config('services.openai.realtime_voice')],
            ],
            'tools' => $actions->toolDefinitions(),
            'tool_choice' => 'auto',
            'parallel_tool_calls' => false,
        ];

        try {
            $response = Http::withToken(config('services.openai.key'))
                ->baseUrl(rtrim(config('services.openai.base_url'), '/'))
                ->timeout(60)
                ->accept('application/sdp')
                ->withHeaders([
                    'OpenAI-Safety-Identifier' => hash('sha256', 'crisispulse-user-'.$request->user()->id),
                ])
                ->asMultipart()
                ->post('/realtime/calls', [
                    ['name' => 'sdp', 'contents' => $sdp],
                    ['name' => 'session', 'contents' => json_encode($session)],
                ])
                ->throw();
        } catch (\Throwable $exception) {
            Log::warning('CrisisPulse Realtime session failed.', [
                'message' => $exception->getMessage(),
                'model' => config('services.openai.realtime_model'),
            ]);

            return response('Realtime session failed: '.$exception->getMessage(), 502);
        }

        $answer = $this->normalizeSdp($response->body());

        if (! str_starts_with($answer, 'v=0')) {
            return response('Realtime did not return a valid SDP answer.', 502);
        }

        return response($answer, 200, [
            'Content-Type' => 'application/sdp',
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function normalizeSdp(string $sdp): string
    {
        $sdp = preg_replace('/^\xEF\xBB\xBF/', '', $sdp) ?? $sdp;
        $sdp = trim($sdp);
        $sdp = preg_replace("/\r\n|\r|\n/", "\r\n", $sdp) ?? $sdp;

        return $sdp."\r\n";
    }

    private function fallbackReply(string $message): string
    {
        $lower = mb_strtolower($message);

        if (str_contains($lower, 'explain') || str_contains($lower, 'what is') || str_contains($lower, 'how')) {
            return 'CrisisPulse AI monitors hospital-related communication, analyzes sentiment and crisis risk, stores evidence, and helps communication officers understand what needs attention.';
        }

        return 'I can help with CrisisPulse records, summaries, response drafts, and system guidance. OpenAI is unavailable right now, but the built-in action buttons and shortcuts still work.';
    }
}
