<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LogLevel;
use Database\Factories\CronLogEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['cron_run_id', 'site_id', 'level', 'message', 'context', 'logged_at'])]
class CronLogEntry extends Model
{
    /** @use HasFactory<CronLogEntryFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => LogLevel::class,
            'context' => 'array',
            'logged_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<CronRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(CronRun::class, 'cron_run_id');
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
