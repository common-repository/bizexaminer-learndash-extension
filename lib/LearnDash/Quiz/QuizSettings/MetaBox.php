<?php

namespace BizExaminer\LearnDashExtension\LearnDash\Quiz\QuizSettings;

use BizExaminer\LearnDashExtension\Helper\I18n;
use BizExaminer\LearnDashExtension\Helper\Util;
use BizExaminer\LearnDashExtension\Internal\Interfaces\AssetAwareInterface;
use BizExaminer\LearnDashExtension\Internal\Traits\AssetAwareTrait;
use LearnDash_Settings_Metabox;

/**
 * Class LearnDash Settings Metabox for custom Quiz Settings
 * Taken form LearnDash_Settings_Metabox_Quiz_Access_Settings
 *
 * @see LearnDash_Settings_Metabox_Quiz_Access_Settings
 */
class MetaBox extends LearnDash_Settings_Metabox implements AssetAwareInterface
{
    use AssetAwareTrait;

    /**
     * Key (unique) of the meta box
     * made available here so AdminSubscriber can reuse it
     */
    public const METABOX_KEY = 'learndash-quiz-bizexaminer-settings';

    /**
     * Quiz edit object
     *
     * @var object
     */
    public $quiz_edit = null;

    /**
     * MetaBoxHelper instance to use
     *
     * @var MetaBoxHelper
     */
    private MetaBoxHelper $metaBoxHelper;

    /**
     * Public constructor for class
     * @see register() for calling parent:__construct()
     */
    public function __construct()
    {
        // What screen ID are we showing on.
        $this->settings_screen_id = 'sfwd-quiz';

        // Used within the Settings Api to uniquely identify this section.
        $this->settings_metabox_key = self::METABOX_KEY;

        // Section label/header.
        $this->settings_section_label = sprintf(
            // translators: placeholder: Quiz.
            esc_html_x('%s bizExaminer Settings', 'placeholder: Quiz', 'bizexaminer-learndash-extension'),
            learndash_get_custom_label('quiz')
        );

        $this->settings_section_description = sprintf(
            /* translators: placeholder: quiz label */
            esc_html_x(
                'Connect this %s with a bizExaminer exam',
                'placeholder: quiz',
                'bizexaminer-learndash-extension'
            ),
            learndash_get_custom_label_lower('quiz')
        );

        // Required for checks which fields to save
        $this->settings_fields_map = [];
    }

    /**
     * Sets the MetaBoxHelper instance
     *
     * @param MetaBoxHelper $metaBoxHelper
     * @return void
     */
    public function setMetaBoxHelper(MetaBoxHelper $metaBoxHelper)
    {
        $this->metaBoxHelper = $metaBoxHelper;
        if (empty($this->settings_fields_map)) {
            $this->initFieldsMap();
        }
    }

    /**
     * Initializes the field keys used by this metabox from the default value keys
     * fields_map is used to check which values in $_POST to save
     *
     * @return void
     */
    private function initFieldsMap(): void
    {
        foreach ($this->metaBoxHelper->getDefaultValues() as $key => $val) {
            $this->settings_fields_map[$key] = $key;
        }
    }

    /**
     * Registers the metabox and initializes all hooks
     *
     * LearnDash_Settings_Metabox registers all hooks inside the constructor
     * But registering should be moved outside into it's own function
     * so instances can be generated without side-effects (eg in the DI container)
     *
     * @return void
     */
    public function register(): void
    {
        parent::__construct();

        add_filter(
            'learndash_metabox_save_fields_' . $this->settings_metabox_key,
            [$this, 'filter_saved_fields'],
            30,
            3
        );

        /**
         *
         */
        add_filter(
            'learndash_settings_field_html_after',
            [$this, 'addQuizSettingsErrorMessages'],
            10,
            2
        );

        $scriptData = [
            'incompatibleSettings' => $this->metaBoxHelper->getIncompatibleLearnDashSettings(),
            'builderTabHelpText' => esc_html__(
                'Disabled. Please use bizExaminer to build your questions.',
                'bizexaminer-learndash-extension'
            ),
        ];

        $this->getAssetService()->registerScript('quiz-settings', 'quiz-settings', true);
        $this->getAssetService()->addScriptData('quiz-settings', 'bizExaminerQuizSettings', $scriptData);
        $this->getAssetService()->registerStyle('quiz-settings', 'quiz-settings');
    }

