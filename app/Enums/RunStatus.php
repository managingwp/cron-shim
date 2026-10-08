<?php

declare(strict_types=1);

namespace App\Enums;

enum RunStatus: string
{
    case Success = 'success';
    case Warning = 'warning';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Success => 'Success',
            self::Warning => 'Warning',
            self::Failed => 'Failed',
        };
    }

    public function isFailure(): bool
    {
        return $this === self::Failed;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
