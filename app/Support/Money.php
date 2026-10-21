<?php

declare(strict_types=1);

namespace App\Support;

final class Money
{
    public const DEFAULT_CURRENCY = 'UGX';

    /**
     * Internal storage scale. Every amount is persisted as an integer of
     * "minor units" where 100 minor units make one major unit. The scale is
     * fixed for the platform so stored amounts never mix conventions.
     */
    public const STORAGE_SCALE = 100;

    /**
     * Currencies whose smallest banknote/coin unit is the major unit itself.
     * UGX (Ugandan shilling) has no subunit, so it is displayed without
     * decimals and sent to the gateway in whole shillings.
     *
     * @var array<string, int>
     */
    private const SUBUNIT_SCALE = [
        'UGX' => 1,
        'GHS' => 100,
        'KES' => 100,
        'NGN' => 100,
        'USD' => 100,
        'EUR' => 100,
    ];

    public static function currency(): string
    {
        $currency = strtoupper(trim((string) setting('currency', self::DEFAULT_CURRENCY)));

        return $currency !== '' ? $currency : self::DEFAULT_CURRENCY;
    }

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
        $currency = strtoupper($currency ?? self::currency());
        $major = $minor / self::STORAGE_SCALE;
        $decimals = self::decimalsFor($currency);

        return $currency.' '.number_format($major, $decimals);
    }

    public static function toMinor(float|int|string $amount, ?string $currency = null): int
    {
        return (int) round(((float) $amount) * self::STORAGE_SCALE);
    }

    public static function fromMinor(int $minor, ?string $currency = null): float
    {
        return round($minor / self::STORAGE_SCALE, self::decimalsFor($currency ?? self::currency()));
    }

    /**
     * Convert a stored amount into the unit the payment gateway expects.
     *
     * Paystack charges UGX in whole shillings while currencies with a subunit
     * are charged in their subunit.
     */
    public static function toGatewayAmount(int $minor, ?string $currency = null): int
    {
        $currency = strtoupper($currency ?? self::currency());

        return intdiv($minor, self::STORAGE_SCALE) * self::gatewayScale($currency);
    }

    /**
     * Convert an amount reported by the payment gateway back into stored units.
     */
    public static function fromGatewayAmount(int|string|float $amount, ?string $currency = null): int
    {
        $currency = strtoupper($currency ?? self::currency());
        $scale = self::gatewayScale($currency);

        if ($scale <= 0) {
            return 0;
        }

        return (int) round(((float) $amount / $scale) * self::STORAGE_SCALE);
    }

    /**
     * Percentage rendered for a fee label, e.g. 2000 bps becomes "20%".
     */
    public static function percent(int $bps): string
    {
        $bps = max(0, min(10_000, $bps));

        return rtrim(rtrim(number_format($bps / 100, 2), '0'), '.').'%';
    }

    /**
     * Compare an amount reported by a gateway with the stored order total.
     *
     * Gateways report the amount in the currency subunit while the platform
     * stores minor units, so both representations are accepted for the same
     * currency. Anything else is a genuine mismatch.
     */
    public static function matchesAmount(int|string|float $received, int $expectedMinor, ?string $currency = null): bool
    {
        if ((int) $received === $expectedMinor) {
            return true;
        }

        return self::fromGatewayAmount($received, $currency) === $expectedMinor;
    }

    private static function decimalsFor(string $currency): int
    {
        return (self::SUBUNIT_SCALE[$currency] ?? 100) >= 100 ? 2 : 0;
    }

    private static function gatewayScale(string $currency): int
    {
        return self::SUBUNIT_SCALE[$currency] ?? 100;
    }
}
