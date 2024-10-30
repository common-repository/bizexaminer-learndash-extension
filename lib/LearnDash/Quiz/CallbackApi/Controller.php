<?php

namespace BizExaminer\LearnDashExtension\LearnDash\Quiz\CallbackApi;

use BizExaminer\LearnDashExtension\Core\LogService;
use BizExaminer\LearnDashExtension\Helper\Util;
use BizExaminer\LearnDashExtension\Internal\Interfaces\ErrorServiceAwareInterface;
use BizExaminer\LearnDashExtension\Internal\Interfaces\EventManagerAwareInterface;
use BizExaminer\LearnDashExtension\Internal\Interfaces\LogServiceAwareInterface;
use BizExaminer\LearnDashExtension\Internal\Traits\ErrorServiceAwareTrait;
use BizExaminer\LearnDashExtension\Internal\Traits\EventManagerAwareTrait;
use BizExaminer\LearnDashExtension\Internal\Traits\LogServiceAwareTrait;
use BizExaminer\LearnDashExtension\LearnDash\Quiz\QuizAttempt;
use BizExaminer\LearnDashExtension\LearnDash\Quiz\QuizAttemptsDataStore;
use BizExaminer\LearnDashExtension\LearnDash\Quiz\QuizService;
use WP_Error;

/**
 * HTTP-Controller for handling callback API request
 * See README.md#Flow for details about the flow of quiz results
 */
class Controller implements LogServiceAwareInterface, EventManagerAwareInterface, ErrorServiceAwareInterface
{
    use LogServiceAwareTrait;
    use EventManagerAwareTrait;
    use ErrorServiceAwareTrait;

    /**
     * QuizService instance to use
     *
     * @var QuizService
     */
    protected QuizService $quizService;

    /**
     * QuizAttemptsDataStore to use
     *
     * @var QuizAttemptsDataStore
     */
    protected QuizAttemptsDataStore $quizAttempts;

    /**
     * Creates a new Controller instance
     *
     * @param QuizService $quizService QuizService instance to use
     * @param QuizAttemptsDataStore $quizAttempts QuizAttemptsDataStore to use
     */
    public function __construct(QuizService $quizService, QuizAttemptsDataStore $quizAttempts)
    {
        $this->quizService = $quizService;
        $this->quizAttempts = $quizAttempts;
    }

    /**
     * Start the exam and redirect the user to the exam
     * Requires a nonce
     *
     * @param array $request Request variables (eg from $_GET)
     * @return void Redirects the user to the exam on success
     *         shows nonce expired screen (and dies) if nonce validation fails
     *         returns silently on any errors regarding start-exam (so QuizFrontend::renderQuiz can show a notice)
     *         dies on any other errors
     */
    public function startExam(array $request): void
    {
        $params = $this->parseRequestVariables(
            $request,
            [
                'quizId' => [
                    'default' => false,
                    'type' => 'int',
                    'key' => 'be-quizId'
                ],
                'userId' => [
                    'default' => wp_get_current_user()->ID,
                    'type' => 'int',
                    'key' => 'be-userId'
                ],
                'nonce' => [
                    'default' => null,
                    'type' => 'string',
                    'key' => '_benonce'
                ]
            ]
        );

        $this->logService->logData('[Callback API] Start Exam', $params, LogService::LEVEL_DEBUG);

        /**
         * nonce lifetime: default (=short)
         * url is generated when viewing quiz - start is allowed instantly after
         */
        if (empty($params['nonce']) || !wp_verify_nonce($params['nonce'], 'be-start-exam')) {
            $this->die(__('The link you followed has expired.'));
            exit; // die dies, but exit for clarity
        }

        /**
         * Allows doing something / outputting something before the controller handles the action
         *
         * @param array $params The parsed params for this controller action
         * @param array $request   The complete request vars (merged from $_REQUEST and passed queryVars)
         */
        $this->eventManager->do_action(
            'bizexaminer/callbackapi/startExam',
            $params,
            $request
        );

        if (!empty($params['quizId']) && !empty($params['userId'])) {
            // Will also check if quiz exists and has bizExaminer enabled.
            $examUrl = $this->quizService->startQuiz($params['quizId'], $params['userId']);
            if (is_wp_error($examUrl)) {
                // let QuizFrontend::renderQuiz handle displaying errors
                if ($this->errorService->hasErrors('start-exam')) {
                    return;
                }
                // An error that's not belonging to start-exam has occurred, die
                $this->die();
                exit; // die dies, but exit for clarity
            }
            // SUCCESS - redirect to exam
            // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- url is from bizExaminer instance
            wp_redirect(
                $examUrl,
                302
            );
            exit;
        }
    }

