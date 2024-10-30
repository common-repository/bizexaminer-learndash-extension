<?php

namespace BizExaminer\LearnDashExtension\LearnDash;

use BizExaminer\LearnDashExtension\Internal\EventManagement\ActionSubscriberInterface;

/**
 * Main subscriber for quiz frontend and cron job hooks
 */
class Subscriber implements ActionSubscriberInterface
{
    /**
     * The Shortcodes instance to use
     *
     * @var Shortcodes
     */
    protected Shortcodes $shortcodes;

    /**
     * Creates a new SettingsService Instance
     *
     * @param Shortcodes $shortcodes The Shortcodes instance to use
     */
    public function __construct(Shortcodes $shortcodes)
    {
        $this->shortcodes = $shortcodes;
    }

    public function getSubscribedActions(): array
    {
        return [
            'init' => ['registerShortcodes'],
        ];
    }

    /**
     * Registers shortcodes to display a "Import attempts" table and button
     *
     * @return void
     */
    public function registerShortcodes(): void
    {
        add_shortcode('be_import_attempts_button', [$this->shortcodes, 'renderImportAttemptsButton']);
        add_shortcode('be_import_attempts_table', [$this->shortcodes, 'renderImportableQuizAttemptsTable']);
    }
}
