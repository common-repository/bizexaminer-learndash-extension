<?php

namespace BizExaminer\LearnDashExtension\LearnDash\Quiz;

use BizExaminer\LearnDashExtension\Helper\Util;
use BizExaminer\LearnDashExtension\Internal\Interfaces\ErrorServiceAwareInterface;
use BizExaminer\LearnDashExtension\Internal\Interfaces\EventManagerAwareInterface;
use BizExaminer\LearnDashExtension\Internal\Interfaces\TemplateAwareInterface;
use BizExaminer\LearnDashExtension\Internal\Traits\ErrorServiceAwareTrait;
use BizExaminer\LearnDashExtension\Internal\Traits\EventManagerAwareTrait;
use BizExaminer\LearnDashExtension\Internal\Traits\TemplateAwareTrait;
use BizExaminer\LearnDashExtension\LearnDash\CertificatesService;
use BizExaminer\LearnDashExtension\LearnDash\Quiz\CallbackApi\CallbackApiService;
use BizExaminer\LearnDashExtension\LearnDash\Quiz\Helper\PrerequisitesMessagesFilter;
use BizExaminer\LearnDashExtension\LearnDash\Quiz\Helper\ResultMessagesFilter;
use BizExaminer\LearnDashExtension\LearnDash\Quiz\QuizSettings\QuizSettingsService;
use WpProQuiz_Model_CategoryMapper;
use WpProQuiz_Model_Quiz;
use WpProQuiz_Model_QuizMapper;
use WpProQuiz_View_FrontQuiz;

/**
 * Class to show quiz on the frontend
 */
class QuizFrontend implements TemplateAwareInterface, EventManagerAwareInterface, ErrorServiceAwareInterface
{
    use TemplateAwareTrait;
    use EventManagerAwareTrait;
    use ErrorServiceAwareTrait;

    /**
     * QuizService instance to use
     *
     * @var QuizService
     */
    protected QuizService $quizService;

    /**
     * QuizSettingsService instance to use
     *
     * @var QuizSettingsService
     */
    protected QuizSettingsService $quizSettingsService;

    /**
     * QuizAttemptsDataStore instance to use
     *
     * @var QuizAttemptsDataStore
     */
    protected QuizAttemptsDataStore $quizAttempts;

    /**
     * CallbackApiService instance to use
     *
     * @var CallbackApiService
     */
    protected CallbackApiService $callbackApiService;

    /**
     * QuizAccess instance to use
     *
     * @var QuizAccess
     */
    protected QuizAccess $quizAccess;

    /**
     * CertificatesService instance to use
     *
     * @var CertificatesService
     */
    protected CertificatesService $certificatesService;

    /**
     * Current LearnDash quiz frontend class
     *
     * @var WpProQuiz_View_FrontQuiz
     */
    private ?WpProQuiz_View_FrontQuiz $ldFrontend;


    /**
     * LearnDash Quiz mapper class to get pro quiz
     *
     * @var WpProQuiz_Model_QuizMapper
     */
    protected WpProQuiz_Model_QuizMapper $ldQuizMapper;

    /**
     * Additional template args that should be passed to overwritten LearnDash templates
     * Will be merged with LearnDash template args
     *
     * @var array
     */
    protected array $templateArgs;

    /**
     * Alerts/errors that should be rendered
     *
     * @uses learndash_get_template_part('modules/alert.php', $alertData)
     *
     * @var array
     *      [
     *          'type' => 'warning'
     *          'icon' => 'alert',
     *          'message' => (string) (will be escaped on output)
     *      ]
     */
    protected array $alerts;

    /**
     * In which state the form will be rendered
     * error, start, pending, results,...
     * Used for CSS class
     *
     * @var string
     */
    protected string $state;

    /**
     * Enqueued script/style handle to which to add inline JS/CSS
     *
     * @var string
     */
    protected string $assetHandle;