    /**
     * Callback for booking events (webhooks) from API
     *
     * Does not require a nonce (because nonce lifetime is too short) - but requires a secret key
     *
     * API Docs:
     *  The receiver MUST respond with a HTTP 2XX response code,
     *  except in cases where an actual error occurred.
     *
     *  If the receiving system cannot handle a booking event,
     *  it MUST NOT respond with an HTTP error status (since 2021-08-24).
     *
     * @link https://docs.google.com/document/d/1UvvQJOhBZ6d2SpPSz5U5SL6dULwgdW1dnVSXwrDaODQ/#heading=h.gg3nsor9iiba
     *
     * @param array $request
     * @return void dies on errors, sets HTTP error status according to API docs
     */
    public function eventCallback(array $request): void
    {
        $params = $this->parseRequestVariables(
            $request,
            [
                'quizId' => [
                    'default' => false,
                    'type' => 'int',
                    'key' => 'be-quizId'
                ],
                'userId' => [
                    'default' => wp_get_current_user()->ID,
                    'type' => 'int',
                    'key' => 'be-userId'
                ],
                'quizAttempt' => [
                    'default' => null,
                    'type' => 'string',
                    'key' => 'be-quizAttempt'
                ],
                'key' => [
                    'default' => null,
                    'type' => 'string',
                    'key' => 'be-key'
                ],
                'participant' => [
                    'default' => null,
                    'type' => 'string',
                    'key' => 'participantsID'
                ],
                /**
                 * exam_finished, exam_evaluated
                 */
                'eventType' => [
                    'default' => null,
                    'type' => 'string'
                ],
            ]
        );

        $this->logService->logData('[Callback API] Event Callback', $params, LogService::LEVEL_DEBUG);

        if (
            empty($params['quizId']) || empty($params['userId']) ||
            empty($params['quizAttempt']) || empty($params['eventType'])
        ) {
            $this->handleEventCallbackError(new \WP_Error(
                'bizexaminer-callback-apimissing-arguments',
                __('Arguments for eventCallback are missing', 'bizexaminer-learndash-extension'),
                ['params' => $params, 'code' => 400]
            ));
            exit;
        }

        $quizAttempt = $this->quizAttempts->getQuizAttempt(
            $params['userId'],
            $params['quizId'],
            $params['quizAttempt']
        );

        if (!$quizAttempt) {
            $this->handleEventCallbackError(new \WP_Error(
                'bizexaminer-callback-api-no-quiz-attempt-found',
                sprintf(
                    /* translators: %1$s quiz label, %2$s quiz attempt id from request */
                    __('No %1$s attempt found for id %2$s', 'bizexaminer-learndash-extension'),
                    learndash_get_custom_label_lower('quiz'),
                    $params['quizAttempt']
                ),
                ['params' => $params, 'code' => 404]
            ));
            exit;
        }

        /**
         * Security key, to prevent unauthorized access
         * instead of nonce, because it needs to have a long lifetime
         */
        if (empty($params['key']) || !$quizAttempt->isKeyValid($params['key'])) {
            $this->handleEventCallbackError(new \WP_Error(
                'bizexaminer-callback-api-invalid-quiz-attempt-key',
                sprintf(
                    /* translators: placeholder: quiz label */
                    __("Invalid key for %s attempt.", 'bizexaminer-learndash-extension'),
                    learndash_get_custom_label_lower('quiz')
                ),
                ['params' => $params, 'code' => 401]
            ));
            exit;
        }

        $response = null;

        /**
         * Allows doing something / outputting something before the controller handles the action
         *
         * @param array $params The parsed params for this controller action
         * @param array $request   The complete request vars (merged from $_REQUEST and passed queryVars)
         */
        $this->eventManager->do_action(
            'bizexaminer/callbackapi/eventCallback',
            $params,
            $request
        );

        switch ($params['eventType']) {
                /**
             * user finished the exam, does not necessarily mean results are available
             * results are handled by exam_evaluated callback
             */
            case 'exam_finished':
                // successful response by default, to redirect user
                $response = true;
                /**
                 * only set status to pending, if it's still in started status
                 * may have already been set by examReturn
                 */
                if ($quizAttempt->getStatus() === QuizAttempt::STATUS_STARTED) {
                    $response = $this->quizService->endQuiz(
                        $params['quizId'],
                        $params['userId'],
                        $params['quizAttempt']
                    );
                }
                break;

                /**
                 * exam_evaluated = results are available
                 * either directly after finishing or after manual evaluation
                 */
            case 'exam_evaluated':
                /**
                 * fetch results from API again
                 * exam_evaluated only passes passed & result but not timeTaken
                 *  and other data that is required for LearnDash
                 *
                 * do not check if results are already loaded, alway update them when this hook is called
                 */
                $response = $this->quizService->updateQuizResults(
                    $params['quizId'],
                    $params['userId'],
                    $params['quizAttempt']
                );
                break;
            case 'exam_started':
            case 'exam_sent_to_manual_evaluation':
            case 'exam_insight_pdf_available':
            case 'exam_archive_pdf_available':
            default:
                break;
        }

        if ($response !== null && !is_wp_error($response)) {
            $this->die('', 200); // return 200 status header
            exit;
        }

        // return generic 400 error for other actions (not defined/allowed)
        $this->handleEventCallbackError(new \WP_Error(
            'bizexaminer-callback-api-error',
            'Error',
            ['params' => $params, 'code' => 400, 'previous' => $response]
        ));
        exit;
    }

