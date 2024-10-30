<?php

namespace BizExaminer\LearnDashExtension\LearnDash\Settings;

use BizExaminer\LearnDashExtension\Internal\Interfaces\ApiAwareInterface;
use BizExaminer\LearnDashExtension\Internal\Interfaces\AdminTemplateAwareInterface;
use BizExaminer\LearnDashExtension\Internal\Traits\ApiAwareTrait;
use BizExaminer\LearnDashExtension\Internal\Traits\TemplateAwareTrait;
use BizExaminer\LearnDashExtension\LearnDash\Quiz\QuizSettings\QuizSettingsService;
use LearnDash_Settings_Section;

/**
 * Displays the Api Settings/Credentials Section
 * Taken from \LearnDash_Settings_Section_Emails_Sender_Settings
 *
 * @see LearnDash_Settings_Section_Emails_Sender_Settings
 */
class ApiCredentialsSection extends LearnDash_Settings_Section implements AdminTemplateAwareInterface, ApiAwareInterface
{
    use TemplateAwareTrait;
    use ApiAwareTrait;

    /**
     * The QuizSettingsService instance to use
     *
     * @var QuizSettingsService
     */
    private QuizSettingsService $quizSettingsService;

    /**
     * Public constructor for class
     * @see register() for calling parent:__construct()
     */
    public function __construct()
    {
        $this->settings_page_id = 'learndash_lms_settings_bizexaminer';

        // This is the 'option_name' key used in the wp_options table.
        $this->setting_option_key = 'learndash_settings_bizexaminer_api_credentials';

        // This is the HTML form field prefix used.
        $this->setting_field_prefix = 'learndash_settings_bizexaminer_api_credentials';

        // Used within the Settings Api to uniquely identify this section.
        $this->settings_section_key = 'settings_bizexaminer_api_credentials';

        // Section label/header.
        $this->settings_section_label = esc_html__('API Settings', 'bizexaminer-learndash-extension');

        // Used to show the section description above the fields. Can be empty.
        $this->settings_section_description = esc_html__(
            'Configure API credentials to connect with bizExaminer',
            'bizexaminer-learndash-extension'
        );
    }

    /**
     * Setter injection for quiz settings service
     *
     * @param QuizSettingsService $quizSettingsService
     * @return void
     */
    public function setQuizSettingsService(QuizSettingsService $quizSettingsService): void
    {
        $this->quizSettingsService = $quizSettingsService;
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
        $this->metabox_key = 'settings_bizexaminer_api_credentials';
    }

    /**
     * Initialize the metabox settings values.
     *
     * @return void
     */
    public function load_settings_values() // phpcs:ignore PSR1.Methods.CamelCapsMethodName
    {
        parent::load_settings_values();

        // always append a 'new' key option
        if (empty($this->setting_option_values['new'])) {
            $this->setting_option_values['new'] = [
                'api_key_name' => esc_html_x(
                    'New API Credentials',
                    'new api credentials name',
                    'bizexaminer-learndash-extension'
                ),
                'api_key_instance' => '',
                'api_key_owner' => '',
                'api_key_organisation' => '',
            ];
        }
    }

