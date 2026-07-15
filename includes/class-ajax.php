<?php
/**
 * Tada_Site_Agent_Ajax — endpoint AJAX cho quét điểm SEO từ admin.
 *
 * Bảo mật: check_ajax_referer (nonce) + current_user_can('manage_options').
 * JS gọi list_targets → lặp score_post từng trang (progress) để không timeout.
 *
 * @since 1.5.1
 */

defined('ABSPATH') || exit;

class Tada_Site_Agent_Ajax {

    const NONCE = 'tada_site_agent_nonce';

    public static function init(): void {
        add_action('wp_ajax_tsa_list_targets', [self::class, 'list_targets']);
        add_action('wp_ajax_tsa_score_post', [self::class, 'score_post']);
    }

    private static function guard(): void {
        check_ajax_referer(self::NONCE, 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('forbidden', 403);
        }
    }

    /** Trả danh sách trang publish (post + page) để chấm. */
    public static function list_targets(): void {
        self::guard();
        $ids = get_posts([
            'post_type'   => ['post', 'page'],
            'post_status' => 'publish',
            'numberposts' => 200,
            'fields'      => 'ids',
            'orderby'     => 'modified',
            'order'       => 'DESC',
        ]);
        $out = [];
        foreach ($ids as $id) {
            $out[] = ['id' => $id, 'title' => get_the_title($id)];
        }
        wp_send_json_success($out);
    }

    /** Chấm 1 post → lưu meta → trả điểm + issues. */
    public static function score_post(): void {
        self::guard();
        $id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        if (!$id) {
            wp_send_json_error('missing post_id', 400);
        }
        $r = Tada_Site_Agent_Seo_Scorer::score_post($id);
        if (isset($r['error'])) {
            wp_send_json_error($r['error']);
        }
        wp_send_json_success([
            'id'     => $id,
            'title'  => get_the_title($id),
            'edit'   => get_edit_post_link($id, ''),
            'score'  => (int) $r['score'],
            'kw'     => $r['keywordScore'],
            'issues' => Tada_Site_Agent_Seo_Scorer::top_issues($r),
        ]);
    }
}
