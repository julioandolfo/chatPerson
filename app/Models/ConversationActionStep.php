<?php

namespace App\Models;

class ConversationActionStep extends Model
{
    protected string $table = 'conversation_action_steps';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'button_id',
        'type',
        'payload',
        'sort_order'
    ];
    protected bool $timestamps = true;
}