    /**
     * Creates a new Quiz Service Instance
     *
     * @param QuizService $quizService QuizService instance to use
     * @param QuizSettingsService $quizSettingsService QuizSettingsService instance to use
     * @param QuizAttemptsDataStore $quizAttempts QuizAttemptsDataStore instance to use
     * @param CallbackApiService $callbackApiService CallbackApiService instance to use
     * @param QuizAccess $quizAccess QuizAccess instance to use
     * @param CertificatesService $certificatesService CertificatesService instance to use
     */
    public function __construct(
        QuizService $quizService,
        QuizSettingsService $quizSettingsService,
        QuizAttemptsDataStore $quizAttempts,
        CallbackApiService $callbackApiService,
        QuizAccess $quizAccess,
        CertificatesService $certificatesService
    ) {
        $this->quizService = $quizService;
        $this->quizSettingsService = $quizSettingsService;
        $this->quizAttempts = $quizAttempts;
        $this->callbackApiService = $callbackApiService;
        $this->quizAccess = $quizAccess;
        $this->certificatesService = $certificatesService;

        // always create a new instance, treate it like a data object not like a service
        $this->ldFrontend = null; // set on each render call
        $this->ldQuizMapper = new WpProQuiz_Model_QuizMapper();

        $this->templateArgs = [];
        $this->alerts = [];
    }

    /**
     * Render a quiz depending on the state
     * Will use LearnDash templates where possible,
     *
     * @param int $quizId The quiz' post ID (NOT quiz pro id)
     * @param string|null $quizAttemptId to use to show results
     * @param bool $shouldShowResults
     * @return string|null The rendered output or null if bizExaminer is not enabled for this quiz
     */
    public function renderQuiz(int $quizId, ?string $quizAttemptId = null, bool $shouldShowResults = false): ?string
    {
        // getQuizSettings returns false if bizExaminer is not enabled for this quiz
        $quizSettings = $this->quizSettingsService->getQuizSettings($quizId);
        if (!$quizSettings) {
            return null;
        }

        // Check if starting is disabled, because import attempts is enabled
        if ($quizSettings['importExternalAttempts'] && $quizSettings['importExternalAttemptsDisableStart']) {
            return '';
        }

        Util::setNoCache();

        // reset & init state
        $this->state = '';

        $userId = wp_get_current_user()->ID;
        $quizAttempt = $quizAttemptId ? $this->quizAttempts->getQuizAttempt($userId, $quizId, $quizAttemptId) : false;
        $quizProid = get_post_meta($quizId, 'quiz_pro_id', true);

        $proQuiz = $this->ldQuizMapper->fetch($quizProid);

        $this->ldFrontend = new WpProQuiz_View_FrontQuiz();
        // use WpProQuiz_View_FrontQuiz to get data in same way as LD does for frontend rendering
        $course = learndash_get_setting($quizId, 'course');
        $course = absint($course);
        $lesson = learndash_course_get_single_parent_step($course, $quizId, learndash_get_post_type_slug('lesson'));
        $topic = learndash_course_get_single_parent_step($course, $quizId, learndash_get_post_type_slug('topic'));

        $shortcodeAtts = [
            'quiz_id' => $quizId,
            'course_id' => 0,
            'lesson_id'   => $lesson,
            'topic_id'    => $topic,
            'quiz_pro_id' => $quizProid,
        ];
        $categoryMapper = new WpProQuiz_Model_CategoryMapper();


        $this->ldFrontend->set_shortcode_atts($shortcodeAtts);
        $this->ldFrontend->quiz = $proQuiz;
        $this->ldFrontend->question = [];
        /**
         * Will trigger a PHP notice in WpProQuiz_Model_CategoryMapper::fetchByQuiz (#65/#36)
         * Because the quiz does not contain any questions
         * no way to fix atm, not sure if ldFrontend->category is even required,
         * it's used for parity with LearnDashs frontend rendering
         *
         * surpress errors via @
         */
        $this->ldFrontend->category = @$categoryMapper->fetchByQuiz($proQuiz);

        // enqueue an empty script to which inline scripts can be added
        // TODO: ideally this would be loaded after the inline scripts of LearnDash
        $this->assetHandle = 'bizexaminer-quiz-' . $quizProid;
        wp_register_script($this->assetHandle, '', ['jquery', 'learndash-front'], (string)time(), true);
        wp_enqueue_script($this->assetHandle);
        // enqueue an empty style to which inline styles can be added
        // @phpstan-ignore-next-line (Wrong doc in phpstan-wordpress)
        wp_register_style($this->assetHandle, null, [], time());
        wp_enqueue_style($this->assetHandle);

        ob_start();

        /**
         * Allows doing something something before the quiz is rendered
         * Use Hooks in TemplateService to filter/change output
         *
         * @param int $quizId The quiz' post ID (NOT quiz pro id)
         * @param int $userId
         * @param QuizAttempt $quizAttempt
         */
        $this->eventManager->do_action(
            'bizexaminer/quiz/render/before',
            $quizId,
            $userId,
            $quizAttempt
        );

        $this->prepareRender($quizId, $userId, $quizAttemptId, $shouldShowResults, $proQuiz);

        /**
         * add filter to maybe overwrite certain templates
         * priority 50 so it's after most themes, but can still be overwritten
         */
        add_filter('learndash_template', [$this, 'overwriteTemplates'], 50, 2);

        // Let the LearnDash frontend render
        $this->ldFrontend->show();
        // add our custom styles to show/hide some quiz parts/states
        $this->printStyles();

        /**
         * Allows doing something something before the quiz is rendered
         * Use Hooks in TemplateService to filter/change output
         *
         * @param int $quizId The quiz' post ID (NOT quiz pro id)
         * @param int $quizProId The quiz pro id
         * @param int $userId
         * @param QuizAttempt $quizAttempt
         */
        $this->eventManager->do_action(
            'bizexaminer/quiz/render/after',
            $quizId,
            $proQuiz->getId(),
            $userId,
            $quizAttempt
        );
        $output = ob_get_clean();

        // add class for targeting state
        $output = str_replace(
            '<div class="wpProQuiz_content',
            '<div class="wpProQuiz_content bizexaminer bizexaminer--' . esc_attr($this->state),
            $output
        );
        return $output;
    }

