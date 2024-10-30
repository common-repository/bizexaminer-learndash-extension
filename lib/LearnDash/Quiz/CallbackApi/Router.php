<?php

namespace BizExaminer\LearnDashExtension\LearnDash\Quiz\CallbackApi;

use BizExaminer\LearnDashExtension\Core\LogService;
use BizExaminer\LearnDashExtension\Helper\Util;
use BizExaminer\LearnDashExtension\Internal\Interfaces\EventManagerAwareInterface;
use BizExaminer\LearnDashExtension\Internal\Traits\EventManagerAwareTrait;
use WP_Error;

/**
 * HTTP-Router for callback API requests
 */
class Router implements EventManagerAwareInterface
{
    use EventManagerAwareTrait;

    /**
     * WordPress rewrite endpoint / path in permalink
     *
     * @var string
     */
    public const PATH = 'be-api';

    /**
     * Allowed actions
     *
     * @var string[]
     */
    public const ACTIONS = [
        'start-exam' => 'start-exam',
        'exam-completed' => 'exam-completed',
        'callback' => 'callback',
        'import-attempts' => 'import-attempts'
    ];

    /**
     * HTTP-Controller instance to use
     *
     * @var Controller
     */
    protected Controller $controller;

    /**
     * LogService instance to use
     *
     * @var LogService
     */
    protected LogService $logService;

    /**
     * Creates a new Router instance
     *
     * @param Controller $controller HTTP-Controller instance to use
     * @param LogService $logService LogService instance to use
     */
    public function __construct(Controller $controller, LogService $logService)
    {
        $this->controller = $controller;
        $this->logService = $logService;
    }

    /**
     * Checks if the request in the passed WP context matches this router
     *
     * @param array $queryVars The query variables from a \WP instance, the global wp_query or $_GET
     * @return bool
     */
    public function shouldHandleRequest(array $queryVars): bool
    {
        // only handle requests on quiz urls
        if (
            empty($queryVars['post_type']) || $queryVars['post_type'] !== 'sfwd-quiz' ||
            empty($queryVars['sfwd-quiz'])
        ) {
            return false;
        }

        $action = $queryVars[self::PATH] ?? null;
        if (!$action) {
            return false;
        }

        return true;
    }

    /**
     * Handle requests to the custom callback api which handles exam return urls and webhooks
     *
     * @param array $queryVars
     * @return void dying (if necessary) should be done by controller,
     *              otherwise just returns and let's rest of WordPress handle request
     */
    public function handleRequest(array $queryVars): void
    {
        $action = $queryVars[self::PATH] ?? null;
        $action = strtolower(sanitize_key(wp_unslash($action)));

        if (empty($action) || !isset(self::ACTIONS[$action])) {
            return;
        }

        Util::setNoCache();

        /**
         * Nonce verification is handled per-action in the controller
         * because some actions need non-expiring/longer-lifetime nonces (like callback URLs)
         */
        $request = array_merge($_REQUEST, $queryVars);

        ob_start();

        /**
         * Allows doing something / outputting something before the request is passed to the controller
         *
         * @param string $action The callback API action to call
         * @param array $request   The complete request vars (merged from $_REQUEST and passed queryVars)
         * @param array $queryVars       The passed query vars
         * @param Controller $controller The callback API controller instance
         */
        $this->eventManager->do_action(
            'bizexaminer/callbackapi/beforeRequest',
            $action,
            $request,
            $queryVars,
            $this->controller
        );

        try {
            switch ($action) {
                case self::ACTIONS['start-exam']:
                    $this->controller->startExam($request);
                    break;
                case self::ACTIONS['exam-completed']:
                    $this->controller->examReturn($request);
                    break;
                case self::ACTIONS['callback']:
                    $this->controller->eventCallback($request);
                    break;
                case self::ACTIONS['import-attempts']:
                    $this->controller->importAttempts($request);
                    break;
            }


            /**
             * Allows doing something / outputting something after the request has been passed to the controller
             *
             * @param mixed|WP_Error $return The return value from the controller
             * @param string $action The callback API action to call
             * @param array $request   The complete request vars (merged from $_REQUEST and passed queryVars)
             * @param array $queryVars       The passed query vars
             * @param Controller $controller The callback API controller instance
             */
            $this->eventManager->do_action(
                'bizexaminer/callbackapi/afterRequest',
                $action,
                $request,
                $queryVars,
                $this->controller
            );
        } catch (\Exception $exception) {

            /**
             * Allows doing something / outputting something after the request has been passed to the controller
             *
             * @param null $return The return value from the controller (null because of error)
             * @param string $action The callback API action to call
             * @param array $request   The complete request vars (merged from $_REQUEST and passed queryVars)
             * @param array $queryVars       The passed query vars
             * @param Controller $controller The callback API controller instance
             */
            $this->eventManager->do_action(
                'bizexaminer/callbackapi/afterRequest',
                null,
                $action,
                $request,
                $queryVars,
                $this->controller
            );
        }

        ob_end_clean();
    }

    /**
     * Registers custom rewrite rules for the custom callback api
     * Should be called on activation (@see Activation)
     *  as well as on init (according to https://make.wordpress.org/plugins/2012/06/07/rewrite-endpoints-api/)
     *
     * @return void
     */
    public function addRewriteRules(): void
    {
        add_rewrite_endpoint(self::PATH, EP_PERMALINK);
        /**
         * normal rewrite rule if pretty urls are not enabled
         * = ^be-api/endpoint -> index.php?be-api=/endpoint
         */
        add_rewrite_rule(
            '^' . self::PATH . '(.*)?',
            'index.php?' . self::PATH . '=$matches[1]',
            'top'
        );
    }
}
