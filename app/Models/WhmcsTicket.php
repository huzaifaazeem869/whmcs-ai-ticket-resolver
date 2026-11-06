<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhmcsTicket extends Model
{
    protected $table = 'tbltickets';

    protected $fillable = [
        'tid',
        'did',
        'userid',
        'name',
        'email',
        'title',
        'message',
        'status',
        'urgency',
        'date',
        'ai_analysis',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'datetime',
            'ai_analysis' => 'array',
        ];
    }

    /**
     * Compatibility alias: $ticket->subject -> $ticket->title
     */
    protected function subject(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->title,
            set: fn ($val) => ['title' => $val],
        );
    }

    /**
     * Compatibility alias: $ticket->priority -> $ticket->urgency
     */
    protected function priority(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->urgency,
            set: fn ($val) => ['urgency' => $val],
        );
    }

    /**
     * Department Name accessor ($ticket->department)
     */
    protected function department(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->departmentRelation?->name ?? 'Technical Support',
        );
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(WhmcsClient::class, 'userid');
    }

    public function departmentRelation(): BelongsTo
    {
        return $this->belongsTo(WhmcsDepartment::class, 'did');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(WhmcsTicketReply::class, 'tid');
    }
}
