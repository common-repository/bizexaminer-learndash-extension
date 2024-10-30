<?php

namespace BizExaminer\LearnDashExtension\Internal\Traits;

use BizExaminer\LearnDashExtension\Core\AssetService;
use Exception;

/**
 * Adds setter and (protected) getter for the setter-injection of the AssetService
 * @see AssetAwareInterface
 */
trait AssetAwareTrait
{
    /**
     * The injected AssetService instance to use
     *
     * @var AssetService
     */
    protected AssetService $assetService;

    public function setAssetService(AssetService $assetService): void
    {
        $this->assetService = $assetService;
    }

    /**
     * Gets the AssetService instance
     *
     * @return AssetService
     */
    protected function getAssetService(): AssetService
    {
        if (isset($this->assetService) && $this->assetService instanceof AssetService) {
            return $this->assetService;
        }

        throw new Exception('No AssetService set.');
    }
}
