<?php

declare(strict_types=1);

namespace App\Services\Security;

/**
 * HTML sanitizer for question text and explanations.
 *
 * Allow-list approach per docs/phase-1/08-security-architecture.md §5:
 * Kept: <b> <strong> <i> <em> <u> <sup> <sub> <br> <p> <code>
 * Stripped: everything else (scripts, iframes, styles, event handlers)
 *
 * Applied on WRITE (before storage), not on read. Defence in depth:
 * the frontend additionally purifies with DOMPurify on render.
 */
class ContentSanitizer
{
    /** Tags allowed in question text and explanations */
    private const ALLOWED_TAGS = [
        'b', 'strong', 'i', 'em', 'u', 'sup', 'sub', 'br', 'p', 'code',
    ];

    /**
     * Sanitize HTML content — strip everything except allowed tags.
     * Removes all attributes (no style, no on*, no href with javascript:).
     */
    public static function sanitize(string $input): string
    {
        // 1. Remove dangerous patterns before strip_tags
        $input = self::removeScriptContent($input);
        $input = self::removeEventHandlers($input);
        $input = self::removeJavascriptUrls($input);

        // 2. Strip all tags except allowed
        $allowedTagString = implode('', array_map(fn ($tag) => "<{$tag}>", self::ALLOWED_TAGS));
        $cleaned = strip_tags($input, $allowedTagString);

        // 3. Remove any attributes from remaining tags (defence in depth)
        $cleaned = preg_replace('/<(\w+)\s[^>]*>/u', '<$1>', $cleaned) ?? $cleaned;

        // 4. Normalise whitespace (collapse runs, trim)
        $cleaned = preg_replace('/\s+/u', ' ', $cleaned) ?? $cleaned;

        return trim($cleaned);
    }

    /**
     * Check if content contains disallowed patterns (for validation).
     * Returns true if UNSAFE content is detected.
     */
    public static function isUnsafe(string $input): bool
    {
        $patterns = [
            '/<script/i',
            '/<iframe/i',
            '/<style/i',
            '/<link/i',
            '/<object/i',
            '/<embed/i',
            '/<form/i',
            '/on\w+\s*=/i',           // event handlers
            '/javascript\s*:/i',      // javascript: URLs
            '/data\s*:.*base64/i',    // data URIs with base64 (can hide scripts)
            '/vbscript\s*:/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }

        return false;
    }

    private static function removeScriptContent(string $input): string
    {
        return preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $input) ?? $input;
    }

    private static function removeEventHandlers(string $input): string
    {
        return preg_replace('/\s*on\w+\s*=\s*["\'][^"\']*["\']/i', '', $input) ?? $input;
    }

    private static function removeJavascriptUrls(string $input): string
    {
        return preg_replace('/(?:href|src|action)\s*=\s*["\']?\s*javascript\s*:[^"\'>\s]*/i', '', $input) ?? $input;
    }
}
