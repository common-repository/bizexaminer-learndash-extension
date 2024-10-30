<?php

namespace BizExaminer\LearnDashExtension\LearnDash\Settings;

use BizExaminer\LearnDashExtension\Helper\Util;
use BizExaminer\LearnDashExtension\Internal\Interfaces\EventManagerAwareInterface;
use BizExaminer\LearnDashExtension\Internal\Traits\EventManagerAwareTrait;
use LearnDash_Settings_Section;

/**
 * Displays the other plugin settings (excl. api credentials) section
 *
 */
class OtherSettingsSection extends LearnDash_Settings_Section implements EventManagerAwareInterface
{
    use EventManagerAwareTrait;

    /**
     * Public constructor for class
     * @see register() for calling parent:__construct()
     */
    public function __construct()
    {
        $this->settings_page_id = 'learndash_lms_settings_bizexaminer';

        // This is the 'option_name' key used in the wp_options table.
        $this->setting_option_key = 'learndash_settings_bizexaminer_settings';

        // This is the HTML form field prefix used.
        $this->setting_field_prefix = 'learndash_settings_bizexaminer_settings';

        // Used within the Settings Api to uniquely identify this section.
        $this->settings_section_key = 'settings_bizexaminer_other';

        // Section label/header.
        $this->settings_section_label = esc_html__(
            'Other Settings',
            'bizexaminer-learndash-extension'
        );
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
        $this->metabox_key = 'settings_bizexaminer_other';
    }

    /**
     * Initialize the metabox settings fields.
     *
     * @since 3.0.0
     */
    public function load_settings_fields() // phpcs:ignore PSR1.Methods.CamelCapsMethodName
    {
        $this->setting_option_fields = [];

        /** This filter is documented in includes/settings/settings-metaboxes/class-ld-settings-metabox-course-access-settings.php */
        $this->setting_option_fields = apply_filters(
            'learndash_settings_fields',
            $this->setting_option_fields,
            $this->settings_section_key
        );

        parent::load_settings_fields();
    }
}
