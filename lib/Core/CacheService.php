<?php

namespace BizExaminer\LearnDashExtension\Core;

/**
 * Service for caching short-timed data via WordPress transients
 */
class CacheService
{
    /**
     * Will be prefixed to transients name
     * {$prefix}{$key} (should contain a seperator)
     *
     * @var string
     */
    protected string $prefix;

    /**
     * Default expiration of cache transients in seconds
     *
     * @var int
     */
    protected int $defaultExpiration;

    /**
     * All keys handled/added by this cache service
     * used for deleting all cache keys
     *
     * @var string[]
     */
    protected array $registeredKeys;

    /**
     * Creates a new CacheService instance
     *
     * @param string $prefix Prefix cache/transients with (should end with a delemiter like _)
     * @param int $defaultExpiration default expiration of cache values in seconds
     */
    public function __construct(string $prefix, int $defaultExpiration)
    {
        $this->prefix = $prefix;
        $this->defaultExpiration = $defaultExpiration;
        $this->registeredKeys = get_option("{$this->prefix}-cache-keys", []);
    }

    /**
     * Set (add or update) a transient value
     *
     * @uses set_transient
     *
     * @param string $key
     * @param mixed $value
     * @param int|null $expiresIn Expiration in seconds or null if to use default expiration
     * @return bool true if value was set, false otherweise
     */
    public function set(string $key, $value, ?int $expiresIn = null): bool
    {
        if ($expiresIn === null) {
            $expiresIn = $this->defaultExpiration;
        }

        $this->registerKey($key);

        return set_transient(
            $this->buildKey($key),
            $value,
            $expiresIn
        );
    }

    /**
     * Get a value from the cache
     *
     * @param string $key
     * @return mixed|false
     */
    public function get(string $key)
    {
        return get_transient($this->buildKey($key));
    }

    /**
     * Delete a value from the cache
     *
     * @param string $key
     * @return bool true if it was deleted, false otherwise
     */
    public function delete(string $key): bool
    {
        return delete_transient($this->buildKey($key));
    }

    /**
     * Delete all registered cache keys
     *
     * @return void
     */
    public function deleteAll(): void
    {
        foreach ($this->registeredKeys as $key) {
            $this->delete($key);
        }
    }

    /**
     * Registers a key as handled by this cache service
     *
     * @param string $key key without prefix
     * @return void
     */
    protected function registerKey(string $key)
    {
        if (!in_array($key, $this->registeredKeys)) {
            $this->registeredKeys[] = $key;
            update_option("{$this->prefix}-cache-keys", $this->registeredKeys, false);
        }
    }

    /**
     * Build the cache key by combining the prefix and the key
     *
     * @param string $key
     * @return string The full key including prefix
     */
    protected function buildKey(string $key): string
    {
        return "{$this->prefix}{$key}";
    }
}
