<?php

namespace BizExaminer\LearnDashExtension\Internal\Interfaces;

/**
 * Interface that signals that there's something to do on plugin activation
 */
interface ActivationInterface
{
    /**
     * Executes this method on plugin activation
     *
     * @return void
     */
    public function activate(): void;
}
