<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Incident;
use App\Models\NotificationChannel;
use App\Models\NotificationLog;
use App\Services\NotificationSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendIncidentNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 5;

    public function __construct(
        public int $incidentId,
        public int $channelId,
        public string $event,
    ) {}

    public function handle(NotificationSender $sender): void
    {
        $incident = Incident::query()->with('site')->find($this->incidentId);
        $channel = NotificationChannel::query()->find($this->channelId);

        if ($incident === null || $channel === null) {
            return;
        }

        $subject = $this->subject($incident);

        try {
            $sender->send($channel, $subject, $this->body($incident));

            $this->log($incident, $channel, $subject, 'sent');
        } catch (Throwable $exception) {
            $this->log($incident, $channel, $subject, 'failed', $exception->getMessage());

            throw $exception;
        }
    }

    private function subject(Incident $incident): string
    {
        return sprintf(
            '[Cron Shim] %s: %s — %s',
            ucfirst($this->event),
            $incident->type->label(),
            $incident->site?->name ?? 'site',
        );
    }

    private function body(Incident $incident): string
    {
        $lines = [
            'Event: '.ucfirst($this->event),
            'Site: '.($incident->site?->name ?? '—'),
            'Type: '.$incident->type->label(),
            'Opened: '.$incident->opened_at->toDateTimeString(),
        ];

        if ($incident->resolved_at !== null) {
            $lines[] = 'Resolved: '.$incident->resolved_at->toDateTimeString();
        }

        if (! empty($incident->details)) {
            $lines[] = 'Details: '.json_encode($incident->details, JSON_UNESCAPED_SLASHES);
        }

        return implode("\n", $lines);
    }

    private function log(Incident $incident, NotificationChannel $channel, string $subject, string $status, ?string $error = null): void
    {
        NotificationLog::query()->create([
            'incident_id' => $incident->id,
            'notification_channel_id' => $channel->id,
            'subject' => $subject,
            'status' => $status,
            'error' => $error,
            'sent_at' => now(),
        ]);
    }
}
