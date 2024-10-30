<?php

namespace BizExaminer\LearnDashExtension\Migration;

use BizExaminer\LearnDashExtension\Internal\EventManagement\EventManager;

/**
 * Service to handle migration/updates in plugin
 */
class Migration
{
    /**
     * The option key of the plugin version stored in the database
     * = previous version on updates
     */
    protected const OPTION_KEY = 'be_ld_db_version';

    /**
     * Current installed (code) plugin version
     *
     * @var string
     */
    protected string $pluginVersion;

    /**
     * The EventManager instance to use
     *
     * @var EventManager
     */
    protected EventManager $eventManager;

    /**
     * Creates a new Migration service instance
     *
     * @param string $pluginVersion Current installed (code) plugin version
     * @param EventManager $eventManager The EventManager instance to use
     */
    public function __construct(string $pluginVersion, EventManager $eventManager)
    {
        $this->pluginVersion = $pluginVersion;
        $this->eventManager = $eventManager;
    }

    /**
     * Checks if a migration is required depending on the stored version in the database
     *
     * @return void
     */
    public function checkMigration(): void
    {
        $currentVersion = get_option(self::OPTION_KEY, 0);
        if (version_compare($currentVersion, $this->pluginVersion, '<')) {
            $this->runMigration($this->pluginVersion, $currentVersion);
            update_option(self::OPTION_KEY, $this->pluginVersion);
        }
    }

    /**
     * Runs the plugin migration
     *
     * @triggers 'bizexaminer/migration'
     *
     * @param string $newVersion new plugin version
     * @param string $oldVersion old/previous plugin version
     * @return void
     */
    protected function runMigration(string $newVersion, string $oldVersion): void
    {
        $this->eventManager->do_action('bizexaminer/migration', $newVersion, $oldVersion);
    }
}
