<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhmcsClient extends Model
{
    protected $table = 'tblclients';

    protected $fillable = [
        'firstname',
        'lastname',
        'companyname',
        'email',
        'status',
    ];

    /**
     * Virtual attribute for full name ($client->name)
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn () => trim("{$this->firstname} {$this->lastname}"),
        );
    }

    /**
     * Compatibility alias: $client->company_name -> $client->companyname
     */
    protected function companyName(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->companyname,
            set: fn ($val) => ['companyname' => $val],
        );
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(WhmcsInvoice::class, 'userid');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(WhmcsTicket::class, 'userid');
    }
}
