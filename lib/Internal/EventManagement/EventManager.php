<?php

namespace BizExaminer\LearnDashExtension\Internal\EventManagement;

use BizExaminer\LearnDashExtension\Internal\Interfaces\EventManagerAwareInterface;

/**
 * The event manager manages events using the WordPress plugin API.
 * Inspired by Carl Alexander (carlalexander.ca)
 */
class EventManager
{
    /**
     * Adds a callback to a specific action of the WordPress plugin API.
     *
     * @uses add_action()
     *
     * @param string   $action     Name of the action.
     * @param callable $callback      Callback function.
     * @param int      $priority      Priority.
     * @param int      $acceptedArgs Number of arguments.
     * @return void
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName -- keep function naming similar to WordPress
    public function add_action($action, $callback, $priority = 10, $acceptedArgs = 1): void
    {
        add_action($action, $callback, $priority, $acceptedArgs);
    }

    /**
     * Adds a callback to a specific filter of the WordPress plugin API.
     *
     * @uses add_filter()
     *
     * @param string   $filter     Name of the filter.
     * @param callable $callback      Callback function.
     * @param int      $priority      Priority.
     * @param int      $acceptedArgs Number of arguments.
     * @return void
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName -- keep function naming similar to WordPress
    public function add_filter($filter, $callback, $priority = 10, $acceptedArgs = 1): void
    {
        add_filter($filter, $callback, $priority, $acceptedArgs);
    }

    /**
     * Calls the callback functions that have been added to an action hook (global, not only in this event manager)
     *
     * @uses do_action()
     *
     * @param string $action The name of the action to be executed.
     * @param mixed  ...$args    Optional. Additional arguments which are passed on to the
     *                          functions hooked to the action. Default empty.
     * @return void
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName -- keep function naming similar to WordPress
    public function do_action($action, ...$args): void
    {
        do_action($action, ...$args);
    }

    /**
     * Calls the callback functions that have been added to an action hook (global, not only in this event manager)
     *
     * @uses apply_filters()
     *
     * @param string $filter The name of the filter hook.
     * @param mixed  $value     The value to filter.
     * @param mixed  ...$args   Additional parameters to pass to the callback functions.
     * @return mixed The filtered value after all hooked functions are applied to it.
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName -- keep function naming similar to WordPress
    public function apply_filters($filter, $value, ...$args)
    {
        return apply_filters($filter, $value, ...$args);
    }

    /**
     * Removes the given callback from the given action. The WordPress plugin API only
     * removes the action if the callback and priority match a registered action.
     *
     * @uses remove_action()
     *
     * @param string   $action Action name.
     * @param callable $callback  Callback.
     * @param int      $priority  Priority.
     * @return bool
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName -- keep function naming similar to WordPress
    public function remove_action($action, $callback, $priority = 10): bool
    {
        return remove_action($action, $callback, $priority);
    }

    /**
     * Removes the given callback from the given filter. The WordPress plugin API only
     * removes the filter if the callback and priority match a registered filter.
     *
     * @uses remove_filter()
     *
     * @param string   $filter Filter name.
     * @param callable $callback  Callback.
     * @param int      $priority  Priority.
     * @return bool
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName -- keep function naming similar to WordPress
    public function remove_filter($filter, $callback, $priority = 10): bool
    {
        return remove_filter($filter, $callback, $priority);
    }

    /**
     * Add an event subscriber.
     *
     * The event manager registers all the hooks that the given subscriber
     * wants to register with the WordPress Plugin API.
     *
     * @param SubscriberInterface $subscriber Subscriber_Interface implementation.
     * @return void
     */
    public function addSubscriber(SubscriberInterface $subscriber): void
    {
        if ($subscriber instanceof EventManagerAwareInterface) {
            $subscriber->setEventManager($this);
        }

        if ($subscriber instanceof ActionSubscriberInterface) {
            $this->addActionSubscriber($subscriber);
        }

        if ($subscriber instanceof FilterSubscriberInterface) {
            $this->addFilterSubscriber($subscriber);
        }
    }

