<?php

namespace BizExaminer\LearnDashExtension\Admin;

use BizExaminer\LearnDashExtension\Internal\AbstractServiceProvider;

/**
 * Service Provider for wp-admin
 */
class ServiceProvider extends AbstractServiceProvider
{
    protected array $provides = [
        'admin',
        'admin.subscriber'
    ];

    public function register(): void
    {

        $this->addShared('admin.subscriber', AdminSubscriber::class)
            ->addArgument('admin')->addTag('subscriber.admin');

        $this->addShared('admin', AdminService::class)->addArgument('errors');
    }
}
