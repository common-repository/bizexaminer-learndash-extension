<?php

declare(strict_types=1);

namespace BizExaminer\LearnDashExtension\Vendor\League\Container\Inflector;

use IteratorAggregate;
use BizExaminer\LearnDashExtension\Vendor\League\Container\ContainerAwareInterface;

interface InflectorAggregateInterface extends ContainerAwareInterface, IteratorAggregate
{
    public function add(string $type, callable $callback = null): Inflector;
    public function inflect(object $object);
}