    /**
     * Remove an event subscriber.
     *
     * The event manager removes all the hooks that the given subscriber
     * wants to register with the WordPress Plugin API.
     *
     * @param SubscriberInterface $subscriber Subscriber_Interface implementation.
     * @return void
     */
    public function removeSubscriber(SubscriberInterface $subscriber): void
    {
        if ($subscriber instanceof ActionSubscriberInterface) {
            $this->removeActionSubscriber($subscriber);
        }

        if ($subscriber instanceof FilterSubscriberInterface) {
            $this->removeFilterSubscriber($subscriber);
        }
    }

    /**
     * Add an action subscriber.
     *
     * The event manager registers all the actions that the given subscriber
     * wants to register with the WordPress Plugin API.
     *
     * @param ActionSubscriberInterface $subscriber ActionSubscriberInterface implementation.
     * @return void
     */
    protected function addActionSubscriber(ActionSubscriberInterface $subscriber): void
    {
        $actions = $subscriber->getSubscribedActions();
        if (empty($actions)) {
            return;
        }
        foreach ($actions as $action => $params) {
            if (is_string($params)) {
                $this->add_action($action, [$subscriber, $params]);
            } elseif (is_array($params) && isset($params[0])) {
                $this->add_action(
                    $action,
                    [$subscriber, $params[0]],
                    isset($params[1]) ? $params[1] : 10,
                    isset($params[2]) ? $params[2] : 1
                );
            }
        }
    }

    /**
     * Add an filter subscriber.
     *
     * The event manager registers all the filters that the given subscriber
     * wants to register with the WordPress Plugin API.
     *
     * @param FilterSubscriberInterface $subscriber FilterSubscriberInterface implementation.
     * @return void
     */
    protected function addFilterSubscriber(FilterSubscriberInterface $subscriber): void
    {
        $filters = $subscriber->getSubscribedFilters();
        if (empty($filters)) {
            return;
        }
        foreach ($filters as $filter => $params) {
            if (is_string($params)) {
                $this->add_filter($filter, [$subscriber, $params]);
            } elseif (is_array($params) && isset($params[0])) {
                $this->add_filter(
                    $filter,
                    [$subscriber, $params[0]],
                    isset($params[1]) ? $params[1] : 10,
                    isset($params[2]) ? $params[2] : 1
                );
            }
        }
    }

    /**
     * Remove an action subscriber.
     *
     * The event manager removes all the actions that the given subscriber
     * wants to register with the WordPress Plugin API.
     *
     * @param ActionSubscriberInterface $subscriber ActionSubscriberInterface implementation.
     * @return void
     */
    protected function removeActionSubscriber(ActionSubscriberInterface $subscriber): void
    {
        $actions = $subscriber->getSubscribedActions();
        if (empty($actions)) {
            return;
        }
        foreach ($actions as $action => $params) {
            if (is_string($params)) {
                $this->add_action($action, [$subscriber, $params]);
            } elseif (is_array($params) && isset($params[0])) {
                $this->add_action(
                    $action,
                    [$subscriber, $params[0]],
                    isset($params[1]) ? $params[1] : 10,
                    isset($params[2]) ? $params[2] : 1
                );
            }
        }
    }

    /**
     * Remove a filter subscriber.
     *
     * The event manager removes all the filters that the given subscriber
     * wants to register with the WordPress Plugin API.
     *
     * @param FilterSubscriberInterface $subscriber FilterSubscriberInterface implementation.
     * @return void
     */
    protected function removeFilterSubscriber(FilterSubscriberInterface $subscriber): void
    {
        $filters = $subscriber->getSubscribedFilters();
        if (empty($filters)) {
            return;
        }
        foreach ($filters as $filter => $params) {
            if (is_string($params)) {
                $this->add_filter($filter, [$subscriber, $params]);
            } elseif (is_array($params) && isset($params[0])) {
                $this->add_filter(
                    $filter,
                    [$subscriber, $params[0]],
                    isset($params[1]) ? $params[1] : 10,
                    isset($params[2]) ? $params[2] : 1
                );
            }
        }
    }
}
