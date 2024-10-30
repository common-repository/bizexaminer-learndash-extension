<?php

namespace BizExaminer\LearnDashExtension\LearnDash\Quiz;

use BizExaminer\LearnDashExtension\Plugin;

/**
 * Data object for a quiz attempt
 */
class QuizAttempt
{
    /**
     * Quiz attempt status when it has started
     *
     * @var string
     */
    public const STATUS_STARTED = 'started';

    /**
     * Quiz attempt status when it's ended, but no results yet
     *
     * @var string
     */
    public const STATUS_PENDING_RESULTS = 'pending_results';

    /**
     * Quiz attempt status when it's completed and has results
     *
     * @var string
     */
    public const STATUS_COMPLETED = 'completed';

    /**
     * Quiz attempt status when it's canceled (aborted, minpulated,...)
     *
     * @var string
     */
    public const STATUS_CANCELED = 'canceled';

    /**
     * $data keys which can be updated in updateData
     *
     * @see updateData
     *
     * @var string[]
     */
    protected const FILLABLE_KEYS = [
        'score', 'count', 'pass', 'rank', 'points', 'total_points', 'percentage',
        'time', 'timespent', 'started', 'completed',
        'be_status', 'be_booking', 'be_participant', 'be_valid_until', 'be_certificate', 'be_has_results',
        'be_attendance',
    ];

    /**
     * The quiz id this quiz attempt belongs to (post id)
     *
     * @var int
     */
    protected int $quizId;

    /**
     * The user id this quiz attempt belongs to
     *
     * @var int
     */
    protected int $userId;

    /**
     * All the data stored in this quiz attempt
     *
     * @see setData for keys
     *
     * @var array
     */
    protected array $data;

    /**
     * Creates a new QuizAttempt instance (data object)
     *
     * @param integer $quizId The quiz id this quiz attempt belongs to (post id)
     * @param integer $userId The user id this quiz attempt belongs to
     * @param array $data All the data stored in this quiz attempt
     */
    public function __construct(int $quizId, int $userId, array $data)
    {
        $this->quizId = $quizId;
        $this->userId = $userId;

        $this->setData($data);

        if (!$this->getKey()) {
            $this->data['be_key'] = self::generateKey();
        }

        if (!$this->getId()) {
            $this->data['be_id'] = self::generateId($this->quizId, $this->data);
        }
    }

    /**
     * Sets default and passed data in the quiz attempt
     *
     * @see LD_QuizPro::wp_pro_quiz_completed / ld-quiz-pro.php#1224 ff
     * @watch LD_QuizPro::wp_pro_quiz_completed / ld-quiz-pro.php#1224 ff
     *
     * @param array $data
     * @return void
     */
    protected function setData(array $data = []): void
    {
        /**
         * @see LD_QuizPro::wp_pro_quiz_completed / ld-quiz-pro.php#1224 ff
         * @watch LD_QuizPro::wp_pro_quiz_completed / ld-quiz-pro.php#1224 ff
         */
        $this->data = wp_parse_args($data, [
            'quiz'                => $this->quizId,
            'score'               => 0, // (int) number of correct questions
            'count'               => 0, // (int) questions in the quiz
            'question_show_count' => 0, // (int) questions shown to the user (eg if questions are randomly selected)
            'pass'                => 0, // (int) 1 if passed, 0 if not passed
            'rank'                => '-',
            'time'                => 0, // (int) timestamp of saving (in LD that's normally when completed)
            'pro_quizid'          => 0, // (int|string)
            'course'              => 0, // (int)
            'lesson'              => 0, // (int)
            'topic'               => 0, // (int)
            // Since v4.14.0 LearnDash allows floats for points and total_points
            // and uses learndash_format_course_points to format them.
            // But in bizExaminer these values are still integers.
            'points'              => 0, // (int|float) received points
            'total_points'        => 0, // (int|float) learndash_format_course_points( $points * 100 / $result )
            'percentage'          => 0, // (float/double) points
            'timespent'           => 0,
            'has_graded'          => false, // (bool) whether the quiz has later-graded exams
            'statistic_ref_id'    => 0, // (int) statistic reference id, LD defaults to 0
            'started'             => 0, // (int) timestamp started
            'completed'           => 0, // (int) timestamp completed
            'ld_version'          => LEARNDASH_VERSION,
            'quiz_key'            => '', // (string) - "unique" id - see below for generation

            'bizExaminer'         => 1,
            'be_status'           => '',
            'be_contentRevision'  => null,
            'be_participant'      => '',
            'be_booking'          => '',
            'be_attendance'       => '',
            'be_id'               => '',
            'be_key'              => '',
            'be_certificate'      => '',
            'be_has_results'      => 0,
            'be_valid_until'      => 0, // (int) timestamp of the date passed as validTo to createBooking
        ]);

        /**
         * Allways updated quiz_key with new data
         */
        $this->data['quiz_key'] = self::generateQuizKey($this->quizId, $this->data);
    }

    /**
     * Updates data in this QuizAttempt instance data object
     * only updates allowed fillable keys
     *
     * @see FILLABLE_KEYS
     *
     * @param array $data
     * @return void
     */
    public function updateData(array $data = []): void
    {
        $allowedUpdatedData = array_intersect_key(
            $data,
            array_flip(self::FILLABLE_KEYS)
        );

        $this->data = array_merge($this->data, $allowedUpdatedData);

        /**
         * Allways updated quiz_key with new data
         */
        $this->data['quiz_key'] = self::generateQuizKey($this->quizId, $this->data);
    }

