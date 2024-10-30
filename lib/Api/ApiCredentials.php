<?php

namespace BizExaminer\LearnDashExtension\Api;

/**
 * Data object for API credentials
 */
class ApiCredentials
{
    /**
     * The id under which the credentials are saved
     *
     * @var string
     */
    protected string $id;

    /**
     * The name given by the admin/user to easier select the credentials
     *
     * @var string
     */
    protected string $name;

    /**
     * Domain instance the API credentials are used on
     *
     * @var string
     */
    protected string $instance;

    /**
     * The API key for the (content) owner
     *
     * @var string
     */
    protected string $ownerKey;

    /**
     * The API key for the organisation
     *
     * @var string
     */
    protected string $organisationKey;

    /**
     * Creates a new ApiCredentials instance
     *
     * @param string $id The id under which the credentials are saved
     * @param string $name The name given by the admin/user to easier select the credentials
     * @param string $instance Domain instance the API credentials are used on
     * @param string $ownerKey The API key for the (content) owner
     * @param string $organisationKey The API key for the organisation
     */
    public function __construct(string $id, string $name, string $instance, string $ownerKey, string $organisationKey)
    {
        $this->id = $id;
        $this->name = $name;
        $this->instance = $instance;
        $this->ownerKey = $ownerKey;
        $this->organisationKey = $organisationKey;
    }

    /**
     * Get the id under which the credentials are saved
     *
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Get the name given by the admin/user to easier select the credentials
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the domain instance the API credentials are used on
     *
     * @return string
     */
    public function getInstance(): string
    {
        return $this->instance;
    }

    /**
     * Get the API key for the (content) owner
     *
     * @return string
     */
    public function getOwnerKey(): string
    {
        return $this->ownerKey;
    }

    /**
     * Get the API key for the organisation
     *
     * @return string
     */
    public function getOrganisationKey(): string
    {
        return $this->organisationKey;
    }
}
