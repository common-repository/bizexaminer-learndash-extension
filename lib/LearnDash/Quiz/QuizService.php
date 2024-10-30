<?php

namespace BizExaminer\LearnDashExtension\LearnDash\Quiz;

use BizExaminer\LearnDashExtension\Api\ApiClient;
use BizExaminer\LearnDashExtension\Api\ExamModulesService;
use BizExaminer\LearnDashExtension\Api\RemoteProctorsService;
use BizExaminer\LearnDashExtension\Helper\I18n;
use BizExaminer\LearnDashExtension\Helper\Scheduler;
use BizExaminer\LearnDashExtension\Helper\User;
use BizExaminer\LearnDashExtension\Internal\Interfaces\ApiAwareInterface;
use BizExaminer\LearnDashExtension\Internal\Interfaces\ErrorServiceAwareInterface;
use BizExaminer\LearnDashExtension\Internal\Interfaces\EventManagerAwareInterface;
use BizExaminer\LearnDashExtension\Internal\Interfaces\LogServiceAwareInterface;
use BizExaminer\LearnDashExtension\Internal\Interfaces\SettingsServiceAwareInterface;
use BizExaminer\LearnDashExtension\Internal\Traits\ApiAwareTrait;
use BizExaminer\LearnDashExtension\Internal\Traits\ErrorServiceAwareTrait;
use BizExaminer\LearnDashExtension\Internal\Traits\EventManagerAwareTrait;
use BizExaminer\LearnDashExtension\Internal\Traits\LogServiceAwareTrait;
use BizExaminer\LearnDashExtension\Internal\Traits\SettingsServiceAwareTrait;
use BizExaminer\LearnDashExtension\LearnDash\Quiz\CallbackApi\CallbackApiService;
use BizExaminer\LearnDashExtension\LearnDash\Quiz\QuizSettings\QuizSettingsService;
use BizExaminer\LearnDashExtension\LearnDash\Quiz\QuizAttempt;
use BizExaminer\LearnDashExtension\Plugin;
use stdClass;
use WP_Error;

/**
 * Main service for handling quiz flow
 * for rendering, starting, ending a quiz and handling results
 */
