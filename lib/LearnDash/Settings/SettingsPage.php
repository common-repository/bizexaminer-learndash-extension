<?php

namespace BizExaminer\LearnDashExtension\LearnDash\Settings;

use BizExaminer\LearnDashExtension\Internal\Interfaces\AssetAwareInterface;
use BizExaminer\LearnDashExtension\Internal\Traits\AssetAwareTrait;
use LearnDash_Settings_Page;

/**
 * Shows the bizExaminer Settings Page in LearnDash Settings
 * Taken from \LearnDash_Settings_Page_Emails
 *
 * @see LearnDash_Settings_Page_Emails
 */
class SettingsPage extends LearnDash_Settings_Page implements AssetAwareInterface
{
    use AssetAwareTrait;

    /**
     * Public constructor for class
     *
     * @see register() for calling parent:__construct()
     */
    public function __construct()
    {
        $this->parent_menu_page_url = 'admin.php?page=learndash_lms_settings';
        $this->menu_page_capability = LEARNDASH_ADMIN_CAPABILITY_CHECK;
        $this->settings_page_id     = 'learndash_lms_settings_bizexaminer';
        $this->settings_page_title  = esc_html_x(
            'bizExaminer',
            'settings page title',
            'bizexaminer-learndash-extension'
        );
        $this->settings_tab_title   = esc_html_x(
            'bizExaminer',
            'settings page title',
            'bizexaminer-learndash-extension'
        );
    }

    /**
     * Registers the page and initializes all hooks
     *
     * LearnDash_Settings_Page registers all hooks inside the constructor
     * But registering should be moved outside into it's own function
     * so instances can be generated without side-effects (eg in the DI container)
     *
     * @return void
     */
    public function register(): void
    {
        parent::__construct();

        $this->getAssetService()->registerScript('learndash-settings', 'learndash-settings');
        $this->getAssetService()->registerStyle('learndash-settings', 'learndash-settings');
    }

    /**
     * Action hook to handle current settings page load.
     *
     * @return void
     */
    public function load_settings_page() // phpcs:ignore PSR1.Methods.CamelCapsMethodName
    {
        parent::load_settings_page();

        // registered in Settings
        $this->getAssetService()->enqueueScript('learndash-settings');
        $this->getAssetService()->enqueueStyle('learndash-settings');
    }
}
