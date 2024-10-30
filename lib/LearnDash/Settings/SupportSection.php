<?php

namespace BizExaminer\LearnDashExtension\LearnDash\Settings;

use BizExaminer\LearnDashExtension\Helper\Util;
use LearnDash_Settings_Section;

/**
 * Displays the Support Data as Section
 * Taken from \LearnDash_Settings_Section_Support_LearnDash
 *
 * @see LearnDash_Settings_Section_Support_LearnDash
 */
class SupportSection extends LearnDash_Settings_Section
{
    /**
     * SupportSectionHelper instance to use
     *
     * @var SupportSectionHelper
     */
    private SupportSectionHelper $sectionHelper;

    /**
     * Public constructor for class
     * @see register() for calling parent:__construct()
     */
    public function __construct()
    {
        $this->settings_page_id = 'learndash_support';

        // This is the 'option_name' key used in the wp_options table.
        $this->setting_option_key = 'bizexaminer_data'; // used in SupportService to add to system info data

        // Used within the Settings Api to uniquely identify this section.
        $this->settings_section_key = 'bizexaminer_data';

        // Section label/header.
        $this->settings_section_label = esc_html_x(
            'bizExaminer Data',
            'support screen data heading',
            'bizexaminer-learndash-extension'
        );
    }

    /**
     * Sets the SupportSectionHelper instance
     *
     * @param SupportSectionHelper $sectionHelper
     * @return void
     */
    public function setSectionHelper(SupportSectionHelper $sectionHelper)
    {
        $this->sectionHelper = $sectionHelper;
    }

    /**
     * Registers the section and initializes all hooks
     *
     * LearnDash_Settings_Section registers all hooks inside the constructor
     * But registering should be moved outside into it's own function
     * so instances can be generated without side-effects (eg in the DI container)
     *
     * @return void
     */
    public function register(): void
    {
        parent::__construct();
        add_action('learndash_section_fields_before', [$this, 'show_support_section'], 30, 2);
    }

    /**
     * Show Support Section
     *
     * @param string $settings_section_key Section Key.
     * @param string $settings_screen_id   Screen ID.
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName
    public function show_support_section($settings_section_key = '', $settings_screen_id = '')
    {
        if ($settings_section_key === $this->settings_section_key) {
            if (
                !empty($_GET['be-action']) &&
                !empty($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'be-support-action') &&
                current_user_can(LEARNDASH_ADMIN_CAPABILITY_CHECK)
            ) {
                $this->sectionHelper->handleAction(Util::sanitizeInput($_GET['be-action']), Util::sanitizeInput($_GET));
            }

            /** @var \LearnDash_Settings_Page_Support|null */
            $support_page_instance = \LearnDash_Settings_Page::get_page_instance('LearnDash_Settings_Page_Support');
            if ($support_page_instance) {
                $support_page_instance->show_support_section($this->setting_option_key);
            }
        }
    }
}