    /**
     * Used to save the settings fields back to the global $_POST object so
     * the WPProQuiz normal form processing can take place.
     *
     * {@inheritDoc}
     *
     * @param object $pro_quiz_edit WpProQuiz_Controller_Quiz instance (not used).
     * @param array  $settings_values Array of settings fields.
     */
    public function save_fields_to_post( // phpcs:ignore PSR1.Methods.CamelCapsMethodName
        $pro_quiz_edit,
        $settings_values = []
    ) {
        foreach ($settings_values as $setting_key => $setting_value) {
            if (isset($this->settings_fields_map[$setting_key])) {
                $_POST[$setting_key] = $setting_value;
            }
        }
    }

    /**
     * Initialize the metabox settings values.
     *
     * {@inheritDoc}
     *
     * @return void
     */
    public function load_settings_values() // phpcs:ignore PSR1.Methods.CamelCapsMethodName
    {
        $reload_pro_quiz = false;
        if (true !== $this->settings_values_loaded) {
            $reload_pro_quiz = true;
        }

        if (empty($this->settings_fields_map)) {
            $this->initFieldsMap();
        }

        parent::load_settings_values();

        if (true === $this->settings_values_loaded) {
            $this->quiz_edit = $this->init_quiz_edit($this->_post, $reload_pro_quiz);

            foreach ($this->metaBoxHelper->getDefaultValues() as $key => $value) {
                // checks for empty strings and null values - from load_settings_values (empty strings)
                if (empty($this->setting_option_values[$key])) {
                    $this->setting_option_values[$key] = $value;
                }
            }
        }
    }

    /**
     * Initialize the metabox settings fields.
     *
     * {@inheritDoc}
     *
     * @return void
     */
    public function load_settings_fields() // phpcs:ignore PSR1.Methods.CamelCapsMethodName
    {
        $generalFields = $this->buildGeneralFields();
        $remoteProctoringFields = $this->buildRemoteProctoringFields();
        $extraFields = $this->buildExtraFields();
        $this->setting_option_fields = array_merge([], $generalFields, $remoteProctoringFields, $extraFields);

        /** This filter is documented in includes/settings/settings-metaboxes/class-ld-settings-metabox-course-access-settings.php */
        $this->setting_option_fields = apply_filters(
            'learndash_settings_fields',
            $this->setting_option_fields,
            $this->settings_metabox_key
        );

        parent::load_settings_fields();
    }

    /**
     * Show Settings Section Fields.
     *
     * @param object $metabox Metabox.
     */
    protected function show_settings_metabox_fields($metabox = null) // phpcs:ignore PSR1.Methods.CamelCapsMethodName
    {
        /**
         * Check for invalid Api credentials (maybe credentials were deleted)
         */
        $selectedCredentials = $this->setting_option_values[QuizSettingsService::SETTING_KEY_CREDENTIALS];
        if (!$this->metaBoxHelper->isCredentialsIdValid($selectedCredentials)) {
            $this->metaBoxHelper->addSettingsError(
                'bizExaminerApiCredentials',
                __(
                    'Previously selected API Credentials were deleted, please select another set.',
                    'bizexaminer-learndash-extension'
                )
            );
        }

        // enqueue js for credential select handling
        $this->getAssetService()->enqueueScript('quiz-settings');
        $this->getAssetService()->enqueueStyle('quiz-settings');

        parent::show_settings_metabox_fields($metabox);
    }

