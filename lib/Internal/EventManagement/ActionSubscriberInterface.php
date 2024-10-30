<?php

namespace BizExaminer\LearnDashExtension\Internal\EventManagement;

/**
 * A Subscriber knows what specific WordPress actions it wants to listen to.
 *
 * When an EventManager adds a Subscriber, it gets all the WordPress events that
 * it wants to listen to. It then adds the subscriber as a listener for each of them.
 *
 * Inspired by Carl Alexander (carlalexander.ca)
 */
interface ActionSubscriberInterface extends SubscriberInterface
{
    /**
     * Returns an array of actions that this subscriber wants to listen to.
     *
     * The array key is the event name. The value can be:
     *
     *  * The method name
     *  * An array with the method name and priority
     *  * An array with the method name, priority and number of accepted arguments
     *
     * For instance:
     *
     *  * ['hook_name' => 'method_name']
     *  * ['hook_name' => ['method_name', $priority]]
     *  * ['hook_name' => ['method_name', $priority, $accepted_args]]
     *
     * @return array
     */
    public function getSubscribedActions(): array;
}
