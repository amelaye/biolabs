<?php
/**
 * Normalisation of reference names (display form, unique key, loose key)
 * Freely inspired by Plasmi::db 0.9.1 (Mark Brooks) and BioPHP's project biophp.org
 * Created 17 October 2026
 * Last modified 17 October 2026
 */
declare(strict_types=1);

namespace App\Reference\Service;

/**
 * Pure functions that normalise the names of reference entries.
 *
 * - display form: Unicode NFC, trimmed, runs of whitespace collapsed; case, accents,
 *   parentheses and Greek letters are kept ("DH5α" is not "DH5a", "BL21(DE3)" is not "BL21 DE3");
 * - key: the display form lowercased, used for uniqueness;
 * - loose key: the key without anything but letters and digits, used to warn about near-duplicates.
 *
 * @author Amélie DUVERNET aka Amelaye <amelieonline@gmail.com>
 */
final class ReferenceName
{
    /**
     * Display form of a name: Unicode NFC, trimmed, runs of whitespace collapsed; case, accents, parentheses and Greek letters are kept.
     *
     * @param string|null $name Name as typed
     * @return string The display form, empty for null
     */
    public static function display(?string $name): string
    {
        $name = $name ?? '';
        if (class_exists(\Normalizer::class)) {
            $name = \Normalizer::normalize($name, \Normalizer::FORM_C) ?: $name;
        }

        return trim((string) preg_replace('/\s+/u', ' ', $name));
    }

    /**
     * Unique key of a name: its display form lowercased.
     *
     * @param string|null $name Name as typed
     * @return string The key
     */
    public static function key(?string $name): string
    {
        return mb_strtolower(self::display($name));
    }

    /**
     * Loose key used to spot near-duplicates: the key with everything but letters and digits removed.
     *
     * @param string|null $name Name as typed
     * @return string The loose key
     */
    public static function looseKey(?string $name): string
    {
        return (string) preg_replace('/[^\p{L}\p{N}]+/u', '', self::key($name));
    }
}
