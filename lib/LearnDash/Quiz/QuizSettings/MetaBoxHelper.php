<?php

namespace BizExaminer\LearnDashExtension\LearnDash\Quiz\QuizSettings;

use BizExaminer\LearnDashExtension\Api\ExamModulesService;
use BizExaminer\LearnDashExtension\Api\RemoteProctorsService;
use BizExaminer\LearnDashExtension\Internal\Interfaces\ApiAwareInterface;
use BizExaminer\LearnDashExtension\Internal\Traits\ApiAwareTrait;
use BizExaminer\LearnDashExtension\LearnDash\CertificatesService;

/**
 * A class for helper functions for the MetaBox
 * To keep MetaBox class clean and tidied up
 */
class MetaBoxHelper implements ApiAwareInterface
{
    use ApiAwareTrait;

    /**
     * ExamModulesService instance to use
     *
     * @var ExamModulesService
     */
    protected ExamModulesService $examModulesService;

    /**
     * RemoteProctorsService instance to use
     *
     * @var RemoteProctorsService
     */
    protected RemoteProctorsService $remoteProctorsService;

    /**
     * CertificatesService instance to use
     *
     * @var CertificatesService
     */
    protected CertificatesService $certificatesService;

    /**
     * Creates a new MetaBoxHelper instance
     *
     * @param ExamModulesService $examModulesService ExamModulesService instance to use
     * @param RemoteProctorsService $remoteProctorsService RemoteProctorsService instance to use
     * @param CertificatesService $certificatesService CertificatesService instance to use
     */
    public function __construct(
        ExamModulesService $examModulesService,
        RemoteProctorsService $remoteProctorsService,
        CertificatesService $certificatesService
    ) {
        $this->examModulesService = $examModulesService;
        $this->remoteProctorsService = $remoteProctorsService;
        $this->certificatesService = $certificatesService;
    }

    /**
     * Build the full name of a remote proctor settings field
     *
     * @param string $proctor
     * @param string $fieldName
     * @return string
     */
    public function buildRemoteProctorFieldName(string $proctor, string $fieldName): string
    {
        return QuizSettingsService::SETTING_KEY_REMOTE_PROCTOR . "_{$proctor}_{$fieldName}";
    }

    /**
     * Get default values for all quiz settings
     *
     * @return array
     */
    public function getDefaultValues(): array
    {
        $defaults = [
            QuizSettingsService::SETTING_KEY_ENABLED => '',
            QuizSettingsService::SETTING_KEY_CREDENTIALS => null,
            QuizSettingsService::SETTING_KEY_EXAM_MODULE => null,
            QuizSettingsService::SETTING_KEY_REMOTE_PROCTOR => '-1', // none
            QuizSettingsService::SETTING_KEY_CERTIFICATE => '',
            QuizSettingsService::SETTING_KEY_IMPORT_EXTERNAL_ATTEMPTS => '',
            QuizSettingsService::SETTING_KEY_IMPORT_EXTERNAL_ATTEMPTS_DISABLE_START => '',
        ];

        foreach ($this->getRemoteProctorSettingFields() as $proctor => $proctorFields) {
            foreach ($proctorFields as $fieldName => $proctorField) {
                $fullFieldName = $this->buildRemoteProctorFieldName($proctor, $fieldName);
                $default = $proctorField['default'] ?? '';
                $defaults[$fullFieldName] = $default;
            }
        }
        return $defaults;
    }

    /**
     * Build select options for api credentials
     *
     * @param string|null $selectedCredentials
     * @return array
     */
    public function buildApiCredentialsOptions(?string $selectedCredentials): array
    {
        $credentialsOptionDefault = [
            '-1' => esc_html__(
                'Search or select a set of API Credentials to connect to bizExaminer.',
                'bizexaminer-learndash-extension'
            )
        ];

        $credentialOptions = [];
        $credentialSets = $this->getApiService()->getApiCredentials();
        if ($credentialSets) {
            foreach ($credentialSets as $credential) {
                $credentialOptions[$credential->getId()] = esc_html($credential->getName());
            }
            $credentialOptions = $credentialsOptionDefault + $credentialOptions;
        } else {
            $credentialOptions = $credentialsOptionDefault;
        }

        // add any previously selected value (maybe a api credential was deleted) to the options
        // javascript will handle marking this option as deleted
        if (!empty($selectedCredentials) && !isset($credentialOptions[$selectedCredentials])) {
            $credentialOptions[$selectedCredentials] = sprintf(
                /* translators: %s: deleted api credentials id */
                esc_html__('%s (deleted)', 'bizexaminer-learndash-extension'),
                $selectedCredentials
            );
        }

        return $credentialOptions;
    }

    /**
     * Whether a selected api credentials id is valid
     *
     * @param string $apiCredentialsId
     * @return bool
     */
    public function isCredentialsIdValid(?string $apiCredentialsId): bool
    {
        return !empty($apiCredentialsId) && $this->getApiService()->hasApiCredentials($apiCredentialsId);
    }

    /**
     * Build select options for exam modules
     *
     * @param string|null $apiCredentials Id of the api credentials to use
     * @return array
     */
    public function buildExamModulesOptions(?string $apiCredentials): array
    {
        $examModuleOptions = [];
        if (learndash_use_select2_lib()) {
            $examModulesDefault = array(
                '-1' => esc_html__('Search or select an exam module', 'bizexaminer-learndash-extension'),
            );

            /**
             * always get option
             * 1. either because an existing exam module was selected:
             *  get all values to show the selected value with title again
             * 2. no value was selected:
             *  show all values to let user select
             */
            if ($apiCredentials) {
                $examModuleOptions = $this->getExamModulesOptions($apiCredentials);
            }

            $examModuleOptions = $examModulesDefault + $examModuleOptions;
        } else {
            $examModulesDefault = array(
                '-1' => esc_html__('Select an exam module', 'bizexaminer-learndash-extension'),
            );

            if ($apiCredentials) {
                $examModuleOptions = $this->getExamModulesOptions($apiCredentials);
            }

            if ((is_array($examModuleOptions)) && (!empty($examModuleOptions))) {
                $examModuleOptions = $examModulesDefault + $examModuleOptions;
            } else {
                $examModuleOptions = $examModulesDefault;
            }
            $examModulesDefault = '';
        }

        return $examModuleOptions;
    }