    /**
     * Build general setting fields array
     *
     * @return array
     */
    private function buildGeneralFields(): array
    {
        /**
         * Options for Api credentials field
         */
        $selectedCredentials = $this->setting_option_values[QuizSettingsService::SETTING_KEY_CREDENTIALS];
        $doSelectedCredentialsExist = $this->metaBoxHelper->isCredentialsIdValid($selectedCredentials);
        $credentialsOptions = $this->metaBoxHelper->buildApiCredentialsOptions($selectedCredentials);

        /**
         * Options for exam module field
         * if select 2 is enabled and select2 ajax is enabled use it to lazy load options
         * otherweise load options on loading
         */
        $selectedExamModule = $this->setting_option_values[QuizSettingsService::SETTING_KEY_EXAM_MODULE];
        $examModuleOptions = $this->metaBoxHelper->buildExamModulesOptions($selectedCredentials);
        if (learndash_use_select2_lib_ajax_fetch()) {
            $examModuleFetchDataJson = $this->build_settings_select2_lib_ajax_fetch_json(
                array(
                    // uses last saved value as base - will be updated on client
                    'api_credentials' => $selectedCredentials,
                    'be_selected_value' => $selectedExamModule, // TODO: Workaround for #3 - @see quiz-settings.js
                    'settings_element' => array(
                        'settings_parent_class' => get_parent_class(__CLASS__),
                        'settings_class'        => __CLASS__,
                        'settings_field'        => 'bizExaminerExamModule',
                    ),
                )
            );
        }

        return [
            'bizExaminerEnabled' => [
                'name'                => QuizSettingsService::SETTING_KEY_ENABLED,
                'type'                => 'checkbox-switch',
                'label'               => esc_html__('bizExaminer Exam', 'bizexaminer-learndash-extension'),
                'value'               => $this->setting_option_values[QuizSettingsService::SETTING_KEY_ENABLED],
                'default'             => '',
                'help_text'           => esc_html(sprintf(
                    /* translators: placeholder: quiz label */
                    __(
                        'Enable this option to connect this %s with a bizExaminer exam module',
                        'bizexaminer-learndash-extension'
                    ),
                    learndash_get_custom_label_lower('quiz')
                )),
                'options'             => [
                    ''   => '',
                    'on' => esc_html__('bizExaminer enabled', 'bizexaminer-learndash-extension'),
                ],
                'child_section_state' => $this->setting_option_values[QuizSettingsService::SETTING_KEY_ENABLED] ?
                    'open' : 'closed',
            ],
            'bizExaminerApiCredentials' => [
                'name' => QuizSettingsService::SETTING_KEY_CREDENTIALS,
                'label' => esc_html_x(
                    'API Credentials',
                    'api credentials for quiz label',
                    'bizexaminer-learndash-extension'
                ),
                'type' => 'select',
                'required' => true,
                'value' => $selectedCredentials,
                'help_text' => wp_kses_post(
                    sprintf(
                        /* translators: %s: Link to Settings Page */
                        __(
                            'You can configure your API Credentials under
                        <a href="%s" target="_blank">Settings > bizExaminer</a>.',
                            'bizexaminer-learndash-extension'
                        ),
                        esc_url(admin_url('admin.php?page=learndash_lms_settings_bizexaminer'))
                    )
                ),
                'placeholder' => esc_html__(
                    'Search or select a set of API Credentials to connect to bizExaminer.',
                    'bizexaminer-learndash-extension'
                ),
                'options' => $credentialsOptions,
                'parent_setting' => 'bizExaminerEnabled',
                /**
                 * if current selection is invalid show specific error,
                 * otherwise output (and hide) error message to use in JavaScript if field is empty
                 */
                'input_error' => !$doSelectedCredentialsExist ? esc_html__(
                    'Previously selected API Credentials were deleted, please select another set.',
                    'bizexaminer-learndash-extension'
                ) : esc_html__('You have to select API credentials', 'bizexaminer-learndash-extension'),
            ],
            'bizExaminerExamModule' => [
                'name' => QuizSettingsService::SETTING_KEY_EXAM_MODULE,
                'label' => esc_html_x('Exam Module', 'exam module for quiz label', 'bizexaminer-learndash-extension'),
                'type' => 'select',
                'value' => is_array($selectedExamModule) ? $selectedExamModule['id'] : $selectedExamModule,
                'help_text' => esc_html__(
                    'Select an exam module and a content revision.
                        Exam Modules will be reloaded after selecting new API credentials.',
                    'bizexaminer-learndash-extension'
                ),
                'required' => true,
                'placeholder' => esc_html__('Select an exam module', 'bizexaminer-learndash-extension'),
                'options' => $examModuleOptions,
                'attrs'       => [
                    'data-ld_selector_nonce'   => wp_create_nonce('bizexaminer-exam-modules'),
                    'data-ld_selector_default' => '1',
                    'data-select2-query-data'  => $examModuleFetchDataJson ?? '',
                ],
                'parent_setting' => 'bizExaminerEnabled',
                // output (and hide) error message to use in JavaScript if field is empty
                'input_error' => esc_html__(
                    'You have to select an exam module to use.',
                    'bizexaminer-learndash-extension'
                ),
            ],
            'bizExaminerCertificate' => [
                'name'                => QuizSettingsService::SETTING_KEY_CERTIFICATE,
                'type'                => 'checkbox-switch',
                'label'               => esc_html__('Use bizExaminer certificate', 'bizexaminer-learndash-extension'),
                'value'               => $this->setting_option_values[QuizSettingsService::SETTING_KEY_CERTIFICATE],
                'default'             => '',
                'help_text'           => esc_html__(
                    'Enable this option to show users the certificate you
                        designed/configured in bizExaminer instead of the LearnDash one.',
                    'bizexaminer-learndash-extension'
                ),
                'options'             => [
                    ''   => '',
                    'on' => esc_html__('Use bizExaminer certificate', 'bizexaminer-learndash-extension'),
                ],
                'parent_setting' => 'bizExaminerEnabled',
            ],
            'bizExaminerImportExternalAttempts' => [
                'name'                => QuizSettingsService::SETTING_KEY_IMPORT_EXTERNAL_ATTEMPTS,
                'type'                => 'checkbox-switch',
                'label'               => esc_html__(
                    'Import attempt & results from attempts started outside of LearnDash',
                    'bizexaminer-learndash-extension'
                ),
                'value'               =>
                $this->setting_option_values[QuizSettingsService::SETTING_KEY_IMPORT_EXTERNAL_ATTEMPTS],
                'default'             => '',
                'help_text'           => esc_html__(
                    //phpcs:disable Generic.Files.LineLength.TooLong
                    'Enable this option to sync results from attempts started directly in bizExaminer back to LearnDash.
                        You should add the shortcode with the table or the button to allow the user to import it somewhere.',
                    //phpcs:enable
                    'bizexaminer-learndash-extension'
                ),
                'options'             => [
                    ''   => '',
                    'on' => esc_html__('Import attempts', 'bizexaminer-learndash-extension'),
                ],
                'parent_setting' => 'bizExaminerEnabled',
            ],
            'bizExaminerImportExternalAttemptsDisableStart' => [
                'name'                => QuizSettingsService::SETTING_KEY_IMPORT_EXTERNAL_ATTEMPTS_DISABLE_START,
                'type'                => 'checkbox-switch',
                'label'               => esc_html__(
                    'Disable starting the Quiz from LearnDash when "Import attempts" is enabled',
                    'bizexaminer-learndash-extension'
                ),
                'value'               =>
                $this->setting_option_values[QuizSettingsService::SETTING_KEY_IMPORT_EXTERNAL_ATTEMPTS_DISABLE_START],
                'default'             => '',
                'help_text'           => esc_html__(
                    //phpcs:disable Generic.Files.LineLength.TooLong
                    'If you only want to import attempts from bizExaminer but do not want to allow users to start attempts in LearnDash, enable this option.',
                    //phpcs:enable
                    'bizexaminer-learndash-extension'
                ),
                'options'             => [
                    ''   => '',
                    'on' => esc_html__('Disable starting', 'bizexaminer-learndash-extension'),
                ],
                // LearnDash does not handle nested parent_settings, need to manually show/hide.
                'parent_setting' => 'bizExaminerEnabled',
                'attrs' => [
                    'data-biz-show-if' => 'bizExaminerImportExternalAttempts',
                ]
            ]
        ];
    }

