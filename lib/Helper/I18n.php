<?php

namespace BizExaminer\LearnDashExtension\Helper;

use IntlTimeZone;

/**
 * Helper class for internationalization/localization
 */
class I18n
{
    /**
     * Get the language for a user
     * Tries to get the current language from WPML, the user defined locale
     * or falls back to the WordPress global defined local
     *
     * @param int|null $userId
     * @return string The two-letter language (eg: en, de)
     */
    public static function getLanguage(?int $userId = null): string
    {
        $language = get_locale();

        if (has_filter('wpml_current_language')) {
            $wpmlLanguage = apply_filters('wpml_current_language', null);
            if ($wpmlLanguage) {
                $language = $wpmlLanguage;
            }
        }

        if ($userId) {
            $userLocale = get_user_meta($userId, 'locale', true);
            if ($userLocale) {
                $language = $userLocale;
            }
        }

        if (str_contains($language, '_')) {
            $language = explode('_', $language)[0];
        }

        return $language;
    }

    /**
     * Gets the timezone in a standard ISO format
     * WordPress allows setting timezones which do not conform to iso (eg `+07:00`)
     * which then causes errors in bizExaminer API
     *
     * @see issue#47
     * @uses wp_timezone_string
     * @return string|null null if the WordPress configured timezone is not a valid ISO timezone
     */
    public static function getIsoTimezone(): ?string
    {
        $wpTimezone = wp_timezone_string();
        if (class_exists('IntlTimeZone')) { // intl extension may not be available
            $isoTimezone = IntlTimeZone::createTimeZone($wpTimezone);

            if ($isoTimezone->getID() === 'Etc/Unknown') {
                return null;
            }
        }


        return $wpTimezone;
    }
}