    /**
     * Helper function for renderQuiz so conditionals can return early and don't need to fetch unnecessary data
     *
     * @see renderQuiz
     *
     * @param int $quizId
     * @param int $userId
     * @param null|string $quizAttemptId
     * @param bool $shouldShowResults
     * @param WpProQuiz_Model_Quiz $proQuiz
     */
    private function prepareRender(
        int $quizId,
        int $userId,
        ?string $quizAttemptId,
        bool $shouldShowResults,
        WpProQuiz_Model_Quiz $proQuiz
    ) {
        /**
         * 0. Check for errors that happened during starting the quiz
         * and render them here
         *
         * if the error is because the user is not allowed to access the quiz, let the methods below handle them instead
         */
        if (
            ($this->errorService->hasErrors('start-exam')
                || $this->errorService->hasErrors('end-exam')
                || $this->errorService->hasErrors('import-attempt'))
            &&
            !$this->errorService->hasErrorCode('bizexaminer-quiz-start-not-allowed', 'start-exam')
        ) {
            $this->handleErrors();
            return;
        }

        /**
         * 1. Render not-logged-in message is user is not logged in
         *
         * Force user logged in, even if quiz does not have setting enabled
         * bizExaminer only works with known users (not anonymous/not-logged-in users)
         * because of how data is stored and data about the user the bizExaminer API requires (name etc)
         *
         * LearnDash will render no start button in this case
         */
        if (empty($userId)) {
            $this->prepareNotLoggedIn();
            return;
        }

        /**
         * 2. Maybe render results, if available
         * TODO: check if quizAttemptId exists and belongs to the current user
         */
        if ($shouldShowResults) {
            // If no quiz attempt, get the latest completed.
            if ($quizAttemptId) {
                $this->prepareResults($quizId, $userId, $quizAttemptId);
                return;
            } else {
                $completedQuizAttempt = $this->quizAttempts->getQuizAttemptWithStatus(
                    $quizId,
                    $userId,
                    QuizAttempt::STATUS_COMPLETED
                );
                if ($completedQuizAttempt) {
                    $this->prepareResults($quizId, $userId, $completedQuizAttempt->getId());
                    return;
                }
            }
        }

        /**
         * 3. Render pending results message, if there's a pending-results quiz attempt
         */
        $pendingQuizAttempt = $this->quizAttempts->getQuizAttemptWithStatus(
            $quizId,
            $userId,
            QuizAttempt::STATUS_PENDING_RESULTS
        );
        if ($pendingQuizAttempt) {
            $this->preparePendingResults();
            return;
        }

        /**
         * 4. Render resume button if there's a running quiz attempt
         */
        $runningQuizAttempt = $this->quizAttempts->getQuizAttemptWithStatus(
            $quizId,
            $userId,
            QuizAttempt::STATUS_STARTED,
            false // get the latest = newest to compare valid until correctly
        );
        if ($runningQuizAttempt && (int)$runningQuizAttempt->get('be_valid_until') > time()) {
            $runningQuizAttemptUrl = $this->quizService->getQuizExamAccessUrl($runningQuizAttempt);
            if (is_string($runningQuizAttemptUrl)) {
                $this->prepareResume($runningQuizAttempt, $runningQuizAttemptUrl);
                return;
            }
        }

        /**
         * 5. Render missing prerequisites
         * max. retakes is handled by LearnDash (@see learndash/themes/legacy/templates/quiz.php $attempts_left)
         */
        $missingPrerequisites = $this->quizAccess->getMissingQuizPrerequisites($proQuiz->getId(), $userId);
        if ($missingPrerequisites) {
            $this->prepareMissingPrerequisites($missingPrerequisites);
            return;
        }

        /**
         * 6. Autostart quiz if enabled
         */
        if ($proQuiz->isAutostart()) {
            $booking = $this->quizService->startQuiz($quizId, $userId);
            if (!is_wp_error($booking)) {
                // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- url is from bizExaminer instance
                wp_redirect(
                    $booking,
                    302
                );
                exit;
            } else {
                $this->handleErrors();
                return;
            }
        }

        /**
         * 7. (default) Prepare quiz start rendering
         * this will only be called if other cases do not match,
         * therefore the button has no URL if another case match
         * and it won't be possible for user to start
         * additionally to hiding button (that's how LearnDash does it)
         */
        $this->prepareStart($quizId, $userId);
    }

