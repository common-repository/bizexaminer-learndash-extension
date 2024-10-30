<?php

declare(strict_types=1);

namespace BizExaminer\LearnDashExtension\Vendor\League\Container\Argument;

use BizExaminer\LearnDashExtension\Vendor\League\Container\ContainerAwareInterface;
use ReflectionFunctionAbstract;

interface ArgumentResolverInterface extends ContainerAwareInterface
{
    public function resolveArguments(array $arguments): array;
    public function reflectArguments(ReflectionFunctionAbstract $method, array $args = []): array;
}
