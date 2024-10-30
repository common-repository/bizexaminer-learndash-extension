<?php

namespace BizExaminer\LearnDashExtension\LearnDash\Quiz\Helper;

use BizExaminer\LearnDashExtension\Helper\Util;

/**
 * LearnDash Messages filter for quiz results
 */
class ResultMessagesFilter extends AbstractMessageFilter
{
    /**
     * Quiz attempt results as parsed from QuizService::buildResultsFromRawResults
     *
     * @var array
     */
    protected array $results;

    /**
     * Creates a new ResultMessagesFilter instance
     *
     * @param array $results Quiz attempt results as parsed from QuizService::buildResultsFromRawResults
     */
    public function __construct(array $results)
    {
        $this->results = $results;
    }

    /**
     * Sets the results in the message
     *
     * @inheritDoc
     *
     * @param array $templateArgs
     * @return array
     */
    public function filterMessageTemplateArgs(array $templateArgs): array
    {
        if (empty($this->results)) {
            return $templateArgs;
        }

        switch ($templateArgs['context']) {
            case 'quiz_questions_answered_correctly_message':
                $templateArgs['placeholders'] = [
                    $this->results['score'], // correct questions
                    $this->results['count'] // all questions
                ];
                // <p><span class="wpProQuiz_correct_answer">0</span> of <span>0</span> Questions answered correctly</p>
                $templateArgs['message'] = str_replace(
                    ['<span class="wpProQuiz_correct_answer">0</span>', '<span>0</span>'],
                    [
                        '<span class="wpProQuiz_correct_answer">' . $templateArgs['placeholders'][0] . '</span>',
                        '<span>' . $templateArgs['placeholders'][1] . '</span>'
                    ],
                    $templateArgs['message']
                );
                break;
            case 'quiz_your_time_message':
                $templateArgs['placeholders'] = [
                    /**
                     * time spent on quiz
                     * timespent is time spent in seconds, use gmdate to format (starts at timestamp 0, works)
                     */
                    gmdate('H:i:s', $this->results['timespent'])
                ];
                // "Your time: <span></span>"
                $templateArgs['message'] = str_replace(
                    '<span></span>',
                    '<span>' . $templateArgs['placeholders'][0] . '</span>',
                    $templateArgs['message']
                );
                break;
            case 'quiz_have_reached_points_message':
                $templateArgs['placeholders'] = [
                    $this->results['points'], // reached points
                    $this->results['total_points'], // possible points
                    $this->results['percentage'] . '%' // percentage of reached points
                ];
                // "You have reached <span>0</span> of <span>0</span> point(s), (<span>0</span>)"
                $templateArgs['message'] = Util::str_replace_multiple(
                    '<span>0</span>',
                    [
                        '<span>' . $templateArgs['placeholders'][0] . '</span>',
                        '<span>' . $templateArgs['placeholders'][1] . '</span>',
                        '<span>' . $templateArgs['placeholders'][2] . '</span>'
                    ],
                    $templateArgs['message']
                );
                break;
            case 'quiz_earned_points_message':
                $templateArgs['placeholders'] = [
                    $this->results['points'], // reached points
                    $this->results['total_points'], // possible points
                    $this->results['percentage'] // percentage of reached points
                ];
                // "You have reached <span>0</span> of <span>0</span> point(s), (<span>0</span>)"
                $templateArgs['message'] = Util::str_replace_multiple(
                    '<span>0</span>',
                    [
                        '<span>' . $templateArgs['placeholders'][0] . '</span>',
                        '<span>' . $templateArgs['placeholders'][1] . '</span>',
                        '<span>' . $templateArgs['placeholders'][2] . '</span>'
                    ],
                    $templateArgs['message']
                );
                break;
        }
        return $templateArgs;
    }
}
