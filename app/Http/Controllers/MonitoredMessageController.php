<?php

namespace App\Http\Controllers;

use App\Models\MonitoredMessage;
use App\Services\CrisisAnalysisService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class MonitoredMessageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $messages = MonitoredMessage::query()
            ->latest()
            ->paginate(15);

        return view('messages.index', ['messages' => $messages]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): RedirectResponse
    {
        return redirect()->route('dashboard');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, CrisisAnalysisService $analysis): RedirectResponse
    {
        $validated = $request->validate([
            'source' => ['nullable', 'string', 'max:255'],
            'author' => ['nullable', 'string', 'max:255'],
            'source_url' => ['nullable', 'url', 'max:2048'],
            'content' => ['nullable', 'string', 'min:5', 'max:5000'],
            'attachments' => ['nullable', 'array', 'max:8'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,webp,mp4,mov,webm', 'max:51200'],
        ]);

        if (blank($validated['content'] ?? null) && blank($validated['source_url'] ?? null) && ! $request->hasFile('attachments')) {
            return back()
                ->withErrors(['content' => 'Provide a message, a source link, or at least one photo or video.'])
                ->withInput();
        }

        $content = $validated['content'] ?? null;
        $attachments = $this->storeAttachments($request);
        $analysisResult = filled($content) || filled($validated['source_url'] ?? null) || count($attachments) > 0
            ? $analysis->analyze($content, $attachments, $validated['source_url'] ?? null)
            : $this->evidenceOnlyAnalysis();

        $message = MonitoredMessage::create([
            ...Arr::only($validated, ['author', 'source_url']),
            'source' => ($validated['source'] ?? null) ?: 'Manual Entry',
            'content' => $content,
            'attachments' => $attachments,
            ...$analysisResult,
            'analyzed_at' => now(),
        ]);

        return redirect()
            ->route('messages.show', $message)
            ->with('status', 'Message analyzed successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(MonitoredMessage $message): View
    {
        return view('messages.show', ['message' => $message]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(MonitoredMessage $message): RedirectResponse
    {
        return redirect()->route('messages.show', $message);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, MonitoredMessage $message): RedirectResponse
    {
        return redirect()->route('messages.show', $message);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MonitoredMessage $message): RedirectResponse
    {
        foreach ($message->attachments ?? [] as $attachment) {
            if (isset($attachment['path'])) {
                Storage::disk('public')->delete($attachment['path']);
            }
        }

        $message->delete();

        return redirect()
            ->route('messages.index')
            ->with('status', 'Message removed.');
    }

    public function import(Request $request, CrisisAnalysisService $analysis): RedirectResponse
    {
        $validated = $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $path = $validated['csv_file']->getRealPath();
        $handle = fopen($path, 'r');
        $imported = 0;

        if ($handle === false) {
            return back()->withErrors(['csv_file' => 'The uploaded CSV could not be read.']);
        }

        while (($row = fgetcsv($handle)) !== false) {
            $content = trim((string) ($row[2] ?? $row[1] ?? $row[0] ?? ''));

            if ($content === '' || strcasecmp($content, 'content') === 0 || strcasecmp($content, 'message') === 0) {
                continue;
            }

            MonitoredMessage::create([
                'source_url' => filter_var($row[3] ?? null, FILTER_VALIDATE_URL) ? $row[3] : null,
                'source' => trim((string) ($row[0] ?? 'CSV Import')) ?: 'CSV Import',
                'author' => trim((string) ($row[1] ?? '')) ?: null,
                'attachments' => [],
                'content' => $content,
                ...$analysis->analyze($content, [], filter_var($row[3] ?? null, FILTER_VALIDATE_URL) ? $row[3] : null),
                'analyzed_at' => now(),
            ]);

            $imported++;
        }

        fclose($handle);

        return redirect()
            ->route('messages.index')
            ->with('status', $imported.' CSV messages imported and analyzed.');
    }

    /**
     * @return array<int, array{path: string, original_name: string, mime: string, size: int, type: string}>
     */
    private function storeAttachments(Request $request): array
    {
        if (! $request->hasFile('attachments')) {
            return [];
        }

        $attachments = [];

        foreach ($request->file('attachments') as $file) {
            $mime = (string) $file->getMimeType();
            $path = $file->store('message-evidence', 'public');

            $attachments[] = [
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime' => $mime,
                'size' => $file->getSize(),
                'type' => str_starts_with($mime, 'video/') ? 'video' : 'image',
            ];
        }

        return $attachments;
    }

    /**
     * @return array{
     *     sentiment: string,
     *     sentiment_score: float,
     *     crisis_level: string,
     *     crisis_keywords: array<int, string>,
     *     recommended_response: string,
     *     summary: string,
     *     detailed_analysis: string
     * }
     */
    private function evidenceOnlyAnalysis(): array
    {
        return [
            'sentiment' => 'neutral',
            'sentiment_score' => 0,
            'crisis_level' => 'low',
            'crisis_keywords' => [],
            'recommended_response' => 'Review the attached evidence and add a verified communication note after assessment.',
            'summary' => 'Evidence-only record with no message text provided.',
            'detailed_analysis' => implode("\n\n", [
                'This record was processed as an evidence-only submission because no message text was provided. The system did not perform text sentiment analysis, so the sentiment was set to neutral and the crisis level was set to low by default.',
                'The conclusion was based on the available input type rather than language signals. Since the record contains a link, photo, or video evidence without written context, a communication officer should manually review the attached evidence before deciding whether the issue should be escalated.',
                'Recommended action: inspect the uploaded evidence, verify the source and incident details, then update the communication response plan if the evidence shows a service failure, emergency, safety concern, or reputational risk.',
            ]),
        ];
    }
}
