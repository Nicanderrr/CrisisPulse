<?php

namespace App\Http\Controllers;

use App\Models\MonitoredMessage;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $messages = MonitoredMessage::query()
            ->latest()
            ->limit(12)
            ->get();

        $totalMessages = MonitoredMessage::count();
        $sentimentCounts = MonitoredMessage::query()
            ->selectRaw('sentiment, count(*) as total')
            ->groupBy('sentiment')
            ->pluck('total', 'sentiment');
        $crisisCounts = MonitoredMessage::query()
            ->selectRaw('crisis_level, count(*) as total')
            ->groupBy('crisis_level')
            ->pluck('total', 'crisis_level');

        return view('dashboard', [
            'messages' => $messages,
            'totalMessages' => $totalMessages,
            'sentimentCounts' => $sentimentCounts,
            'crisisCounts' => $crisisCounts,
            'highRiskMessages' => MonitoredMessage::where('crisis_level', 'high')->count(),
            'negativeMessages' => MonitoredMessage::where('sentiment', 'negative')->count(),
        ]);
    }
}
