<?php

namespace BizExaminer\LearnDashExtension\Migration;

use BizExaminer\LearnDashExtension\Plugin;
use BizExaminer\LearnDashExtension\Internal\EventManagement\EventManager;

/**
 * Service for handling plugin activation
 */
class Activation
{
    /**
     * When activation/deactivation happens, plugins_loaded is never triggered
     * therefore the plugin is never initialized
     *
     * Initialize a new instance here
     *
     * @hooked on plugin activation
     * @triggers 'bizexaminer/activation' action
     *
     * @return void
     */
    public static function runActivation(): void
    {
        /** @var Plugin */
        $bizExaminerPlugin = null;
        try {
            /**
             * try to get an already initialized instance which was loaded on plugins_loaded
             * so ->init does not trigger two times (eg event manager adds events a second time)
             * allthough activating runs after plugins_loaded so this will probably never happen
             */
            $bizExaminerPlugin = Plugin::getInstance();
        } catch (\Exception $exception) {
            // if there's no existing instance, create a new one and init it
            $bizExaminerPlugin = Plugin::create(BIZEXAMINER_LEARNDASH_FILE);
            $bizExaminerPlugin->init();
        }

        /** @var EventManager */
        $eventManager = $bizExaminerPlugin->getContainer()->get('events');
        $eventManager->do_action('bizexaminer/activation');
    }
}
