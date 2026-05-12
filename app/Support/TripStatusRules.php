<?php

declare(strict_types=1);

namespace App\Support;

final class TripStatusRules
{
    /**
     * @return list<string>
     */
    public static function allowedNextStatuses(string $current): array
    {
        return match ($current) {
            'pending' => ['pending', 'in_progress', 'cancelled'],
            'in_progress' => ['in_progress', 'completed', 'cancelled'],
            'completed' => ['completed'],
            'cancelled' => ['cancelled'],
            default => [],
        };
    }

    public static function allows(string $from, string $to): bool
    {
        return in_array($to, self::allowedNextStatuses($from), true);
    }
}
