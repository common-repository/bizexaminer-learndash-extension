<?php

namespace BizExaminer\LearnDashExtension\Internal\Interfaces;

use BizExaminer\LearnDashExtension\Internal\EventManagement\EventManager;

/**
 * Interface to signal usage of a EventManager
 * @see EventManagerAwareTrait
 */
interface EventManagerAwareInterface
{
    /**
     * Sets the EventManager instance
     *
     * @param EventManager $eventManager
     * @return void
     */
    public function setEventManager(EventManager $eventManager): void;
}