    /**
     * Handle returning from an exam
     * - if status is still "started", set it to pending
     * - redirect user
     *
     * @param array $request
     * @return void Redirects the user to the quiz page to view results on success
     *              returns silently on any errors regarding start-exam (so QuizFrontend::renderQuiz can show a notice)
     */
    public function examReturn(array $request): void
    {
        $params = $this->parseRequestVariables(
            $request,
            [
                'quizId' => [
                    'default' => false,
                    'type' => 'int',
                    'key' => 'be-quizId'
                ],
                'userId' => [
                    'default' => wp_get_current_user()->ID,
                    'type' => 'int',
                    'key' => 'be-userId'
                ],
                'quizAttempt' => [
                    'default' => null,
                    'type' => 'string',
                    'key' => 'be-quizAttempt'
                ],
                'key' => [
                    'default' => null,
                    'type' => 'string',
                    'key' => 'be-key'
                ],
                'participant' => [
                    'default' => null,
                    'type' => 'string',
                    'key' => 'be:participantID'
                ],
            ]
        );

        $this->logService->logData('[Callback API] Return URL', $params, LogService::LEVEL_DEBUG);

        if (empty($params['quizId']) || empty($params['userId']) || empty($params['quizAttempt'])) {
            $this->errorService->addError(new \WP_Error(
                'bizexaminer-missing-arguments',
                __('Arguments for examReturn are missing', 'bizexaminer-learndash-extension'),
                ['params' => $params, 'code' => 400]
            ), 'end-exam');
            // let QuizFrontend::renderQuiz handle displaying errors
            return;
        }

        // TODO: maybe check if quiz exists and has bizExaminer enabled.
        // TODO: maybe enforce login, then redirect to callback api?

        $quizAttempt = $this->quizAttempts->getQuizAttempt(
            $params['userId'],
            $params['quizId'],
            $params['quizAttempt']
        );

        if (!$quizAttempt) {
            $this->errorService->addError(new \WP_Error(
                'bizexaminer-callback-api-no-quiz-attempt-found',
                sprintf(
                    /* translators: %1$s quiz label, %2$s quiz attempt id from request */
                    __('No %1$s attempt found for id %2$s', 'bizexaminer-learndash-extension'),
                    learndash_get_custom_label_lower('quiz'),
                    $params['quizAttempt']
                ),
                ['params' => $params, 'code' => 404]
            ), 'end-exam');
            // let QuizFrontend::renderQuiz handle displaying errors
            return;
        }

        /**
         * Security key, to prevent unauthorized access
         * instead of nonce, because it needs to have a long lifetime
         */
        if (empty($params['key']) || !$quizAttempt->isKeyValid($params['key'])) {
            $this->errorService->addError(new \WP_Error(
                'bizexaminer-invalid-quiz-attempt-key',
                sprintf(
                    /* translators: placeholder: quiz label */
                    __("Invalid key for %s attempt.", 'bizexaminer-learndash-extension'),
                    learndash_get_custom_label_lower('quiz')
                ),
                ['params' => $params, 'code' => 401]
            ), 'end-exam');
            // let QuizFrontend::renderQuiz handle displaying errors
            return;
        }

        // additional "security" check
        if (empty($params['participant']) || $quizAttempt->get('be_participant') !== $params['participant']) {
            $this->errorService->addError(new \WP_Error(
                'bizexaminer-invalid-quiz-attempt-participant',
                sprintf(
                    /* translators: placeholder: quiz label */
                    __("Invalid participant for %s attempt.", 'bizexaminer-learndash-extension'),
                    learndash_get_custom_label_lower('quiz')
                ),
                ['params' => $params, 'code' => 400]
            ), 'end-exam');
            // let QuizFrontend::renderQuiz handle displaying errors
            return;
        }

        /**
         * Allows doing something / outputting something before the controller handles the action
         *
         * @param array $params The parsed params for this controller action
         * @param array $request   The complete request vars (merged from $_REQUEST and passed queryVars)
         */
        $this->eventManager->do_action(
            'bizexaminer/callbackapi/examReturn',
            $params,
            $request
        );

        $response = 200; // default to 200 OK status

        /**
         * only set status to pending, if it's still in started status
         * may have already been set by eventCallback
         */
        if ($quizAttempt->getStatus() === QuizAttempt::STATUS_STARTED) {
            $response = $this->quizService->endQuiz(
                $params['quizId'],
                $params['userId'],
                $params['quizAttempt']
            );
        }

        /**
         * Either if status was not updated $response is default value (200)
         * or the result from the status update
         */
        if (!is_wp_error($response)) {
            $url = get_permalink($params['quizId']);
            $url = add_query_arg([
                'be-quizAttempt' => $params['quizAttempt'],
                'be-showResults' => 1
            ], $url);
            wp_redirect($url, 302);
            exit;
        }

        // generic/other
        $this->errorService->addError($response, 'end-exam');
        // let QuizFrontend::renderQuiz handle displaying errors
        return;
    }

