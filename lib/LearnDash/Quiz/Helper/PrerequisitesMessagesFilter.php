<?php

namespace BizExaminer\LearnDashExtension\LearnDash\Quiz\Helper;

/**
 * LearnDash Messages filter for missing quiz prerequisites
 */
class PrerequisitesMessagesFilter extends AbstractMessageFilter
{
    /**
     * A comma separated list of missing quiz (names) prerequisites
     *
     * @var string
     */
    protected string $missingPrerequisites;

    /**
     * Creates a new PrerequisitesMessagesFilter instance
     *
     * @param string[] $missingPrerequisites names of missing prerequisites
     */
    public function __construct(array $missingPrerequisites)
    {
        $this->missingPrerequisites = implode(',', $missingPrerequisites);
    }

    /**
     * Sets the missing prerequisites in the message
     *
     * @inheritDoc
     *
     * @param array $templateArgs
     * @return array
     */
    public function filterMessageTemplateArgs(array $templateArgs): array
    {
        if (empty($this->missingPrerequisites)) {
            return $templateArgs;
        }

        switch ($templateArgs['context']) {
            case 'quiz_prerequisite_message':
                $templateArgs['placeholders'] = [
                    $this->missingPrerequisites, // name of missing quizes
                ];
                // "<p>You must first complete the following: <span></span></p>"
                $templateArgs['message'] = str_replace(
                    '<span></span>',
                    '<span>' . $this->missingPrerequisites . '</span>',
                    $templateArgs['message']
                );
                break;
        }
        return $templateArgs;
    }
}