    /**
     * Filters file path for the learndash template being called.
     *
     *
     * @param string  $filepath         Template file path.
     * @param string  $name             Template name.
     * @return string $filepath
     */
    public function overwriteTemplates(string $filepath, string $name): string
    {
        if ($name === 'quiz/partials/show_quiz_start_box.php') {
            $filepath = $this->templateService->locateTemplate('learndash-template');
            add_filter('ld_template_args_' . $name, [$this, 'overwriteTemplateArgs'], 11);
        }

        return $filepath;
    }

    /**
     * Overwrites LearnDash template args passed to an overwritten template
     *
     * @param array $args
     * @return array
     */
    public function overwriteTemplateArgs(array $args): array
    {
        $this->templateArgs['be-alerts'] = $this->alerts;
        if (!empty($this->templateArgs)) {
            $args = wp_parse_args($this->templateArgs, $args);
        }
        return $args;
    }

    /**
     * Gets the bizExaminer certificate download link from the latest(!) finished quiz attempt
     * Because LearnDash does not get a certificate for a specific quiz attempt
     * but just for a quiz and user combination, therefore use the latest attempt
     *
     * @param int $quizId
     * @param int $userId
     * @return string|false URL if bizExaminer certificates are enabled, a quiz attempt was found and link was saved,
     *  false otherwise
     */
    public function getBizExaminerCertificateLink(int $quizId, int $userId)
    {
        // getQuizSettings returns false if bizExaminer is not enabled for this quiz
        $quizSettings = $this->quizSettingsService->getQuizSettings($quizId);
        if (!$quizSettings || !$quizSettings['useBeCertificate']) {
            return false;
        }

        $finishedQuizAttempt = $this->quizAttempts->getQuizAttemptWithStatus(
            $quizId,
            $userId,
            QuizAttempt::STATUS_COMPLETED,
            false
        );

        if (!$finishedQuizAttempt) {
            return false;
        }

        $certificateLink = $finishedQuizAttempt->get('be_certificate');
        if (!empty($certificateLink)) {
            return $certificateLink;
        }

        return false;
    }

    /**
     * Handle any errors added before rendering (eg failed starting) or during rendering
     * and show them as alerts
     *
     * Alerts will always be shown by the show_quiz_start_box.php template
     * the template will always be rendered and alerts are output on a custom div which is always visible
     *
     * @return void
     */
    protected function handleErrors()
    {
        $startErrors = $this->errorService->getErrors('start-exam');
        $endErrors = $this->errorService->getErrors('end-exam');
        $importErrors = $this->errorService->getErrors('import-attempt');

        $error = null;

        if (!empty($startErrors)) {
            $error = $startErrors[array_key_first($startErrors)];
        } elseif (!empty($endErrors)) {
            $error = $endErrors[array_key_first($endErrors)];
        } elseif (!empty($importErrors)) {
            $error = $importErrors[array_key_first($importErrors)];
        }

        $message = __(
            'Something went wrong. Please try again or contact us.',
            'bizexaminer-learndash-extension'
        );
        if (!empty($error)) {
            $message .= sprintf(
                ' <span class="bizexaminer-quiz__error-details">%1$s %2$s</span>',
                esc_html__('Error details:', 'bizexaminer-learndash-extension'),
                wp_kses_post($error->get_error_message())
            );

            if ($error->get_error_code() === 'bizexaminer-import-attempt-error-no-results') {
                $message = __('No results to import.', 'bizexaminer-learndash-extension');
            }
        }

        $this->alerts[] = [
            'type'    => 'warning',
            'icon'    => 'alert',
            'message' => $message
        ];

        $this->state = 'error';
    }

