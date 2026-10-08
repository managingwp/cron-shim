<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use Database\Factories\IncidentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'site_id', 'type', 'status', 'opened_at', 'resolved_at',
    'details', 'last_notified_at', 'notify_count',
])]
class Incident extends Model
{
    /** @use HasFactory<IncidentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => IncidentType::class,
            'status' => IncidentStatus::class,
            'opened_at' => 'datetime',
            'resolved_at' => 'datetime',
            'details' => 'array',
            'last_notified_at' => 'datetime',
            'notify_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function isOpen(): bool
    {
        return $this->status === IncidentStatus::Open;
    }

    /**
     * @param  Builder<Incident>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', IncidentStatus::Open->value);
    }

    /**
     * @param  Builder<Incident>  $query
     */
    public function scopeResolved(Builder $query): void
    {
        $query->where('status', IncidentStatus::Resolved->value);
    }
}
