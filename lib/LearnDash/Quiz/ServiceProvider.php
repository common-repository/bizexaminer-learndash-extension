<?php

namespace BizExaminer\LearnDashExtension\LearnDash\Quiz;

use BizExaminer\LearnDashExtension\Internal\AbstractServiceProvider;
use BizExaminer\LearnDashExtension\LearnDash\Quiz\QuizSettings\QuizSettingsService;

/**
 * Main service provider for services/classes related to quizes
 */
class ServiceProvider extends AbstractServiceProvider
{
    protected array $provides = [
        'learndash.quiz.subscriber',
        'learndash.quiz',
        'learndash.quiz.access',
        'learndash.quiz.frontend',
        'learndash.quiz.settings',
        'learndash.quiz.settings.subscriber.admin',
        'learndash.quiz.settings.metabox.helper',
        QuizSettings\MetaBox::class,
        'learndash.quiz.attempts',
        'learndash.quiz.api',
        'learndash.quiz.api.subscriber',
        'learndash.quiz.api.router',
        'learndash.quiz.api.controller',
    ];

    public function register(): void
    {
        /**
         * Quiz Service
         */
        $this->addShared('learndash.quiz', QuizService::class)
            ->addArgument('learndash.quiz.settings')
            ->addArgument('learndash.quiz.attempts')
            ->addArgument('learndash.quiz.api')
            ->addArgument('api.exam-modules')
            ->addArgument('api.remote-proctors')
            ->addArgument('learndash.quiz.access');

        /**
         * Quiz Frontend
         */
        $this->addShared('learndash.quiz.frontend', QuizFrontend::class)
            ->addArgument('learndash.quiz')
            ->addArgument('learndash.quiz.settings')
            ->addArgument('learndash.quiz.attempts')
            ->addArgument('learndash.quiz.api')
            ->addArgument('learndash.quiz.access')
            ->addArgument('learndash.certificates');

        $this->addShared('learndash.quiz.access', QuizAccess::class)
            ->addArgument('learndash.quiz.attempts');

        /**
         * Quiz Attempts Service
         */
        $this->addShared('learndash.quiz.attempts', QuizAttemptsDataStore::class);

        /**
         * Subscriber
         */
        $this->addShared('learndash.quiz.subscriber', Subscriber::class)
            ->addArgument('learndash.quiz')->addArgument('learndash.quiz.frontend')
            ->addArgument('learndash.quiz.settings')->addTag('subscriber');

        /**
         * Quiz Settings & Metabox
         */
        $this->addShared('learndash.quiz.settings', QuizSettingsService::class);
        $this->addShared('learndash.quiz.settings.subscriber.admin', QuizSettings\AdminSubscriber::class)
            ->addArgument(QuizSettings\MetaBox::class)->addArgument('learndash.quiz.settings.metabox.helper')
            ->addTag('subscriber.admin');
        $this->addShared('learndash.quiz.settings.metabox.helper', QuizSettings\MetaBoxHelper::class)
            ->addArgument('api.exam-modules')->addArgument('api.remote-proctors')
            ->addArgument('learndash.certificates');
        $this->addShared(QuizSettings\MetaBox::class, function () {
            /** @var QuizSettings\MetaBox */
            $instance = QuizSettings\MetaBox::add_metabox_instance();
            $instance->setMetaBoxHelper($this->get('learndash.quiz.settings.metabox.helper'));
            $metaboxes[QuizSettings\MetaBox::class] = $instance;
            return $instance;
        });

        /**
         * Callback Api
         */
        $this->addShared('learndash.quiz.api', CallbackApi\CallbackApiService::class);
        $this->addShared('learndash.quiz.api.subscriber', CallbackApi\Subscriber::class)
            ->addArgument('learndash.quiz.api.router')->addTag('subscriber');
        $this->addShared('learndash.quiz.api.router', CallbackApi\Router::class)
            ->addArgument('learndash.quiz.api.controller')->addArgument('logs');
        $this->addShared('learndash.quiz.api.controller', CallbackApi\Controller::class)
            ->addArgument('learndash.quiz')->addArgument('learndash.quiz.attempts');
    }
}