    /**
     * Sets up LearnDash template for rendering the "not logged in" message
     *
     * @return void
     */
    protected function prepareNotLoggedIn(): void
    {
        /**
         * show elements which LearnDash normally hides (inline style)
         * and shows via JavaScript on quiz loading/starting/ending
         */

        $this->state = 'not-logged-in';
    }

    /**
     * Sets up LearnDash Template for rendering the start button
     *
     * @param int $quizId The quiz' post ID (NOT quiz pro id)
     * @param int $userId
     * @return void
     */
    protected function prepareStart(int $quizId, int $userId): void
    {
        $this->templateArgs = [
            'button' => [
                // use an expiring nonce here, since the click should haben soon after rendering
                'link' => $this->callbackApiService->buildCallbackApiUrl(
                    'start-exam',
                    [
                        'be-quizId' => $quizId,
                        'be-userId' => $userId
                    ],
                    null,
                    true
                ),
            ]
        ];

        $this->state = 'start';
    }

    /**
     * Sets up LearnDash Template for rendering the resume button
     *
     * @param QuizAttempt $runningQuizAttempt
     * @param string $examAccessUrl
     * @return void
     */
    protected function prepareResume(QuizAttempt $runningQuizAttempt, string $examAccessUrl): void
    {
        $this->templateArgs = [
            'button' => [
                // use an expiring nonce here, since the click should haben soon after rendering
                'link' => $examAccessUrl,
            ],
            'resume' => true
        ];
        $this->state = 'resume';
    }

