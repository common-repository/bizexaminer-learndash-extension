<?php
// phpcs:ignoreFile PSR1.Files.SideEffects

/**
 * Plugin Name: bizExaminer LearnDash Extension
 * Plugin URI: https://www.bizexaminer.com/
 * Description: An extension for LearnDash to connect with bizExaminer
 * Version: 1.5.1
 *
 * Requires at least: 6.0
 * Requires PHP: 7.4
 *
 * LearnDash requires at least: 4.3
 * LearnDash tested up to: 4.15.2
 *
 * Author: bizDevelop
 * Text Domain: bizexaminer-learndash-extension
 * Domain Path: /languages
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 */

namespace BizExaminer\LearnDashExtension;

use BizExaminer\LearnDashExtension\Migration\Activation;
use BizExaminer\LearnDashExtension\Migration\Deactivation;

defined('ABSPATH') || exit;

require_once __DIR__ . '/vendor/autoload.php';

define('BIZEXAMINER_LEARNDASH_FILE', __FILE__);

/**
 * Boot the complete plugin only on plugins_loaded
 * When all other plugins are loaded
 */
function init()
{
    /** @var Plugin */
    $bizExaminerPlugin = null;
    try {
        /**
         * try to get an already initialized instance which was loaded on activation/elsewhere
         * so ->init does not trigger two times (eg event manager adds events a second time)
         * because activating runs before plugins_loaded
         */
        $bizExaminerPlugin = Plugin::getInstance();
    } catch (\Exception $exception) {
        // if there's no existing instance, create a new one and init it
        $bizExaminerPlugin = Plugin::create(BIZEXAMINER_LEARNDASH_FILE);
        $bizExaminerPlugin->init();
    }
}
add_action('plugins_loaded', __NAMESPACE__ . '\init');

/**
 * When activation/deactivation happens, plugins_loaded is never triggered
 * therefore our plugin is never initialized
 */
register_activation_hook(__FILE__, [Activation::class, 'runActivation']);
register_deactivation_hook(__FILE__, [Deactivation::class, 'runDeactivation']);
