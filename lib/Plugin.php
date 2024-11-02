<?php

namespace BizExaminer\LearnDashExtension;

use BizExaminer\LearnDashExtension\Core\LearnDashService;
use BizExaminer\LearnDashExtension\Internal\EventManagement\EventManager;
use BizExaminer\LearnDashExtension\Vendor\League\Container\Container;
use BizExaminer\LearnDashExtension\Vendor\League\Container\ServiceProvider\AbstractServiceProvider;

/**
 * The main plugin class which initializes DI-Container, core services and everything else
 */
class Plugin
{
    /**
     * Current version of the plugin as of source code
     *
     * @var string
     */
    public const VERSION = '1.5.2';

    /**
     * Store one "main" instance as a kind of singleton
     * But still allow creating other instances
     *
     * @var Plugin
     */
    private static Plugin $instance;

    /**
     * Get the current plugin main instance
     *
     * @return Plugin
     */
    public static function getInstance(): Plugin
    {
        if (!isset(self::$instance)) {
            throw new \Exception('No Plugin instance initialized / set yet.');
        }

        return self::$instance;
    }

    /**
     * Create a plugin instance (only if there's not already an instance)
     *
     * @param string $pluginFile
     * @return Plugin
     */
    public static function create(string $pluginFile): Plugin
    {
        if (!isset(self::$instance)) {
            $plugin = new Plugin($pluginFile);
            self::$instance = $plugin;
        }
        return self::$instance;
    }

    /**
     * Directory (absolute path) of the plugin
     *
     * @var string
     */
    protected string $path = '';

    /**
     * Main plugin file (used for plugin urls)
     *
     * @var string
     */
    protected string $file = '';

    /**
     * The DI container
     *
     * @var Container
     */
    protected Container $container;

    /**
     * Creates a new plugin instance main plugin file
     *
     * @param string $pluginFile
     */
    public function __construct(string $pluginFile)
    {
        $this->file = $pluginFile;
        $this->path = dirname($pluginFile);
    }

    /**
     * Initialize this plugin instance
     * Init all services, register it in WordPress, bootup services
     *
     * @return void
     */
    public function init(): void
    {
        $this->initContainer(); // 1. init container & common service providers
        $this->initLearnDashCompat(); // 2. check for LearnDash and init LearnDash service providers
        $this->initEventManager(); // 3. init event manager (hooks)

        /**
         * Allows doing something / outputting something after the plugin is initialized
         *
         * @param Plugin $plugin The plugin instance
         */
        $this->container->get('events')->do_action('bizexaminer/init', $this);
    }

    /**
     * Gets the directory (absolute path) of the plugin
     *
     * @return string the full path to the plugin directory, without trailing slash
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Gets the main plugin file
     *
     * @return string
     */
    public function getPluginFile(): string
    {
        return $this->file;
    }

    /**
     * Get the DI container instance
     *
     * @throws \Exception if container is not initialized yet
     *
     * @return Container
     */
    public function getContainer(): Container
    {
        if (!isset($this->container)) {
            throw new \Exception('Container was not initialized yet.');
        }
        return $this->container;
    }

    /**
     * Checks for LearnDash requirements/compatibility
     * and adds all LearnDash related ServiceProviders to the Container
     *
     * @return void
     */
    protected function initLearnDashCompat(): void
    {
        /** @var LearnDashService */
        $learnDashService = $this->container->get('learndash');

        if (!$learnDashService->isLearnDashAvailable()) {
            $this->container->get('errors')->addError(
                new \WP_Error(
                    'learndash-requirement-not-met',
                    sprintf(
                        /* translators: %s is replaced with the minimum required LearnDash version */
                        __(
                            'bizExaminer LearnDash Extension requires LearnDash.
                    The plugin is not installed, activated or does not meet the required minimum version %s.
                    Please check, install or update LearnDash.',
                            'bizexaminer-learndash-extension'
                        ),
                        $learnDashService::MIN_VERSION
                    )
                ),
                'admin'
            );
            return;
        }

        foreach ($this->getLearnDashServiceProviders() as $providerClass) {
            /** @var AbstractServiceProvider */
            $provider = new $providerClass();
            $this->container->addServiceProvider($provider);
        }
    }

