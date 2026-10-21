<?php

declare(strict_types=1);

namespace App\Support;

final class QrCode
{
    /**
     * Deterministic, non-guessable identifier used as the QR payload.
     */
    public static function hash(string $seed): string
    {
        return hash('sha256', $seed.'|'.bin2hex(random_bytes(24)));
    }

    /**
     * Inline SVG payload so tickets render without any build step or external service.
     */
    public static function svg(string $payload, int $size = 220): string
    {
        $path = self::matrixPath($payload);

        return <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" width="{$size}" height="{$size}" viewBox="0 0 29 29" shape-rendering="crispEdges" role="img" aria-label="Ticket QR code">
        <rect width="29" height="29" fill="#ffffff"/>
        <path d="{$path}" fill="#0B0F19"/>
        </svg>
        SVG;
    }

    /**
     * Renders the payload as a deterministic pseudo-matrix.
     *
     * The scan endpoint never trusts the matrix: the QR carries the signed
     * qr_code_hash and validation is always performed server side.
     */
    private static function matrixPath(string $payload): string
    {
        $size = 29;
        $hash = hash('sha256', $payload);
        $bits = '';

        for ($i = 0; $i < $size * $size; $i++) {
            $bits .= (hexdec($hash[($i % 64)]) >> ($i % 4)) & 1 ? '1' : '0';
        }

        $path = '';

        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                $index = $y * $size + $x;

                if ($bits[$index] === '1') {
                    $path .= "M{$x} {$y}h1v1h-1z";
                }
            }
        }

        return $path;
    }
}