    /**
     * Build Remote Proctoring selection + setting fields
     *
     * @return array
     */
    private function buildRemoteProctoringFields(): array
    {
        $selectedCredentials = $this->setting_option_values[QuizSettingsService::SETTING_KEY_CREDENTIALS];

        /**
         * Options for remote proctoring field
         * if select 2 is enabled and select2 ajax is enabled use it to lazy load options
         * otherweise load options on loading
         */
        $selectedRemoteProctoring = $this->setting_option_values[QuizSettingsService::SETTING_KEY_REMOTE_PROCTOR];
        $remoteProctoringOptions = $this->metaBoxHelper->buildRemoteProctorOptions($selectedCredentials);
        // If no remote proctors are configured the only option is the blank one
        // do not show fields then.
        if (count($remoteProctoringOptions) === 1) {
            return [];
        }
        if (learndash_use_select2_lib_ajax_fetch()) {
            $remoteProctorFetchDataJson = $this->build_settings_select2_lib_ajax_fetch_json(
                array(
                    // uses last saved value as base - will be updated on client
                    'api_credentials' => $selectedCredentials,
                    'settings_element' => array(
                        'settings_parent_class' => get_parent_class(__CLASS__),
                        'settings_class'        => __CLASS__,
                        'settings_field'        => 'bizExaminerRemoteProctoring',
                    ),
                )
            );
        }

        $fields = [
            'bizExaminerRemoteProctoring' => [
                'name' => QuizSettingsService::SETTING_KEY_REMOTE_PROCTOR,
                'label' => esc_html__('Use remote proctoring', 'bizexaminer-learndash-extension'),
                'type' => 'select',
                'value' => $selectedRemoteProctoring,
                'help_text' => esc_html__(
                    'Choose one of your configured remote proctoring services.',
                    'bizexaminer-learndash-extension'
                ),
                'placeholder' => esc_html__(
                    'Search or select a remote proctoring service.',
                    'bizexaminer-learndash-extension'
                ),
                'required' => true,
                'options' => $remoteProctoringOptions,
                'attrs'       => [
                    'data-ld_selector_nonce'   => wp_create_nonce('bizexaminer-remote-proctors'),
                    'data-ld_selector_default' => '1',
                    'data-select2-query-data'  => $remoteProctorFetchDataJson ?? '',
                ],
                'parent_setting' => 'bizExaminerEnabled',
                // output (and hide) error message to use in JavaScript if field is empty
                'input_error' => esc_html__(
                    'You have to select a remote proctore service to use or select "no".',
                    'bizexaminer-learndash-extension'
                ),
            ],
        ];

        foreach ($this->metaBoxHelper->getRemoteProctorSettingFields() as $proctor => $proctorFields) {
            foreach ($proctorFields as $fieldName => $proctorField) {
                $fullFieldName = $this->metaBoxHelper->buildRemoteProctorFieldName($proctor, $fieldName);
                $fields[$fullFieldName] = array_merge(
                    $proctorField,
                    [
                        'name' => $fullFieldName,
                        'value' => $this->setting_option_values[$fullFieldName],
                        'parent_setting' => 'bizExaminerEnabled',
                        'attrs' => array_merge([
                            'data-bizexaminer-proctor' => esc_attr($proctor),
                        ], $proctorField['attrs'] ?? [])
                    ]
                );
            }
        }

        return $fields;
    }

