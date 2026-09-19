<x-layouts.app>
    <div class="d-flex flex-column gap-4">
        <div class="cp-page-heading">
            <div>
                <div class="cp-kicker">Evidence Archive</div>
                <h1 class="cp-page-title">Message Records</h1>
                <p class="cp-page-copy">Analyzed communication data, evidence counts, sentiment, and crisis classifications.</p>
            </div>
            <a href="{{ route('dashboard') }}#analysis-form" class="btn btn-dark"><i class="bi bi-plus-circle me-1"></i> New Analysis</a>
        </div>

        <div class="cp-panel">
            <div class="d-flex justify-content-between align-items-center gap-3 mb-3">
                <div>
                    <span class="cp-panel-label">Archive</span>
                    <h2 class="cp-panel-title">Analyzed Records</h2>
                </div>
                <span class="badge text-bg-light border">{{ $messages->total() }} total</span>
            </div>

            <div class="table-responsive">
                <table class="table cp-table align-middle">
                    <thead>
                        <tr>
                            <th>Source</th>
                            <th>Author</th>
                            <th>Sentiment</th>
                            <th>Crisis</th>
                            <th>Evidence</th>
                            <th>Analyzed</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($messages as $message)
                            <tr>
                                <td>{{ $message->source }}</td>
                                <td>{{ $message->author ?? 'Unknown' }}</td>
                                <td><span class="badge rounded-pill text-bg-{{ $message->sentiment === 'negative' ? 'danger' : ($message->sentiment === 'positive' ? 'success' : 'secondary') }}">{{ ucfirst($message->sentiment) }}</span></td>
                                <td><span class="badge rounded-pill text-bg-{{ $message->crisis_level === 'high' ? 'warning' : ($message->crisis_level === 'medium' ? 'info' : 'light') }}">{{ ucfirst($message->crisis_level) }}</span></td>
                                <td>
                                    @if ($message->source_url || count($message->attachments ?? []) > 0)
                                        <span class="badge text-bg-dark"><i class="bi bi-paperclip me-1"></i>{{ ($message->source_url ? 1 : 0) + count($message->attachments ?? []) }}</span>
                                    @else
                                        <span class="text-secondary">None</span>
                                    @endif
                                </td>
                                <td>{{ $message->analyzed_at?->format('M d, Y H:i') ?? 'Pending' }}</td>
                                <td class="text-end">
                                    <a href="{{ route('messages.show', $message) }}" class="btn btn-sm btn-outline-dark"><i class="bi bi-arrow-right"></i></a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-secondary">No records found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $messages->links() }}
        </div>
    </div>
</x-layouts.app>
