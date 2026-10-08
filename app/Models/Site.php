<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RunStatus;
use Database\Factories\SiteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'uuid', 'name', 'url', 'environment', 'timezone', 'expected_interval_minutes',
    'grace_minutes', 'is_active', 'ingest_token_hash', 'signing_secret',
    'last_run_at', 'last_status', 'last_seen_ip', 'notes',
])]
#[Hidden(['ingest_token_hash', 'signing_secret'])]
class Site extends Model
{
    /** @use HasFactory<SiteFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'expected_interval_minutes' => 'integer',
            'grace_minutes' => 'integer',
            'signing_secret' => 'encrypted',
            'last_run_at' => 'datetime',
            'last_status' => RunStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Site $site): void {
            if (empty($site->uuid)) {
                $site->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * @return HasMany<CronRun, $this>
     */
    public function runs(): HasMany
    {
        return $this->hasMany(CronRun::class);
    }

    /**
     * @return HasMany<CronLogEntry, $this>
     */
    public function logEntries(): HasMany
    {
        return $this->hasMany(CronLogEntry::class);
    }

    /**
     * @return HasMany<Incident, $this>
     */
    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    public function isSilent(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->last_run_at === null) {
            return true;
        }

        return now()->greaterThan(
            $this->last_run_at->copy()->addMinutes(
                $this->expected_interval_minutes + $this->grace_minutes,
            ),
        );
    }

    /**
     * Coarse health bucket used by the catalog and dashboard.
     */
    public function health(): string
    {
        if (! $this->is_active) {
            return 'disabled';
        }

        if ($this->isSilent()) {
            return 'silent';
        }

        return $this->last_status === RunStatus::Failed ? 'degraded' : 'healthy';
    }
}
