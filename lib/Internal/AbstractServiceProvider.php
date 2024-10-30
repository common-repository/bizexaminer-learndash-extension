<?php

namespace BizExaminer\LearnDashExtension\Internal;

use BizExaminer\LearnDashExtension\Vendor\League\Container\Definition\DefinitionInterface;
use BizExaminer\LearnDashExtension\Vendor\League\Container\ServiceProvider\AbstractServiceProvider
as BaseAbstractServiceProvider;
use BizExaminer\LearnDashExtension\Vendor\League\Container\ServiceProvider\BootableServiceProviderInterface;

/**
 * Abstract Service Provider implementation based on PHP-Leagues
 * provides utils and documentation
 */
abstract class AbstractServiceProvider extends BaseAbstractServiceProvider implements BootableServiceProviderInterface
{
    /**
     * The id of the service provider uniquely identifies it, so
     * that the container can quickly determine if it has already been registered.
     * Defaults to get_class($provider).
     *
     * @var string|null
     */
    // @phpstan-ignore-next-line (Allow null values so we can use get_class by default)
    protected $identifier;

    /**
     * The classes/interfaces that are serviced by this service provider.
     *
     * @var array
     */
    protected array $provides = [];

    /**
     * Returns a bool if checking whether this provider provides a specific
     * service or returns an array of provided services if no argument passed.
     *
     * @param string $id
     *
     * @return bool
     */
    public function provides(string $id): bool
    {
        return in_array($id, $this->provides, true);
    }

    /**
     * The id of the service provider uniquely identifies it, so
     * that the container can quickly determine if it has already been registered.
     * Defaults to get_class($provider).
     *
     * @return string
     */
    public function getIdentifier(): string
    {
        return isset($this->identifier) ? $this->identifier : get_class($this);
    }

    /**
     * Method will be invoked on registration of a service provider implementing
     * this interface. Provides ability for eager loading of Service Providers.
     *
     * @return void
     */
    public function boot(): void
    {
    }

    /**
     * Register the classes.
     *
     * @return void
     */
    abstract public function register(): void;

    /**
     * Register an entry in the container.
     *
     * @param string $id Entry id (typically a class or interface name).
     * @param mixed|null $concrete Concrete entity to register under that id, null for automatic creation.
     *
     * @return DefinitionInterface The generated container definition.
     */
    protected function add(string $id, $concrete = null): DefinitionInterface
    {
        return $this->getContainer()->add($id, $concrete);
    }

    /**
     * Register a shared entry in the container (`get` always returns the same instance).
     *
     * @param string $id Entry id (typically a class or interface name).
     * @param mixed|null $concrete Concrete entity to register under that id, null for automatic creation.
     *
     * @return DefinitionInterface The generated container definition.
     */
    protected function addShared(string $id, $concrete = null): DefinitionInterface
    {
        return $this->getContainer()->addShared($id, $concrete);
    }

    /**
     * Get an entry from the container
     *
     * @param string $id Entry id (typically a class or interface name).
     * @throws \BizExaminer\LearnDashExtension\Vendor\League\Container\Exception\ContainerException
     * @return mixed
     */
    protected function get(string $id)
    {
        return $this->getContainer()->get($id);
    }
}