    /**
     * Whether a selected exam module id is valid
     *
     * @param string $examModule
     * @param string $apiCredentialsId
     * @return bool
     */
    public function isExamModuleValid(?string $examModule, string $apiCredentialsId): bool
    {
        if (empty($examModule) || empty($apiCredentialsId)) {
            return false;
        }
        $credentials = $this->getApiService()->getApiCredentialsById($apiCredentialsId);
        return $this->examModulesService->hasExamModuleContentRevision($examModule, $credentials);
    }

    /**
     * Build select options for remote proctor accounts
     *
     * @param string|null $apiCredentials Id of the api credentials to use
     * @return array
     */
    public function buildRemoteProctorOptions(?string $apiCredentials): array
    {
        $remoteProctorDefault = [
            '-1' => esc_html__('No remote proctoring.', 'bizexaminer-learndash-extension'),
        ];
        $remoteProctorOptions = [];
        if (learndash_use_select2_lib()) {
            /**
             * always get option
             * 1. either because an existing exam module was selected:
             *  get all values to show the selected value with title again
             * 2. no value was selected:
             *  show all values to let user select
             */
            if ($apiCredentials) {
                $remoteProctorOptions = $this->getRemoteProctorsOptions($apiCredentials);
                if (empty($remoteProctorOptions)) {
                    $remoteProctorOptions = $remoteProctorDefault;
                }
            }
            $remoteProctorOptions = $remoteProctorDefault + $remoteProctorOptions;
        } else {
            if ($apiCredentials) {
                $remoteProctorOptions = $this->getRemoteProctorsOptions($apiCredentials);
            }

            if (empty($remoteProctorOptions)) {
                $remoteProctorOptions = $remoteProctorDefault;
            }
        }

        return $remoteProctorOptions;
    }

    /**
     * Whether a selected remote proctor account is valid
     *
     * @param string $remoteProctor name
     * @param string $apiCredentialsId
     * @return bool
     */
    public function isRemoteProctorValid(string $remoteProctor, string $apiCredentialsId): bool
    {
        if (empty($remoteProctor) || empty($apiCredentialsId)) {
            return false;
        }
        $credentials = $this->getApiService()->getApiCredentialsById($apiCredentialsId);
        return $this->remoteProctorsService->hasRemoteProctor($remoteProctor, $credentials);
    }