    /**
     * Gets the quiz id this quiz attempt belongs to (post id)
     *
     * @return int
     */
    public function getQuizId(): int
    {
        return $this->quizId;
    }

    /**
     * Gets the user id this quiz attempt belongs to
     *
     * @return int
     */
    public function getUserId(): int
    {
        return $this->userId;
    }

    /**
     * Gets the quiz attempt id used by bizExaminer
     *
     * @return string|null
     */
    public function getId(): ?string
    {
        if (!empty($this->data['be_id'])) {
            return $this->data['be_id'];
        }
        return null;
    }

    /**
     * Gets the bizExaminer status of this quiz attempt
     *
     * @return string
     */
    public function getStatus(): string
    {
        return $this->data['be_status'];
    }

    /**
     * Gets the bizExaminer secure key which allows updates via API
     *
     * @return string|null
     */
    public function getKey(): ?string
    {
        if (!empty($this->data['be_key'])) {
            return $this->data['be_key'];
        }
        return null;
    }

    /**
     * Gets all the data stored in this quiz attempt
     *
     * @see setData for keys
     *
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Get a specific data entry
     *
     * @param string $key
     * @return ?mixed
     */
    public function get(string $key)
    {
        if (isset($this->data[$key])) {
            return $this->data[$key];
        }
        return null;
    }

    /**
     * Whether this quiz attempt has stored & valid results
     *
     * @return bool
     */
    public function hasResults(): bool
    {
        $results = $this->getResults();
        // check for total_points for backwards compatibility
        // do not check for >0 for points and percentage because a user might get 0 points
        // but total_points is the possible points and only set when results are available
        return $this->get('be_has_results') === 1 || (isset($results['points']) && $results['total_points'] > 0);
    }

    /**
     * Gets all results related data
     *
     * @return array
     */
    public function getResults(): array
    {
        $results = [
            'pass' => $this->get('pass'),
            'points' => $this->get('points'),
            'total_points' => $this->get('total_points'),
            'percentage' => $this->get('percentage'),
            'timespent' => $this->get('timespent'),
            'completed' => $this->get('completed'),
            'has_graded' => $this->get('has_graded'),
            'score' => $this->get('score'),
            'question_show_count' => $this->get('question_show_count'),
            'count' => $this->get('count'),
            'certificate' => $this->getCertificate()
        ];

        // Since v4.14.0 LearnDash allows floats for points and total_points
        // and uses learndash_format_course_points to format them.
        $learnDashVersion = Plugin::getInstance()->getContainer()->get('learndash')->getLearnDashVersion();
        if ($learnDashVersion && version_compare($learnDashVersion, '4.14.0', '>=')) {
            $results['points'] = learndash_format_course_points($results['points']);
            $results['total_points'] = learndash_format_course_points($results['total_points']);
        }

        return $results;
    }

    /**
     * Gets the link to the certificate if any is configured and if the threshold is met
     *
     * @return string|false false if the threshold is not met or no results available, url on success
     */
    public function getCertificate()
    {
        $certificateDetails = learndash_certificate_details($this->quizId, $this->userId);
        if (empty($certificateDetails)) {
            return false;
        }
        if (
            isset($certificateDetails['certificate_threshold'])
            && floatval($certificateDetails['certificate_threshold']) >= 0
        ) {
            // floatval($this->get('percentage')) will be 0 if no results yet
            if ((floatval($this->get('percentage')) / 100) < floatval($certificateDetails['certificate_threshold'])) {
                return false;
            }
        }
        return $certificateDetails['certificateLink'];
    }

    /**
     * If the bizExaminer security key is valid
     *
     * @param string $key
     * @return bool
     */
    public function isKeyValid(string $key): bool
    {
        return hash_equals($this->getKey(), $key);
    }

    /**
     * Generates a nearly-unique id to which to refer to within the bizExaminer code
     * will not be the index in the stored quiz attempts, but a value inside data array
     *
     * @since 1.4.0 only use content revision id for building id, because it's unique enough.
     *
     * @param int $quizId
     * @param array $data quizattempt data
     * @return string
     */
    protected static function generateId(int $quizId, array $data): string
    {
        $id = $data['time'] . '_' . absint($quizId) . '_' . absint($data['be_contentRevision']);

        return $id;
    }

    /**
     * Generate an quiz attempt secret key with prefix.
     * By default generates a 16 digit secret + prefix = 22chars.
     *
     * @return string The secret key (22chars)
     */
    protected static function generateKey(): string
    {
        $key = wp_generate_password(16, false);
        return 'be-qa_' . $key;
    }

    /**
     * Generates the quiz key used by LearnDash
     *
     * @see ld-quiz-pro.php#1273
     * @watch ld-quiz-pro.php#1273
     *
     * @param int $quizId
     * @param array $data quizattempt data
     * @return string
     */
    protected static function generateQuizKey(int $quizId, array $data): string
    {
        return $data['completed'] . '_' .
            absint($data['pro_quizid']) . '_' . absint($quizId) . '_' . (absint($data['course']));
    }
}