    /**
     * Initialize the metabox settings fields.
     *
     * @return void
     */
    public function load_settings_fields() // phpcs:ignore PSR1.Methods.CamelCapsMethodName
    {
        $this->setting_option_fields = [];

        foreach ($this->setting_option_values as $setId => $credentialsSet) {
            // this key is also stored in wp_settings_fields as key then
            $fieldPrefix = "{$this->setting_field_prefix}[{$setId}]";

            // the field names are built with array ([]) style, so it's a nested array in $_POST after submitting
            // - $this->setting_option_key
            //   - set_id
            //     - option_name
            // = OPTION_NAME[ID][FIELD]

            $this->setting_option_fields["{$setId}_api_key_name"] = [
                'name'        => "{$fieldPrefix}[api_key_name]",
                'name_wrap'   => false, // keep custom build field name because of OPTION_NAME[ID][FIELD]
                'label'       => esc_html__('Name', 'bizexaminer-learndash-extension'),
                'type'        => 'text',
                'help_text'   => esc_html__(
                    'The name of this credentials set (only used internally for better organisation).',
                    'bizexaminer-learndash-extension'
                ),
                'value'       => $credentialsSet['api_key_name'],
                'required'    => true,
                'class'       => 'js-bizexaminer-api-name',
            ];

            $this->setting_option_fields["{$setId}_api_key_instance"] = [
                'name'        => "{$fieldPrefix}[api_key_instance]",
                'name_wrap'   => false, // keep custom build field name because of OPTION_NAME[ID][FIELD]
                'label'       => esc_html__('Instance Domain', 'bizexaminer-learndash-extension'),
                'type'        => 'text',
                'help_text'   => esc_html__(
                    'The domain name of your bizExaminer instance (without https:// or path).',
                    'bizexaminer-learndash-extension'
                ),
                'value'       => $credentialsSet['api_key_instance'] ?? '',
                'required'    => true,
                'class'       => 'js-bizexaminer-api-instance',
            ];

            $this->setting_option_fields["{$setId}_api_key_owner"] = [
                'name'        => "{$fieldPrefix}[api_key_owner]",
                'name_wrap'   => false, // keep custom build field name because of OPTION_NAME[ID][FIELD]
                'label'       => esc_html__('API Key Owner', 'bizexaminer-learndash-extension'),
                'type'        => 'text',
                'help_text'   => esc_html__('The API key for the (content) owner.', 'bizexaminer-learndash-extension'),
                'value'       => $credentialsSet['api_key_owner'],
                'required'    => true,
                'class'       => 'js-bizexaminer-api-owner'
            ];

            $this->setting_option_fields["{$setId}_api_key_organisation"] = [
                'name'        => "{$fieldPrefix}[api_key_organisation]",
                'name_wrap'   => false, // keep custom build field name because of OPTION_NAME[ID][FIELD]
                'label'       => esc_html__('API Key Organisation', 'bizexaminer-learndash-extension'),
                'type'        => 'text',
                'help_text'   => esc_html__('The API key for the organisation.', 'bizexaminer-learndash-extension'),
                'value'       => $credentialsSet['api_key_organisation'],
                'required'    => true,
                'class'       => 'js-bizexaminer-api-organisation'
            ];

            $this->setting_option_fields["{$setId}_actions"] = [
                'name'        => "{$fieldPrefix}[actions]",
                'name_wrap'   => false, // keep custom build field name because of OPTION_NAME[ID][FIELD]
                'label'       => '',
                'type'        => 'checkbox',
                'help_text'   => esc_html__(
                    'Actions for this API credentials set.',
                    'bizexaminer-learndash-extension'
                ),
                'value'       => '',
                'options' => [
                    'delete' => [
                        // button_label is used in custom template to show buttons for actions
                        'button_label' => esc_html_x(
                            'Delete',
                            'delete API credentials set',
                            'bizexaminer-learndash-extension'
                        ),
                        /* translators: api credentials row index number */
                        'button_aria-label' => esc_html_x(
                            'Delete API credentials set %s.',
                            'delete API credentials set button aria label',
                            'bizexaminer-learndash-extension'
                        ),
                        'label' => '<span class="screen-reader-text">' .
                            esc_html_x(
                                '&nbsp;',
                                'api credentials delete label prefix',
                                'bizexaminer-learndash-extension'
                            )
                            . '</span>' . esc_html_x(
                                'Delete',
                                'delete API credentials set',
                                'bizexaminer-learndash-extension'
                            ) .
                            '<span class="screen-reader-text">' .
                            esc_html_x(
                                '&nbsp;this API credentials set.',
                                'api credentials delete label suffix',
                                'bizexaminer-learndash-extension'
                            )
                            . '</span>',
                    ],
                    'test' => [
                        // button_label is used in custom template to show buttons for actions
                        'button_label' => esc_html_x(
                            'Test',
                            'test API credentials set',
                            'bizexaminer-learndash-extension'
                        ),
                        /* translators: api credentials row index number */
                        'button_aria-label' => esc_html_x(
                            'Test API credentials set %s.',
                            'test API credentials set button aria label',
                            'bizexaminer-learndash-extension'
                        ),
                        'label' => '<span class="screen-reader-text">' .
                            esc_html_x(
                                '&nbsp;',
                                'api credentials test label prefix',
                                'bizexaminer-learndash-extension'
                            )
                            . '</span>' .
                            esc_html_x('Test', 'test API credentials set', 'bizexaminer-learndash-extension') .
                            '<span class="screen-reader-text">' .
                            esc_html_x(
                                '&nbsp;this API credentials set.',
                                'api credentials test label suffix',
                                'bizexaminer-learndash-extension'
                            )
                            . '</span>',
                    ]
                ],
            ];

            // TODO: maybe only run this on the settings screen

            // Disable delete action if credentials set is still used in quiz.
            if ($this->quizSettingsService->getQuizCountWithApiCredentials($setId) > 0) {
                $this->setting_option_fields["{$setId}_actions"]['options']['delete']['attrs'] = [
                    'disabled' => 'disabled',
                    'title' => esc_html__(
                        'API Credentials cannot be deleted, if they are still used in quizzes',
                        'bizexaminer-learndash-extension'
                    ),
                ];
            }
        }

        /** This filter is documented in includes/settings/settings-metaboxes/class-ld-settings-metabox-course-access-settings.php */
        $this->setting_option_fields = apply_filters(
            'learndash_settings_fields',
            $this->setting_option_fields,
            $this->settings_section_key
        );

        parent::load_settings_fields();
    }