class QuizService implements
    SettingsServiceAwareInterface,
    ApiAwareInterface,
    ErrorServiceAwareInterface,
    LogServiceAwareInterface,
    EventManagerAwareInterface
{
    use SettingsServiceAwareTrait;
    use ApiAwareTrait;
    use ErrorServiceAwareTrait;
    use LogServiceAwareTrait;
    use EventManagerAwareTrait;

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
     * QuizAccess instance to use
     *
     * @var QuizAccess
     */
    protected QuizAccess $quizAccess;

    /**
     * Creates a new Quiz Service Instance
     *
     * @param QuizSettingsService $quizSettingsService QuizSettingsService instance to use
     * @param QuizAttemptsDataStore $quizAttempts QuizAttemptsDataStore instance to use
     * @param CallbackApiService $callbackApiService CallbackApiService instance to use
     * @param ExamModulesService $examModulesService ExamModulesService instance to use
     * @param RemoteProctorsService $remoteProctorsService RemoteProctorsService instance to use
     * @param QuizAccess $quizAccess QuizAccess instance to use
     */
    public function __construct(
        QuizSettingsService $quizSettingsService,
        QuizAttemptsDataStore $quizAttempts,
        CallbackApiService $callbackApiService,
        ExamModulesService $examModulesService,
        RemoteProctorsService $remoteProctorsService,
        QuizAccess $quizAccess
    ) {
        $this->quizSettingsService = $quizSettingsService;
        $this->quizAttempts = $quizAttempts;
        $this->callbackApiService = $callbackApiService;
        $this->examModulesService = $examModulesService;
        $this->remoteProctorsService = $remoteProctorsService;
        $this->quizAccess = $quizAccess;
    }

    /**
     * Gets all quizzes in a course
     *
     * @param int $courseId
     * @param bool $filterAccess Filters quizzes by if they are accessible by the current user id
     * @param int $userId If $filterAccess is set, which user to check, defaults to current user id
     *
     * @return \WP_Post[] An array of WP_Post quizzes
     */
    public function getQuizzes(int $courseId, bool $filterAccess = false, int $userId = null): array
    {
        // Quizzes as direct child of the course, not in a lesson
        $quizzes = learndash_course_get_quizzes($courseId, $courseId);

        // Get all lessons and all their quizzes
        $lessons = learndash_course_get_lessons($courseId);

        foreach ($lessons as $lesson) {
            $lessonQuizzes = learndash_course_get_quizzes($courseId, $lesson->ID);
            if (!empty($lessonQuizzes)) {
                $quizzes = array_merge($quizzes, $lessonQuizzes);
            }

            // Get all topics and all their quizzes
            $topics = learndash_course_get_topics($courseId, $lesson->ID);

            foreach ($topics as $topic) {
                $topicQuizzes = learndash_course_get_quizzes($courseId, $topic->ID);
                if (!empty($topicQuizzes)) {
                    $quizzes = array_merge($quizzes, $topicQuizzes);
                }
            }
        }

        $filteredQuizzes = [];

        if ($filterAccess) {
            if (!$userId) {
                $userId = get_current_user_id();
            }
        }

        foreach ($quizzes as $quizPost) {
            $quizSettings = $this->quizSettingsService->getQuizSettings($quizPost->ID);
            // If quiz does not have bizExaminer enabled
            if (!$quizSettings) {
                continue;
            }

            if ($filterAccess) {
                if (!$this->quizAccess->canStartQuiz($quizPost->ID, $userId)) {
                    continue;
                }
            }


            $filteredQuizzes[] = $quizPost;
        }

        return $filteredQuizzes;
    }

    /**
     * Starts a quiz by
     *  - creating/checking for a participant
     *  - creating a quiz attempt
     *  - creating the redirect url for the exam
     *  - booking the exam
     *  - returning the exam URL
     *
     * @param int $quizId The quiz' post ID (NOT quiz pro id)
     * @param int $userId
     * @return string|WP_Error The exam URL including the return UR
     */
    public function startQuiz(int $quizId, int $userId)
    {
        $quizSettings = $this->quizSettingsService->getQuizSettings($quizId);
        if (!$quizSettings) {
            return $this->handleError(
                'bizexaminer-quiz-not-enabled',
                sprintf(
                    /* translators: placeholder: quiz label */
                    __('This %s does not have bizExaminer enabled/configure.', 'bizexaminer-learndash-extension'),
                    learndash_get_custom_label_lower('quiz')
                ),
                ['quizId' => $quizId],
                'start-exam',
                'debug'
            );
        }

        // Check if starting is disabled, because import attempts is enabled
        if ($quizSettings['importExternalAttempts'] && $quizSettings['importExternalAttemptsDisableStart']) {
            return $this->handleError(
                'bizexaminer-quiz-start-disabled',
                sprintf(
                    /* translators: placeholder: quiz label */
                    __(
                        'Starting of this %s is disabled because import attempts are enabled.',
                        'bizexaminer-learndash-extension'
                    ),
                    learndash_get_custom_label_lower('quiz')
                ),
                ['quizId' => $quizId],
                'start-exam',
                'debug'
            );
        }

        // Check if user is allowed to start quiz in context of quiz restrictions
        if (!$this->quizAccess->canStartQuiz($quizId, $userId)) {
            return $this->handleError(
                'bizexaminer-quiz-start-not-allowed',
                sprintf(
                    /* translators: placeholder: quiz label */
                    __(
                        'The user is not allowed to start the %s because of missing prerequisites,
                        maximum retakes or other restrictions.',
                        'bizexaminer-learndash-extension'
                    ),
                    learndash_get_custom_label_lower('quiz')
                ),
                ['quizId' => $quizId, 'userId' => $userId],
                'start-exam',
                'debug'
            );
        }

        $credentials = $this->getApiService()->getApiCredentialsById($quizSettings['credentials']);
        if (!$credentials) {
            return $this->handleError(
                'bizexaminer-quiz-invalid-credentials',
                sprintf(
                    /* translators: placeholder: quiz label */
                    __('The credentials configured for this %s are not valid.', 'bizexaminer-learndash-extension'),
                    learndash_get_custom_label_lower('quiz')
                ),
                ['quizId' => $quizId, 'credentials' => $quizSettings['credentials']],
                'start-exam',
                'debug'
            );
        }

        $examModule = $this->examModulesService->explodeExamModuleIds($quizSettings['examModule']);
        if (!$examModule) {
            return $this->handleError(
                'bizexaminer-quiz-invalid-exam-module',
                sprintf(
                    /* translators: placeholder: quiz label */
                    __('The exam module configured for this %s is not valid.', 'bizexaminer-learndash-extension'),
                    learndash_get_custom_label_lower('quiz')
                ),
                ['quizId' => $quizId, 'examModule' => $quizSettings['examModule']],
                'start-exam',
                'debug'
            );
        }

        $apiClient = $this->makeApi($credentials);

        $participant = $this->getParticipant($userId, $apiClient);
        if (!$participant) {
            return $this->handleError(
                'bizexaminer-quiz-invalid-participant',
                __('Could not create a participant with the API.', 'bizexaminer-learndash-extension'),
                ['quizId' => $quizId, 'quizSettings' => $quizSettings, 'participant' => $participant],
                'start-exam',
                'error'
            );
        }

        // Anything changing here, needs to be changed in importExternalAttempts as well.
        $quizAttempt = $this->quizAttempts->createQuizAttempt(
            $userId,
            $quizId,
            $examModule, // already comes from database, no sanitizing required
            true
        );

        if (!$quizAttempt) {
            return $this->handleError(
                'bizexaminer-quiz-create-attempt',
                sprintf(
                    /* translators: placeholder: quiz label */
                    __('Could not store the %s attempt.', 'bizexaminer-learndash-extension'),
                    learndash_get_custom_label_lower('quiz')
                ),
                ['quizId' => $quizId, 'quizSettings' => $quizSettings, 'quizAttempt' => $quizAttempt],
                'start-exam',
                'error'
            );
        }

        $returnUrl = $this->buildReturnUrl($quizId, $userId, $quizAttempt->getId(), $quizAttempt->getKey());
        $callbackUrl = $this->buildCallbackUrl($quizId, $userId, $quizAttempt->getId(), $quizAttempt->getKey());

        $validStart = new \DateTime('now');
        $validEnd = new \DateTime();
        $validEnd->setTimestamp(($validStart->getTimestamp()));

        /**
         * Filters the default duration an exam is valid
         * Must return a string that gets passed into \DateTime::modify
         * to be added to the start date which is always 'now'
         *
         * @param string $validDuration The duration the exam is valid, in format for DateTime::modify
         * @param int $quizId The quiz post type id (not the pro quiz id)
         */
        $validDuration = $this->eventManager->apply_filters('bizexaminer/examValidDuration', '+24 hours', $quizId);
        $validDuration = !is_string($validDuration) ? '+24 hours' : $validDuration; // default to 24 hours future
        $validEnd->modify($validDuration);

        $booking = $apiClient->bookExam(
            $examModule['productPart'],
            $examModule['contentRevision'],
            $participant,
            $returnUrl,
            $callbackUrl,
            $quizSettings['remoteProctor'],
            $this->remoteProctorsService->formatOptionsForApi(
                $quizSettings['remoteProctor'],
                $quizSettings['remoteProctorSettings'],
                $apiClient->getApiCredentials()
            ),
            I18n::getLanguage($userId),
            $validStart,
            $validEnd
        );

        if (!$booking || is_wp_error($booking)) {
            return $this->handleError(
                'bizexaminer-quiz-create-booking',
                is_wp_error($booking) ? $booking->get_error_message() :
                    __('Could not create a booking with bizExaminer.', 'bizexaminer-learndash-extension'),
                ['quizId' => $quizId, 'quizSettings' => $quizSettings],
                'start-exam',
                'error',
            );
        }

        // Anything changing here, needs to be changed in importExternalAttempts as well.
        $quizAttempt->updateData([
            // data from bizExaminer API should be safe, but still sanitize
            'be_participant' => sanitize_text_field($participant),
            'be_booking' => sanitize_text_field($booking['bookingId']),
            'be_status' => QuizAttempt::STATUS_STARTED,
            'be_valid_until' => $validEnd->getTimestamp(),
        ]);
        $this->quizAttempts->saveQuizAttempt($quizAttempt);
        // schedule cron job to fetch results - if user does not return back and callbacks fail
        $this->maybeScheduleResultsCheck($quizId, $userId, $quizAttempt->getId());

        $this->triggerLearnDashQuizStarted($quizId, $userId);

        /**
         * Allows doing something something after the quiz has been started
         *
         * @param QuizAttempt $quizAttempt
         */
        $this->eventManager->do_action(
            'bizexaminer/quiz/start',
            $quizAttempt
        );

        return $booking['url'];
    }

    /**
     * Ends a quiz by
     *  - setting it's status to "pending"
     *  - saving now as the completed date (may get updated by results), timespent as well
     * Only ends a quiz which is still in "started" state
     * Does NOT handle results - call updateQuizResults for that
     *
     * @param int $quizId
     * @param int $userId
     * @param string $quizAttemptId
     * @return bool|WP_Error true on success, false if quiz is already pending/ended, WP_Error on error
     */
    public function endQuiz(int $quizId, int $userId, string $quizAttemptId)
    {
        $quizSettings = $this->quizSettingsService->getQuizSettings($quizId);
        if (!$quizSettings) {
            return $this->handleError(
                'bizexaminer-quiz-not-enabled',
                sprintf(
                    /* translators: placeholder: quiz label */
                    __('This %s does not have bizExaminer enabled/configure.', 'bizexaminer-learndash-extension'),
                    learndash_get_custom_label_lower('quiz')
                ),
                ['quizId' => $quizId],
                'end-exam',
                'debug'
            );
        }

        $quizAttempt = $this->quizAttempts->getQuizAttempt($userId, $quizId, $quizAttemptId);
        if (!$quizAttempt) {
            return $this->handleError(
                'bizexaminer-no-quiz-attempt',
                sprintf(
                    /* translators: placeholder: quiz label */
                    __('No %s attempt could be found.', 'bizexaminer-learndash-extension'),
                    learndash_get_custom_label_lower('quiz')
                ),
                ['quizId' => $quizId, 'quizAttemptId' => $quizAttemptId],
                'end-exam',
                'debug'
            );
        }

        if ($quizAttempt->getStatus() === QuizAttempt::STATUS_STARTED) {
            // Anything changing here, needs to be changed in importExternalAttempts as well.
            $quizAttempt->updateData([
                'be_status' => QuizAttempt::STATUS_PENDING_RESULTS,
                // may get overwritten with exact data from bizExaminer in updateQuizResults
                'completed' => time(),
                // may get overwritten with exact data from bizExaminer in updateQuizResults
                'timespent' => time() - $quizAttempt->get('started')
            ]);
            $success = $this->quizAttempts->saveQuizAttempt($quizAttempt);

            if (!$success) {
                return $this->handleError(
                    'bizexaminer-quiz-store-results',
                    sprintf(
                        /* translators: placeholder: quiz label */
                        __('Could not store the %s attempt results.', 'bizexaminer-learndash-extension'),
                        learndash_get_custom_label_lower('quiz')
                    ),
                    ['quizId' => $quizId, 'quizSettings' => $quizSettings],
                    'end-exam',
                    'error'
                );
            }
            // Try to fetch results directly after finishing; if not cron job will be scheduled
            $this->updateQuizResults($quizId, $userId, $quizAttemptId);

            // Try to get updated quizAttempt data if updateQuizResults fetched results
            $quizAttempt = $this->quizAttempts->getQuizAttempt($userId, $quizId, $quizAttemptId);
            // trigger learndash quiz submitted (completed will be called when results are available)
            $this->triggerLearnDashQuizSubmitted($quizId, $userId, $quizAttempt);

            /**
             * Allows doing something something after the quiz has been ended
             *
             * @param QuizAttempt $quizAttempt
             */
            $this->eventManager->do_action(
                'bizexaminer/quiz/end',
                $quizAttempt
            );

            return true;
        }

        return false;
    }

    /**
     * Fetches the results for a quiz attempt/exam booking from the API
     * and stores it in the quiz attempt
     * always tries to update the results & status, regardless of the state
     * Sets the status to pending-results if no results available yet (and schedules the cron to fetch them later)
     *
     * @param int $quizId
     * @param int $userId
     * @param string $quizAttemptId
     * @return bool|WP_Error true on success, false if no results yet (pending), WP_Error on error
     */
    public function updateQuizResults(int $quizId, int $userId, string $quizAttemptId)
    {
        $quizSettings = $this->quizSettingsService->getQuizSettings($quizId);
        if (!$quizSettings) {
            return $this->handleError(
                'bizexaminer-quiz-not-enabled',
                sprintf(
                    /* translators: placeholder: quiz label */
                    __('This %s does not have bizExaminer enabled/configure.', 'bizexaminer-learndash-extension'),
                    learndash_get_custom_label_lower('quiz')
                ),
                ['quizId' => $quizId],
                'update-results',
                'debug'
            );
        }

        $quizAttempt = $this->quizAttempts->getQuizAttempt($userId, $quizId, $quizAttemptId);
        if (!$quizAttempt) {
            return $this->handleError(
                'bizexaminer-no-quiz-attempt',
                sprintf(
                    /* translators: placeholder: quiz label */
                    __('No %s attempt could be found.', 'bizexaminer-learndash-extension'),
                    learndash_get_custom_label_lower('quiz')
                ),
                ['quizId' => $quizId, 'quizAttemptId' => $quizAttemptId],
                'update-results',
                'debug'
            );
        }

        $credentials = $this->getApiService()->getApiCredentialsById($quizSettings['credentials']);
        if (!$credentials) {
            return $this->handleError(
                'bizexaminer-quiz-invalid-credentials',
                sprintf(
                    /* translators: placeholder: quiz label */
                    __('The credentials configured for this %s are not valid.', 'bizexaminer-learndash-extension'),
                    learndash_get_custom_label_lower('quiz')
                ),
                ['quizId' => $quizId, 'credentials' => $quizSettings['credentials']],
                'update-results',
                'debug'
            );
        }

        $apiClient = $this->makeApi($credentials);

        $allResults = $apiClient->getParticipantOverviewWithDetails(
            $quizAttempt->get('be_participant'),
            $quizAttempt->get('be_booking')
        );

        if (is_wp_error($allResults)) {
            return $this->handleError(
                'bizexaminer-quiz-create-booking',
                __('Could not fetch results from the API.', 'bizexaminer-learndash-extension'),
                ['quizId' => $quizId, 'quizAttempt' => $quizAttemptId, 'results' => $allResults],
                'update-results',
                'error'
            );
        }

        $handleSuccess = $this->updateQuizResultsInAttempt(
            $quizId,
            $userId,
            $allResults[0] ?? new stdClass(),
            $quizAttempt,
            $quizSettings
        );

        if (is_wp_error($handleSuccess)) {
            return $this->handleError(
                'bizexaminer-quiz-store-results',
                sprintf(
                    /* translators: placeholder: quiz label */
                    __('Could not store the %s attempt results.', 'bizexaminer-learndash-extension'),
                    learndash_get_custom_label_lower('quiz')
                ),
                ['quizId' => $quizId, 'quizSettings' => $quizSettings],
                'update-results',
                'error'
            );
        }

        return true;
    }

    /**
     * Fetches the results for a quiz attempt/exam booking from the API
     * only runs when the quiz attempts state is still pending (not if not finished yet or already results available)
     *
     * @see updateQuizResults
     *
     * @param int $quizId
     * @param int $userId
     * @param string $quizAttemptId
     * @return bool|WP_Error true on success, false if no results yet (pending) or attempt is not pending,
     *                       WP_Error on error
     */
    public function maybeUpdateQuizResults(int $quizId, int $userId, string $quizAttemptId)
    {
        $quizAttempt = $this->quizAttempts->getQuizAttempt($userId, $quizId, $quizAttemptId);
        if (!$quizAttempt) {
            return $this->handleError(
                'bizexaminer-no-quiz-attempt',
                sprintf(
                    /* translators: placeholder: quiz label */
                    __('No %s attempt could be found.', 'bizexaminer-learndash-extension'),
                    learndash_get_custom_label_lower('quiz')
                ),
                ['quizId' => $quizId, 'quizAttemptId' => $quizAttemptId],
                'update-results',
                'debug'
            );
        }

        $success = false;

        if ($quizAttempt->getStatus() === QuizAttempt::STATUS_PENDING_RESULTS) {
            $success = $this->updateQuizResults($quizId, $userId, $quizAttemptId);
        }

        return $success;
    }

    /**
     * Gets the direct exam access url to an exam.
     * This url should not be stored (see #30 and #33).
     *
     * This will also return false/error if the booking is not valid anymore (=expired).
     *
     * @param QuizAttempt $quizAttempt
     * @return string|false
     */
    public function getQuizExamAccessUrl(QuizAttempt $quizAttempt)
    {
        if ($quizAttempt->getStatus() !== QuizAttempt::STATUS_STARTED) {
            $this->handleError(
                'bizexaminer-quiz-attempt-already-finished',
                __(
                    'The exam was already finished and therefore no acess url can be generated.',
                    'bizexaminer-learndash'
                ),
                ['quizAttemptId' => $quizAttempt->getId()]
            );
            return false;
        }

        $quizSettings = $this->quizSettingsService->getQuizSettings($quizAttempt->getQuizId());
        if (!$quizSettings) {
            $this->handleError(
                'bizexaminer-quiz-not-enabled',
                sprintf(
                    /* translators: placeholder: quiz label */
                    __('This %s does not have bizExaminer enabled/configure.', 'bizexaminer-learndash-extension'),
                    learndash_get_custom_label_lower('quiz')
                ),
                ['quizId' => $quizAttempt->getQuizId()],
                'update-results',
                'debug'
            );
            return false;
        }

        $credentials = $this->getApiService()->getApiCredentialsById($quizSettings['credentials']);
        if (!$credentials) {
            $this->handleError(
                'bizexaminer-quiz-invalid-credentials',
                sprintf(
                    /* translators: placeholder: quiz label */
                    __('The credentials configured for this %s are not valid.', 'bizexaminer-learndash-extension'),
                    learndash_get_custom_label_lower('quiz')
                ),
                ['quizId' => $quizAttempt->getQuizId(), 'credentials' => $quizSettings['credentials']],
                'update-results',
                'debug'
            );
            return false;
        }

        $apiClient = $this->makeApi($credentials);

        $examUrl = $apiClient->getExaminationAccessUrl($quizAttempt->get('be_booking'));

        if (is_wp_error($examUrl)) {
            $this->handleError(
                'bizexaminer-quiz-get-exam-url',
                __('Could not fetch the direct exam access url from the API.', 'bizexaminer-learndash-extension'),
                ['quizAttempt' => $quizAttempt->getId(), 'error' => $examUrl],
                'update-results',
                'error'
            );
            return false;
        }

        return $examUrl;
    }

    /**
     * Run the import for external attempts for a specific quizId for an (optional) userId.
     *
     * @param int $quizId The post quiz id
     * @param int $userId Optionaly, by default uses current_user_id
     * @return WP_Error|true WP_Error on error, true if all succeeded or no attempts to import
     */
    public function importExternalAttempts(int $quizId, ?int $userId = null)
    {
        // If $userId = 0 it's a guest, only handle "null" values
        if ($userId === null) {
            $userId = get_current_user_id();
        }

        $quizSettings = $this->quizSettingsService->getQuizSettings($quizId);
        if (!$quizSettings) {
            return $this->handleError(
                'bizexaminer-quiz-not-enabled',
                sprintf(
                    /* translators: placeholder: quiz label */
                    __('This %s does not have bizExaminer enabled/configure.', 'bizexaminer-learndash-extension'),
                    learndash_get_custom_label_lower('quiz')
                ),
                ['quizId' => $quizId],
                'import-attempt',
                'debug'
            );
        }

        // If quiz does not have importing attempts enabled
        if (!$quizSettings['importExternalAttempts']) {
            return $this->handleError(
                'bizexaminer-quiz-import-not-enabled',
                sprintf(
                    /* translators: placeholder: quiz label */
                    __('This %s does not have importing attempts enabled.', 'bizexaminer-learndash-extension'),
                    learndash_get_custom_label_lower('quiz')
                ),
                ['quizId' => $quizId],
                'import-attempt',
                'debug'
            );
        }

        if (!$this->quizAccess->canStartQuiz($quizId, $userId, false)) {
            return $this->handleError(
                'bizexaminer-quiz-import-not-allowed',
                sprintf(
                    /* translators: placeholder: quiz label */
                    __('The user cannot start this %s.', 'bizexaminer-learndash-extension'),
                    learndash_get_custom_label_lower('quiz')
                ),
                ['quizId' => $quizId],
                'import-attempt',
                'debug'
            );
        }

        $credentials = $this->getApiService()->getApiCredentialsById($quizSettings['credentials']);
        if (!$credentials) {
            return $this->handleError(
                'bizexaminer-quiz-invalid-credentials',
                sprintf(
                    /* translators: placeholder: quiz label */
                    __('The credentials configured for this %s are not valid.', 'bizexaminer-learndash-extension'),
                    learndash_get_custom_label_lower('quiz')
                ),
                ['quizId' => $quizId, 'credentials' => $quizSettings['credentials']],
                'import-attempt',
                'debug'
            );
        }

        $apiClient = $this->makeApi($credentials);

        $participant = $this->getParticipant($userId, $apiClient);

        if (!$participant) {
            return $this->handleError(
                'bizexaminer-import-attempt--participant',
                __('Could not create a participant with the API.', 'bizexaminer-learndash-extension'),
                ['quizId' => $quizId, 'quizSettings' => $quizSettings, 'participant' => $participant],
                'import-attempt',
                'error'
            );
        }

        $allResults = $apiClient->getParticipantOverviewWithDetails($participant);

        if (is_wp_error($allResults)) {
            return $this->handleError(
                'bizexaminer-import-attempt-error-results',
                __('Could not fetch results to import from the API.', 'bizexaminer-learndash-extension'),
                ['quizId' => $quizId, 'results' => $allResults],
                'import-attempt',
                'error'
            );
        }

        // No results
        if (empty($allResults)) {
            // Log message.
            return $this->handleError(
                'bizexaminer-import-attempt-error-no-results',
                __('No results to import.', 'bizexaminer-learndash-extension'),
                ['quizId' => $quizId, 'results' => $allResults],
                'import-attempt',
                'debug'
            );
        }

        // Loop through all results, check if there is already an attempt stored for this result for this user
        // If yes, maybe check if it needs updating; if no create a new attempt

        $userAttempts = $this->quizAttempts->findUserQuizAttempts($userId, 'quiz', $quizId);
        $userAttemptsByBookingId = [
            'none' => [],
        ];
        foreach ($userAttempts as $userAttempt) {
            $bookingId = $userAttempt->get('be_booking');
            $attendanceId = $userAttempt->get('be_attendance');
            if (empty($bookingId) && empty($attendanceId)) {
                continue;
            }
            // Some attempts may not have a bookingId; sort them by attendanceId
            // @phpstan-ignore-next-line
            if (empty($bookingId) && !empty($attendanceId)) {
                $userAttemptsByBookingId['none'][$attendanceId] = $userAttempt;
                continue;
            }
            // With LearnDash started attempts, there's always only one attempt per booking
            // Because for each new attempt, a new just-in-time booking is created
            // But for attempts from bizExaminer, there could be multiple attendances per booking, therefore use array.
            if (!isset($userAttemptsByBookingId[$bookingId])) {
                $userAttemptsByBookingId[$bookingId] = [];
            }
            $userAttemptsByBookingId[$bookingId][] = $userAttempt;
        }

        $errors = [];

        foreach ($allResults as $resultRaw) {
            // No evaluated score yet: abort; Only handle completed attempts.
            if (!$resultRaw->result) {
                continue;
            }

            // For attempts from bizExaminer, there could be multiple attendances per booking, therefore use array.
            // crtParticipantAttendancesId is a unique id for each attempt
            // and can be used to differentiate multiple attempts
            // for one booking.
            // bookingId is still useful for "grouping" those.
            // exmBookingsId is not returned by getParticipantOverviewWithDetails but should be
            $bookingId = $resultRaw->exmBookingsId;
            $attendanceId = $resultRaw->crtParticipantAttendancesId;

            $createAttempt = false;

            // If results have a bookingId, sort them by bookingId
            // If not, sort them into another array and check by attendanceId
            // Reason: started in bizExaminer can have multiple attendances for a booking
            // started in LearnDash always creates a new booking
            // and attendanceId is only returned with results (not when booking)
            if (!empty($bookingId)) {
                // Check if it's new results or existing that need updating.
                if (
                    !array_key_exists($bookingId, $userAttemptsByBookingId)
                    || empty($userAttemptsByBookingId[$bookingId])
                ) {
                    // There's no stored attempt yet for that booking, so for sure it's a new one
                    $createAttempt = true;
                } else {
                    // There are already results for this bookingId, but let's check if it's a new attendance = attempt.
                    $createAttempt = true; // default to create a new attempt, unless a matching attendance is found
                    foreach ($userAttemptsByBookingId[$bookingId] as $attempt) {
                        if ($attempt->get('be_attendance') === $attendanceId) {
                            $createAttempt = false;
                            break;
                        }
                    }

                    // TODO maybe compare data and update
                    // Allthough results/status updates should be handled by the schedule results hook
                    // Is there any reason to check for this? Or just import new ones with code above
                }
            } elseif (!empty($attendanceId)) {
                // Check for attendance id
                if (!array_key_exists($attendanceId, $userAttemptsByBookingId['none'])) {
                    // There's no stored attempt yet for that booking and attendance
                    $createAttempt = true;
                }
            }



            // If no need to create attempt, go to next result item.
            if (!$createAttempt) {
                continue;
            }

            // lets create an attempt
            // Combines creating & updating of quizAttempt from startQuiz, endQuiz and updateQuizResults.

            // From startQuiz
            // x   creates the attempt
            // x   creates the booking and stores booking data in atttempt
            // -   schedules results check (only after checking results)
            // x   trigger learndash quiz started
            // -   Triggers bizexaminer/quiz/start - no, because it's already finished

            // Changes here, may need to be changed in startQuiz as well.
            $quizAttempt = $this->quizAttempts->createQuizAttempt(
                $userId,
                $quizId,
                [
                    // getParticipantOverview returns other ids than bookExam normally
                    // rely on contentRevision only (not exmExamsId/contentsId)
                    // since it's unique enough
                    // see docs/bizexaminer-api.md
                    'contentRevision' => absint($resultRaw->contentRevisionsId),
                ],
                false, // do not start right now, set time below
                // do not save right now, because saving will trigger learndash to directly record course progresses
                // which will lead to wrong times
                // also save in one go below
                false
            );

            if (!$quizAttempt) {
                $errors[] = $this->handleError(
                    'bizexaminer-quiz-create-attempt',
                    sprintf(
                        __('Could not create an attempt.', 'bizexaminer-learndash-extension'),
                        learndash_get_custom_label_lower('quiz')
                    ),
                    ['quizId' => $quizId, 'quizSettings' => $quizSettings, 'quizAttempt' => $quizAttempt],
                    'import-attempt',
                    'error'
                );
                continue; // abort
            }

            // Set to started by default, may trigger results check in future
            $attemptStatus = QuizAttempt::STATUS_STARTED;

            // 69 = finished, 71 = manualevaluation
            if ($resultRaw->wflStatesId === 69 || $resultRaw->wflStatesId === 71) {
                $attemptStatus = QuizAttempt::STATUS_PENDING_RESULTS;
            } elseif ($resultRaw->wflStatesId === 70) { // evaluated
                $attemptStatus = QuizAttempt::STATUS_COMPLETED;
            }

            // Changes here, may need to be changed in startQuiz as well.
            $quizAttempt->updateData([
                // Changes here, may need to be changed in startQuiz/endQuiz as well.

                // data from bizExaminer API should be safe, but still sanitize
                'be_attendance' => absint($attendanceId),
                'be_participant' => sanitize_text_field($participant),
                'be_booking' => absint($bookingId),
                'be_status' => $attemptStatus,
                // 'be_valid_until' => $validEnd->getTimestamp(),
                'started' => strtotime($resultRaw->whenStarted),
                'time' => strtotime($resultRaw->whenStarted),

                // endQuiz updates be_status, completed and timespent
                // completed and timespent are updated with bizExaminer data by updateQuizResultsInAttempt
                // be_status is set above
            ]);
            $saveSuccess = $this->quizAttempts->addQuizAttempt($quizAttempt);

            if (!$saveSuccess) {
                $errors[] = $this->handleError(
                    'bizexaminer-quiz-store-attempt',
                    sprintf(
                        /* translators: placeholder: quiz label */
                        __('Could not store the %s attempt.', 'bizexaminer-learndash-extension'),
                        learndash_get_custom_label_lower('quiz')
                    ),
                    ['quizId' => $quizId, 'quizSettings' => $quizSettings, 'quizAttempt' => $quizAttempt],
                    'import-attempt',
                    'error'
                );
                continue; // abort
            }

            $this->triggerLearnDashQuizStarted($quizId, $userId, strtotime($resultRaw->whenStarted));

            // From endQuiz
            // -   Update be_status, completed, timespent -
            //         will haben in updateQuizResultsInAttempt with bizExaminer data
            // x   Trigger learndash quiz completed
            // -   Trigger bizExaminer/quiz/end - no because it ended not now, but probably earlier

            // trigger learndash quiz submitted (completed will be called when results are available)
            $this->triggerLearnDashQuizSubmitted($quizId, $userId, $quizAttempt);

            // From updateQuizResults
            // x   Call updateQuizResultsInAttempt
            //         To save results, update timings, unschedule hooks, trigger learndash
            //         We will schedule a results check, just to make sure to get results of still pending attempts
            $saveSuccess = $this->updateQuizResultsInAttempt(
                $quizId,
                $userId,
                $resultRaw,
                $quizAttempt,
                $quizSettings
            );

            if (is_wp_error($saveSuccess)) {
                $errors[] = $this->handleError(
                    'bizexaminer-quiz-store-attempt',
                    __('Could not store attempt results.', 'bizexaminer-learndash-extension'),
                    ['quizId' => $quizId, 'quizSettings' => $quizSettings, 'quizAttempt' => $quizAttempt],
                    'import-attempt',
                    'error'
                );
                continue; // abort
            }

            /**
             * Allows triggering something after an attempt has been imported
             *
             * @param QuizAttempt $quizAttempt
             */
            $this->eventManager->do_action(
                'bizexaminer/quiz/importAttempt',
                $quizAttempt
            );

            if (!empty($bookingId)) {
                $userAttemptsByBookingId[$bookingId][] = $quizAttempt;
            } elseif (!empty($attendanceId)) {
                $userAttemptsByBookingId['none'][$attendanceId] = $quizAttempt;
            }
        }

        if (!empty($errors)) {
            return $this->handleError(
                'bizexaminer-quiz-store-results',
                sprintf(
                    /* translators: placeholder: quiz label */
                    __('Could not import the %s attempt results.', 'bizexaminer-learndash-extension'),
                    learndash_get_custom_label_lower('quiz')
                ),
                ['quizId' => $quizId, 'userId' => $userId, 'quizSettings' => $quizSettings],
                'import-attempt',
                'error'
            );
        }

        return true;
    }

    /**
     * Checks if a participant already exists
     * if yes, use it; if not create a new one in the API
     *
     * @param int $userId
     * @param ApiClient $api
     * @return string|false
     */
    protected function getParticipant(int $userId, ApiClient $api)
    {
        $participantId = null;

        if ($userId) { // if there's a valid, loggedin user (may be 0 if current user is not logged in)
            // Store the participant id per user per apiCredentials context
            // (because different api credentials can use different organizations and therefore different participants)
            $userMetaKey = '_be_participantid_' . $api->getApiCredentials()->getId();
            $storedParticipantId = get_user_meta($userId, $userMetaKey, true);
            if (!empty($storedParticipantId)) {
                return $storedParticipantId;
            }

            $userInfo = User::getUserInfo($userId);

            $participantData = [
                'firstName' => $userInfo['firstName'],
                'lastName' => $userInfo['lastName'],
                'email' => $userInfo['email'],
                // required but an empty value is allowed - for backwards compatibility
                'gender' => ''
            ];

            /**
             * Allows filtering the values sent to bizExaminer api for checking and creating a participant
             * See the API docs for available fields
             * for example use the field orgParticipantID for passing a custom/internal ID
             *
             * Notice: checkParticipant does not support all fields that createParticipant does
             *
             * @param array $participantData The participant data to send
             * @param int $userId The WordPress user id
             * @param QuizService $quizService The quiz service instance
             */
            $participantData = $this->eventManager->apply_filters(
                'bizexaminer/participantData',
                $participantData,
                $userId,
                $this
            );

            $participantId = $api->checkParticipant($participantData);

            if (!$participantId || is_wp_error($participantId)) {
                $newParticipantId = $api->createParticipant($participantData);

                if ($newParticipantId) {
                    $participantId = $newParticipantId;
                }
            }
            // Save participantId, because it wasn't stored yet.
            update_user_meta($userId, $userMetaKey, $participantId);
        } else {
            // not handling loggedin users atm (@see issue #2)
        }

        if (!$participantId) {
            return false;
        }
        return $participantId;
    }

    /**
     * Builds a return URL sent to bizExaminer API
     * which includes everything to identify this quiz attempt and show the results again
     *
     * @param string|int $quizId The quiz' post ID (NOT quiz pro id)
     * @param int $userId
     * @param string $quizAttemptId
     * @param string $quizAttemptKey
     * @return string
     */
    protected function buildReturnUrl($quizId, int $userId, string $quizAttemptId, string $quizAttemptKey): string
    {
        $baseUrl = get_permalink($quizId);
        $queryArgs = [
            'be-quizId' => $quizId,
            'be-userId' => $userId,
            'be-quizAttempt' => $quizAttemptId,
            'be-key' => $quizAttemptKey,
        ];

        $redirectUrl = $this->callbackApiService->buildCallbackApiUrl(
            'exam-completed',
            $queryArgs,
            $baseUrl,
            false // do not add nonce
        );

        return $redirectUrl;
    }

    /**
     * Builds a callbackUrl for webhooks/events form the bizExaminer API
     * which includes everything to identify this quiz attempt and show the results again
     *
     * @param string|int $quizId The quiz' post ID (NOT quiz pro id)
     * @param int $userId
     * @param string $quizAttemptId
     * @param string $quizAttemptKey
     * @return string
     */
    protected function buildCallbackUrl($quizId, int $userId, string $quizAttemptId, string $quizAttemptKey): string
    {
        $baseUrl = get_permalink($quizId);
        $queryArgs = [
            'be-quizId' => $quizId,
            'be-userId' => $userId,
            'be-quizAttempt' => $quizAttemptId,
            'be-key' => $quizAttemptKey
        ];

        $callbackUrl = $this->callbackApiService->buildCallbackApiUrl(
            'callback',
            $queryArgs,
            $baseUrl,
            false // do not add nonce
        );
        return $callbackUrl;
    }

    /**
     * Returns results in the LearnDash format from the bizExaminer API format
     *
     * @see QuizAttempt for format/keys
     *
     * @param stdClass $rawResults from the bizExaminer API
     * @return array
     */
    protected function buildResultsFromRawResults(stdClass $rawResults): array
    {
        // parse/cast to int/floats, no further sanitizing required
        $results = [
            'completed' => strtotime($rawResults->whenFinished), // finished at bizExaminer
            'timespent' => intval($rawResults->timeTaken), // in seconds
            'percentage' => floatval($rawResults->result),
            'pass' => $rawResults->passed === 'Pass' ? 1 : 0,
            // Since v4.14.0 LearnDash allows floats for points and total_points
            // and uses learndash_format_course_points to format them.
            // But in bizExaminer these values are still integers.
            'points' => intval($rawResults->achievedScore),
            'total_points' => intval($rawResults->maxScore),
            'be_has_results' => 1
        ];

        $learnDashVersion = Plugin::getInstance()->getContainer()->get('learndash')->getLearnDashVersion();
        if ($learnDashVersion && version_compare($learnDashVersion, '4.14.0', '>=')) {
            $results['points'] = learndash_format_course_points($rawResults->achievedScore);
            $results['total_points'] = learndash_format_course_points($rawResults->maxScore);
        }

        $questions = 0;
        $questionsCorrect = 0;

        if (isset($rawResults->questionDetails)) {
            foreach ($rawResults->questionDetails->blocks as $block) {
                foreach ($block->questions as $question) {
                    $questions++;
                    /**
                     * assume all questions with more than 0 points answered correctly
                     * not fully right, but enough for this stat
                     * TODO: no way to get correct questions atm, only reached & max points
                     */
                    if ($question->points_reached > 0) {
                        $questionsCorrect++;
                    }
                }
            }
        }

        $results['count'] = $questions;
        $results['question_show_count'] = $questions;
        $results['score'] = $questionsCorrect;

        return $results;
    }

    /**
     * Schedules a cron job to fetch results later on
     * only registers a job ONCE for each quizId+userId+quizAttemptId combination
     *
     * @param int $quizId
     * @param int $userId
     * @param string $quizAttemptId
     * @return void
     */
    protected function maybeScheduleResultsCheck(int $quizId, int $userId, string $quizAttemptId): void
    {
        $hook = 'bizexaminer/checkQuizResults';
        $args = [$quizId, $userId, $quizAttemptId];
        $interval = DAY_IN_SECONDS * 2; // 2x / day
        if (!Scheduler::hasScheduled($hook, $args)) {
            Scheduler::scheduleRecurring($interval, $hook, $args);
        }
    }

    /**
     * Unschedules the cron job to fetch results for a specific quizId+userId+quizAttemptId combination
     *
     * @param int $quizId
     * @param int $userId
     * @param string $quizAttemptId
     * @return void
     */
    protected function unscheduleResultsCheck(int $quizId, int $userId, string $quizAttemptId): void
    {
        $hook = 'bizexaminer/checkQuizResults';
        $args = [$quizId, $userId, $quizAttemptId];
        Scheduler::unschedule($hook, $args);
    }

    /**
     * Triggers the quiz started functionality of LearnDash
     *
     * Copied from learndash_quiz_shortcode_function
     * which calls WpProQuiz_Controller_front::shortcode
     * but WpProQuiz_Controller_front::handleShortCode returns if the quiz contains no questions
     *
     * @see learndash_quiz_shortcode_function
     * @see WpProQuiz_Controller_front::shortcode
     * @watch learndash_quiz_shortcode_function
     * @watch WpProQuiz_Controller_front::shortcode
     *
     * @param int $quizId
     * @param int $userId
     * @param int|null $startedTime
     * @return void
     */
    protected function triggerLearnDashQuizStarted(int $quizId, int $userId, ?int $startedTime = null)
    {
        $course = learndash_get_setting($quizId, 'course');
        $course = absint($course);

        if (!empty($course)) {
            $activity_started_time = $startedTime ?? time();

            $course_activity = learndash_activity_start_course($userId, $course, $activity_started_time);
            // @phpstan-ignore-next-line (Comes from LearnDash)
            if ($course_activity) {
                learndash_activity_update_meta_set(
                    $course_activity->activity_id,
                    array(
                        'steps_completed' => learndash_course_get_completed_steps($userId, $course),
                        'steps_last_id'   => $quizId,
                    )
                );
            }

            $lesson = learndash_course_get_single_parent_step($course, $quizId, learndash_get_post_type_slug('lesson'));
            $lesson = absint($lesson);
            if (!empty($lesson)) {
                learndash_activity_start_lesson($userId, $course, $lesson, $activity_started_time);
            }

            $topic = learndash_course_get_single_parent_step($course, $quizId, learndash_get_post_type_slug('topic'));
            $topic = absint($topic);
            if (!empty($topic)) {
                learndash_activity_start_topic($userId, $course, $topic, $activity_started_time);
            }
        }
    }

    /**
     * Triggers the quiz completed functionality of LearnDash
     *
     * Copied from ld-quiz-pro.php LD_QuizPro::wp_pro_quiz_completed
     * which can't be called publicly because it gets the data directly from $_POST
     *
     * @see LD_QuizPro::wp_pro_quiz_completed
     * @watch LD_QuizPro::wp_pro_quiz_completed
     *
     * @param int $quizId
     * @param int $userId
     * @param QuizAttempt $quizAttempt
     * @return void
     */
    protected function triggerLearnDashQuizSubmitted(int $quizId, int $userId, QuizAttempt $quizAttempt): void
    {
        // See LD_QuizPro::wp_pro_quiz_completed for format of data
        $quizdata = $quizAttempt->getData();
        if (!empty($quizdata['course'])) {
            $quizdata['course'] = get_post($quizdata['course']);
        } else {
            $quizdata['course'] = 0;
        }

        if (!empty($quizdata['lesson'])) {
            $quizdata['lesson'] = get_post($quizdata['lesson']);
        } else {
            $quizdata['lesson'] = 0;
        }

        if (!empty($quizdata['topic'])) {
            $quizdata['topic'] = get_post($quizdata['topic']);
        } else {
            $quizdata['topic'] = 0;
        }

        $quizdata['questions'] = [];
        /**
         * Fires after the quiz is submitted
         *
         * This action is documented in ld-quiz-pro.php wp_pro_quiz_completed
         *
         * @param array   $quiz_data    An array of quiz data.
         * @param WP_User $current_user Current user object.
         */
        $this->eventManager->do_action('learndash_quiz_submitted', $quizdata, get_user_by('id', $userId));
    }

    /**
     * Triggers the quiz completed functionality of LearnDash
     *
     * Code taken from various places in LearnDash which handle quiz completion
     *
     * @see LD_QuizPro::wp_pro_quiz_completed
     * @see learndash_update_quiz_data
     * @see learndash_process_user_course_progress_update
     * @watch LD_QuizPro::wp_pro_quiz_completed
     * @watch learndash_update_quiz_data
     * @watch learndash_process_user_course_progress_update
     *
     * @param int $quizId
     * @param int $userId
     * @param QuizAttempt $quizAttempt
     * @return void
     */
    protected function triggerLearnDashQuizCompleted(int $quizId, int $userId, QuizAttempt $quizAttempt): void
    {
        $course = $quizAttempt->get('course');

        /**
         * code is kept as is and variables are mapped here so it's very easy to copy & update the code from LD
         */
        $course_id = $course;
        $quiz_id = $quizId;
        $quiz_post_id = $quizId;
        $user_id = $userId;
        $quizdata = $quizAttempt->getData();
        $quizdata_pass = $quizdata['pass'] === true ? true : false;
        $topic_id = $quizdata['topic'] ?? null;
        $lesson_id = $quizdata['lesson'] ?? null;

        /**
         * Taken from ld-users.php learndash_process_user_course_progress_update
         * which can't be called publicly because it creates new quiz attempt data
         *
         * @see learndash_process_user_course_progress_update
         * @watch learndash_process_user_course_progress_update
         */
        if ((isset($quizdata['course'])) && (!empty($quizdata['course']))) {
            $quizdata['course'] = get_post($quizdata['course']);
        }

        if ((isset($quizdata['lesson'])) && (!empty($quizdata['lesson']))) {
            $quizdata['lesson'] = get_post($quizdata['lesson']);
        }

        if ((isset($quizdata['topic'])) && (!empty($quizdata['topic']))) {
            $quizdata['topic'] = get_post($quizdata['topic']);
        }

        // Then we add the quiz entry to the activity database.
        learndash_update_user_activity(
            array(
                'course_id' => $course_id,
                'user_id' => $user_id,
                'post_id' => $quiz_id,
                'activity_type' => 'quiz',
                'activity_action' => 'insert',
                'activity_status' => $quizdata_pass,
                'activity_started' => $quizdata['time'],
                'activity_completed' => $quizdata['time'],
                'activity_meta' => $quizdata,
            )
        );

        /**
         * Taken from ld-quiz-pro.php LD_QuizPro::wp_pro_quiz_completed
         * which can't be called publicly because it gets the data directly from $_POST
         *
         * @see LD_QuizPro::wp_pro_quiz_completed
         * @watch LD_QuizPro::wp_pro_quiz_completed
         */
        if (!empty($course)) {
            $quiz_parent_post_id = 0;
            if (!empty($topic_id)) {
                $quiz_parent_post_id = $topic_id;
            } elseif (!empty($lesson_id)) {
                $quiz_parent_post_id = $lesson_id;
            }

            if (!empty($quiz_parent_post_id)) {

                /**
                 * Filter to set all parent steps completed.
                 *
                 * @param bool $set_all_steps_completed Whether to set all steps completed.
                 * @param int     $quiz_post_id            Quiz post ID.
                 * @param int     $user_id                 User ID.
                 * @param int     $course_id               Course ID.
                 */
                if (apply_filters('learndash_complete_all_parent_steps', true, $quiz_post_id, $user_id, $course_id)) {
                    if (!empty($topic_id)) {
                        if (learndash_can_complete_step($user_id, $topic_id, $course_id)) {
                            learndash_process_mark_complete($user_id, $topic_id, false, $course_id);
                        }
                    }
                    if (!empty($lesson_id)) {
                        if (learndash_can_complete_step($user_id, $lesson_id, $course_id)) {
                            learndash_process_mark_complete($user_id, $lesson_id, false, $course_id);
                        }
                    }
                } else {
                    if (learndash_can_complete_step($user_id, $quiz_parent_post_id, $course_id)) {
                        learndash_process_mark_complete($user_id, $quiz_parent_post_id, false, $course_id);
                    }
                }
            } else {
                $all_quizzes_complete = true;
                $quizzes              = learndash_get_global_quiz_list($course_id);
                if (!empty($quizzes)) {
                    foreach ($quizzes as $quiz) {
                        if (learndash_is_quiz_notcomplete($user_id, array($quiz->ID => 1), false, $course_id)) {
                            $all_quizzes_complete = false;
                            break;
                        }
                    }
                }
                if (true === $all_quizzes_complete) {
                    learndash_process_mark_complete($user_id, $course_id, false, $course_id);
                }
            }
        }

        /** This action is documented in includes/ld-users.php */
        $this->eventManager->do_action('learndash_quiz_completed', $quizdata, get_user_by('id', $user_id));

        /**
         * Taken from ld-users.php learndash_process_user_course_progress_update
         * which can't be called publicly because it creates new quiz attempt data
         *
         * called after quiz_completed
         *
         * @see learndash_process_user_course_progress_update
         * @watch learndash_process_user_course_progress_update
         *
         * In v4.12.1 LearnDash passes a $force = true parameter to this function in some places to fix a bug
         * (eg to force the user progress when a leader sets it)
         * ATM no bug in relation to this is known to us, therefor keep it like this (as LD does in other places).
         */
        learndash_process_mark_complete($user_id, $course_id);
        learndash_update_group_course_user_progress($course_id, $user_id, true);
    }

    /**
     * Handles updating the results in a quiz attempt
     * Handles building results form bizExaminer results format, checking results are available,
     * scheduling results check, trigger learndash completed
     *
     * Used by updateQuizResults, importExternalAttempts
     *
     * @param int $quizId
     * @param int $userId
     * @param stdClass|null $rawResults
     * @param QuizAttempt $quizAttempt
     * @param array $quizSettings
     * @return WP_Error|bool WP_Error on error, false if no results yet, true if results updated
     */
    protected function updateQuizResultsInAttempt(
        int $quizId,
        int $userId,
        ?stdClass $rawResults,
        QuizAttempt $quizAttempt,
        array $quizSettings
    ) {
        if (empty($rawResults) || !isset($rawResults->result)) {
            // no results yet

            $quizAttempt->updateData([
                'be_status' => QuizAttempt::STATUS_PENDING_RESULTS,
            ]);
            $success = $this->quizAttempts->saveQuizAttempt($quizAttempt);

            if (!$success) {
                return $this->handleError(
                    'bizexaminer-quiz-store-results',
                    sprintf(
                        /* translators: placeholder: quiz label */
                        __('Could not store the %s attempt results.', 'bizexaminer-learndash-extension'),
                        learndash_get_custom_label_lower('quiz')
                    ),
                    ['quizId' => $quizId, 'quizSettings' => $quizSettings],
                    'update-results',
                    'error'
                );
            }
            // schedule cron job to update results - will only get scheduled once
            $this->maybeScheduleResultsCheck($quizId, $userId, $quizAttempt->getId());
            return false;
        } else {
            // get first set of results, since exmBookingsId is given, it should contain the results for this attempt
            $results = $this->buildResultsFromRawResults($rawResults);
            $quizAttempt->updateData(array_merge($results, [
                'be_status' => QuizAttempt::STATUS_COMPLETED,
                'be_certificate' => $quizSettings['useBeCertificate'] ? $rawResults->certDownloadUrl : null
            ]));

            $success = $this->quizAttempts->saveQuizAttempt($quizAttempt);
            // unschedule cron job to update results
            $this->unscheduleResultsCheck($quizId, $userId, $quizAttempt->getId());
            // trigger learndash quiz completed
            $this->triggerLearnDashQuizCompleted($quizId, $userId, $quizAttempt);

            /**
             * Allows doing something something after the quiz results have been saved
             *
             * @param QuizAttempt $quizAttempt
             */
            $this->eventManager->do_action(
                'bizexaminer/quiz/updateResults',
                $quizAttempt
            );
            return true;
        }
    }

    /**
     * Creates a new WP_Error instance, adds it to the error service and returns it
     *
     * @param string $code
     * @param string $message
     * @param array $data
     * @param string $context
     * @param false|string $log false or log level to log on
     * @return WP_Error
     */
    protected function handleError(
        string $code,
        string $message,
        array $data = [],
        string $context = '',
        $log = false
    ): WP_Error {
        $error = new \WP_Error($code, $message, $data);
        $this->getErrorService()->addError($error, $context);
        if ($log) {
            $this->logService->logError($error, $log);
        }
        return $error;
    }
}
