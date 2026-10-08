<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Incident;
use App\Models\NotificationChannel;
use App\Models\NotificationLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationLog>
 */
class NotificationLogFactory extends Factory
{
    protected $model = NotificationLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'incident_id' => Incident::factory(),
            'notification_channel_id' => NotificationChannel::factory(),
            'subject' => 'Incident opened',
            'status' => 'sent',
            'error' => null,
            'sent_at' => now(),
        ];
    }
}
