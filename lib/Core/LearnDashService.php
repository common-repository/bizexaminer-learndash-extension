<?php

namespace BizExaminer\LearnDashExtension\Core;

/**
 * A Class to setup the LearnDash integration before LearnDash may be available
 * Therefore provided by the CoreServiceProvider (and not the LearnDashServiceProvider)
 */
class LearnDashService
{
    /**
     * The minimum version of LearnDash required
     */
    public const MIN_VERSION = '4.3';

    /**
     * Creates a new LearnDashService instance
     */
    public function __construct()
    {
    }

    /**
     * Gets the installed LearnDash version
     *
     * @return string|null
     */
    public function getLearnDashVersion(): ?string
    {
        if (defined('LEARNDASH_VERSION')) {
            return LEARNDASH_VERSION;
        }
        return null;
    }

    /**
     * Checks if LearnDash is installed and enabled and meets the required minimum version
     *
     * @return bool
     */
    public function isLearnDashAvailable(): bool
    {
        if (!function_exists('is_plugin_active')) {
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        if (!is_plugin_active('sfwd-lms/sfwd_lms.php') || !defined('LEARNDASH_VERSION')) {
            return false;
        }

        if (!version_compare($this->getLearnDashVersion(), self::MIN_VERSION, '>=')) {
            return false;
        }

        return true;
    }
}
