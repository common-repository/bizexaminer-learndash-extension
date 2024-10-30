<?php

namespace BizExaminer\LearnDashExtension\LearnDash;

use BizExaminer\LearnDashExtension\Internal\AbstractServiceProvider;

/**
 * The core LearnDash service provider
 */
class ServiceProvider extends AbstractServiceProvider
{
    protected array $provides = [
        'learndash.certificates',
        'learndash.subscriber',
        'learndash.shortcodes'
    ];

    public function register(): void
    {
        /**
         * Subscriber
         */
        $this->addShared('learndash.subscriber', Subscriber::class)
            ->addArgument('learndash.shortcodes')->addTag('subscriber');

        $this->addShared('learndash.certificates', CertificatesService::class);
        $this->addShared('learndash.shortcodes', Shortcodes::class)
            ->addArgument('learndash.quiz')->addArgument('learndash.quiz.settings')
            ->addArgument('learndash.quiz.api')->addArgument('learndash.quiz.access');
    }
}
