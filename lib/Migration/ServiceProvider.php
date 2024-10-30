<?php

namespace BizExaminer\LearnDashExtension\Migration;

use BizExaminer\LearnDashExtension\Internal\AbstractServiceProvider;

/**
 * Service provider for classes/services related to migration/activation/deactivation
 */
class ServiceProvider extends AbstractServiceProvider
{
    protected array $provides = [
        'migration.subscriber',
        'activation',
        'deactivation',
        'migration'
    ];

    public function register(): void
    {
        $this->addShared('migration.subscriber', AdminSubscriber::class)
            ->addArgument('migration')->addTag('subscriber');
        $this->addShared('activation', Activation::class)->addArgument('plugin');
        $this->addShared('deactiviation', Deactivation::class)->addArgument('plugin');
        $this->addShared('migration', function () {
            return new Migration(
                $this->get('plugin')::VERSION,
                $this->get('events')
            );
        });
    }
}
