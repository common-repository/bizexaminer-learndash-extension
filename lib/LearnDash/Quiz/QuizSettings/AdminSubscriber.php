<?php

namespace BizExaminer\LearnDashExtension\LearnDash\Quiz\QuizSettings;

use BizExaminer\LearnDashExtension\Helper\Util;
use BizExaminer\LearnDashExtension\Internal\EventManagement\ActionSubscriberInterface;
use BizExaminer\LearnDashExtension\Internal\EventManagement\FilterSubscriberInterface;
use BizExaminer\LearnDashExtension\LearnDash\Quiz\QuizSettings\MetaBox;

/**
 * Subscriber for QuizSettings related hooks in wp-admin
 */
class AdminSubscriber implements ActionSubscriberInterface, FilterSubscriberInterface
{
    /**
     * MetaBox instance to use
     *
     * @var MetaBox
     */
    protected MetaBox $settingsMetaBox;

    /**
     * MetaBoxHelper instance to use
     *
     * @var MetaBoxHelper
     */
    protected MetaBoxHelper $metaBoxHelper;

    /**
     * Creates a new SettingsService Instance
     *
     * @param MetaBox $settingsMetaBox
     * @param MetaBoxHelper $metaBoxHelper
     */
    public function __construct(
        MetaBox $settingsMetaBox,
        MetaBoxHelper $metaBoxHelper
    ) {
        $this->settingsMetaBox = $settingsMetaBox;
        $this->metaBoxHelper = $metaBoxHelper;
    }

    public function getSubscribedFilters(): array
    {
        /**
         * LearnDashs classes initialize and add hooks in the constructor,
         * Instance creation should happen in the container because of some dependencies (via setter injection)
         * But the initialization should only happen on the hooks defined in here
         */
        return [
            'learndash_header_tab_menu' => ['showMetaBoxInTab', 10, 3],
            // LearnDash registers it's metaboxes on 50, so register it after them
            'learndash_post_settings_metaboxes_init_' . learndash_get_post_type_slug('quiz') => ['registerMetaBox', 51],
        ];
    }

    public function getSubscribedActions(): array
    {
        return [
            // run before LearnDash (10), because it wp_dies/exits
            'wp_ajax_learndash_settings_select2_query' => ['getOptionsAjax', 9]
        ];
    }

    /**
     * Register the settings meta box and create a instance with setter dependency injection
     * also registers assets used in the meta box (will be enqueued when rendering )
     *
     * @hooked on 'learndash_post_settings_metaboxes_init_' . learndash_get_post_type_slug('quiz')
     *            'learndash_post_settings_metaboxes_init_sfwd-quiz'
     *
     * @param array $metaboxes
     * @return array
     */
    public function registerMetaBox($metaboxes = []): array
    {
        if (!isset($metaboxes[get_class($this->settingsMetaBox)])) {
            $this->settingsMetaBox->register();
            $metaboxes[get_class($this->settingsMetaBox)] = $this->settingsMetaBox;
        }

        return $metaboxes;
    }

    /**
     * Enables the custom settings meta box to be shown in the settings/quiz builder tab
     *
     * @hooked on learndash_header_tab_menu
     *
     * @param array  $tabs An array of header tabs data.
     * @param string $menu_tab_key     Menu tab key.
     * @param string $screen_post_type Screen post type slug.
     * @return array
     */
    public function showMetaBoxInTab($tabs, $menu_tab_key, $screen_post_type): array
    {
        if ($screen_post_type !== learndash_get_post_type_slug('quiz')) {
            return $tabs;
        }

        foreach ($tabs as $i => $tab) {
            if ($tab['id'] !== 'sfwd-quiz-settings') {
                continue;
            }
            $tabs[$i]['metaboxes'][] = $this->settingsMetaBox::METABOX_KEY;
        }
        return $tabs;
    }


    /**
     * Callback for AJAX Action to get exam module or remote proctors select options via ajax (from select2 library)
     * Should runs on the same hook as LearnDashs core handling, but BEFORE
     * Because LearnDash only support passing query data for getting posts
     * has to run before because LearnDash wp_dies and exits
     *
     * @see class-ld-settings-page.php #905
     * @watch class-ld-settings-page.php #905
     *
     * @return void echos and wp_dies if its a request for a bizExaminer settings field
     */
    public function getOptionsAjax(): void
    {
        if (empty($_POST['query_data']['settings_element']['settings_field'])) {
            return;
        }

        $settingsField = Util::sanitizeInput($_POST['query_data']['settings_element']['settings_field']);

        if (!str_starts_with($settingsField, 'bizExaminer')) {
            return;
        }

        // Do the same security checks as LearnDash
        // TODO: do other capability checks if user can edit quiz settings?
        if (!current_user_can('read') || empty($_POST['query_data'])) {
            return;
        }

        /** @var string[] */
        $post_query_data = Util::sanitizeInput(wp_unslash($_POST['query_data']));

        if (empty($post_query_data['nonce'])) {
            return;
        }

        $post_query_data_nonce = $post_query_data['nonce'];
        unset($post_query_data['nonce']);

        if (empty($post_query_data['settings_element'])) {
            return;
        }

        $post_query_data_json = wp_json_encode($post_query_data['settings_element'], JSON_FORCE_OBJECT);
        if (!wp_verify_nonce($post_query_data_nonce, $post_query_data_json)) {
            return;
        }

        if (empty($post_query_data['api_credentials'])) {
            return;
        }

        $post_query_data['search'] = !empty($_POST['search']) ? Util::sanitizeInput($_POST['search']) : false;

        $results = $this->metaBoxHelper->getAjaxOptions($settingsField, $post_query_data);
        if ($results) {
            echo wp_json_encode($results);
            wp_die();
        }
    }
}