    /**
     * Initializes the container and adds all base/common service providers
     *
     * @return void
     */
    protected function initContainer(): void
    {
        $container = new Container();
        $container->addShared('plugin', [self::class, 'getInstance']);

        /**
         * set BIZEXAMINER_LEARNDASH_DEBUG to 0 (no logs), 1 (error logs) or 2 (debug logs)
         * and set LEARNDASH_QUIZ_DEBUG to true
         */
        $container->add('debug_mode', defined('BIZEXAMINER_LEARNDASH_DEBUG') ? BIZEXAMINER_LEARNDASH_DEBUG : 0);

        /**
         * Use setter injection for classes implementing these interfaces (eg via trait) automatically
         */
        $container->inflector(Internal\Interfaces\AssetAwareInterface::class)
            ->invokeMethod('setAssetService', ['assets']);
        $container->inflector(Internal\Interfaces\ApiAwareInterface::class)
            ->invokeMethod('setApiService', ['api']);
        $container->inflector(Internal\Interfaces\EventManagerAwareInterface::class)
            ->invokeMethod('setEventManager', ['events']);
        $container->inflector(Internal\Interfaces\SettingsServiceAwareInterface::class)
            ->invokeMethod('setSettingsService', ['learndash.settings']);
        $container->inflector(Internal\Interfaces\TemplateAwareInterface::class)
            ->invokeMethod('setTemplateService', ['templates']);
        $container->inflector(Internal\Interfaces\AdminTemplateAwareInterface::class)
            ->invokeMethod('setTemplateService', ['templates.admin']);
        $container->inflector(Internal\Interfaces\CacheAwareInterface::class)
            ->invokeMethod('setCacheService', ['cache']);
        $container->inflector(Internal\Interfaces\ErrorServiceAwareInterface::class)
            ->invokeMethod('setErrorService', ['errors']);
        $container->inflector(Internal\Interfaces\LogServiceAwareInterface::class)
            ->invokeMethod('setLogService', ['logs']);

        foreach ($this->getServiceProviders() as $providerClass) {
            /** @var AbstractServiceProvider */
            $provider = new $providerClass();
            $container->addServiceProvider($provider);
        }

        $this->container = $container;
    }

    /**
     * Inits the EventManager and adds all subscribers
     *
     * @return void
     */
    protected function initEventManager(): void
    {
        $eventManager = new EventManager();
        $this->container->addShared('events', $eventManager); // may be required by some subscribers as dependencies
        foreach ($this->getSubscribers() as $subscriber) {
            // may be a LearnDash subscriber which is not loaded
            if ($this->container->has($subscriber)) {
                $eventManager->addSubscriber($this->container->get($subscriber));
            }
        }
    }

    /**
     * Service providers to initialize
     *
     * @return string[] Array of classnames of service providers - must all extend AbstractServiceProvider
     */
    protected function getServiceProviders(): array
    {
        return [
            Core\ServiceProvider::class,
            Migration\ServiceProvider::class,
            Admin\ServiceProvider::class,
            Api\ServiceProvider::class,
        ];
    }

    /**
     * Service providers to initialize only when LearnDash Requirements are met
     *
     * @return string[] Array of classnames of service providers - must all extend AbstractServiceProvider
     */
    protected function getLearnDashServiceProviders(): array
    {
        return [
            LearnDash\ServiceProvider::class,
            LearnDash\Settings\ServiceProvider::class,
            LearnDash\Quiz\ServiceProvider::class,
        ];
    }

    /**
     * Event Subscribers to add to the event manager
     *
     * PHP-League DI Container supports lazy loading of services
     * But since most services are used in subscribers anywhere,
     * and subscribers all get created on load (so they can be registered)
     * all dependencies of subscribers (and their complete dependency chain) will be resolved on each load
     * Therefore try to only load subscribers which are really needed
     *
     * @return string[] Array of container elements which are subscribers
     */
    protected function getSubscribers(): array
    {
        // Does not work atm, because tagged services (incl. their dependencies) would need to be defined in boot
        // = eagerly loaded not lazy loaded; ATM all dependencies of subscribers get loaded on each page load
        // // Subscribers should be tagged in the container with 'subscriber'
        // $subscribers = $this->container->get('subscriber');
        // if (is_admin()) {
        //     $subscribers = array_merge($this->container->get('subscriber.admin'), $subscribers);
        // }
        $subscribers = [
            'core.subscriber',
            'learndash.subscriber',
            'learndash.quiz.subscriber',
            'learndash.quiz.api.subscriber'
        ];

        if (is_admin()) {
            $subscribers = array_merge([
                'admin.subscriber',
                'migration.subscriber',
                /**
                 * LearnDash actually triggers the events to register section settings in the frontend as well
                 * but we do not need it there
                 */
                'learndash.settings.subscriber.admin',
                'learndash.quiz.settings.subscriber.admin',
            ], $subscribers);
        }
        return $subscribers;
    }
}
