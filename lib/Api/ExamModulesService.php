<?php

namespace BizExaminer\LearnDashExtension\Api;

use BizExaminer\LearnDashExtension\Internal\Interfaces\ApiAwareInterface;
use BizExaminer\LearnDashExtension\Internal\Interfaces\CacheAwareInterface;
use BizExaminer\LearnDashExtension\Internal\Traits\ApiAwareTrait;
use BizExaminer\LearnDashExtension\Internal\Traits\CacheAwareTrait;

/**
 * Service for getting exam modules and content revisions
 * Provides methods to get exam modules from the API, explode them to exam modules / content revision
 * and handles caching
 */
class ExamModulesService implements ApiAwareInterface, CacheAwareInterface
{
    use ApiAwareTrait;
    use CacheAwareTrait;

    /**
     * Get exam modules and content revisions from the Api, uses a cache via transients
     *
     * @param ApiCredentials $apiCredentials
     * @return array $examModules (array):
     *               'id' => (string) productPartsId
     *               'name' => (string) productPartName
     *               'contentRevisions' => (array):
     *                  'id' => (string) content revision id
     *                  'fullId' => (string) {$productId}_{$productPartsId}_{$contentRevisionId} (seperated by a _)
     *                  'name' => (string) Name of Product Part Name and content revision name/id
     */
    public function getExamModules(ApiCredentials $apiCredentials): array
    {
        $cacheKey = "exam-modules_{$apiCredentials->getId()}";

        $returnExams = $this->getCacheService()->get($cacheKey);

        if (!$returnExams) {
            $returnExams = [];
            $apiClient = $this->makeApi($apiCredentials);
            $examModules = $apiClient->getExamModules();

            if (!$examModules || is_wp_error($examModules)) {
                return $returnExams;
            }

            /**
             * @see #8 for explanation of structure
             *
             * product = exam
             * productPart = exam Module
             * contentRevision/examRevision = version of exam module (only 1 - there for backwards compatibility)
             */
            foreach ($examModules as $examModule) {
                $productId = $examModule->productId;
                // multiple examModules with the same productId (=exam) will be returned by API
                if (!isset($returnExams[$productId])) {
                    $returnExams[$productId] = [
                        'id' => $productId,
                        'name' => $examModule->productName,
                        'modules' => []
                    ];
                }
                // always use first item - should be the only one
                // because API returns an examModule entry for each productPart
                if (!empty($examModule->examRevisions)) {
                    $revision = $examModule->examRevisions[0];
                    // @phpstan-ignore-next-line (no types for examModule)
                    $id = $examModule->productPartsId;
                    // productId is used for grouping; productPartsId and contentRevisionsId for booking
                    // @phpstan-ignore-next-line (no types for examModule)
                    $fullId = "{$productId}_{$examModule->productPartsId}_{$revision->crtContentsRevisionsId}";
                    if (empty($examModule->productPartName)) {
                        $name = sprintf(
                            /* translators: %1$s: exam name, %2$s: exam module id */
                            _x(
                                '%1$s Revision #%2$s',
                                'exam module content revision name',
                                'bizexaminer-learndash-extension'
                            ),
                            // @phpstan-ignore-next-line (no types for examModule)
                            $examModule->productName,
                            // @phpstan-ignore-next-line (no types for examModule)
                            $examModule->productPartsId
                        );
                    } else {
                        $name = "{$examModule->productPartName} (#{$id})";
                    }
                    $returnExams[$productId]['modules'][$id] = [
                        'id' => $id,
                        'revisionId' => $revision->crtContentsRevisionsId,
                        'fullId' => $fullId,
                        'name' => $name
                    ];
                }
            }

            /**
             * save with a relative short amount of expiration
             * this is mostly cached so when viewing settings page, saving, validating (mulitple times within minutes)
             * it gets the same values from local
             * but it needs to be short, so new exam modules created in bizExaminer show here soon
             */
            $this->getCacheService()->set($cacheKey, $returnExams, MINUTE_IN_SECONDS * 5);
        }

        return $returnExams;
    }

    /**
     * Extracts the exam module ID and content revision ID from a combined id
     *
     * API works with the "old" format of product | productPart | contentRevision IDs
     * a contentRevision will always be unique across all products/productParts.
     * To create a booking / book an exam, only the productPart and contentRevision ID is required.
     *
     * Other API methods (like getParticipantOverview) will use the contentRevisionsId and the contentsId.
     * Therefore the contentRevsionsId is the most unique one.
     *
     * @param string $fullId ID of exam module & contentRevision ({$productPartsId}_{$contentRevisionId})
     * @return array|false
     *              'product' => (string) product ID
     *              'productPart' => (string) product part ID
     *              'contentRevision' => (string) content revision id
     */
    public function explodeExamModuleIds(string $fullId)
    {
        if (!str_contains($fullId, '_') || substr_count($fullId, '_') !== 2) {
            return false;
        }
        $idParts = explode('_', $fullId);
        return [
            'product' => $idParts[0],
            'productPart' => $idParts[1],
            'contentRevision' => $idParts[2]
        ];
    }

    /**
     * Checks if an exammodule and content revision exist for a set of api credentials
     *
     * @param string $fullId ID of exam module & contentRevision ({$productId}_{$productPartsId}_{$contentRevisionId})
     * @param ApiCredentials $apiCredentials
     * @return bool
     */
    public function hasExamModuleContentRevision(string $fullId, ApiCredentials $apiCredentials): bool
    {
        $ids = $this->explodeExamModuleIds($fullId);
        if (!$ids) {
            return false;
        }
        $productId = $ids['product'];
        $productPartId = $ids['productPart'];
        $contentRevisionId = $ids['contentRevision'];

        $allExamModules = $this->getExamModules($apiCredentials);
        if (!isset($allExamModules[$productId])) {
            return false;
        }

        if (!isset($allExamModules[$productId]['modules'][$productPartId])) {
            return false;
        }

        if ($allExamModules[$productId]['modules'][$productPartId]['revisionId'] != $contentRevisionId) {
            return false;
        }
        return true;
    }
}