    /**
     * Gets the settings per remote proctor to use
     * can be used for LearnDash settings field format
     *
     * @return array
     */
    public function getRemoteProctorSettingFields(): array
    {
        $settings = [
            'proctorexam' => [ // key needs to be the same as 'type' from api
                'sessionType' => [
                    'label' => esc_html_x(
                        'Session Type',
                        'ProctorExam setting label',
                        'bizexaminer-learndash-extension'
                    ),
                    'type' => 'select',
                    'default' => 'record_review',
                    'options' => [
                        'classroom' => esc_html_x(
                            'Classroom',
                            'ProctorExam session type',
                            'bizexaminer-learndash-extension'
                        ),
                        'record_review' => esc_html_x(
                            'Record Review',
                            'ProctorExam session type',
                            'bizexaminer-learndash-extension'
                        ),
                        'live_proctoring' => esc_html_x(
                            'Live Proctoring',
                            'ProctorExam session type',
                            'bizexaminer-learndash-extension'
                        ),
                    ],
                ],
                'mobileCam' => [
                    'label' => esc_html_x(
                        'Use mobile camera as additional recording device',
                        'ProctorExam setting label',
                        'bizexaminer-learndash-extension'
                    ),
                    'type' => 'checkbox-switch',
                    'value_type' => 'int',
                    'default' => 0,
                    'options' => [
                        0   => '',
                        1 => esc_html_x(
                            'Use mobile camera as additional recording device',
                            'ProctorExam setting label',
                            'bizexaminer-learndash-extension'
                        ),
                    ],
                ],
                'dontSendEmails' => [
                    'label' => esc_html_x(
                        'Do not send participant emails',
                        'ProctorExam setting label',
                        'bizexaminer-learndash-extension'
                    ),
                    'type' => 'checkbox-switch',
                    'value_type' => 'int',
                    'default' => 0,
                    'options' => [
                        0   => '',
                        1 => esc_html_x(
                            'Do not send participant emails',
                            'ProctorExam setting label',
                            'bizexaminer-learndash-extension'
                        ),
                    ],
                ],
                'examInfo' => [
                    'label' => esc_html_x(
                        'General instructions for the exam',
                        'ProctorExam setting label',
                        'bizexaminer-learndash-extension'
                    ),
                    'help_text' => esc_html_x(
                        'They are displayed before the student starts the exam.',
                        'ProctorExam setting help',
                        'bizexaminer-learndash-extension'
                    ),
                    'type' => 'text',
                ],
                'individualInfo' => [
                    'label' => esc_html_x(
                        'Individual information for each student.',
                        'ProctorExam setting label',
                        'bizexaminer-learndash-extension'
                    ),
                    'help_text' => esc_html_x(
                        'A personalized link to start the exam will be appended
                        at the bottom using the the text from below.
                        Alternatively, the <code>##start_exam##</code>
                        placeholder can be used to control positioning of the link.',
                        'ProctorExam setting help',
                        'bizexaminer-learndash-extension'
                    ),
                    'type' => 'text',
                ],
                'startExamLinkText' => [
                    'label' => esc_html_x(
                        '»Start exam« link text',
                        'ProctorExam setting label',
                        'bizexaminer-learndash-extension'
                    ),
                    'default' => esc_html_x(
                        'Start exam',
                        'ProctorExam start exam button text',
                        'bizexaminer-learndash-extension'
                    ),
                    'type' => 'text',
                ],
            ],
            'examity' => [
                'courseId' => [
                    'label' => esc_html_x(
                        'ID of the course',
                        'Examity setting label',
                        'bizexaminer-learndash-extension'
                    ),
                    'type' => 'text',
                ],
                'courseName' => [
                    'label' => esc_html_x(
                        'Name of the course',
                        'Examity setting label',
                        'bizexaminer-learndash-extension'
                    ),
                    'type' => 'text',
                ],
                'instructorFirstName' => [
                    'label' => esc_html_x(
                        'First name of the instructor',
                        'Examity setting label',
                        'bizexaminer-learndash-extension'
                    ),
                    'type' => 'text',
                ],
                'instructorLastName' => [
                    'label' => esc_html_x(
                        'Last name of the instructor',
                        'Examity setting label',
                        'bizexaminer-learndash-extension'
                    ),
                    'type' => 'text',
                ],
                'instructorEmail' => [
                    'label' => esc_html_x(
                        'Email address of the instructor',
                        'Examity setting label',
                        'bizexaminer-learndash-extension'
                    ),
                    'type' => 'email',
                ],
                'examName' => [
                    'label' => esc_html_x(
                        'Name of the exam',
                        'Examity setting label',
                        'bizexaminer-learndash-extension'
                    ),
                    'type' => 'text',
                ],
                'examLevel' => [
                    'label' => esc_html_x(
                        'Session Type',
                        'Examity setting label',
                        'bizexaminer-learndash-extension'
                    ),
                    'type' => 'select',
                    'value_type' => 'int',
                    'options' => [
                        1 => esc_html_x(
                            'Live Authentication',
                            'Examity exam level',
                            'bizexaminer-learndash-extension'
                        ),
                        2 => esc_html_x(
                            'Automated Proctoring Premium',
                            'Examity exam level',
                            'bizexaminer-learndash-extension'
                        ),
                        3 => esc_html_x(
                            'Record and Review Proctoring',
                            'Examity exam level',
                            'bizexaminer-learndash-extension'
                        ),
                        4 => esc_html_x(
                            'Live Proctoring',
                            'Examity exam level',
                            'bizexaminer-learndash-extension'
                        ),
                        5 => esc_html_x(
                            'Auto-Authentication',
                            'Examity exam level',
                            'bizexaminer-learndash-extension'
                        ),
                        6 => esc_html_x(
                            'Automated Proctoring Standard',
                            'Examity exam level',
                            'bizexaminer-learndash-extension'
                        ),
                    ],
                ],
                'examInstructions' => [
                    'label' => esc_html_x(
                        'Instructions for the student',
                        'Examity setting label',
                        'bizexaminer-learndash-extension'
                    ),
                    'type' => 'text',
                ],
                'proctorInstructions' => [
                    'label' => esc_html_x(
                        'Instructions for the proctor',
                        'Examity setting label',
                        'bizexaminer-learndash-extension'
                    ),
                    'type' => 'text',
                ],
            ],
            'examity_v5' => [
                'courseCode' => [
                    'label' => esc_html_x(
                        'Code of the course',
                        'Examity setting label',
                        'bizexaminer-learndash-extension'
                    ),
                    'type' => 'text',
                ],
                'courseName' => [
                    'label' => esc_html_x(
                        'Name of the course',
                        'Examity setting label',
                        'bizexaminer-learndash-extension'
                    ),
                    'type' => 'text',
                ],
                'instructorFirstName' => [
                    'label' => esc_html_x(
                        'First name of the instructor',
                        'Examity setting label',
                        'bizexaminer-learndash-extension'
                    ),
                    'type' => 'text',
                ],
                'instructorLastName' => [
                    'label' => esc_html_x(
                        'Last name of the instructor',
                        'Examity setting label',
                        'bizexaminer-learndash-extension'
                    ),
                    'type' => 'text',
                ],
                'instructorEmail' => [
                    'label' => esc_html_x(
                        'Email address of the instructor',
                        'Examity setting label',
                        'bizexaminer-learndash-extension'
                    ),
                    'type' => 'email',
                ],
                'examName' => [
                    'label' => esc_html_x(
                        'Name of the exam',
                        'Examity setting label',
                        'bizexaminer-learndash-extension'
                    ),
                    'type' => 'text',
                ],
                'examSecurityLevel' => [
                    'label' => esc_html_x(
                        'Exam security level',
                        'Examity setting label',
                        'bizexaminer-learndash-extension'
                    ),
                    'type' => 'select',
                    'value_type' => 'int',
                    'options' => [
                        2 => esc_html_x(
                            'Automated + Audit',
                            'Examity exam security level',
                            'bizexaminer-learndash-extension'
                        ),
                        4 => esc_html_x(
                            'Live Proctoring',
                            'Examity exam level',
                            'bizexaminer-learndash-extension'
                        ),
                        10 => esc_html_x(
                            'Live Authentication + Audit',
                            'Examity exam security level',
                            'bizexaminer-learndash-extension'
                        ),
                        11 => esc_html_x(
                            'Live Proctoring',
                            'Automated',
                            'bizexaminer-learndash-extension'
                        ),
                        12 => esc_html_x(
                            'Automated Practice',
                            'Examity exam security level',
                            'bizexaminer-learndash-extension'
                        ),
                    ],
                ],
            ],
            'examus' => [
                'language' => [
                    'label' => esc_html_x(
                        'Constructor UI language',
                        'Examus setting label',
                        'bizexaminer-learndash-extension'
                    ),
                    'type' => 'select',
                    'default' => 'en',
                    'options' => [
                        'en' => esc_html_x('English', 'Examus setting label', 'bizexaminer-learndash-extension'),
                        'ru' => esc_html_x('Russian', 'Examus setting label', 'bizexaminer-learndash-extension'),
                        'es' => esc_html_x('Spanish', 'Examus setting label', 'bizexaminer-learndash-extension'),
                        'it' => esc_html_x('Italian', 'Examus setting label', 'bizexaminer-learndash-extension'),
                        'ar' => esc_html_x('Arabic', 'Examus setting label', 'bizexaminer-learndash-extension'),
                        'fr' => esc_html_x('French', 'Examus setting label', 'bizexaminer-learndash-extension'),
                    ],
                ],
                'proctoring' => [
                    'label' => esc_html_x('Type', 'Examus setting label', 'bizexaminer-learndash-extension'),
                    'type' => 'select',
                    'default' => 'online',
                    'options' => [
                        'online' => esc_html_x(
                            'Live Proctoring',
                            'Examus proctoring',
                            'bizexaminer-learndash-extension'
                        ),
                        'offline' => esc_html_x(
                            'Record and Post Exam Review',
                            'Examus proctoring',
                            'bizexaminer-learndash-extension'
                        ),
                    ],
                ],
                'identification' => [
                    'label' => esc_html_x(
                        'Identification',
                        'Examus setting label',
                        'bizexaminer-learndash-extension'
                    ),
                    'type' => 'select',
                    'default' => 'face',
                    'options' => [
                        'face' => esc_html_x('Face', 'Examus identification', 'bizexaminer-learndash-extension'),
                        'passport' => esc_html_x(
                            'Passport',
                            'Examus identification',
                            'bizexaminer-learndash-extension'
                        ),
                        'face_and_passport' => esc_html_x(
                            'Face and Passport',
                            'Examus identification',
                            'bizexaminer-learndash-extension'
                        ),
                    ],
                ],
                'respondus' => [
                    'label' => esc_html_x(
                        'Use Respondus LockDown Browser',
                        'Examus setting label',
                        'bizexaminer-learndash-extension'
                    ),
                    'type' => 'checkbox-switch',
                    'value_type' => 'int',
                    'default' => 0,
                    'options' => [
                        0   => '',
                        1 => esc_html_x(
                            'Use Respondus LockDown Browser',
                            'Examus setting label',
                            'bizexaminer-learndash-extension'
                        ),
                    ],
                ],
                'userAgreementUrl' => [
                    'label' => esc_html_x(
                        'User agreement URL (optional)',
                        'Examus userAgreementUrl',
                        'bizexaminer-learndash-extension'
                    ),
                    'type' => 'url',
                ]
            ],
            'proctorio' => [
                'recordVideo' => [
                    'label' => esc_html_x(
                        'Record video',
                        'Proctorio setting label',
                        'bizexaminer-learndash-extension'
                    ),
                    'type' => 'checkbox-switch',
                    'value_type' => 'int',
                    'options' => [
                        0   => '',
                        1 => esc_html_x(
                            'Record video',
                            'Proctorio setting label',
                            'bizexaminer-learndash-extension'
                        ),
                    ],
                ],
                'recordAudio' => [
                    'label' => esc_html_x(
                        'Record audio',
                        'Proctorio setting label',
                        'bizexaminer-learndash-extension'
                    ),
                    'type' => 'checkbox-switch',
                    'value_type' => 'int',
                    'options' => [
                        0   => '',
                        1 => esc_html_x(
                            'Record audio',
                            'Proctorio setting label',
                            'bizexaminer-learndash-extension'
                        ),
                    ],
                ],
                'recordScreen' => [
                    'label' => esc_html_x(
                        'Record screen',
                        'Proctorio setting label',
                        'bizexaminer-learndash-extension'
                    ),
                    'type' => 'checkbox-switch',
                    'value_type' => 'int',
                    'options' => [
                        0   => '',
                        1 => esc_html_x(
                            'Record screen',
                            'Proctorio setting label',
                            'bizexaminer-learndash-extension'
                        ),
                    ],
                ],
                'recordRoomStart' => [
                    'label' => esc_html_x(
                        'Record room on start',
                        'Proctorio setting label',
                        'bizexaminer-learndash-extension'
                    ),
                    'help_text' => esc_html_x(
                        'Require the test taker to perform a room scan before starting the exam',
                        'Proctorio setting help text',
                        'bizexaminer-learndash-extension'
                    ),
                    'type' => 'checkbox-switch',
                    'value_type' => 'int',
                    'options' => [
                        0   => '',
                        1 => esc_html_x(
                            'Record',
                            'Proctorio recordRoomStart setting label',
                            'bizexaminer-learndash-extension'
                        ),
                    ],
                ],
                'verifyIdMode' => [
                    'label' => esc_html_x(
                        'Verify ID',
                        'Proctorio setting label',
                        'bizexaminer-learndash-extension'
                    ),
                    'type' => 'select',
                    'options' => [
                        '' => esc_html_x(
                            'no',
                            'Proctorio verifyIdMode label',
                            'bizexaminer-learndash-extension'
                        ),
                        'auto' => esc_html_x(
                            'Automatic ID verification',
                            'Proctorio verifyIdMode label',
                            'bizexaminer-learndash-extension'
                        ),
                        'live' => esc_html_x(
                            'Live ID verification',
                            'Proctorio verifyIdMode label',
                            'bizexaminer-learndash-extension'
                        ),
                    ],
                ],
                'closeOpenTabs' => [
                    'label' => esc_html_x(
                        'Close open tabs',
                        'Proctorio setting label',
                        'bizexaminer-learndash-extension'
                    ),
                    'type' => 'checkbox-switch',
                    'value_type' => 'int',
                    'options' => [
                        0   => '',
                        1 => esc_html_x(
                            'Close open tabs',
                            'Proctorio setting label',
                            'bizexaminer-learndash-extension'
                        ),
                    ],
                ],
                'allowNewTabs' => [
                    'label' => esc_html_x(
                        'Allow new tabs',
                        'Proctorio setting label',
                        'bizexaminer-learndash-extension'
                    ),
                    'type' => 'checkbox-switch',
                    'value_type' => 'int',
                    'options' => [
                        0   => '',
                        1 => esc_html_x(
                            'Allow new tabs',
                            'Proctorio setting label',
                            'bizexaminer-learndash-extension'
                        ),
                    ],
                ],
                'fullscreenMode' => [
                    'label' => esc_html_x(
                        'Force fullscreen',
                        'Proctorio setting label',
                        'bizexaminer-learndash-extension'
                    ),
                    'type' => 'select',
                    'options' => [
                        '' => esc_html_x(
                            'no',
                            'Proctorio fullScreenMode label',
                            'bizexaminer-learndash-extension'
                        ),
                        'lenient' => esc_html_x(
                            'Lenient',
                            'Proctorio fullscreenMode label',
                            'bizexaminer-learndash-extension'
                        ),
                        'moderate' => esc_html_x(
                            'Moderate',
                            'Proctorio fullscreenMode label',
                            'bizexaminer-learndash-extension'
                        ),
                        'severe' => esc_html_x(
                            'Severe',
                            'Proctorio fullscreenMode label',
                            'bizexaminer-learndash-extension'
                        ),
                    ],
                ],
                'disableClipboard' => [
                    'label' => esc_html_x(
                        'Disable clipboard',
                        'Proctorio setting label',
                        'bizexaminer-learndash-extension'
                    ),
                    'type' => 'checkbox-switch',
                    'value_type' => 'int',
                    'options' => [
                        0   => '',
                        1 => esc_html_x(
                            'Disable clipboard',
                            'Proctorio setting label',
                            'bizexaminer-learndash-extension'
                        ),
                    ],
                ],
                'disableRightClick' => [
                    'label' => esc_html_x(
                        'Disable rightclick',
                        'Proctorio setting label',
                        'bizexaminer-learndash-extension'
                    ),
                    'type' => 'checkbox-switch',
                    'value_type' => 'int',
                    'options' => [
                        0   => '',
                        1 => esc_html_x(
                            'Disable rightclick',
                            'Proctorio setting label',
                            'bizexaminer-learndash-extension'
                        ),
                    ],
                ],
                'disableDownloads' => [
                    'label' => esc_html_x(
                        'Disable downloads',
                        'Proctorio setting label',
                        'bizexaminer-learndash-extension'
                    ),
                    'type' => 'checkbox-switch',
                    'value_type' => 'int',
                    'options' => [
                        0   => '',
                        1 => esc_html_x(
                            'Disable downloads',
                            'Proctorio setting label',
                            'bizexaminer-learndash-extension'
                        ),
                    ],
                ],
                'disablePrinting' => [
                    'label' => esc_html_x(
                        'Disable printing',
                        'Proctorio setting label',
                        'bizexaminer-learndash-extension'
                    ),
                    'type' => 'checkbox-switch',
                    'value_type' => 'int',
                    'options' => [
                        0  => '',
                        1  => esc_html_x(
                            'Disable printing',
                            'Proctorio setting label',
                            'bizexaminer-learndash-extension'
                        ),
                    ],
                ],
            ],
            'meazure' => [
                'sessionType' => [
                    'label' => esc_html_x('Type', 'Meazure setting label', 'bizexaminer-learndash-extension'),
                    'type' => 'select',
                    'default' => 'live+',
                    'options' => [
                        'live+' => esc_html_x(
                            'Live+',
                            'Meazure setting label',
                            'bizexaminer-learndash-extension'
                        ),
                        'record+' => esc_html_x(
                            'Record+',
                            'Meazure setting label',
                            'bizexaminer-learndash-extension'
                        ),
                    ],
                ],
                'dontNotifyTestTaker' => [
                    'label' => esc_html_x(
                        'Do not notifiy test taker',
                        'Meazure setting label',
                        'bizexaminer-learndash-extension'
                    ),
                    'type' => 'checkbox-switch',
                    'value_type' => 'int',
                    'options' => [
                        0   => '',
                        1 => esc_html_x(
                            'Do not notifiy test taker',
                            'Meazure setting label',
                            'bizexaminer-learndash-extension'
                        ),
                    ],
                ],
                'securityPreset' => [
                    'label' => esc_html_x(
                        'Security preset',
                        'Meazure setting label',
                        'bizexaminer-learndash-extension'
                    ),
                    'type' => 'select',
                    'default' => 'low',
                    'options' => [
                        'low' => esc_html_x(
                            'Low',
                            'Meazure setting label',
                            'bizexaminer-learndash-extension'
                        ),
                        'medium' => esc_html_x(
                            'Medium',
                            'Meazure setting label',
                            'bizexaminer-learndash-extension'
                        ),
                        'high' => esc_html_x(
                            'High',
                            'Meazure setting label',
                            'bizexaminer-learndash-extension'
                        ),
                    ],
                ],
                'allowedUrls' => [
                    'label' => esc_html_x('Allowed URLs', 'Meazure setting label', 'bizexaminer-learndash-extension'),
                    'type'      => 'multiselect',
                    'help_text' => esc_html_x(
                        'Choose which URLs are allowed. Type an URL and press "enter" to allow it.',
                        'Meazure setting label',
                        'bizexaminer-learndash-extension'
                    ),
                    'placeholder' => esc_html_x('Add URL', 'Meazure setting label', 'bizexaminer-learndash-extension'),
                    'options' => [],
                    'value_type' => 'url',
                    'attrs' => [
                        // Allow user adding URLs
                        'data-tags' => '1'
                    ],
                    'allowNew' => true, // For sanitization, allow new values not defined in options
                    /**
                     * if current selection is invalid show specific error,
                     * otherwise output (and hide) error message to use in JavaScript if field is empty
                     */
                    'input_error' => esc_html__(
                        'Only valid URLs are accepted.',
                        'bizexaminer-learndash-extension'
                    ),
                    // TODO: allow setting create-on-start per url by adding a repeater
                    // 'type' => 'repeater',
                    // 'addlabel' => esc_html_x(
                    //    'Add allowed URL', 'Meazure setting label', 'bizexaminer-learndash-extension'),
                    // 'default' => '',
                    // 'fields' => [
                    //     'url' => [
                    //         'type' => 'text'
                    //         'label' => esc_html_x('URL', 'Meazure setting label', 'bizexaminer-learndash-extension'),
                    //         'default' => '',
                    //         'sanitize' => 'url'
                    //     ],
                    //     'open_on_start' => [
                    //         'type' => 'switch',
                    //         'label' => esc_html_x(
                    //             'Open on start', 'Meazure setting label', 'bizexaminer-learndash-extension'),
                    //         'default' => 0,
                    //         'sanitize' => 'bool'
                    //     ]
                    // ]
                ],
                'allowedResources' => [
                    'label' => esc_html_x(
                        'Allowed resources',
                        'Meazure setting label',
                        'bizexaminer-learndash-extension'
                    ),
                    'type' => 'multiselect',
                    'default' => [],
                    'options' => [
                        'all_websites' => esc_html_x(
                            'All websites',
                            'Meazure setting label',
                            'bizexaminer-learndash-extension'
                        ),
                        'approved_website' => esc_html_x(
                            'Approved website',
                            'Meazure setting label',
                            'bizexaminer-learndash-extension'
                        ),
                        'bathroom_breaks' => esc_html_x(
                            'Bathroom breaks',
                            'Meazure setting label',
                            'bizexaminer-learndash-extension'
                        ),
                        'computer_calculator' => esc_html_x(
                            'Computer\'s calculator',
                            'Meazure setting label',
                            'bizexaminer-learndash-extension'
                        ),
                        'course_website' => esc_html_x(
                            'Course website',
                            'Meazure setting label',
                            'bizexaminer-learndash-extension'
                        ),
                        'ebook_computer' => esc_html_x(
                            'Ebook (computer)',
                            'Meazure setting label',
                            'bizexaminer-learndash-extension'
                        ),
                        'ebook_website'     => esc_html_x(
                            'Ebook (website)',
                            'Meazure setting label',
                            'bizexaminer-learndash-extension'
                        ),
                        'excel' => esc_html_x(
                            'Excel',
                            'Meazure setting label',
                            'bizexaminer-learndash-extension'
                        ),
                        'excel_notes' => esc_html_x(
                            'Notes (Excel)',
                            'Meazure setting label',
                            'bizexaminer-learndash-extension'
                        ),
                        'financial_calculator' => esc_html_x(
                            'Financial calculator',
                            'Meazure setting label',
                            'bizexaminer-learndash-extension'
                        ),
                        'formula_sheet' => esc_html_x(
                            'Formula sheet',
                            'Meazure setting label',
                            'bizexaminer-learndash-extension'
                        ),
                        'four_function_calculator' => esc_html_x(
                            'Four function calculator',
                            'Meazure setting label',
                            'bizexaminer-learndash-extension'
                        ),
                        'graphing_calculator' => esc_html_x(
                            'Graphing calculator',
                            'Meazure setting label',
                            'bizexaminer-learndash-extension'
                        ),
                        'handwritten_notes' => esc_html_x(
                            'Handwritten notes',
                            'Meazure setting label',
                            'bizexaminer-learndash-extension'
                        ),
                        'note_cards' => esc_html_x(
                            'Note cards',
                            'Meazure setting label',
                            'bizexaminer-learndash-extension'
                        ),
                        'notepad' => esc_html_x(
                            'Notepad',
                            'Meazure setting label',
                            'bizexaminer-learndash-extension'
                        ),
                        'online_calculator' => esc_html_x(
                            'Online calculator',
                            'Meazure setting label',
                            'bizexaminer-learndash-extension'
                        ),
                        'paint' => esc_html_x(
                            'Paint',
                            'Meazure setting label',
                            'bizexaminer-learndash-extension'
                        ),
                        'pdf_notes' => esc_html_x(
                            'Notes (PDF)',
                            'Meazure setting label',
                            'bizexaminer-learndash-extension'
                        ),
                        'powerpoint' => esc_html_x(
                            'Powerpoint',
                            'Meazure setting label',
                            'bizexaminer-learndash-extension'
                        ),
                        'powerpoint_notes' => esc_html_x(
                            'Notes (Powerpoint)',
                            'Meazure setting label',
                            'bizexaminer-learndash-extension'
                        ),
                        'printed_notes' => esc_html_x(
                            'Printed notes',
                            'Meazure setting label',
                            'bizexaminer-learndash-extension'
                        ),
                        'scientific_calculator' => esc_html_x(
                            'Scientific calculator',
                            'Meazure setting label',
                            'bizexaminer-learndash-extension'
                        ),
                        'scratch1' => esc_html_x(
                            'Scratch paper (1 sheet)',
                            'Meazure setting label',
                            'bizexaminer-learndash-extension'
                        ),
                        'scratch2' => esc_html_x(
                            'Scratch paper (2 sheets)',
                            'Meazure setting label',
                            'bizexaminer-learndash-extension'
                        ),
                        'scratch_more' => esc_html_x(
                            'Scratch paper (multiple sheets)',
                            'Meazure setting label',
                            'bizexaminer-learndash-extension'
                        ),
                        'spss' => esc_html_x(
                            'SPSS',
                            'Meazure setting label',
                            'bizexaminer-learndash-extension'
                        ),
                        'textbook' => esc_html_x(
                            'Textbook',
                            'Meazure setting label',
                            'bizexaminer-learndash-extension'
                        ),
                        'whiteboard' => esc_html_x(
                            'Whiteboard',
                            'Meazure setting label',
                            'bizexaminer-learndash-extension'
                        ),
                        'word' => esc_html_x(
                            'Word',
                            'Meazure setting label',
                            'bizexaminer-learndash-extension'
                        ),
                        'word_notes' => esc_html_x(
                            'Notes (Word)',
                            'Meazure setting label',
                            'bizexaminer-learndash-extension'
                        ),
                    ]
                ]
            ],
        ];

        return $settings;
    }

