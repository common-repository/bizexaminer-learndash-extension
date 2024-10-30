<?php

namespace BizExaminer\LearnDashExtension\Core;

use BizExaminer\LearnDashExtension\Internal\AbstractServiceProvider;
use BizExaminer\LearnDashExtension\Plugin;

/**
 * The core service provider for providing the core / global services
 */
class ServiceProvider extends AbstractServiceProvider
{
    protected array $provides = [
        'cache',
        'errors',
        'logs',
        'assets',
        'templates',
        'templates.admin',
        'learndash',
        'i18n',
        'core.subscriber'
    ];

    public function register(): void
    {
        $this->addShared('cache', CacheService::class)
            ->addArgument('be_')->addArgument(HOUR_IN_SECONDS);

        $this->addShared('errors', ErrorService::class);
        $this->addShared('assets', function () {
            /** @var Plugin */
            $plugin = $this->get('plugin');
            return new AssetService($plugin->getPluginFile(), $plugin::VERSION);
        });
        $this->addShared('templates', function () {
            /** @var Plugin */
            $plugin = $this->get('plugin');
            return new TemplateService(
                $plugin->getPath() . DIRECTORY_SEPARATOR . 'templates',
                'bizexaminer'
            );
        });
        $this->addShared('templates.admin', function () {
            /** @var Plugin */
            $plugin = $this->get('plugin');
            return new TemplateService(
                $plugin->getPath() . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'admin',
                null
            );
        });

        $this->addShared('logs', function () {
            $learnDashVersion = $this->get('learndash')->getLearnDashVersion();
            if ($learnDashVersion && version_compare($learnDashVersion, '4.5.0', '>=')) {
                return new LogService($this->get('debug_mode'), new LearnDashLogger());
            } else {
                return new DeprecatedLogService($this->get('debug_mode'));
            }
        });


        $this->addShared('learndash', LearnDashService::class);

        $this->addShared('i18n', function () {
            /** @var Plugin */
            $plugin = $this->get('plugin');
            return new I18n($plugin->getPath());
        });

        /**
         * Subscriber
         */
        $this->addShared('core.subscriber', Subscriber::class)
            ->addArgument('i18n')->addArgument('logs')->addTag('subscriber');
    }
}
