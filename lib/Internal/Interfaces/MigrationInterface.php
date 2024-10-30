<?php

namespace BizExaminer\LearnDashExtension\Internal\Interfaces;

/**
 * Interface that signals that there's something to do on plugin migration/update
 */
interface MigrationInterface
{
    /**
     * Executes this method on plugin migration
     *
     * @param string $newVersion the new version to upgrade to
     * @param string $oldVersion the old/current version
     * @return void
     */
    public function migrate(string $newVersion, string $oldVersion): void;
}
