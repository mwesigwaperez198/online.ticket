<?php

declare(strict_types=1);

namespace App\Support;

final class Money
{
    public const DEFAULT_CURRENCY = 'GHS';

    public static function commissionBps(): int
    {
        $raw = (int) setting('platform_commission_bps', 2000);

        return max(0, min(10_000, $raw));
    }

    /**
     * Split a gross amount into the platform commission and the organizer share.
     *
     * @return array{commission_minor:int, organizer_share_minor:int}
     */
    public static function split(int $grossMinor, ?int $overrideBps = null): array
    {
        $bps = $overrideBps ?? self::commissionBps();
        $bps = max(0, min(10_000, $bps));

        $commission = intdiv(($grossMinor * $bps) + 5_000, 10_000);

        return [
            'commission_minor' => $commission,
            'organizer_share_minor' => $grossMinor - $commission,
        ];
    }

    public static function format(int $minor, ?string $currency = null): string
    {
        $currency ??= (string) setting('currency', self::DEFAULT_CURRENCY);

        return $currency.' '.number_format($minor / 100, 2);
    }

    public static function toMinor(float|int|string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    public static function fromMinor(int $minor): float
    {
        return round($minor / 100, 2);
    }
}
