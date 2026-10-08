<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\NotificationChannelType;
use App\Models\NotificationChannel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationChannel>
 */
class NotificationChannelFactory extends Factory
{
    protected $model = NotificationChannel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Ops email',
            'type' => NotificationChannelType::Email,
            'config' => ['recipients' => ['ops@example.com']],
            'is_active' => true,
        ];
    }

    public function slack(): static
    {
        return $this->state(fn (): array => [
            'name' => 'Ops Slack',
            'type' => NotificationChannelType::Slack,
            'config' => ['webhook_url' => 'https://hooks.slack.com/services/T000/B000/XXXX'],
        ]);
    }
}
