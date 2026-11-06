<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhmcsLicense extends Model
{
    use HasFactory;

    protected $table = 'whmcs_licenses';

    protected $fillable = [
        'license_key',
        'client_name',
        'whmcs_domain',
        'status',
        'tier',
        'monthly_limit',
        'usage_count',
        'last_used_at',
        'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monthly_limit' => 'integer',
            'usage_count'   => 'integer',
            'last_used_at'  => 'datetime',
            'expires_at'    => 'datetime',
        ];
    }

    /**
     * Check if the license is currently active and not expired.
     */
    public function isActive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Check if the monthly usage quota has remaining capacity.
     */
    public function hasQuota(): bool
    {
        return $this->usage_count < $this->monthly_limit;
    }

    /**
     * Increment usage counter and touch timestamp.
     */
    public function recordUsage(): void
    {
        $this->increment('usage_count');
        $this->update(['last_used_at' => now()]);
    }

    /**
     * Remaining tickets in this billing cycle.
     */
    public function remainingQuota(): int
    {
        return max(0, $this->monthly_limit - $this->usage_count);
    }
}
