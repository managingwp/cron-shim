<?php

declare(strict_types=1);

namespace App\Enums;

enum IncidentType: string
{
    case MissedRun = 'missed_run';
    case RunFailed = 'run_failed';
    case RepeatedFailure = 'repeated_failure';
    case Recovered = 'recovered';

    public function label(): string
    {
        return match ($this) {
            self::MissedRun => 'Missed run',
            self::RunFailed => 'Run failed',
            self::RepeatedFailure => 'Repeated failures',
            self::Recovered => 'Recovered',
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