    /**
     * Validates user input for remote proctoring settings
     * by checking available options, casting types, resetting to defaults
     *
     * info: no remote proctor setting is required
     *
     * @param array $values Array of user input vlaues, should already be sanitized
     * @return array
     */
    public function validateRemoteProctoringSettings($values): array
    {
        $defaults = $this->getDefaultValues();

        foreach ($this->getRemoteProctorSettingFields() as $proctor => $proctorFields) {
            foreach ($proctorFields as $fieldName => $proctorField) {
                $fullFieldName = $this->buildRemoteProctorFieldName($proctor, $fieldName);
                // no settings for remote proctores are required
                if (!isset($values[$fullFieldName])) {
                    continue;
                }
                $value = $values[$fullFieldName];
                $originalValue = $value;
                switch ($proctorField['type']) {
                    case 'url':
                    case 'text':
                        // treat all empty (null, '') as same and set to default
                        if (empty($value)) {
                            $value = $defaults[$fullFieldName];
                        }
                        break;
                    case 'select':
                    case 'checkbox-switch':
                        // cast value to defined typesq
                        if (isset($proctorField['value_type'])) {
                            if ($proctorField['value_type'] === 'int') {
                                $value = intval($value);
                            }
                        }
                        // make sure the value is a valid option, if not set it to default
                        if (!isset($proctorField['options'][$value])) {
                            $value = $defaults[$fullFieldName];
                        }
                        break;
                    case 'multiselect':
                        $originalValue = (array) $value;
                        if (!isset($proctorField['allowNew']) || !$proctorField['allowNew']) {
                            $value = array_intersect((array)$value, array_keys($proctorField['options']));
                        }

                        if (isset($proctorField['value_type'])) {
                            if ($proctorField['value_type'] === 'int') {
                                $value = array_map('intval', (array)$value);
                            } elseif ($proctorField['value_type'] === 'url') {
                                $value = array_filter((array)$value, function ($url) {
                                    return filter_var($url, FILTER_VALIDATE_URL);
                                });
                            }
                        }

                        if (count((array)$originalValue) !== count((array)$value)) {
                            $this->addSettingsError($fullFieldName, __(
                                'Invalid options were removed.',
                                'bizexaminer-learndash-extension'
                            ));
                        }
                }

                $values[$fullFieldName] = $value;
            }
        }

        return $values;
    }

