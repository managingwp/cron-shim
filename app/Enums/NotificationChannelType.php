<?php

declare(strict_types=1);

namespace App\Enums;

enum NotificationChannelType: string
{
    case Email = 'email';
    case Slack = 'slack';

    public function label(): string
    {
        return match ($this) {
            self::Email => 'Email',
            self::Slack => 'Slack',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
