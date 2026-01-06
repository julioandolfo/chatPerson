<?php

namespace App\Models;

class ConversationActionButton extends Model
{
    protected string $table = 'conversation_action_buttons';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'name',
        'description',
        'color',
        'icon',
        'sort_order',
        'is_active',
        'visibility'
    ];
    protected bool $timestamps = true;
}
