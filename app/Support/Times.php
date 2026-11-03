<?php

namespace App\Support;

class Times
{
    /** ['07:00' => '07:00', '07:15' => ...] for time dropdowns. */
    public static function options(string $from = '06:00', string $to = '21:00', ?int $step = null): array
    {
        $step ??= (int) config('booking.slot_minutes');
        $out = [];
        for ($m = self::minutes($from); $m <= self::minutes($to); $m += $step) {
            $t = sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
            $out[$t] = $t;
        }

        return $out;
    }

    public static function minutes(string $hhmm): int
    {
        [$h, $m] = array_map('intval', explode(':', $hhmm)) + [0, 0];

        return $h * 60 + $m;
    }

    public static function format(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60) % 24, $minutes % 60);
    }
}
