<?php

namespace App\Models;

use Database\Factories\MonitoredMessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MonitoredMessage extends Model
{
    /** @use HasFactory<MonitoredMessageFactory> */
    use HasFactory;

    protected $fillable = [
        'source',
        'author',
        'source_url',
        'attachments',
        'content',
        'sentiment',
        'sentiment_score',
        'crisis_level',
        'crisis_keywords',
        'recommended_response',
        'summary',
        'detailed_analysis',
        'analyzed_at',
    ];

    protected function casts(): array
    {
        return [
            'crisis_keywords' => 'array',
            'attachments' => 'array',
            'sentiment_score' => 'decimal:2',
            'analyzed_at' => 'datetime',
        ];
    }
}
