<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhmcsDepartment extends Model
{
    protected $table = 'tblticketdepartments';

    protected $fillable = [
        'name',
        'description',
    ];

    public function tickets(): HasMany
    {
        return $this->hasMany(WhmcsTicket::class, 'did');
    }
}