    /**
     * Build extra fields like error messages
     *
     * @return array
     */
    private function buildExtraFields(): array
    {
        $fields = [];
        if (!I18n::getIsoTimezone()) {
            $fields['bizExaminerTimezoneWarning'] = [
                'name' => 'bizExaminerTimezoneWarning',
                'parent_setting' => 'bizExaminerEnabled',
                'type' => 'html',
                'label' => __('Timezone'),
                /* translators: URL to settings */
                'value' => sprintf(__(
                    'bizExaminer requires a valid ISO timezone set.
                    Please configure WordPress to use a named timezone under <a href="%s">Settings > General</a>.',
                    'bizexaminer-learndash-extension'
                ), esc_url(admin_url('options-general.php'))),
                'attrs' => [
                    'style' => 'color: red',
                ]
            ];
        }
        return $fields;
    }

    /**
     * SANITIZE & VALIDATE Fields
     * Filter settings values for metabox before save to database.
     * Used for validating and sanitizing
     *
     * @hooked into 'learndash_metabox_save_fields_' . $this->settings_metabox_key (@see __construct)
     * which is called by parent::trigger_metabox_settings_post_filters
     *      which is called by parent::get_post_settings_field_updates
     *      which also calls validate_metabox_settings_post_updates before
     *          which uses the validate_callbacks of the fields
     *
     * Select dropdowns are compared against available values and then set
     *
     * Adds settings errors, but actually LearnDash does not validate them via AJAX
     * And there's no page reload since everything is saved via the block editor
     * So also do some live-feedback for user in UI in JavaScript (@see quiz-settings.js)
     *
     * @param array  $settings_values Array of settings values.
     * @param string $settings_metabox_key Metabox key.
     * @param string $settings_screen_id Screen ID.
     *
     * @return array $settings_values.
     */
    public function filter_saved_fields( // phpcs:ignore PSR1.Methods.CamelCapsMethodName
        $settings_values = [],
        $settings_metabox_key = '',
        $settings_screen_id = ''
    ) {
        if (
            ($settings_screen_id !== $this->settings_screen_id) ||
            ($settings_metabox_key !== $this->settings_metabox_key)
        ) {
            return $settings_values;
        }

        /**
         * 1. Sanitize all values just to make sure - in most cases not needed
         *
         * selectedCredentials are compared against saved credentials (which are already sanitized)
         * if the exact id does not exists, it's not set - but still sanitize here again
         *
         * 'on' / 'off' will be converted to bool, still sanitize
         *
         * sanitize string/int exam module field
         */

        $settings_values = Util::sanitizeInput($settings_values);

        /**
         * 2. Convert enabled string to bool
         */
        if ($settings_values[QuizSettingsService::SETTING_KEY_ENABLED] === 'on') {
            $settings_values[QuizSettingsService::SETTING_KEY_ENABLED] = true;
        } else {
            $settings_values[QuizSettingsService::SETTING_KEY_ENABLED] = false;
        }

        /**
         * 3. If not enabled, reset all values and bail early
         */
        if (!$settings_values[QuizSettingsService::SETTING_KEY_ENABLED]) {
            $settings_values = array_map('__return_empty_string', $settings_values);
            return $settings_values;
        }

        /**
         * 4. Check if credentials are selected and if they exist
         * otherwise reset it and add an error
         */
        if (
            !isset($settings_values[QuizSettingsService::SETTING_KEY_CREDENTIALS]) ||
            !is_string($settings_values[QuizSettingsService::SETTING_KEY_CREDENTIALS])
        ) {
            $settings_values[QuizSettingsService::SETTING_KEY_CREDENTIALS] = '';
            $this->metaBoxHelper->addSettingsError(
                'bizExaminerApiCredentials',
                __(
                    'Please select API credentials to use.',
                    'bizexaminer-learndash-extension'
                )
            );
        } else {
            $selectedCredentials = $settings_values[QuizSettingsService::SETTING_KEY_CREDENTIALS];
            if (!$this->metaBoxHelper->isCredentialsIdValid($selectedCredentials)) {
                $settings_values[QuizSettingsService::SETTING_KEY_CREDENTIALS] = '';
                $this->metaBoxHelper->addSettingsError(
                    'bizExaminerApiCredentials',
                    __(
                        'Previously selected API Credentials were deleted, please select another set.',
                        'bizexaminer-learndash-extension'
                    )
                );
            }
        }

        /**
         * 5. Check if exam module is selected, and if it exists for this API credentials
         */
        if (
            !isset($settings_values[QuizSettingsService::SETTING_KEY_EXAM_MODULE]) ||
            $settings_values[QuizSettingsService::SETTING_KEY_EXAM_MODULE] === '-1' ||
            !is_string($settings_values[QuizSettingsService::SETTING_KEY_EXAM_MODULE]) ||
            // must have an productPartsId + crtContentsRevisionsId seperated by _
            !str_contains($settings_values[QuizSettingsService::SETTING_KEY_EXAM_MODULE], '_')
        ) {
            $settings_values[QuizSettingsService::SETTING_KEY_EXAM_MODULE] = '';
            $this->metaBoxHelper->addSettingsError(
                'bizExaminerExamModule',
                __(
                    'Please select an exam module to use.',
                    'bizexaminer-learndash-extension'
                )
            );
        } elseif (!empty($settings_values[QuizSettingsService::SETTING_KEY_CREDENTIALS])) {
            $isExamModuleValid = $this->metaBoxHelper->isExamModuleValid(
                $settings_values[QuizSettingsService::SETTING_KEY_EXAM_MODULE],
                $settings_values[QuizSettingsService::SETTING_KEY_CREDENTIALS]
            );
            if (!$isExamModuleValid) {
                $settings_values[QuizSettingsService::SETTING_KEY_EXAM_MODULE] = '';
                $this->metaBoxHelper->addSettingsError(
                    'bizExaminerExamModule',
                    __(
                        'Please select a valid exam module.',
                        'bizexaminer-learndash-extension'
                    )
                );
            }
        }

        /**
         * 6. Check if remote proctoring is selected, and if it exists for this API credentials
         */
        if (
            !isset($settings_values[QuizSettingsService::SETTING_KEY_REMOTE_PROCTOR]) ||
            empty($settings_values[QuizSettingsService::SETTING_KEY_REMOTE_PROCTOR])
            // -1 is allowed for no proctoring
        ) {
            $settings_values[QuizSettingsService::SETTING_KEY_REMOTE_PROCTOR] = '';
            $this->metaBoxHelper->addSettingsError(
                'bizExaminerRemoteProctoring',
                __(
                    'Please select remote proctor to use or select "no".',
                    'bizexaminer-learndash-extension'
                )
            );
        } elseif (
            $settings_values[QuizSettingsService::SETTING_KEY_REMOTE_PROCTOR] !== '-1' &&
            !empty($settings_values[QuizSettingsService::SETTING_KEY_CREDENTIALS])
        ) {
            /**
             * frontend stores it with {$proctorType}_-_{$proctorAccountName} for frontend JS
             * explode it here and check with the real name
             */

            $remoteProctorString = $settings_values[QuizSettingsService::SETTING_KEY_REMOTE_PROCTOR];
            $remoteProctor = substr($remoteProctorString, strpos($remoteProctorString, '_-_') + 3);

            $isRemoteProctorValid = $this->metaBoxHelper->isRemoteProctorValid(
                $remoteProctor,
                $settings_values[QuizSettingsService::SETTING_KEY_CREDENTIALS]
            );
            if (!$isRemoteProctorValid) {
                $settings_values[QuizSettingsService::SETTING_KEY_REMOTE_PROCTOR] = '';
                $this->metaBoxHelper->addSettingsError(
                    'bizExaminerRemoteProctoring',
                    __(
                        'Please select a valid remote proctor.',
                        'bizexaminer-learndash-extension'
                    )
                );
            }
        }

        /**
         * 7. Validate remote proctor settings
         */
        $settings_values = $this->metaBoxHelper->validateRemoteProctoringSettings($settings_values);

        /**
         * 8. convert certificate value
         */
        if ($settings_values[QuizSettingsService::SETTING_KEY_CERTIFICATE] === 'on') {
            $settings_values[QuizSettingsService::SETTING_KEY_CERTIFICATE] = true;
        } else {
            $settings_values[QuizSettingsService::SETTING_KEY_CERTIFICATE] = false;
        }

        /**
         * 9. convert import external attempts value
         */
        if ($settings_values[QuizSettingsService::SETTING_KEY_IMPORT_EXTERNAL_ATTEMPTS] === 'on') {
            $settings_values[QuizSettingsService::SETTING_KEY_IMPORT_EXTERNAL_ATTEMPTS] = true;
        } else {
            $settings_values[QuizSettingsService::SETTING_KEY_IMPORT_EXTERNAL_ATTEMPTS] = false;
        }

        if ($settings_values[QuizSettingsService::SETTING_KEY_IMPORT_EXTERNAL_ATTEMPTS]) {
            if ($settings_values[QuizSettingsService::SETTING_KEY_IMPORT_EXTERNAL_ATTEMPTS_DISABLE_START] === 'on') {
                $settings_values[QuizSettingsService::SETTING_KEY_IMPORT_EXTERNAL_ATTEMPTS_DISABLE_START] = true;
            } else {
                $settings_values[QuizSettingsService::SETTING_KEY_IMPORT_EXTERNAL_ATTEMPTS_DISABLE_START] = false;
            }
        } else {
            $settings_values[QuizSettingsService::SETTING_KEY_IMPORT_EXTERNAL_ATTEMPTS_DISABLE_START] = false;
        }


        return $settings_values;
    }

