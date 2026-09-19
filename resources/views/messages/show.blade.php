<x-layouts.app>
    <div class="d-flex flex-column gap-4">
        <div class="cp-page-heading">
            <div>
                <div class="cp-kicker">Analysis Record #{{ $message->id }}</div>
                <h1 class="cp-page-title">Message Analysis</h1>
                <p class="cp-page-copy">{{ $message->source }} {{ $message->author ? 'by '.$message->author : '' }} · {{ $message->analyzed_at?->format('M d, Y H:i') ?? 'Pending analysis' }}</p>
            </div>
            <div class="d-flex gap-2">
                @php
                    $incidentPayload = [
                        'id' => $message->id,
                        'content' => $message->content,
                        'summary' => $message->summary,
                        'sentiment' => $message->sentiment,
                        'crisis_level' => $message->crisis_level,
                        'findings' => $message->detailed_analysis,
                        'recommended_response' => $message->recommended_response,
                    ];
                @endphp
                <button
                    type="button"
                    class="btn btn-dark"
                    data-incident-read-aloud
                    data-incident="{{ e(json_encode($incidentPayload, JSON_UNESCAPED_SLASHES)) }}"
                ><i class="bi bi-volume-up me-1"></i>Read Aloud</button>
                <form method="POST" action="{{ route('messages.reanalyze', $message) }}">
                    @csrf
                    <button type="submit" class="btn btn-outline-dark"><i class="bi bi-arrow-repeat me-1"></i>Re-analyze</button>
                </form>
                <form method="POST" action="{{ route('messages.destroy', $message) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash me-1"></i>Delete</button>
                </form>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-12 col-xl-8">
                <div class="cp-panel">
                    <span class="cp-panel-label">Captured Communication</span>
                    <h2 class="cp-panel-title mb-3">Original Message</h2>
                    <p class="fs-5 mb-0">{{ $message->content ?: 'No message text was provided for this record.' }}</p>
                </div>

                <div class="cp-panel mt-4">
                    <span class="cp-panel-label">Response Guidance</span>
                    <h2 class="cp-panel-title mb-3">Recommended Response</h2>
                    <p class="mb-0">{{ $message->recommended_response ?: 'No recommendation available.' }}</p>
                </div>

                <div class="cp-panel mt-4">
                    <span class="cp-panel-label">Reasoning</span>
                    <h2 class="cp-panel-title mb-3">Detailed AI Findings</h2>
                    <div class="d-flex flex-column gap-3">
                        @forelse (preg_split("/\r\n|\n|\r/", $message->detailed_analysis ?: '') as $paragraph)
                            @if (trim($paragraph) !== '')
                                <p class="mb-0">{{ $paragraph }}</p>
                            @endif
                        @empty
                            <p class="text-secondary mb-0">No detailed analysis is available for this record.</p>
                        @endforelse
                    </div>
                </div>

                <div class="cp-panel mt-4">
                    <span class="cp-panel-label">Source Material</span>
                    <h2 class="cp-panel-title mb-3">Evidence</h2>

                    @if ($message->source_url)
                        <div class="mb-4">
                            <div class="text-secondary small mb-1">Source Link</div>
                            <a href="{{ $message->source_url }}" target="_blank" rel="noopener noreferrer" class="link-dark text-break">{{ $message->source_url }}</a>
                        </div>
                    @endif

                    @if (count($message->attachments ?? []) > 0)
                        <div class="row g-3">
                            @foreach ($message->attachments ?? [] as $attachment)
                                <div class="col-12 col-md-6">
                                    <div class="cp-evidence-card">
                                        @if (($attachment['type'] ?? '') === 'video')
                                            <video controls preload="metadata">
                                                <source src="{{ asset('storage/'.$attachment['path']) }}" type="{{ $attachment['mime'] ?? 'video/mp4' }}">
                                            </video>
                                        @else
                                            <img src="{{ asset('storage/'.$attachment['path']) }}" alt="{{ $attachment['original_name'] ?? 'Uploaded evidence' }}" class="img-fluid">
                                        @endif
                                        <div class="cp-evidence-caption text-break">{{ $attachment['original_name'] ?? 'Uploaded evidence' }}</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @elseif (! $message->source_url)
                        <p class="text-secondary mb-0">No supporting link, photo, or video was attached.</p>
                    @endif
                </div>
            </div>

            <div class="col-12 col-xl-4">
                <div class="cp-panel">
                    <span class="cp-panel-label">Classification</span>
                    <h2 class="cp-panel-title mb-3">AI Decision</h2>
                    <div class="d-flex flex-column gap-3">
                        <div class="cp-detail-metric">
                            <div class="text-secondary small">Sentiment</div>
                            <div class="h4 mb-0">{{ ucfirst($message->sentiment) }}</div>
                        </div>
                        <div class="cp-detail-metric">
                            <div class="text-secondary small">Sentiment Score</div>
                            <div class="h4 mb-0">{{ number_format((float) $message->sentiment_score, 2) }}</div>
                        </div>
                        <div class="cp-detail-metric">
                            <div class="text-secondary small">Crisis Level</div>
                            <div class="h4 mb-0">{{ ucfirst($message->crisis_level) }}</div>
                        </div>
                        <div class="cp-detail-metric">
                            <div class="text-secondary small mb-2">Keywords</div>
                            <div class="d-flex flex-wrap gap-2">
                                @forelse ($message->crisis_keywords ?? [] as $keyword)
                                    <span class="badge text-bg-dark">{{ $keyword }}</span>
                                @empty
                                    <span class="text-secondary">None detected</span>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>

                <div class="cp-panel mt-4">
                    <span class="cp-panel-label">Brief</span>
                    <h2 class="cp-panel-title mb-3">Summary</h2>
                    <p class="mb-0">{{ $message->summary ?: 'No summary available.' }}</p>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
