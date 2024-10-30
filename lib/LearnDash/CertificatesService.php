<?php

namespace BizExaminer\LearnDashExtension\LearnDash;

use BizExaminer\LearnDashExtension\LearnDash\Quiz\QuizAttempt;

/**
 * Sservice for handling LearnDash certificates
 */
class CertificatesService
{
    /**
     * The post slug of the placeholder certificate
     *
     * @var string
     */
    protected const PLACEHOLDER_CERTIFICATE_SLUG = 'bizexaminer-certificate';

    /**
     * Gets the post id of the placeholder certificate
     *
     * @return int
     */
    public function getPlaceholderCertificateId(): int
    {
        $existingCertificate = get_page_by_path(
            self::PLACEHOLDER_CERTIFICATE_SLUG,
            OBJECT,
            learndash_get_post_type_slug('certificate')
        );

        if ($existingCertificate) {
            return $existingCertificate->ID;
        }

        return $this->createPlaceholderCertificate();
    }

    /**
     * Creates a new placeholder certificate
     *
     * @return int
     */
    protected function createPlaceholderCertificate(): int
    {
        $postId = wp_insert_post([
            'post_title' => _x(
                'bizExaminer Certificate',
                'bizexaminer placeholder certificate',
                'bizexaminer-learndash-extension'
            ),
            'post_status' => 'private',
            'post_type' => learndash_get_post_type_slug('certificate'),
            'post_name' => self::PLACEHOLDER_CERTIFICATE_SLUG,
            'post_content' => __(
                'This is a placeholder certificate used by bizExaminer to show users the bizExaminer certificate.
                    <strong>Please do not edit/delete it!</strong>',
                'bizexaminer-learndash-extension'
            ),
        ]);

        update_post_meta($postId, 'learndash_certificate_options', [
            'pdf_page_format' => 'LETTER',
            'pdf_page_orientation' => 'L'
        ]);

        return $postId;
    }
}
