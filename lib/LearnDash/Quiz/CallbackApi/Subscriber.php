<?php

namespace BizExaminer\LearnDashExtension\LearnDash\Quiz\CallbackApi;

use BizExaminer\LearnDashExtension\Internal\Interfaces\ActivationInterface;
use BizExaminer\LearnDashExtension\Internal\Interfaces\DeactivationInterface;
use BizExaminer\LearnDashExtension\Internal\EventManagement\ActionSubscriberInterface;
use BizExaminer\LearnDashExtension\Internal\Interfaces\MigrationInterface;

/**
 * Subscriber for LearnDash callback API
 */
class Subscriber implements ActionSubscriberInterface, ActivationInterface, DeactivationInterface, MigrationInterface
{
    /**
     * Callback API router instance
     *
     * @var Router
     */
    protected Router $router;

    /**
     * Creates a new Subscriber instance
     *
     * @param Router $router Callback API router instance to use
     */
    public function __construct(Router $router)
    {
        $this->router = $router;
    }

    public function getSubscribedActions(): array
    {
        return [
            'init' => 'addRewriteRules',
            'parse_request' => 'maybeHandleRequest',
            'bizexaminer/activation' => 'activate',
            'bizexaminer/deactivation' => 'deactivate',
            /**
             * in case LearnDash gets activated after this plugin
             * may not get called, depending on hook order / plugins_loaded/activate_plugin
             */
            'learndash_activated' => 'activate',
            'learndash_deactivated' => 'deactivate',
            // call migration, to also flush_rewrite_rules
            'bizexaminer/migration' => ['migrate', 10, 2]
        ];
    }

    /**
     * Adds rewrite rules to WordPress
     * @hooked on 'init'
     *
     * @return void
     */
    public function addRewriteRules(): void
    {
        $this->router->addRewriteRules();
    }

    /**
     * Adds rewrite rules on activation of plugin
     *
     * @hooked on 'bizexaminer/activation' and 'learndash_activated'
     *
     * @return void
     */
    public function activate(): void
    {
        $this->router->addRewriteRules();
        flush_rewrite_rules();
        update_option('bizexaminer_quiz_callback_api_flushed_rewrite_rules', true, false);
    }

    /**
     * Removes/flushes rewrite rules on deactivation of plugin
     *
     * @hooked on 'bizexaminer/deactivation' and 'learndash_deactivated'
     *
     * @return void
     */
    public function deactivate(): void
    {
        flush_rewrite_rules();
    }

    /**
     * Maybe flushes rewrite rules on migration
     *
     * @hooked on bizexaminer/migration
     *
     * @param string $newVersion the new version to upgrade to
     * @param string $oldVersion the old/current version
     * @return void
     */
    public function migrate(string $newVersion, string $oldVersion): void
    {
        if (!get_option('bizexaminer_quiz_callback_api_flushed_rewrite_rules', false)) {
            flush_rewrite_rules();
            update_option('bizexaminer_quiz_callback_api_flushed_rewrite_rules', true, false);
        }
    }

    /**
     * Handle requests to the custom callback api which handles exam return urls and webhooks
     *
     * @hooked on 'parse_request'
     *
     * @param \WP $wp
     * @return void
     */
    public function maybeHandleRequest($wp): void
    {
        if ($this->router->shouldHandleRequest($wp->query_vars)) {
            $this->router->handleRequest($wp->query_vars);
        }
    }
}
