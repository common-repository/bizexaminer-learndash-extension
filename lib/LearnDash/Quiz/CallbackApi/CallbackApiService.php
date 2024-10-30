<?php

namespace BizExaminer\LearnDashExtension\LearnDash\Quiz\CallbackApi;

use LearnDash_Settings_Section;

/**
 * Service for everything related to the quiz callback API
 */
class CallbackApiService
{
    /**
     * WordPress rewrite endpoint / path in permalink
     * Should match Router::PATH
     *
     * @var string
     */
    public const API_PATH = 'be-api';

    /**
     * The WordPress query var used if pretty permalinks are disabled
     * stores the action to trigger
     *
     * @var string
     */
    public const API_QUERY_VAR = 'be-api';

    /**
     * Allowed actions
     * Should match Router::ACTIONS
     *
     * @var string[]
     */
    public const API_ACTIONS = [
        'start-exam',
        'exam-completed',
        'callback',
        'import-attempts'
    ];

    /**
     * Builds a URL for the custom callback api to handle request/callbacks
     * @see QuizService::handleApiRequests
     *
     * @param string $action Must be in CallbackApiService::API_ACTIONS
     * @param array $queryArgs
     * @param string|null $baseUrl Base Url to use, or null if current one should be used (@see add_query_arg)
     * @param bool $nonce whether the nonce should be added
     * @return string|false false if not allowed action was passed, otherwise the url
     */
    public function buildCallbackApiUrl(
        string $action,
        array $queryArgs = [],
        ?string $baseUrl = null,
        bool $nonce = true
    ) {
        /** @var \WP_Rewrite */
        global $wp_rewrite;

        if (!in_array($action, self::API_ACTIONS)) {
            return false;
        }

        if ($nonce) {
            $queryArgs['_benonce'] = wp_create_nonce("be-{$action}");
        }

        $queryArgs[self::API_QUERY_VAR] = $action; // store action as query variable with key defined as API_PATH

        // if empty baseUrl, use currentUrl, therefore get currentUrl how add_query_arg generats it
        if (!$baseUrl) {
            $baseUrl = add_query_arg([]);
        }

        /**
         * when LearnDash uses nested URLs the add_rewrite_endpoint does not work
         * fallback to using query parameters
         */
        $learnDashusesNestedUrls = LearnDash_Settings_Section::get_section_setting(
            'LearnDash_Settings_Section_Permalinks',
            'nested_urls'
        );

        if (
            $wp_rewrite->using_permalinks() &&
            $learnDashusesNestedUrls !== 'yes'
        ) {
            // if pretty permalinks are used, use the action as path appended to the base API_PATH
            $action = $queryArgs[self::API_QUERY_VAR];
            $baseUrl = trailingslashit($baseUrl) . self::API_PATH . '/' . $action;
            unset($queryArgs[self::API_QUERY_VAR]);
        }

        $fullUrl = add_query_arg($queryArgs, $baseUrl);
        return $fullUrl;
    }
}