    /**
     * Show Settings Section Fields.
     * Taken from class-ld-settings-section-registration-fields.php
     * Overwrites parent and does not call LearnDash_Settings_Fields::show_section_fields
     * so it renders the fields in a table
     *
     * @param string $page Page shown.
     * @param string $section Section shown.
     *
     * @return void
     */
    public function show_settings_section_fields($page, $section) // phpcs:ignore PSR1.Methods.CamelCapsMethodName
    {
        global $wp_settings_fields;

        if (!isset($wp_settings_fields[$page][$section])) {
            return;
        }

        // $wp_settings_fields does not use the same naming structure as $this->setting_option_fields
        // instead array keys are the IDs of the field
        // so in this case not $setId_$fieldName but the NAME used for the fields as well
        $fields = $wp_settings_fields[$page][$section];

        $values = $this->setting_option_values;

        $usesPerCredential = [];
        foreach ($values as $setId => $setData) {
            $usesPerCredential[$setId] = $this->quizSettingsService->getQuizCountWithApiCredentials($setId);
        }

        $errors = [];
        foreach (get_settings_errors($this->setting_option_key) as $errorData) {
            $errors[$errorData['code']] = $errorData;
        }

        $this->getTemplateService()->render('learndash/settings/apicredentials-section', [
            'field_prefix' => $this->setting_field_prefix,
            'fields' => $fields,
            'values' => $values,
            'usesPerCredentialSet' => $usesPerCredential,
            'errors' => $errors,
        ]);
    }

