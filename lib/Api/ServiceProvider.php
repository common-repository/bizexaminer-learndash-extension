<?php

namespace BizExaminer\LearnDashExtension\Api;

use BizExaminer\LearnDashExtension\Internal\AbstractServiceProvider;

class ServiceProvider extends AbstractServiceProvider
{
    protected array $provides = [
        'api',
        'api.exam-modules',
        'api.remote-proctors'
    ];

    public function register(): void
    {
        /**
         * Creating an ApiClient always needs ApiCredentials,
         *  so putting the ApiClient in the container does not make sense
         *  because you can't pass arguments/parameter to the container function
         *
         * Therefore put an ApiService into the container,
         * which allows creating an ApiClient
         *
         * @see QuizService::startQuiz() for an example
         */
        $this->addShared('api', ApiService::class);
        $this->addShared('api.exam-modules', ExamModulesService::class);
        $this->addShared('api.remote-proctors', RemoteProctorsService::class);
        // $this->addShared('api', \BizExaminer\LearnDashExtension\Tests\Mocks\ApiFactory::class); // dummy test api
    }
}
