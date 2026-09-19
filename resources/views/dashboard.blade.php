<x-layouts.app>
    <div class="d-flex flex-column gap-4">
        <div class="cp-page-heading">
            <div>
                <div class="cp-kicker">Live Command View</div>
                <h1 class="cp-page-title">Crisis Communication Dashboard</h1>
                <p class="cp-page-copy">Monitor public feedback, sentiment, crisis signals, and supporting evidence from one operational workspace.</p>
            </div>
            <a href="#analysis-form" class="btn btn-dark"><i class="bi bi-plus-circle me-1"></i> New Analysis</a>
        </div>

        <div class="cp-command-band">
            <div class="row g-4 align-items-center">
                <div class="col-12 col-xl-5">
                    <div class="cp-kicker text-white-50">Situation Snapshot</div>
                    <h2 class="h4 fw-bold mb-2">Hospital communications watch</h2>
                    <p class="mb-0">CrisisPulse AI reads text, links, images, and video evidence, then produces sentiment, crisis level, findings, and response guidance for review.</p>
                </div>
                <div class="col-12 col-xl-7">
                    <div class="cp-command-grid">
                        <div class="cp-command-metric">
                            <span>Analyzed Records</span>
                            <strong>{{ number_format($totalMessages) }}</strong>
                        </div>
                        <div class="cp-command-metric">
                            <span>High Alerts</span>
                            <strong>{{ number_format($highRiskMessages) }}</strong>
                        </div>
                        <div class="cp-command-metric">
                            <span>AI Mode</span>
                            <strong>{{ config('services.openai.key') ? 'Online' : 'Local' }}</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-12 col-md-6 col-xl-3">
                <div class="cp-stat">
                    <div class="cp-stat-top">
                        <span class="cp-stat-label">Total Messages</span>
                        <span class="cp-stat-icon"><i class="bi bi-inbox-fill"></i></span>
                    </div>
                    <div class="cp-stat-value">{{ number_format($totalMessages) }}</div>
                    <div class="cp-stat-note">All analyzed records</div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <div class="cp-stat">
                    <div class="cp-stat-top">
                        <span class="cp-stat-label">Negative Sentiment</span>
                        <span class="cp-stat-icon danger"><i class="bi bi-emoji-frown-fill"></i></span>
                    </div>
                    <div class="cp-stat-value text-danger">{{ number_format($negativeMessages) }}</div>
                    <div class="cp-stat-note">Needs careful response</div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <div class="cp-stat">
                    <div class="cp-stat-top">
                        <span class="cp-stat-label">High Crisis Alerts</span>
                        <span class="cp-stat-icon warning"><i class="bi bi-exclamation-triangle-fill"></i></span>
                    </div>
                    <div class="cp-stat-value text-warning">{{ number_format($highRiskMessages) }}</div>
                    <div class="cp-stat-note">Escalation candidates</div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <div class="cp-stat">
                    <div class="cp-stat-top">
                        <span class="cp-stat-label">OpenAI Status</span>
                        <span class="cp-stat-icon success"><i class="bi bi-cpu-fill"></i></span>
                    </div>
                    <div class="cp-stat-value fs-3">{{ config('services.openai.key') ? 'Connected' : 'Fallback' }}</div>
                    <div class="cp-stat-note">Analysis engine</div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-12 col-xl-5">
                <div id="analysis-form" class="cp-panel">
                    <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                        <div>
                            <span class="cp-panel-label">Intake</span>
                            <h2 class="cp-panel-title">Analyze Message</h2>
                        </div>
                        <span class="badge text-bg-dark">Text + Evidence</span>
                    </div>
                    <form method="POST" action="{{ route('messages.store') }}" enctype="multipart/form-data" class="cp-form-grid" data-processing-form>
                        @csrf
                        <div>
                            <label for="source" class="form-label">Source</label>
                            <select id="source" name="source" class="form-select">
                                <option value="">Select source</option>
                                <option value="Manual Entry">Manual Entry</option>
                                <option value="Facebook">Facebook</option>
                                <option value="X">X</option>
                                <option value="News Comment">News Comment</option>
                                <option value="Patient Feedback">Patient Feedback</option>
                            </select>
                        </div>
                        <div>
                            <label for="author" class="form-label">Author</label>
                            <input id="author" name="author" type="text" class="form-control" placeholder="Optional">
                        </div>
                        <div class="cp-span-2">
                            <label for="source_url" class="form-label">Source Link</label>
                            <input id="source_url" name="source_url" type="url" class="form-control" placeholder="https://example.com/post-or-news-link">
                        </div>
                        <div class="cp-span-2">
                            <label for="content" class="form-label">Message</label>
                            <textarea id="content" name="content" rows="6" class="form-control" placeholder="Paste a public comment, complaint, or feedback message"></textarea>
                        </div>
                        <div class="cp-span-2">
                            <label for="attachments" class="form-label">Photos or Videos</label>
                            <input id="attachments" name="attachments[]" type="file" class="form-control" accept="image/jpeg,image/png,image/webp,video/mp4,video/quicktime,video/webm" multiple>
                            <div id="attachment-preview" class="cp-upload-preview" data-file-preview></div>
                            <div class="form-text">Upload screenshots, photos, or videos as supporting evidence.</div>
                        </div>
                        <div class="alert alert-info d-none mb-0 cp-span-2" data-processing-message>
                            <span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
                            Analyzing message and evidence. Please wait...
                        </div>
                        <button class="btn btn-dark cp-span-2" type="submit" data-processing-button>
                            <span data-idle-label>Analyze</span>
                            <span class="d-none" data-loading-label>
                                <span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
                                Processing
                            </span>
                        </button>
                    </form>
                </div>

                <div class="cp-panel mt-4">
                    <span class="cp-panel-label">Bulk Intake</span>
                    <h2 class="cp-panel-title mb-3">CSV Import</h2>
                    <form method="POST" action="{{ route('messages.import') }}" enctype="multipart/form-data" class="d-flex flex-column gap-3" data-processing-form>
                        @csrf
                        <input name="csv_file" type="file" class="form-control" accept=".csv,text/csv" required>
                        <div class="alert alert-info d-none mb-0" data-processing-message>
                            <span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
                            Importing and analyzing CSV records. Please wait...
                        </div>
                        <button class="btn btn-outline-dark" type="submit" data-processing-button>
                            <span data-idle-label>Import CSV</span>
                            <span class="d-none" data-loading-label>
                                <span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
                                Processing
                            </span>
                        </button>
                    </form>
                </div>
            </div>

            <div class="col-12 col-xl-7">
                <div class="cp-panel h-100">
                    <div class="d-flex justify-content-between align-items-center gap-3 mb-3">
                        <div>
                            <span class="cp-panel-label">Recent Intelligence</span>
                            <h2 class="cp-panel-title">Latest Messages</h2>
                        </div>
                        <a href="{{ route('messages.index') }}" class="link-dark">All records</a>
                    </div>

                    <div class="table-responsive">
                        <table class="table cp-table align-middle">
                            <thead>
                                <tr>
                                    <th>Source</th>
                                    <th>Sentiment</th>
                                    <th>Crisis</th>
                                    <th>Message</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($messages as $message)
                                    <tr>
                                        <td>{{ $message->source }}</td>
                                        <td><span class="badge text-bg-{{ $message->sentiment === 'negative' ? 'danger' : ($message->sentiment === 'positive' ? 'success' : 'secondary') }}">{{ ucfirst($message->sentiment) }}</span></td>
                                        <td><span class="badge text-bg-{{ $message->crisis_level === 'high' ? 'warning' : ($message->crisis_level === 'medium' ? 'info' : 'light') }}">{{ ucfirst($message->crisis_level) }}</span></td>
                                        <td><a class="link-dark" href="{{ route('messages.show', $message) }}">{{ Str::limit($message->content ?: 'Evidence-only record', 70) }}</a></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-secondary">No messages analyzed yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-12 col-lg-6">
                <div class="cp-panel">
                    <span class="cp-panel-label">Mood Tracking</span>
                    <h2 class="cp-panel-title mb-3">Sentiment Summary</h2>
                    <div class="d-flex flex-column gap-3">
                        @foreach (['positive', 'neutral', 'negative'] as $sentiment)
                            @php($count = (int) ($sentimentCounts[$sentiment] ?? 0))
                            @php($percent = $totalMessages > 0 ? round(($count / $totalMessages) * 100) : 0)
                            <div>
                                <div class="d-flex justify-content-between small mb-1">
                                    <span>{{ ucfirst($sentiment) }}</span>
                                    <span>{{ $count }} messages</span>
                                </div>
                                <div class="progress" style="height: 10px;">
                                    <div class="progress-bar bg-{{ $sentiment === 'negative' ? 'danger' : ($sentiment === 'positive' ? 'success' : 'secondary') }}" style="width: {{ $percent }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-6">
                <div class="cp-panel">
                    <span class="cp-panel-label">Risk Triage</span>
                    <h2 class="cp-panel-title mb-3">Crisis Level Summary</h2>
                    <div class="d-flex flex-column gap-3">
                        @foreach (['low', 'medium', 'high'] as $level)
                            @php($count = (int) ($crisisCounts[$level] ?? 0))
                            @php($percent = $totalMessages > 0 ? round(($count / $totalMessages) * 100) : 0)
                            <div>
                                <div class="d-flex justify-content-between small mb-1">
                                    <span>{{ ucfirst($level) }}</span>
                                    <span>{{ $count }} messages</span>
                                </div>
                                <div class="progress" style="height: 10px;">
                                    <div class="progress-bar bg-{{ $level === 'high' ? 'warning' : ($level === 'medium' ? 'info' : 'dark') }}" style="width: {{ $percent }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
