<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhmcsTicketReply extends Model
{
    protected $table = 'tblticketreplies';

    protected $fillable = [
        'tid',
        'userid',
        'name',
        'email',
        'date',
        'message',
        'admin',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(WhmcsTicket::class, 'tid');
    }
}