    /**
     * This function is set via the call to 'register_setting'
     * and will be called for this section/option key only
     * This is actually just used for SANITIZING and NOT for validation (does not return errors)
     * @see filter_section_save_fields
     *
     * Modified from parent, because LearnDash loops over the posted array and would check if an $ID field config exists
     * but since our fields are named including the ID-prefix
     *
     * @see LearnDash_Settings_Section::settings_section_fields_validate
     * @watch LearnDash_Settings_Section::settings_section_fields_validate
     *
     * @param array $post_fields Array of section fields.
     * @return array sanitized user submitted values
     */
    public function settings_section_fields_validate($post_fields = []) // phpcs:ignore PSR1.Methods.CamelCapsMethodName
    {
        $setting_option_values = array();

        // This validate_args array will be passed to the validation function for context.
        $validate_args = array(
            'settings_page_id'   => $this->settings_page_id,
            'setting_option_key' => $this->setting_option_key,
            'post_fields'        => $post_fields,
            'field'              => null,
        );

        $fields = ['api_key_name', 'api_key_instance', 'api_key_owner', 'api_key_organisation', 'actions'];

        if (!empty($post_fields)) {
            foreach ($post_fields as $setId => $val) {
                $setting_option_values[$setId] = [];

                foreach ($fields as $field) {
                    $fieldName = "{$setId}_{$field}";
                    if (isset($this->setting_option_fields[$fieldName]) && !empty($val[$field])) {
                        if (
                            /**
                             * validate_callbacks are set per field type in LearnDash
                             * for text fields this uses sanitize_text_field
                             * We only use text fields, therefore this is enough sanitizing.
                             *
                             * @see LearnDash_Settings_Fields_Text::validate_section_field
                             * @watch LearnDash_Settings_Fields_Text::validate_section_field
                             */
                            (isset($this->setting_option_fields[$fieldName]['validate_callback']))
                            && (!empty($this->setting_option_fields[$fieldName]['validate_callback']))
                            && (is_callable($this->setting_option_fields[$fieldName]['validate_callback']))
                        ) {
                            $validate_args['field']        = $this->setting_option_fields[$fieldName];
                            $setting_option_values[$setId][$field] = call_user_func(
                                $this->setting_option_fields[$fieldName]['validate_callback'],
                                $val[$field],
                                $fieldName,
                                $validate_args
                            );
                        }
                    }
                }

                /**
                 * actions will be an array, but only one action at a time is allowed
                 * therefore set it to a string value
                 */
                if (
                    !empty($setting_option_values[$setId]['actions']) &&
                    count($setting_option_values[$setId]['actions']) === 1
                ) {
                    $setting_option_values[$setId]['actions'] = $setting_option_values[$setId]['actions'][0];
                }
            }
        }

        return $setting_option_values;
    }