    public function importAttempts(array $request): void
    {
        $params = $this->parseRequestVariables(
            $request,
            [
                'quizId' => [
                    'default' => false,
                    'type' => 'int',
                    'key' => 'be-quizId'
                ],
                'nonce' => [
                    'default' => null,
                    'type' => 'string',
                    'key' => '_benonce'
                ]
            ]
        );

        $this->logService->logData('[Callback API] Import Attempts', $params, LogService::LEVEL_DEBUG);

        if (empty($params['quizId'])) {
            $this->errorService->addError(new \WP_Error(
                'bizexaminer-missing-arguments',
                __('Arguments for importAttempts are missing', 'bizexaminer-learndash-extension'),
                ['params' => $params, 'code' => 400]
            ), 'import-attempts');
            // let QuizFrontend::renderQuiz handle displaying errors
            return;
        }

        /**
         * nonce lifetime: default (=short)
         * url is generated in a shortcode - importing is allowed instantly after
         */
        if (empty($params['nonce']) || !wp_verify_nonce($params['nonce'], 'be-import-attempts')) {
            $this->die(__('The link you followed has expired.'));
            exit; // die dies, but exit for clarity
        }

        if (!current_user_can('read')) {
            $this->errorService->addError(new \WP_Error(
                'bizexaminer-user-not-allowed',
                __('User is not allowed to run importAttempts', 'bizexaminer-learndash-extension'),
                ['params' => $params, 'code' => 400]
            ), 'import-attempts');
            // let QuizFrontend::renderQuiz handle displaying errors
            return;
        }

        /**
         * Allows doing something / outputting something before the controller handles the action
         *
         * @param array $params The parsed params for this controller action
         * @param array $request   The complete request vars (merged from $_REQUEST and passed queryVars)
         */
        $this->eventManager->do_action(
            'bizexaminer/callbackapi/importAttempts',
            $params,
            $request
        );

        $response = 200; // default to 200 OK status

        $response = $this->quizService->importExternalAttempts($params['quizId']); // use current user

        /**
         * Either if status was not updated $response is default value (200)
         * or the result from the status update
         */
        if (!is_wp_error($response)) {
            $url = get_permalink($params['quizId']);
            $url = add_query_arg([
                // 'be-quizAttempt' => $params['quizAttempt'],
                'be-showResults' => 1
            ], $url);
            wp_redirect($url, 302);
            exit;
        }

        // generic/other
        $this->errorService->addError($response, 'import-attempts');
        // let QuizFrontend::renderQuiz handle displaying errors
        return;
    }

