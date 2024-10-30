<?php

namespace BizExaminer\LearnDashExtension\Internal\Traits;

use BizExaminer\LearnDashExtension\Internal\EventManagement\EventManager;

/**
 * Adds setter and (protected) getter for the setter-injection of the EventManager
 * @see EventManagerAwareInterface
 */
trait EventManagerAwareTrait
{
    /**
     * The injected EventManager instance to use
     *
     * @var EventManager
     */
    protected EventManager $eventManager;

    public function setEventManager(EventManager $eventManager): void
    {
        $this->eventManager = $eventManager;
    }
}
