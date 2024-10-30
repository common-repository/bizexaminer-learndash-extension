<?php

namespace BizExaminer\LearnDashExtension\LearnDash;

/**
 * A class for workarounds around LearnDash bugs/issues
 */
class Compat
{
    /**
     * Gets the quiz ID from the pro quiz ID.
     *
     * Workaround for learndash_get_quiz_id_by_pro_quiz_id
     * See #23
     *
     * EDITS:
     *  - remove global learndash_shortcode_atts lookup
     *  - remove get_queried_object lookup
     *  - move $wpdb->prepare into get_var for phpcs
     *  - add $wpdb->esc_like for regex query
     *  - formatting
     *
     * @global wpdb  $wpdb                     WordPress database abstraction object.
     *
     * @param int $quiz_pro_id Pro quiz ID
     *
     * @return int|null Quiz post ID.
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName -- keep original function name
    public static function learndash_get_quiz_id_by_pro_quiz_id(int $quiz_pro_id): ?int
    {
        global $wpdb;

        static $quiz_post_ids = array();

        if (empty($quiz_pro_id)) {
            return null;
        }
        $quiz_pro_id = absint($quiz_pro_id);

        if ((isset($quiz_post_ids[$quiz_pro_id])) && (!empty($quiz_post_ids[$quiz_pro_id]))) {
            return $quiz_post_ids[$quiz_pro_id];
        } else {
            $quiz_post_ids[$quiz_pro_id] = false;

            $quiz_post_id = $wpdb->get_var($wpdb->prepare(
                'SELECT post_id FROM ' . $wpdb->postmeta . ' as postmeta
                INNER JOIN ' . $wpdb->posts . ' as posts ON posts.ID=postmeta.post_id
                WHERE posts.post_type = %s AND posts.post_status = %s AND postmeta.meta_key = %s',
                'sfwd-quiz',
                'publish',
                'quiz_pro_id_' . absint($quiz_pro_id)
            ));
            if (!empty($quiz_post_id)) {
                $quiz_post_ids[$quiz_pro_id] = absint($quiz_post_id);
                return $quiz_post_ids[$quiz_pro_id];
            }

            $quiz_post_id = $wpdb->get_var($wpdb->prepare(
                'SELECT post_id FROM ' . $wpdb->postmeta . ' as postmeta
                INNER JOIN ' . $wpdb->posts . ' as posts ON posts.ID=postmeta.post_id
                WHERE posts.post_type = %s AND posts.post_status = %s AND meta_key = %s AND meta_value = %d',
                'sfwd-quiz',
                'publish',
                'quiz_pro_id',
                absint($quiz_pro_id)
            ));
            if (!empty($quiz_post_id)) {
                update_post_meta(absint($quiz_post_id), 'quiz_pro_id_' . absint($quiz_pro_id), absint($quiz_pro_id));
                $quiz_post_ids[$quiz_pro_id] = absint($quiz_post_id);
                return $quiz_post_ids[$quiz_pro_id];
            }

            // Because we seem to have a mix of int and string values when these are serialized
            // the format to look for end up being somewhat kludge-y.
            $quiz_pro_id_str = sprintf('%s', absint($quiz_pro_id));
            $quiz_pro_id_len = strlen($quiz_pro_id_str);

            $like_i = 'sfwd-quiz_quiz_pro";i:' . absint($quiz_pro_id) . ';';
            $like_s = '"sfwd-quiz_quiz_pro";s:' . $quiz_pro_id_len . ':"' . $quiz_pro_id_str . '"';

            // Using REGEX because it is slightly faster then OR on text fields pattern search.
            $quiz_post_id = $wpdb->get_var($wpdb->prepare(
                'SELECT post_id FROM ' . $wpdb->postmeta . ' as postmeta
                INNER JOIN ' . $wpdb->posts . " as posts ON posts.ID=postmeta.post_id
                WHERE posts.post_type = %s AND posts.post_status = %s
                    AND postmeta.meta_key=%s AND postmeta.meta_value REGEXP '%s|%s'",
                'sfwd-quiz',
                'publish',
                '_sfwd-quiz',
                $wpdb->esc_like($like_i),
                $wpdb->esc_like($like_s)
            ));
            if (!empty($quiz_post_id)) {
                $quiz_post_id = absint($quiz_post_id);
                update_post_meta($quiz_post_id, 'quiz_pro_id_' . absint($quiz_pro_id), absint($quiz_pro_id));
                update_post_meta($quiz_post_id, 'quiz_pro_id', absint($quiz_pro_id));
                $quiz_post_ids[$quiz_pro_id] = $quiz_post_id;
                return $quiz_post_ids[$quiz_pro_id];
            }
        }
        return null;
    }
}
