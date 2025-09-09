<?php
/**
 * TPT Government Platform - String Utility
 *
 * Advanced string manipulation and processing utilities
 */

namespace Core\Utility;

class Str
{
    /**
     * Convert string to camelCase
     */
    public static function camel(string $string): string
    {
        return lcfirst(static::studly($string));
    }

    /**
     * Convert string to StudlyCase
     */
    public static function studly(string $string): string
    {
        $string = ucwords(str_replace(['-', '_'], ' ', $string));
        return str_replace(' ', '', $string);
    }

    /**
     * Convert string to snake_case
     */
    public static function snake(string $string): string
    {
        if (!ctype_lower($string)) {
            $string = preg_replace('/\s+/u', '', ucwords($string));
            $string = static::lower(preg_replace('/(.)(?=[A-Z])/u', '$1_', $string));
        }

        return $string;
    }

    /**
     * Convert string to kebab-case
     */
    public static function kebab(string $string): string
    {
        return static::snake($string, '-');
    }

    /**
     * Convert string to Title Case
     */
    public static function title(string $string): string
    {
        return mb_convert_case($string, MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * Convert string to UPPERCASE
     */
    public static function upper(string $string): string
    {
        return mb_strtoupper($string, 'UTF-8');
    }

    /**
     * Convert string to lowercase
     */
    public static function lower(string $string): string
    {
        return mb_strtolower($string, 'UTF-8');
    }

    /**
     * Capitalize first letter
     */
    public static function ucfirst(string $string): string
    {
        return static::upper(static::substr($string, 0, 1)) . static::substr($string, 1);
    }

    /**
     * Get substring
     */
    public static function substr(string $string, int $start, ?int $length = null): string
    {
        return mb_substr($string, $start, $length, 'UTF-8');
    }

    /**
     * Get string length
     */
    public static function length(string $string): int
    {
        return mb_strlen($string, 'UTF-8');
    }

    /**
     * Check if string starts with substring
     */
    public static function startsWith(string $haystack, string|array $needles): bool
    {
        if (!is_array($needles)) {
            $needles = [$needles];
        }

        foreach ($needles as $needle) {
            if ($needle !== '' && strncmp($haystack, $needle, static::length($needle)) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if string ends with substring
     */
    public static function endsWith(string $haystack, string|array $needles): bool
    {
        if (!is_array($needles)) {
            $needles = [$needles];
        }

        foreach ($needles as $needle) {
            if ($needle !== '' && static::substr($haystack, -static::length($needle)) === $needle) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if string contains substring
     */
    public static function contains(string $haystack, string|array $needles): bool
    {
        if (!is_array($needles)) {
            $needles = [$needles];
        }

        foreach ($needles as $needle) {
            if ($needle !== '' && mb_strpos($haystack, $needle, 0, 'UTF-8') !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Replace first occurrence
     */
    public static function replaceFirst(string $search, string $replace, string $subject): string
    {
        if ($search === '') {
            return $subject;
        }

        $position = mb_strpos($subject, $search, 0, 'UTF-8');

        if ($position !== false) {
            return static::substr($subject, 0, $position) . $replace . static::substr($subject, $position + static::length($search));
        }

        return $subject;
    }

    /**
     * Replace last occurrence
     */
    public static function replaceLast(string $search, string $replace, string $subject): string
    {
        $position = mb_strrpos($subject, $search, 0, 'UTF-8');

        if ($position !== false) {
            return static::substr($subject, 0, $position) . $replace . static::substr($subject, $position + static::length($search));
        }

        return $subject;
    }

    /**
     * Replace all occurrences
     */
    public static function replace(string|array $search, string|array $replace, string $subject): string
    {
        return str_replace($search, $replace, $subject);
    }

    /**
     * Remove substring
     */
    public static function remove(string $search, string $subject): string
    {
        return str_replace($search, '', $subject);
    }

    /**
     * Generate random string
     */
    public static function random(int $length = 16): string
    {
        $string = '';

        while (($len = static::length($string)) < $length) {
            $size = $length - $len;
            $bytes = random_bytes($size);
            $string .= static::substr(str_replace(['/', '+', '='], '', base64_encode($bytes)), 0, $size);
        }

        return $string;
    }

    /**
     * Generate UUID
     */
    public static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // Set version to 0100
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // Set bits 6-7 to 10

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Limit string length
     */
    public static function limit(string $value, int $limit = 100, string $end = '...'): string
    {
        if (mb_strwidth($value, 'UTF-8') <= $limit) {
            return $value;
        }

        return rtrim(mb_strimwidth($value, 0, $limit, '', 'UTF-8')) . $end;
    }

    /**
     * Extract words from string
     */
    public static function words(string $value, int $words = 100, string $end = '...'): string
    {
        preg_match('/^\s*+(?:\S++\s*+){1,' . $words . '}/u', $value, $matches);

        if (!isset($matches[0]) || static::length($value) === static::length($matches[0])) {
            return $value;
        }

        return rtrim($matches[0]) . $end;
    }

    /**
     * Slugify string
     */
    public static function slug(string $title, string $separator = '-', string $language = 'en'): string
    {
        $title = static::ascii($title, $language);

        // Convert to lowercase
        $title = static::lower($title);

        // Remove accents
        $title = static::removeAccents($title);

        // Replace non letter or digit with separator
        $title = preg_replace('![' . preg_quote($separator, '!') . ']+!u', $separator, $title);

        // Remove any character that is not a word character or separator
        $title = preg_replace('![^' . preg_quote($separator, '!') . '\w]+!u', '', $title);

        // Trim separators from the beginning and end
        return trim($title, $separator);
    }

    /**
     * Convert to ASCII
     */
    public static function ascii(string $value, string $language = 'en'): string
    {
        $value = static::removeAccents($value);

        // Add language-specific replacements
        $replacements = [
            'en' => [
                '/æ|ǽ/' => 'ae',
                '/œ/' => 'oe',
                '/ß/' => 'ss',
            ],
        ];

        if (isset($replacements[$language])) {
            $value = preg_replace(array_keys($replacements[$language]), array_values($replacements[$language]), $value);
        }

        return $value;
    }

    /**
     * Remove accents from string
     */
    protected static function removeAccents(string $string): string
    {
        if (!preg_match('/[\x80-\xff]/', $string)) {
            return $string;
        }

        $chars = [
            // Decompositions for Latin-1 Supplement
            chr(195) . chr(128) => 'A', chr(195) . chr(129) => 'A',
            chr(195) . chr(130) => 'A', chr(195) . chr(131) => 'A',
            chr(195) . chr(132) => 'A', chr(195) . chr(133) => 'A',
            chr(195) . chr(135) => 'C', chr(195) . chr(136) => 'E',
            chr(195) . chr(137) => 'E', chr(195) . chr(138) => 'E',
            chr(195) . chr(139) => 'E', chr(195) . chr(140) => 'I',
            chr(195) . chr(141) => 'I', chr(195) . chr(142) => 'I',
            chr(195) . chr(143) => 'I', chr(195) . chr(145) => 'N',
            chr(195) . chr(146) => 'O', chr(195) . chr(147) => 'O',
            chr(195) . chr(148) => 'O', chr(195) . chr(149) => 'O',
            chr(195) . chr(150) => 'O', chr(195) . chr(153) => 'U',
            chr(195) . chr(154) => 'U', chr(195) . chr(155) => 'U',
            chr(195) . chr(156) => 'U', chr(195) . chr(157) => 'Y',
            chr(195) . chr(159) => 's', chr(195) . chr(160) => 'a',
            chr(195) . chr(161) => 'a', chr(195) . chr(162) => 'a',
            chr(195) . chr(163) => 'a', chr(195) . chr(164) => 'a',
            chr(195) . chr(165) => 'a', chr(195) . chr(167) => 'c',
            chr(195) . chr(168) => 'e', chr(195) . chr(169) => 'e',
            chr(195) . chr(170) => 'e', chr(195) . chr(171) => 'e',
            chr(195) . chr(172) => 'i', chr(195) . chr(173) => 'i',
            chr(195) . chr(174) => 'i', chr(195) . chr(175) => 'i',
            chr(195) . chr(177) => 'n', chr(195) . chr(178) => 'o',
            chr(195) . chr(179) => 'o', chr(195) . chr(180) => 'o',
            chr(195) . chr(181) => 'o', chr(195) . chr(182) => 'o',
            chr(195) . chr(182) => 'o', chr(195) . chr(185) => 'u',
            chr(195) . chr(186) => 'u', chr(195) . chr(187) => 'u',
            chr(195) . chr(188) => 'u', chr(195) . chr(189) => 'y',
            chr(195) . chr(191) => 'y',
            // Decompositions for Latin Extended-A
            chr(197) . chr(146) => 'OE', chr(197) . chr(147) => 'oe',
            chr(197) . chr(160) => 'S', chr(197) . chr(161) => 's',
            chr(197) . chr(189) => 'Z', chr(197) . chr(190) => 'z',
            // Euro Sign
            chr(226) . chr(130) . chr(172) => 'E',
            // GBP (Pound) Sign
            chr(194) . chr(163) => '',
        ];

        $string = strtr($string, $chars);

        return $string;
    }

    /**
     * Check if string is valid JSON
     */
    public static function isJson(string $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        json_decode($value);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Check if string is valid URL
     */
    public static function isUrl(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Check if string is valid email
     */
    public static function isEmail(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Check if string is valid UUID
     */
    public static function isUuid(string $value): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value) === 1;
    }

    /**
     * Mask sensitive data
     */
    public static function mask(string $value, int $visibleStart = 4, int $visibleEnd = 4, string $maskChar = '*'): string
    {
        $length = static::length($value);

        if ($length <= $visibleStart + $visibleEnd) {
            return $value;
        }

        $start = static::substr($value, 0, $visibleStart);
        $end = static::substr($value, -$visibleEnd);
        $maskLength = $length - $visibleStart - $visibleEnd;
        $mask = str_repeat($maskChar, $maskLength);

        return $start . $mask . $end;
    }

    /**
     * Extract domain from email
     */
    public static function domainFromEmail(string $email): string
    {
        $parts = explode('@', $email);
        return $parts[1] ?? '';
    }

    /**
     * Extract username from email
     */
    public static function usernameFromEmail(string $email): string
    {
        $parts = explode('@', $email);
        return $parts[0] ?? '';
    }

    /**
     * Format phone number
     */
    public static function formatPhoneNumber(string $phone, string $country = 'US'): string
    {
        // Remove all non-digit characters
        $phone = preg_replace('/\D/', '', $phone);

        // Format based on country
        switch ($country) {
            case 'US':
                if (strlen($phone) === 10) {
                    return '(' . substr($phone, 0, 3) . ') ' . substr($phone, 3, 3) . '-' . substr($phone, 6);
                }
                break;
            case 'AU':
                if (strlen($phone) === 9) {
                    return substr($phone, 0, 2) . ' ' . substr($phone, 2, 4) . ' ' . substr($phone, 6);
                }
                break;
        }

        return $phone;
    }

    /**
     * Generate initials from name
     */
    public static function initials(string $name): string
    {
        $parts = explode(' ', trim($name));
        $initials = '';

        foreach ($parts as $part) {
            $initials .= static::upper(static::substr($part, 0, 1));
        }

        return $initials;
    }

    /**
     * Check if string is palindrome
     */
    public static function isPalindrome(string $value): bool
    {
        $clean = preg_replace('/[^a-zA-Z0-9]/', '', static::lower($value));
        return $clean === strrev($clean);
    }

    /**
     * Count words in string
     */
    public static function wordCount(string $string): int
    {
        return str_word_count($string);
    }

    /**
     * Reverse string
     */
    public static function reverse(string $string): string
    {
        return strrev($string);
    }

    /**
     * Check if string is empty or whitespace
     */
    public static function isBlank(string $value): bool
    {
        return trim($value) === '';
    }

    /**
     * Check if string is not empty
     */
    public static function isNotBlank(string $value): bool
    {
        return !static::isBlank($value);
    }

    /**
     * Trim whitespace and normalize
     */
    public static function normalize(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', $value));
    }

    /**
     * Convert to base64
     */
    public static function toBase64(string $value): string
    {
        return base64_encode($value);
    }

    /**
     * Convert from base64
     */
    public static function fromBase64(string $value): string
    {
        return base64_decode($value);
    }

    /**
     * Check if string is base64 encoded
     */
    public static function isBase64(string $value): bool
    {
        return base64_encode(base64_decode($value, true)) === $value;
    }

    /**
     * Hash string with specified algorithm
     */
    public static function hash(string $value, string $algorithm = 'sha256'): string
    {
        return hash($algorithm, $value);
    }

    /**
     * Generate HMAC
     */
    public static function hmac(string $value, string $key, string $algorithm = 'sha256'): string
    {
        return hash_hmac($algorithm, $value, $key);
    }
}
