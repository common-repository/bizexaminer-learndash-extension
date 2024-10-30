<?php

namespace BizExaminer\LearnDashExtension\Migration;

use BizExaminer\LearnDashExtension\Internal\EventManagement\ActionSubscriberInterface;

/**
 * Subscriber for migration/activation/deactiviation related hooks in wp-admin
 */
class AdminSubscriber implements ActionSubscriberInterface
{
    /**
     * The migration service instance to use
     *
     * @var Migration
     */
    protected Migration $migration;

    /**
     * Creates a new AdminSubscriber instance
     *
     * @param Migration $migration The migration service instance to use
     */
    public function __construct(Migration $migration)
    {
        $this->migration = $migration;
    }

    public function getSubscribedActions(): array
    {
        return [
            'admin_init' => 'maybeRunMigration'
        ];
    }

    /**
     * Checks for migration and runs migration
     *
     * @hooked on 'admin_init' (each admin page realod, not on frontend)
     *
     * @return void
     */
    public function maybeRunMigration(): void
    {
        $this->migration->checkMigration();
    }
}