    /**
     * Filter values before they are saved.
     * Used for VALIDATING: adds settings errors and sets old values to be saved instead of new invalid values
     *
     * @hooke into 'learndash_settings_section_save_fields_' . $this->setting_option_key (@see __construct)
     * in parent::section_pre_update_option
     *  which is hooked into 'pre_update_option_' . $this->setting_option_key
     *  and checks for a nonce before calling this filter/function
     *
     * @param array  $values                An array of setting fields values.
     * @param array  $old_values            An array of setting fields old values.
     * @param string $settings_section_key Settings section key.
     * @param string $settings_screen_id   Settings screen ID.
     * @return array validated and allowed user submitted values
     */
    public function filter_section_save_fields( // phpcs:ignore PSR1.Methods.CamelCapsMethodName
        $values,
        $old_values,
        $settings_section_key,
        $settings_screen_id
    ) {
        $validatedValues = [];
        foreach ($values as $setId => $setValues) {
            /**
             * handle delete action
             * skip validating this set - so it's not in setting_option_values and not stored therefore
             */
            if (isset($setValues['actions']) && $setValues['actions'] === 'delete') {
                // Prevent deleting if it's still used in a quiz.
                if ($this->quizSettingsService->getQuizCountWithApiCredentials($setId) <= 0) {
                    continue;
                }
            }

            // generate a unique id for this credentials if it's a new one
            $storeSetId = $setId === 'new' ? uniqid() : $setId;

            // if it's the "new" key and the fields are empty, it's just the (hidden) placeholder
            if (
                $setId === 'new' &&
                (empty($setValues['api_key_name']) ||
                    empty($setValues['api_key_owner']) ||
                    empty($setValues['api_key_organisation'])) ||
                empty($setValues['api_key_instance'])
            ) {
                continue;
            }

            /**
             * Only the domain is used for the instance, therefore remove any other URL parts (protocol/scheme, path)
             * do this before validating - so null/false values will be catched in validateApiCredentialsSet
             */
            if (!empty($setValues['api_key_instance'])) {
                /**
                 * if it's already a valid domain, do not parse it again
                 * FILTER_VALIDATE_DOMAIN and FILTER_FLAG_HOSTNAME only check length and allowed characters -
                 * not if its really an allowed domain/subdomain
                 */
                if (!filter_var($setValues['api_key_instance'], FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
                    $setValues['api_key_instance'] = wp_parse_url(
                        $setValues['api_key_instance'],
                        PHP_URL_HOST
                    );
                }
            }

            if ($this->validateApiCredentialsSet($storeSetId, $setValues)) {
                $validatedValues[$storeSetId] = $setValues;
                unset($validatedValues['actions']);

                /**
                 * handle delete action
                 * skip validating this set - so it's not in setting_option_values and not stored therefore
                 */
                if (isset($setValues['actions']) && $setValues['actions'] === 'test') {
                    $apiClient = $this->makeApi($this->getApiService()->makeApiCredentials(
                        array_merge(['id' => $storeSetId], $validatedValues[$storeSetId])
                    ));
                    $testResult = $apiClient->testCredentials();
                    if (!$testResult) {
                        add_settings_error(
                            $this->setting_option_key,
                            "bizexaminer-invalid-api-credentials-{$setId}",
                            esc_html__(
                                'Testing the API credentials was not successful. Please check them again.',
                                'bizexaminer-learndash-extension'
                            ),
                        );
                    } else {
                        add_settings_error(
                            $this->setting_option_key,
                            "bizexaminer-invalid-api-credentials-{$setId}",
                            esc_html__(
                                'Testing the API credentials was successful.',
                                'bizexaminer-learndash-extension'
                            ),
                            'success'
                        );
                    }
                }
            } else {
                add_settings_error(
                    $this->setting_option_key,
                    "bizexaminer-invalid-api-credentials-{$setId}",
                    esc_html__(
                        'The API credentials you entered are empty,
                            not valid or contain non-valid characters. Please check them again.',
                        'bizexaminer-learndash-extension'
                    )
                );
                if (isset($old_values[$storeSetId])) {
                    $validatedValues[$storeSetId] = $old_values[$storeSetId];
                }
            }
        }
        return $validatedValues;
    }

    /**
     * Whether a set of api credentials with all their data are valid
     *
     * Checks for valid strings, domain/hostnames and if hostname exist
     * does not test against the API
     *
     * @param string $id
     * @param array $credentialsSet
     * @return bool
     */
    private function validateApiCredentialsSet(string $id, array $credentialsSet): bool
    {
        if (!is_string($id) || $id === 'new') {
            return false;
        }

        if (
            !isset($credentialsSet['api_key_name']) ||
            empty($credentialsSet['api_key_name']) || !is_string($credentialsSet['api_key_name'])
        ) {
            return false;
        }



        if (
            !isset($credentialsSet['api_key_instance']) ||
            empty($credentialsSet['api_key_instance']) || !is_string($credentialsSet['api_key_instance'])
        ) {
            return false;
        } elseif (
            // use php filter_var to check if it's a valid domain string
            !filter_var($credentialsSet['api_key_instance'], FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) ||
            // use gethostbyname to check if it's a valid domain
            !filter_var(
                gethostbyname($credentialsSet['api_key_instance']),
                FILTER_VALIDATE_IP
            )
        ) {
            return false;
        }

        if (
            !isset($credentialsSet['api_key_owner']) ||
            empty($credentialsSet['api_key_owner']) || !is_string($credentialsSet['api_key_owner'])
        ) {
            return false;
        }

        if (
            !isset($credentialsSet['api_key_organisation']) ||
            empty($credentialsSet['api_key_organisation']) || !is_string($credentialsSet['api_key_organisation'])
        ) {
            return false;
        }

        return true;
    }
}
