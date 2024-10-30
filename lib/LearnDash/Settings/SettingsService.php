<?php

namespace BizExaminer\LearnDashExtension\LearnDash\Settings;

use BizExaminer\LearnDashExtension\Helper\Util;
use BizExaminer\LearnDashExtension\Internal\Interfaces\EventManagerAwareInterface;
use BizExaminer\LearnDashExtension\Internal\Traits\EventManagerAwareTrait;

/**
 * LearnDash bizExaminer settings services
 */
class SettingsService implements EventManagerAwareInterface
{
    use EventManagerAwareTrait;

    /**
     * Maps internal setting names to the full unique setting keys
     *
     * @var string[]
     */
    protected const SETTING_KEYS = [
        'api_credentials' => 'learndash_settings_bizexaminer_api_credentials'
    ];

    /**
     * Gets a setting from the LearnDash bizExaminer settings
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getSetting(string $key, $default = null)
    {
        if (!isset(self::SETTING_KEYS[$key])) {
            return $default;
        }

        // Allow accessing nested keys by .
        if (str_contains(self::SETTING_KEYS[$key], '.')) {
            $keyParts = explode('.', self::SETTING_KEYS[$key]);
            $optionKey = array_shift($keyParts);
            $value = get_option($optionKey, $default);
            foreach ($keyParts as $key) {
                if (is_array($value) && array_key_exists($key, $value)) {
                    $value = $value[$key];
                } else {
                    break;
                }
            }
            return $value;
        } else {
            return get_option(self::SETTING_KEYS[$key], $default);
        }
    }
}
