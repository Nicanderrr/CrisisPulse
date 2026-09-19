<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssistantChat extends Model
{
    protected $fillable = [
        'session_id',
        'user_message',
        'assistant_response',
        'action_name',
        'action_result',
    ];

    protected function casts(): array
    {
        return [
            'action_result' => 'array',
        ];
    }
}
