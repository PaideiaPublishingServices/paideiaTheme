<?php

/**
 * @file plugins/themes/paideia/classes/Colors.php
 *
 * Copyright (c) 2026 Paideia Publishing Services LLC
 * Distributed under the GNU GPL v3 with an additional attribution term
 * (GPLv3 section 7(b)). See LICENSE.
 *
 * @class Colors
 *
 * @brief Variantes legibles del color principal que elige el editor.
 *
 * El editor puede elegir cualquier color. Para que enlaces, estados activos
 * y botones se lean siempre, se calculan variantes con el mismo tono que
 * cumplen el contraste WCAG AA (4,5:1) contra el fondo claro y contra el
 * fondo del modo oscuro, y el color del texto sobre el color principal.
 */

namespace APP\plugins\themes\paideia\classes;

class Colors
{
    /** Fondos del tema (ver styles/paideia.css, tokens). */
    public const BG_LIGHT = '#ffffff';
    public const BG_DARK = '#0f1216';

    /** Contraste mínimo para texto (WCAG AA). */
    public const MIN_CONTRAST = 4.5;

    /** En modo oscuro se busca más margen: los tonos medios se leen peor. */
    public const DARK_CONTRAST = 7.0;

    /**
     * @return array{primary: string, onPrimary: string, ink: string, dark: string, onDark: string}
     */
    public static function palette(string $hex): array
    {
        $primary = self::normalize($hex);
        $dark = self::adjust($primary, self::BG_DARK, +1, self::DARK_CONTRAST);
        return [
            'primary' => $primary,
            'onPrimary' => self::onColor($primary),
            // Enlaces y estados activos sobre fondo claro.
            'ink' => self::adjust($primary, self::BG_LIGHT, -1),
            // El color principal en modo oscuro: mismo tono, más claro si
            // hace falta para leerse sobre el fondo oscuro.
            'dark' => $dark,
            'onDark' => self::onColor($dark),
            // Botones al pasar el mouse: los colores oscuros se aclaran y los
            // claros se oscurecen, así el cambio se nota siempre.
            'hover' => self::hoverOf($primary),
            'darkHover' => self::hoverOf($dark),
        ];
    }

    /**
     * Aclara (+1) u oscurece (-1) el color, manteniendo tono y saturación,
     * hasta que tenga el contraste mínimo contra el fondo.
     */
    public static function adjust(string $hex, string $background, int $direction, float $target = self::MIN_CONTRAST): string
    {
        if (self::contrast($hex, $background) >= $target) {
            return $hex;
        }
        [$h, $s, $l] = self::toHsl($hex);
        for ($i = 0; $i < 100; $i++) {
            $l = max(0.0, min(1.0, $l + 0.01 * $direction));
            $candidate = self::fromHsl($h, $s, $l);
            if (self::contrast($candidate, $background) >= $target || $l <= 0.0 || $l >= 1.0) {
                return $candidate;
            }
        }
        return $direction > 0 ? '#ffffff' : '#000000';
    }

    public static function hoverOf(string $hex): string
    {
        return self::luminance($hex) < 0.18 ? self::mix($hex, '#ffffff', 0.16) : self::mix($hex, '#000000', 0.14);
    }

    /** Mezcla $hex con $with en la proporción $amount (0..1). */
    public static function mix(string $hex, string $with, float $amount): string
    {
        $a = self::toRgb($hex);
        $b = self::toRgb($with);
        return vsprintf('#%02x%02x%02x', array_map(fn ($x, $y) => (int) round($x + ($y - $x) * $amount), $a, $b));
    }

    /** Blanco o casi negro, el que más contraste tenga sobre el color. */
    public static function onColor(string $hex): string
    {
        return self::contrast($hex, '#ffffff') >= self::contrast($hex, '#111111') ? '#ffffff' : '#111111';
    }

    public static function contrast(string $a, string $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);
        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    public static function luminance(string $hex): float
    {
        $channel = function (int $v): float {
            $c = $v / 255;
            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };
        [$r, $g, $b] = self::toRgb($hex);
        return 0.2126 * $channel($r) + 0.7152 * $channel($g) + 0.0722 * $channel($b);
    }

    public static function normalize(string $hex): string
    {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        return '#' . strtolower($hex);
    }

    /** @return int[] */
    protected static function toRgb(string $hex): array
    {
        $hex = ltrim(self::normalize($hex), '#');
        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    /** @return float[] h, s, l en 0..1 */
    protected static function toHsl(string $hex): array
    {
        [$r, $g, $b] = array_map(fn ($v) => $v / 255, self::toRgb($hex));
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;
        if ($max === $min) {
            return [0.0, 0.0, $l];
        }
        $d = $max - $min;
        $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
        $h = match ($max) {
            $r => ($g - $b) / $d + ($g < $b ? 6 : 0),
            $g => ($b - $r) / $d + 2,
            default => ($r - $g) / $d + 4,
        };
        return [$h / 6, $s, $l];
    }

    protected static function fromHsl(float $h, float $s, float $l): string
    {
        if ($s == 0.0) {
            $r = $g = $b = $l;
        } else {
            $hue = function (float $p, float $q, float $t): float {
                if ($t < 0) {
                    $t += 1;
                }
                if ($t > 1) {
                    $t -= 1;
                }
                if ($t < 1 / 6) {
                    return $p + ($q - $p) * 6 * $t;
                }
                if ($t < 1 / 2) {
                    return $q;
                }
                if ($t < 2 / 3) {
                    return $p + ($q - $p) * (2 / 3 - $t) * 6;
                }
                return $p;
            };
            $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
            $p = 2 * $l - $q;
            $r = $hue($p, $q, $h + 1 / 3);
            $g = $hue($p, $q, $h);
            $b = $hue($p, $q, $h - 1 / 3);
        }
        return sprintf('#%02x%02x%02x', (int) round($r * 255), (int) round($g * 255), (int) round($b * 255));
    }
}
