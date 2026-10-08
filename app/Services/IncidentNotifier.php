<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\SendIncidentNotification;
use App\Models\Incident;
use App\Models\NotificationChannel;

class IncidentNotifier
{
    /**
     * Fan an incident event out to every active channel, subject to throttling.
     */
    public function notify(Incident $incident, string $event): void
    {
        if ($event !== 'recovered') {
            $minInterval = (int) config('cronshim.notifications.min_interval_minutes');

            if ($incident->last_notified_at !== null
                && $incident->last_notified_at->greaterThan(now()->subMinutes($minInterval))) {
                return;
            }
        }

        $channels = NotificationChannel::query()->active()->get();

        if ($channels->isEmpty()) {
            return;
        }

        foreach ($channels as $channel) {
            SendIncidentNotification::dispatch($incident->id, $channel->id, $event);
        }

        $incident->forceFill([
            'last_notified_at' => now(),
            'notify_count' => $incident->notify_count + 1,
        ])->save();
    }
}
