<?php
// phpcs:disable Generic.Files.LineLength.TooLong

// HTML structure taken form learndash/themes/ld30/templates/quiz/listing.php
// and learndash/themes/ld30/templates/shortcode/profile/*
?>
<div class="learndash-wrapper">
    <?php if (empty($data['quizzes'])) { ?>
        <?php
        learndash_get_template_part(
            'modules/alert.php',
            array(
                'type'    => 'info',
                'icon'    => 'quiz',
                'message' => $data['emptyMessage']
            ),
            true
        );
        ?>
    <?php } else { ?>
        <div class="ld-item-list ld-be-quiz-list">
            <div class="ld-item-list-items">
                <?php foreach ($data['quizzes'] as $quiz) : ?>
                    <div class="ld-item-list-item ld-item-list-item-quiz">
                        <div class="ld-item-list-item-preview" style="flex-direction: row;">
                            <a class="ld-item-name ld-primary-color-hover" href="<?php echo esc_url($quiz['link']); ?>">
                                <?php echo wp_kses_post(learndash_status_icon($quiz['status'], 'sfwd-quiz')); ?>
                                <span class="ld-item-title"><?php echo wp_kses_post(get_the_title($quiz['id'])); ?>
                                </span>
                            </a>
                            <div class="ld-item-details">
                                <a class="ld-button ld-button--be-import-attempts" title="<?php esc_attr_e('Import attempt from bizExaminer', 'bizexaminer-learndash-extension'); ?>" href="<?php echo esc_url(($quiz['importAttemptUrl'])); ?>">
                                    <?php echo esc_html($data['buttonLabel']); ?></a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php } ?>
</div>
<?php
// phpcs:enable