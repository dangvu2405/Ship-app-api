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
            // Keep legacy states for backward compatibility while supporting spec states.
            'pending' => ['pending', 'assigned', 'in_progress', 'cancelled'],
            'assigned' => ['assigned', 'in_transit', 'in_progress', 'cancelled'],
            'in_transit' => ['in_transit', 'delivered', 'completed', 'cancelled'],
            'delivered' => ['delivered', 'completed', 'cancelled'],
            'in_progress' => ['in_progress', 'delivered', 'completed', 'cancelled'],
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