    /**
     * Sets up LearnDash Template for rendering results
     * By fetching results if required and adding a hook to show/hide the results
     *
     * @param int $quizId The quiz' post ID (NOT quiz pro id)
     * @param int $userId
     * @param string|null $quizAttemptId to use to show results
     * @return void
     */
    protected function prepareResults(int $quizId, int $userId, ?string $quizAttemptId): void
    {
        $results = null;
        $quizAttempt = $this->quizAttempts->getQuizAttempt($userId, $quizId, $quizAttemptId);
        if ($quizAttempt) {
            if (!$quizAttempt->hasResults()) {
                $resultsFetched = $this->quizService->updateQuizResults($quizId, $userId, $quizAttemptId);
                if (!is_wp_error($resultsFetched)) { // true means it has results, false means it's stil pending
                    // refresh quiz attempt instance with fresh data (maybe including results)
                    $quizAttempt = $this->quizAttempts->getQuizAttempt($userId, $quizId, $quizAttemptId);
                }
            }

            // check again after maybe updating data
            if ($quizAttempt->hasResults()) {
                $results = $quizAttempt->getResults();
            }
        }

        // if no results available, return false
        if (!$results || !$quizAttempt) {
            $this->preparePendingResults();
            return;
        }

        $proQuiz = $this->ldFrontend->quiz;
        // hide 'view questions' button, because can't show questions/results per question
        $proQuiz->setBtnViewQuestionHidden(true);
        // hide top list - not supported
        $proQuiz->setToplistActivated(false);

        $messageFilter = new ResultMessagesFilter($results);
        $messageFilter->addFilter();

        $this->state = 'results';
        if (!empty($results['certificate'])) {
            $this->state = 'results-certificate';
        }

        $proQuizId = $proQuiz->getId();
        if ($proQuiz->isResultGradeEnabled()) {
            $resultTexts = $proQuiz->getResultText();
            $resultsTextIndex = null;

            // Find the result text
            // Where the attempt result in percentage is larger
            // than the minimum required percentage for this result text
            // And the diff is the smallest
            if ($resultTexts && isset($resultTexts['prozent'])) {
                // Similar to wpProQuiz_front.js::findResultIndex but PHP based
                $diff = PHP_INT_MAX;
                foreach ($resultTexts['prozent'] as $i => $prozent) {
                    if ($results['percentage'] >= $prozent && ($results['percentage'] - $prozent) < $diff) {
                        $diff = $results['percentage'] - $prozent;
                        $resultsTextIndex = $i;
                    }
                }
            }

            if ($resultsTextIndex !== null) {
                // +1 because css nth-child starts with 1
                $resultsTextIndex += 1;
                wp_add_inline_style($this->assetHandle, '
                #wpProQuiz_' . absint($proQuizId) . '.bizexaminer--results .wpProQuiz_resultsList
                    > li:nth-child(' . absint($resultsTextIndex) . '),
                #wpProQuiz_' . absint($proQuizId) . '.bizexaminer--results-certificate .wpProQuiz_resultsList
                    > li:nth-child(' . absint($resultsTextIndex) . ') {
                    display: block !important;
                }
            ');
            }
        }

        /**
         * On clicking "restart" redirect to quiz without query parameters that trigger showing results again
         * First remove all event listeners because LearnDash triggers just a window.reload on click
         * (which will load with the same $_GET params again)
         */
        $restartUrl = home_url(remove_query_arg(['be-quizAttempt', 'be-showResults']));
        wp_add_inline_script($this->assetHandle, '
            jQuery(function($){
                $("input.wpProQuiz_button_restartQuiz").off("click").on("click", function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                    window.location.href = "' . esc_js($restartUrl) . '";
                });
            });
        ', 'after');
    }

    /**
     * Sets up LearnDash Template for rendering an info about pending results
     *
     * @return void
     */
    protected function preparePendingResults(): void
    {
        $this->alerts[] = [
            'type'    => 'warning',
            'icon'    => 'alert',
            'message' => __(
                'You have not finished the exam yet or your results are still being manually reviewed.
                        You will find the results in your profile, once finished.',
                'bizexaminer-learndash-extension'
            ),
        ];
        $this->state = 'pending-results';
    }

    /**
     * Maybe renders LearnDash box about missing prerequisites/too much retakes, etc
     *
     * @param array $missingPrerequisites
     * @return void
     */
    protected function prepareMissingPrerequisites(array $missingPrerequisites): void
    {
        $messageFilter = new PrerequisitesMessagesFilter($missingPrerequisites);
        $messageFilter->addFilter();

        $this->state = 'missing-prerequisites';
    }

    /**
     * Renders custom styles based on the state of the quiz
     *
     * LearnDash renders all states elements, but hides them via inline styles
     *  and shows them via JavaScript on loading/starting/completing the quiz
     *
     * But bizExaminer knows the state of the quiz on the server not on the client side
     * therefore use CSS rules to show the correct states box
     *
     * @return void
     */
    protected function printStyles(): void
    {
        // instead of using styles, JS could also be used, but server-side css is fallback-safer
        $proQuiz = $this->ldFrontend->quiz;
        $proQuizId = $proQuiz->getId();

        wp_add_inline_style($this->assetHandle, '
            .bizexaminer-quiz__error-details {
                font-size: smaller;
                display: block
            }

            /**
            * show elements which LearnDash normally hides (inline style)
            * and shows via JavaScript on quiz loading/starting/ending
            */
            #wpProQuiz_' . absint($proQuizId) . '.bizexaminer--not-logged-in .wpProQuiz_startOnlyRegisteredUser {
                display: block !important;
            }

            #wpProQuiz_' . absint($proQuizId) . '.bizexaminer--not-logged-in .wpProQuiz_text {
                display: none !important;
                /* start button */
            }

            #wpProQuiz_' . absint($proQuizId) . '.bizexaminer--results .wpProQuiz_results,
            #wpProQuiz_' . absint($proQuizId) . '.bizexaminer--results-certificate .wpProQuiz_results {
                display: block !important;
            }

            #wpProQuiz_' . absint($proQuizId) . '.bizexaminer--results .wpProQuiz_text,
            #wpProQuiz_' . absint($proQuizId) . '.bizexaminer--results-certificate .wpProQuiz_text {
                display: none !important;
                /* start button */
            }

            #wpProQuiz_' . absint($proQuizId) . '.bizexaminer--results-certificate .wpProQuiz_certificate {
                display: block !important;
            }

            #wpProQuiz_' . absint($proQuizId) . '.bizexaminer--missing-prerequisites .wpProQuiz_prerequisite {
                display: block !important;
            }
        ');
    }
}
