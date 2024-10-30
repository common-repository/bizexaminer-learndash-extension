<?php

namespace BizExaminer\LearnDashExtension\Internal\Interfaces;

/**
 * Interface that signals that there's something to do on plugin deactivation
 */
interface DeactivationInterface
{
    /**
     * Executes this method on plugin deactivation
     *
     * @return void
     */
    public function deactivate(): void;
}
