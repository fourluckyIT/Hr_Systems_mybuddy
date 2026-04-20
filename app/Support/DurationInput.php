<?php

namespace App\Support;

final class DurationInput
{
    public static function secondsFromHms(?string $duration): ?int
    {
        if ($duration === null || trim($duration) === '') {
            return null;
        }

        if (!preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', trim($duration), $matches)) {
            return null;
        }

        $hours = (int) $matches[1];
        $minutes = (int) $matches[2];
        $seconds = isset($matches[3]) ? (int) $matches[3] : 0;

        if ($minutes > 59 || $seconds > 59) {
            return null;
        }

        return ($hours * 3600) + ($minutes * 60) + $seconds;
    }

    public static function minutesFromHms(?string $duration): ?float
    {
        $seconds = self::secondsFromHms($duration);

        if ($seconds === null) {
            return null;
        }

        return round($seconds / 60, 2);
    }

    public static function formatSecondsAsHms(?int $seconds): string
    {
        if ($seconds === null || $seconds < 0) {
            return '';
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remainingSeconds = $seconds % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $remainingSeconds);
    }

    public static function formatMinutesAsHms(null|int|float|string $minutes): string
    {
        if ($minutes === null || $minutes === '') {
            return '';
        }

        $totalSeconds = (int) round(((float) $minutes) * 60);

        return self::formatSecondsAsHms($totalSeconds);
    }
}