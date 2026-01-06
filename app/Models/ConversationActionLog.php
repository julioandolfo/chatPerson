<?php

namespace App\Models;

class ConversationActionLog extends Model
{
    protected string $table = 'conversation_action_logs';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'button_id',
        'conversation_id',
        'user_id',
        'result',
        'steps_executed',
        'error_message'
    ];
    protected bool $timestamps = false;
}