    /**
     * Parses the configured request variables, sanitizes and casts them
     *
     * @param array $request Request variables (eg from $_GET)
     * @param array $paramConfiguration
     *              $paramConfiguration[$key] = [
     *                  'default' => (mixed) default value
     *                  'type' => (string, optional) the type to cast, possible values: 'int' (uses intval)
     *                  'allowEmpty' => (bool) Whether to allow empty values (checked via php empty())
     *              ]
     * @return array parsed and sanitized request variables
     */
    protected function parseRequestVariables(array $request, array $paramConfiguration): array
    {
        $parsedValues = [];
        foreach ($paramConfiguration as $key => $config) {
            $requestKey = $config['key'] ?? $key;
            if (isset($request[$requestKey])) {
                $value = Util::sanitizeInput($request[$requestKey]);
                if (!empty($config['type'])) {
                    switch ($config['type']) {
                        case 'int':
                            $value = intval($value);
                            break;
                    }
                }
                $parsedValues[$key] = $value;
            }

            $allowEmpty = !isset($config['allowEmpty']) || $config['allowEmpty'];
            if (!isset($parsedValues[$key]) || (empty($parsedValues[$key]) && !$allowEmpty)) {
                $parsedValues[$key] = $config['default'] ?? null;
            }
        }

        return $parsedValues;
    }

    /**
     * Dies and exits with the WordPress default error screen
     * Should only be used when an unhandleable error occurs
     *
     * @uses wp_die
     * @uses status_header
     *
     * @param string|null $message Message to send in html
     * @param int $statusCode HTTP Status code to use
     *                        if a 20* code (OK) code is given, it dies directly
     *                        otherwise wp_die with error message is used
     * @return void exits/dies via wp_die
     */
    protected function die(?string $message = null, int $statusCode = 403): void
    {
        if ($statusCode > 200 && $statusCode < 300) {
            status_header($statusCode);
            die();
        }

        if ($message === null) {
            $message = __('Something went wrong.');
        }

        wp_die(
            esc_html($message),
            esc_html($message),
            $statusCode // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        );
    }

    /**
     * Handles errors for eventCallback callbacks (which are not triggered by a user)
     *
     * API Docs:
     *  The receiver MUST respond with a HTTP 2XX response code,
     *  except in cases where an actual error occurred.
     *
     *  If the receiving system cannot handle a booking event,
     *  it MUST NOT respond with an HTTP error status (since 2021-08-24).
     *
     * @link https://docs.google.com/document/d/1UvvQJOhBZ6d2SpPSz5U5SL6dULwgdW1dnVSXwrDaODQ/#heading=h.gg3nsor9iiba
     *
     * @uses Controller::die
     *
     * @param WP_Error $error
     * @return void exits via Controller::die
     */
    protected function handleEventCallbackError(WP_Error $error): void
    {
        $this->logService->logError($error, LogService::LEVEL_ERROR);
        $statusCode = 403;
        $errorData = $error->get_error_data();
        if (isset($errorData['code'])) {
            $statusCode = $errorData['code'];
        }

        $this->die(null, $statusCode);
    }
}
