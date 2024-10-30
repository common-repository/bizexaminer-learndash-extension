<?php

namespace BizExaminer\LearnDashExtension\Helper;

/**
 * General utils/helper functions
 */
class Util
{
    /**
     * Allows replacing multiple occurences of $needle with different $replace strings
     *
     * @see str_replace
     * @uses preg_replace_callback
     *
     * @param string $search
     * @param array $replace
     * @param string $subject
     * @return string
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName
    public static function str_replace_multiple(string $search, array $replace, string $subject): string
    {
        return preg_replace_callback("#{$search}#", function ($matches) use (&$replace) {
            return array_shift($replace);
        }, $subject);
    }

    /**
     * Clean variables using sanitize_text_field. Arrays are cleaned recursively.
     * Non-scalar values are ignored.
     *
     * @param string|array|mixed $value Data to sanitize.
     * @return string|array
     */
    public static function sanitizeInput($value)
    {
        if (is_array($value)) {
            return array_map([self::class, 'sanitizeInput'], $value);
        }
        return is_scalar($value) ? sanitize_text_field($value) : $value;
    }

    /**
     * Define a constant if it is not already defined.
     *
     * @param string $name  Constant name.
     * @param mixed  $value Value.
     */
    public static function maybeDefineConstant(string $name, $value): void
    {
        if (!defined($name)) {
            define($name, $value);
        }
    }

    /**
     * Sets no cache headers and constants
     * Disables page caching
     * Required for dynamic pages which need to show another state on each request.
     */
    public static function setNoCache(): void
    {
        self::maybeDefineConstant('DONOTCACHEPAGE', true);

        nocache_headers();
    }

    /**
     * Build an html element attribute string from associative array
     *
     * @since 1.4.0
     * @param array $attrs
     * @return string
     */
    public static function htmlAttrs(array $attrs): string
    {
        return implode(' ', array_map(function ($key, $value) {
            $attrValue = $value;
            if (is_bool($attrValue)) {
                // Return empty attribute if true or nothing if false
                return $attrValue ? sprintf('%s', $key) : '';
            }
            return sprintf('%1$s="%2$s"', $key, esc_attr($attrValue));
        }, array_keys($attrs), $attrs));
    }

    /**
     * Sanitizes an array of hooks by checking they are strings and not empty.
     *
     * @param string[] $hooks
     * @return string[]
     */
    public static function sanitizeHooksArray(array $hooks): array
    {
        return array_filter((array)$hooks, function ($v) {
            return is_string($v) && !empty(trim($v));
        });
    }
}
