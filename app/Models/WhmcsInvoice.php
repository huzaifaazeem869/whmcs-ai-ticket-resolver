<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhmcsInvoice extends Model
{
    protected $table = 'tblinvoices';

    protected $fillable = [
        'userid',
        'invoicenum',
        'date',
        'duedate',
        'total',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'date' => 'date',
            'duedate' => 'date',
        ];
    }

    /**
     * Compatibility alias: $invoice->amount -> $invoice->total
     */
    protected function amount(): Attribute
    {
        return Attribute::make(
            get: fn () => (float) $this->total,
            set: fn ($val) => ['total' => $val],
        );
    }

    /**
     * Compatibility alias: $invoice->invoice_number -> $invoice->invoicenum
     */
    protected function invoiceNumber(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->invoicenum,
            set: fn ($val) => ['invoicenum' => $val],
        );
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(WhmcsClient::class, 'userid');
    }
}