    /**
     * Returns an array of learndash setting groups & settings
     * which are incompatible with bizExaminer
     * and should be disabled when bizExaminer is enabled for a quiz
     *
     * @return array
     *              $conditionalBizExaminerField [
     *                  'type' => (string) 'checkbox'|'text'|'radio'|'select'
     *                  'value' => (mixed) value to check for if fields should be disabled
     *                  'fieldGroups' => [
     *                      $groupKey => [
     *                          $fieldKey => [
     *                              'type' => (string) 'checkbox'|'text'|'radio'|'select'
     *                              'reset_value' => (mixed) the value the field should be reset to when disabled
     *                              'help-text' => (string) a message explaining to the user why it's disabled
     *                          ]
     *                      ]
     *                  ]
     *              ]
     */
    public function getIncompatibleLearnDashSettings()
    {
        return [
            'bizExaminerEnabled' => [
                'type' => 'checkbox',
                'value' => 'on',
                'fieldGroups' => [
                    'access-settings' => [
                        'startOnlyRegisteredUser' => [
                            'type' => 'checkbox',
                            'reset_value' => 'on',
                            'help_text' => esc_html__(
                                'bizExaminer only works with registered users.',
                                'bizexaminer-learndash-extension'
                            )
                        ]
                    ],
                    'progress-settings' => [
                        'passingpercentage' => [
                            'type' => 'text',
                            'reset_value' => 100,
                            'help_text' => esc_html__(
                                'Passing score is configured via bizExaminer.',
                                'bizexaminer-learndash-extension'
                            )
                        ],
                        'quiz_resume' => [
                            'type' => 'checkbox',
                            'reset_value' => 'off',
                            'help_text' => esc_html(sprintf(
                                /* translators: placeholder: quiz label */
                                __(
                                    'bizExaminer allows the user to resume the %1$s/exam when
                                    he opens the %1$s page while an attempt is still running.',
                                    'bizexaminer-learndash-extension'
                                ),
                                learndash_get_custom_label_lower('quiz')
                            )),
                        ],
                        'forcingQuestionSolve' => [
                            'type' => 'checkbox',
                            'reset_value' => 'off',
                            'help_text' => esc_html__(
                                'Not compatible with bizExaminer.',
                                'bizexaminer-learndash-extension'
                            )
                        ],
                        'quiz_time_limit_enabled' => [
                            'type' => 'checkbox',
                            'reset_value' => 'off',
                            'help_text' => esc_html__(
                                'Not compatible with bizExaminer.',
                                'bizexaminer-learndash-extension'
                            )
                        ]
                    ],
                    'display-content-settings' => [
                        'quizModus' => [
                            'type' => 'select',
                            'reset_value' => 'single',
                            'help_text' => esc_html__(
                                'Not compatible with bizExaminer.',
                                'bizexaminer-learndash-extension'
                            )
                        ],
                        'quizModus_single_feedback' => [
                            'type' => 'radio',
                            'reset_value' => 'end',
                            'help_text' => esc_html__(
                                'Not compatible with bizExaminer.',
                                'bizexaminer-learndash-extension'
                            )
                        ],
                        'single_back_button' => [
                            'type' => 'checkbox',
                            'reset_value' => 'off',
                            'help_text' => esc_html__(
                                'Not compatible with bizExaminer.',
                                'bizexaminer-learndash-extension'
                            )
                        ],
                        'showReviewQuestion' => [
                            'type' => 'checkbox',
                            'reset_value' => 'off',
                            'help_text' => esc_html__(
                                'Not compatible with bizExaminer.',
                                'bizexaminer-learndash-extension'
                            )
                        ],
                        'custom_sorting' => [
                            'type' => 'checkbox',
                            'reset_value' => 'off',
                            'help_text' => esc_html__(
                                'Not compatible with bizExaminer.',
                                'bizexaminer-learndash-extension'
                            )
                        ],
                        'custom_question_elements' => [
                            'type' => 'checkbox',
                            'reset_value' => 'off',
                            'help_text' => esc_html__(
                                'Not compatible with bizExaminer.',
                                'bizexaminer-learndash-extension'
                            )
                        ]
                    ],
                    'results-options' => [
                        'custom_answer_feedback' => [
                            'type' => 'checkbox',
                            'reset_value' => 'off',
                            'help_text' => esc_html__(
                                'Not compatible with bizExaminer.',
                                'bizexaminer-learndash-extension'
                            )
                        ],
                        'showAverageResult' => [
                            'type' => 'checkbox',
                            'reset_value' => 'off',
                            'help_text' => esc_html__(
                                'Not compatible with bizExaminer.',
                                'bizexaminer-learndash-extension'
                            )
                        ],
                        'showCategoryScore' => [
                            'type' => 'checkbox',
                            'reset_value' => 'off',
                            'help_text' => esc_html__(
                                'Not compatible with bizExaminer.',
                                'bizexaminer-learndash-extension'
                            )
                        ]
                    ],
                    'admin-data-handling-settings' => [
                        'email_enabled' => [
                            'type' => 'checkbox',
                            'reset_value' => 'off',
                            'help_text' => esc_html__(
                                'Not compatible with bizExaminer.
                                    Please use other plugins which hook into learndash_quiz_completed.',
                                'bizexaminer-learndash-extension'
                            )
                        ]
                    ]
                ]
            ],
            'bizExaminerCertificate' => [
                'type' => 'checkbox',
                'value' => 'on',
                'fieldGroups' => [
                    'progress-settings' => [
                        'certificate' => [
                            'type' => 'select',
                            'reset_value' => $this->certificatesService->getPlaceholderCertificateId(),
                            'reset_value_label' => esc_html_x(
                                'bizExaminer Certificate',
                                'bizexaminer placeholder certificate',
                                'bizexaminer-learndash-extension'
                            ),
                            'help_text' => esc_html__(
                                'You have chosen to use bizExaminer certificates.',
                                'bizexaminer-learndash-extension'
                            )
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Fetches select options via ajax and returns them for displaying in a select2 dropdown
     * for exam modules and remote proctors
     *
     * @see class-ld-settings-page.php #905
     * @watch class-ld-settings-page.php #905
     *
     * @param string $field the settings field name for which to get options
     * @param array $data the query/ajax data
     *
     * @return array Options in the format for select2 ajax queries
     */
    public function getAjaxOptions(string $field, array $data)
    {
        // Format result in same format for LearnDash/Select2
        $result = array(
            'items'       => [],
            'total_items' => 0,
            'page'        => 1,
            'total_pages' => 1,
        );

        // current by use selected value on client
        $apiCredentials = $data['api_credentials'];

        if ($field === 'bizExaminerExamModule') {
            /**
             * ProductPart is an optgroup (because it's not selectable alone)
             *  and content revisions are selectable options (children) of the productpart
             */
            try {
                $options = $this->getExamModulesOptions($apiCredentials);
                if ($options) {
                    $result['items'] = array_map(function ($optionId, $optionChildren) {
                        return [
                            'id' => $optionId,
                            'text' => $optionChildren['optgroup_label'],
                            'children' => array_map(function ($childId, $childText) {
                                return [
                                    'id' => $childId,
                                    'text' => $childText
                                ];
                            }, array_keys($optionChildren['optgroup_options']), $optionChildren['optgroup_options'])
                        ];
                    }, array_keys($options), $options);
                } else {
                    wp_send_json_error([
                        'error' => esc_html__(
                            'No exam modules found. Please make sure you have created exams in bizExaminer.
                            Also make sure your API credentials are correct - you can test them at the options screen.',
                            'bizexaminer-learndash-extension'
                        )
                    ], 400);
                }
            } catch (\Exception $exception) {
                wp_send_json_error([
                    'error' => esc_html__(
                        'Error retrieving exam modules. Please make sure your API credentials are correct -
                        you can test them at the options screen.',
                        'bizexaminer-learndash-extension'
                    )
                ], 500);
            }
        } elseif ($field === 'bizExaminerRemoteProctoring') {
            try {
                $options = $this->getRemoteProctorsOptions($apiCredentials);
                if ($options) {
                    $result['items'] = array_map(function ($optionId, $option) {
                        return [
                            'id' => $optionId,
                            'text' => $option,
                        ];
                    }, array_keys($options), $options);
                } else {
                    wp_send_json_error([
                        'error' => esc_html__(
                            'No remote proctors found. Please make sure you have configured remote proctor accounts.
                            Also make sure your API credentials are correct - you can test them at the options screen.',
                            'bizexaminer-learndash-extension'
                        )
                    ], 400);
                }
            } catch (\Exception $exception) {
                wp_send_json_error([
                    'error' => esc_html__(
                        'Error retrieving remote proctors. Please make sure your API credentials are correct -
                        you can test them at the options screen.',
                        'bizexaminer-learndash-extension'
                    )
                ], 500);
            }
        }

        if (!empty($data['search'])) {
            $search = $data['search'];
            $filteredItems = [];
            foreach ($result['items'] as $i => $item) {
                $hasChildMatch = false;
                $filteredChildren = [];

                if (str_contains(strtolower($item['text']), $search)) {
                    $filteredItems[] = $item;
                    continue; // show all children if the parent item matches
                }

                if (!empty($item['children'])) {
                    foreach ($item['children'] as $j => $child) {
                        if (str_contains(strtolower($child['text']), $search)) {
                            $hasChildMatch = true;
                            $filteredChildren[] = $child;
                        }
                    }
                }

                if ($hasChildMatch) {
                    $item['children'] = $filteredChildren;
                    $filteredItems[] = $item;
                }
            }

            $result['items'] = $filteredItems;
        }

        $result['total_items'] = count($result['items']);

        return $result;
    }

    /**
     * Get the available exam modules and parse them into a format for select-options
     *
     * @param string $apiCredentialsId
     * @return array
     *          $examModuleId => [
     *              'optgroup_label' => (string) name
     *              'optgroup_options' => [
     *                  $fullId (string, productId + examModuleId + contentRevisionid) => (string) name
     *              ]
     *          ]
     */
    protected function getExamModulesOptions($apiCredentialsId): array
    {
        $examOptions = [];

        $credentials = $this->getApiService()->getApiCredentialsById($apiCredentialsId);

        if (!$credentials) {
            return $examOptions;
        }

        $examModules = $this->examModulesService->getExamModules($credentials);

        if (empty($examModules)) {
            return [];
        }

        foreach ($examModules as $id => $examModule) {
            $option = [
                'optgroup_label' => esc_html($examModule['name']),
                'optgroup_options' => []
            ];
            foreach ($examModule['modules'] as $moduleId => $module) {
                // includes product id = exam id, product part id = exam module id AND content revision id
                $fullId = $module['fullId'];
                $option['optgroup_options'][$fullId] = esc_html($module['name']);
            }
            $examOptions[$id] = $option;
        }

        return $examOptions;
    }

    /**
     * Get the available remote proctors and parse them into a format for select-options
     *
     * @param string $apiCredentialsId
     * @return array
     *          $remoteProctorName => (string) name
     */
    protected function getRemoteProctorsOptions($apiCredentialsId): array
    {
        $remoteProctorOptions = [
            '-1' => esc_html__('No remote proctoring.', 'bizexaminer-learndash-extension'),
        ];

        $credentials = $this->getApiService()->getApiCredentialsById($apiCredentialsId);

        if (!$credentials) {
            return $remoteProctorOptions;
        }

        $remoteProctors = $this->remoteProctorsService->getRemoteProctors($credentials);

        if (empty($remoteProctors)) {
            return [];
        }

        foreach ($remoteProctors as $proctor) {
            $id = $proctor['name'];
            $name = $proctor['name'];
            $description = $proctor['description'];
            $proctorType = $this->remoteProctorsService->mapProctorTypeLabel($proctor['type']);
            /**
             * put proctor type in value so it can be read out in JS for conditionally showin/hiding settings
             * has to be a character which is allowed in class names, because LearnDash uses values for some classes
             * and JS triggers (eg `::` would trigger jquery errors)
             */
            $value = trim("{$proctor['type']}_-_{$id}");
            $remoteProctorOptions[$value] = "{$proctorType}: {$name} ({$description})";
        }

        return $remoteProctorOptions;
    }

    /**
     * Adds a settings error to WordPress for a specific field
     *
     * @param string $field
     * @param string $error
     * @return void
     */
    public function addSettingsError(string $field, string $error): void
    {
        add_settings_error(
            'learndash-quiz-bizexaminer-settings',
            $field,
            esc_html($error),
            'error'
        );
    }
}
