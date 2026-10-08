<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RunStatus;
use Database\Factories\CronRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'site_id', 'run_uuid', 'started_at', 'finished_at', 'duration_ms', 'status',
    'exit_code', 'jobs_run', 'jobs_failed', 'summary', 'source_ip', 'meta',
])]
class CronRun extends Model
{
    /** @use HasFactory<CronRunFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RunStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'duration_ms' => 'integer',
            'exit_code' => 'integer',
            'jobs_run' => 'integer',
            'jobs_failed' => 'integer',
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * @return HasMany<CronLogEntry, $this>
     */
    public function logEntries(): HasMany
    {
        return $this->hasMany(CronLogEntry::class);
    }

    /**
     * @param  Builder<CronRun>  $query
     */
    public function scopeFailed(Builder $query): void
    {
        $query->where('status', RunStatus::Failed->value);
    }
}
