<?php

namespace BizExaminer\LearnDashExtension\Internal\Interfaces;

use BizExaminer\LearnDashExtension\Core\AssetService;

/**
 * Interface to signal usage of a AssetService
 * @see AssetAwareTrait
 */
interface AssetAwareInterface
{
    /**
     * Sets the AssetService instance
     *
     * @param AssetService $assetService
     * @return void
     */
    public function setAssetService(AssetService $assetService): void;
}
