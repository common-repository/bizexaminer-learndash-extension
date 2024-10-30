<?php

declare(strict_types=1);

namespace BizExaminer\LearnDashExtension\Vendor\League\Container;

use BizExaminer\LearnDashExtension\Vendor\League\Container\Definition\DefinitionInterface;
use BizExaminer\LearnDashExtension\Vendor\League\Container\Inflector\InflectorInterface;
use BizExaminer\LearnDashExtension\Vendor\League\Container\ServiceProvider\ServiceProviderInterface;
use BizExaminer\LearnDashExtension\Vendor\Psr\Container\ContainerInterface;

interface DefinitionContainerInterface extends ContainerInterface
{
    public function add(string $id, $concrete = null): DefinitionInterface;
    public function addServiceProvider(ServiceProviderInterface $provider): self;
    public function addShared(string $id, $concrete = null): DefinitionInterface;
    public function extend(string $id): DefinitionInterface;
    public function getNew($id);
    public function inflector(string $type, callable $callback = null): InflectorInterface;
}