    /**
     * LearnDash only outputs errors for a field ('input_error' in $fieldArgs)
     * in the class-ld-settings-fields-number.php but not for other types 🤷
     * output it for our own bizExaminerApiCredentials as well
     *
     * @hooked on learndash_settings_field_html_after
     *
     * @param string $html         The HTML output to be displayed after setting field.
     * @param array  $fieldArgs An array of setting field arguments.
     * @return string $html
     */
    public function addQuizSettingsErrorMessages($html, $fieldArgs)
    {
        if (
            !isset($this->settings_fields_map[$fieldArgs['name']]) ||
            empty($fieldArgs['input_error'])
        ) {
            return $html;
        }

        if (
            !empty($fieldArgs['display_callback']) &&
            is_array($fieldArgs['display_callback']) &&
            is_a($fieldArgs['display_callback'][0], 'LearnDash_Settings_Fields_Select')
        ) {
            // try to call the fields class existing callback
            $html .= $fieldArgs['display_callback'][0]->get_field_error_message($fieldArgs);
        } else {
            // fallback to styles copied from get_field_error_message in version 4.3.0.2
            $html .= '<div class="learndash-section-field-error" style="display:none;">' .
                $fieldArgs['input_error'] .
                '</div>';
        }

        return $html;
    }
}
