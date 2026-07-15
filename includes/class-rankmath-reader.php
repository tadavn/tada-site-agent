<?php
/**
 * Tada_Site_Agent_RankMath_Reader
 *
 * Read-only collector for Rank Math SEO plugin configuration.
 * SECURITY: Uses get_option() and $wpdb SELECT queries only — zero write operations.
 *
 * @since 1.0.0
 * @since 1.1.0 Added SEO advanced, content audit, 404 monitor detail
 */

defined('ABSPATH') || exit;

class Tada_Site_Agent_RankMath_Reader {

    public static function collect(): array {
        $active = self::is_rank_math_active();

        if (!$active) {
            return ['rankMathActive' => false, 'rankMathVersion' => null];
        }

        $general = self::get_rm_option('general');
        $titles  = self::get_rm_option('titles');
        $sitemap = self::get_rm_option('sitemap');
        $modules = get_option('rank_math_modules', []);
        if (!is_array($modules)) $modules = [];

        return [
            'rankMathActive'    => true,
            'rankMathVersion'   => self::get_rank_math_version(),
            'activeModules'     => $modules,

            // ── Phase 1: Core Config ──
            'sitemap'           => self::get_sitemap_config($sitemap, $general, $modules),
            'breadcrumbs'       => self::get_breadcrumbs_config($general),
            'schema'            => self::get_schema_config($titles),
            'homepage'          => self::get_homepage_config($titles),
            'postTypes'         => self::get_post_type_configs($titles),
            'redirections'      => self::get_redirections_stats(),
            'focusKeywords'     => self::get_focus_keyword_stats(),
            'focusKeywordsList' => self::get_focus_keywords_list(),
            'monitor404'        => self::get_404_monitor_config($general, $modules),
            'linkCounter'       => self::get_link_counter_config($general, $modules),
            'analytics'         => self::get_analytics_config($general),

            // ── Phase 2: SEO Advanced ──
            'openGraph'         => self::get_open_graph_config($titles),
            'robotsTxt'         => self::get_robots_txt_config($general),
            'noindexSettings'   => self::get_noindex_settings($titles),
            'imageSeo'          => self::get_image_seo_config($general),
            'instantIndexing'   => self::get_instant_indexing_config($general, $modules),

            // ── Phase 2: Content Audit ──
            'contentAudit'      => self::get_content_audit(),

            // ── Phase 3: Internal Links ──
            'internalLinks'     => self::get_internal_link_stats(),

            // ── Phase 3: Content Stats & SEO Health ──
            'contentStats'      => self::get_content_stats(),
            'seoHealth'         => self::get_seo_health(),

            // ── Phase 2: 404 Monitor Detail ──
            'monitor404Detail'  => self::get_404_monitor_detail(),
        ];
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private static function get_rm_option(string $group): array {
        $value = get_option("rank-math-options-{$group}", []);
        return is_array($value) ? $value : [];
    }

    private static function is_rank_math_active(): bool {
        if (!function_exists('is_plugin_active')) {
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        return is_plugin_active('seo-by-rank-math/rank-math.php');
    }

    private static function get_rank_math_version(): ?string {
        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $file = WP_PLUGIN_DIR . '/seo-by-rank-math/rank-math.php';
        if (!file_exists($file)) return null;
        $data = get_plugin_data($file, false, false);
        return isset($data['Version']) ? sanitize_text_field($data['Version']) : null;
    }

    // ─── Phase 1: Core Config ─────────────────────────────────────────────────

    private static function get_sitemap_config(array $sitemap, array $general, array $modules): array {
        $enabled = in_array('sitemap', $modules, true)
            || !empty($sitemap['sitemap'])
            || !empty($general['sitemap']);
        return ['enabled' => (bool) $enabled];
    }

    private static function get_breadcrumbs_config(array $general): array {
        return ['enabled' => !empty($general['breadcrumbs'])];
    }

    private static function get_schema_config(array $titles): array {
        return [
            'type' => sanitize_text_field($titles['knowledgegraph_type'] ?? ''),
            'name' => sanitize_text_field($titles['knowledgegraph_name'] ?? ''),
        ];
    }

    private static function get_homepage_config(array $titles): array {
        $robots = [];
        if (!empty($titles['homepage_robots']) && is_array($titles['homepage_robots'])) {
            $robots = array_map('sanitize_text_field', $titles['homepage_robots']);
        }

        $template_title = sanitize_text_field($titles['homepage_title'] ?? '');
        $template_desc  = sanitize_text_field($titles['homepage_description'] ?? '');

        $front_page_id = (int) get_option('page_on_front', 0);
        $meta_title = '';
        $meta_desc  = '';

        if ($front_page_id > 0) {
            $meta_title = sanitize_text_field(get_post_meta($front_page_id, 'rank_math_title', true) ?: '');
            $meta_desc  = sanitize_text_field(get_post_meta($front_page_id, 'rank_math_description', true) ?: '');
        }

        return [
            'title'          => $template_title,
            'description'    => $template_desc,
            'pageMeta'       => ['title' => $meta_title, 'description' => $meta_desc, 'pageId' => $front_page_id],
            'effectiveTitle' => $meta_title ?: $template_title,
            'effectiveDesc'  => $meta_desc ?: $template_desc,
            'robots'         => $robots,
        ];
    }

    private static function get_post_type_configs(array $titles): array {
        $post_types = get_post_types(['public' => true], 'objects');
        $configs = [];
        foreach ($post_types as $pt) {
            $slug = sanitize_key($pt->name);
            $robots = [];
            if (!empty($titles["pt_{$slug}_robots"]) && is_array($titles["pt_{$slug}_robots"])) {
                $robots = array_map('sanitize_text_field', $titles["pt_{$slug}_robots"]);
            }
            $configs[] = [
                'name'   => $slug,
                'label'  => sanitize_text_field($pt->label),
                'title'  => sanitize_text_field($titles["pt_{$slug}_title"] ?? ''),
                'robots' => $robots,
            ];
        }
        return $configs;
    }

    private static function get_redirections_stats(): array {
        global $wpdb;
        $table = $wpdb->prefix . 'rank_math_redirections';
        if (!$wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table))) {
            return ['total' => 0, 'active' => 0, 'inactive' => 0, 'trashed' => 0];
        }
        return [
            'total'    => (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$table}`"),
            'active'   => (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$table}` WHERE status = 'active'"),
            'inactive' => (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$table}` WHERE status = 'inactive'"),
            'trashed'  => (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$table}` WHERE status = 'trashed'"),
        ];
    }

    private static function get_focus_keyword_stats(): array {
        global $wpdb;
        $types = "'post', 'page', 'product'";
        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status='publish' AND post_type IN ({$types})");
        $with  = (int) $wpdb->get_var("SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON p.ID=pm.post_id WHERE p.post_status='publish' AND p.post_type IN ({$types}) AND pm.meta_key='rank_math_focus_keyword' AND pm.meta_value!=''");
        return [
            'withKeyword' => $with,
            'total'       => $total,
            'percentage'  => $total > 0 ? (int) round(($with / $total) * 100) : 0,
        ];
    }

    private static function get_focus_keywords_list(): array {
        global $wpdb;
        $types = "'post', 'page', 'product'";
        $rows = $wpdb->get_results(
            "SELECT p.ID, p.post_title, p.post_type, p.post_name,
                    fk.meta_value AS focus_keyword,
                    sc.meta_value AS seo_score
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} fk ON p.ID = fk.post_id AND fk.meta_key = 'rank_math_focus_keyword'
             LEFT JOIN {$wpdb->postmeta} sc ON p.ID = sc.post_id AND sc.meta_key = 'rank_math_seo_score'
             WHERE p.post_status = 'publish' AND p.post_type IN ({$types}) AND fk.meta_value != ''
             ORDER BY CAST(sc.meta_value AS UNSIGNED) DESC, p.ID DESC
             LIMIT 50",
            ARRAY_A
        );
        if (!is_array($rows)) return [];
        $items = [];
        foreach ($rows as $r) {
            $items[] = [
                'id'       => (int) $r['ID'],
                'title'    => sanitize_text_field($r['post_title']),
                'type'     => sanitize_key($r['post_type']),
                'slug'     => sanitize_text_field($r['post_name']),
                'keyword'  => sanitize_text_field($r['focus_keyword']),
                'seoScore' => (int) ($r['seo_score'] ?? 0),
                'url'      => get_permalink((int) $r['ID']),
            ];
        }
        return $items;
    }

    private static function get_404_monitor_config(array $general, array $modules): array {
        return ['enabled' => (bool) (in_array('404-monitor', $modules, true) || !empty($general['404_monitor']))];
    }

    private static function get_link_counter_config(array $general, array $modules): array {
        return ['enabled' => (bool) (in_array('link-counter', $modules, true) || !empty($general['link_builder']))];
    }

    private static function get_analytics_config(array $general): array {
        return ['connected' => !empty($general['console_email']) || !empty($general['analytics_connected'])];
    }

    // ─── Phase 2: SEO Advanced ────────────────────────────────────────────────

    /**
     * Open Graph & Twitter Card settings
     */
    private static function get_open_graph_config(array $titles): array {
        return [
            'facebook' => [
                'enabled'      => !empty($titles['facebook_enable']),
                'defaultImage' => sanitize_text_field($titles['open_graph_image_id'] ?? ''),
                'adminId'      => sanitize_text_field($titles['facebook_admin_id'] ?? ''),
                'appId'        => sanitize_text_field($titles['facebook_app_id'] ?? ''),
            ],
            'twitter' => [
                'enabled'  => !empty($titles['twitter_enable']),
                'cardType' => sanitize_text_field($titles['twitter_card_type'] ?? 'summary_large_image'),
            ],
        ];
    }

    /**
     * Custom Robots.txt content
     */
    private static function get_robots_txt_config(array $general): array {
        return [
            'customEnabled' => !empty($general['robots_txt_enable']),
            'content'       => sanitize_textarea_field($general['robots_txt_content'] ?? ''),
        ];
    }

    /**
     * Noindex settings for archives, tags, author, date pages
     */
    private static function get_noindex_settings(array $titles): array {
        return [
            'categories'  => self::is_noindex($titles, 'tax_category'),
            'tags'        => self::is_noindex($titles, 'tax_post_tag'),
            'authorPages' => self::is_noindex($titles, 'author_archive'),
            'dateArchive' => self::is_noindex($titles, 'date_archive'),
            'searchPages' => self::is_noindex($titles, 'search'),
            'attachment'  => self::is_noindex($titles, 'pt_attachment'),
        ];
    }

    private static function is_noindex(array $titles, string $key): bool {
        $robots = $titles["{$key}_robots"] ?? [];
        if (is_array($robots)) {
            return in_array('noindex', $robots, true);
        }
        return false;
    }

    /**
     * Image SEO auto-optimization settings
     */
    private static function get_image_seo_config(array $general): array {
        return [
            'addMissingAlt'    => !empty($general['img_alt_format']),
            'altFormat'        => sanitize_text_field($general['img_alt_format'] ?? ''),
            'addMissingTitle'  => !empty($general['img_title_format']),
            'titleFormat'      => sanitize_text_field($general['img_title_format'] ?? ''),
        ];
    }

    /**
     * Instant Indexing (IndexNow / Google Indexing API)
     */
    private static function get_instant_indexing_config(array $general, array $modules): array {
        $enabled = in_array('instant-indexing', $modules, true);
        return [
            'enabled' => (bool) $enabled,
            'bing'    => !empty($general['bing_verify']),
        ];
    }

    // ─── Phase 2: Content Audit ───────────────────────────────────────────────

    /**
     * Content audit — posts with low SEO scores, missing descriptions, bad titles
     */
    private static function get_content_audit(): array {
        global $wpdb;
        $types = "'post', 'page', 'product'";

        // Posts with lowest SEO scores (bottom 20)
        $low_score_posts = $wpdb->get_results(
            "SELECT p.ID, p.post_title, p.post_type,
                    sc.meta_value AS seo_score,
                    fk.meta_value AS focus_keyword
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} sc ON p.ID = sc.post_id AND sc.meta_key = 'rank_math_seo_score'
             LEFT JOIN {$wpdb->postmeta} fk ON p.ID = fk.post_id AND fk.meta_key = 'rank_math_focus_keyword'
             WHERE p.post_status = 'publish' AND p.post_type IN ({$types})
             AND sc.meta_value IS NOT NULL AND sc.meta_value != ''
             ORDER BY CAST(sc.meta_value AS UNSIGNED) ASC
             LIMIT 20",
            ARRAY_A
        );

        $low_scores = [];
        if (is_array($low_score_posts)) {
            foreach ($low_score_posts as $r) {
                $low_scores[] = [
                    'id'       => (int) $r['ID'],
                    'title'    => sanitize_text_field($r['post_title']),
                    'type'     => sanitize_key($r['post_type']),
                    'seoScore' => (int) ($r['seo_score'] ?? 0),
                    'keyword'  => sanitize_text_field($r['focus_keyword'] ?? ''),
                    'url'      => get_permalink((int) $r['ID']),
                ];
            }
        }

        // Posts missing meta description
        $missing_desc_count = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'rank_math_description'
             WHERE p.post_status = 'publish' AND p.post_type IN ({$types})
             AND (pm.meta_value IS NULL OR pm.meta_value = '')"
        );

        $missing_desc_posts = $wpdb->get_results(
            "SELECT p.ID, p.post_title, p.post_type
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'rank_math_description'
             WHERE p.post_status = 'publish' AND p.post_type IN ({$types})
             AND (pm.meta_value IS NULL OR pm.meta_value = '')
             ORDER BY p.ID DESC LIMIT 20",
            ARRAY_A
        );

        $missing_desc = [];
        if (is_array($missing_desc_posts)) {
            foreach ($missing_desc_posts as $r) {
                $missing_desc[] = [
                    'id'    => (int) $r['ID'],
                    'title' => sanitize_text_field($r['post_title']),
                    'type'  => sanitize_key($r['post_type']),
                    'url'   => get_permalink((int) $r['ID']),
                ];
            }
        }

        // Posts with title too long (>60 chars) or too short (<20 chars)
        $title_issues = $wpdb->get_results(
            "SELECT p.ID, p.post_title, p.post_type, pm.meta_value AS seo_title
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'rank_math_title'
             WHERE p.post_status = 'publish' AND p.post_type IN ({$types})
             AND (
                 (pm.meta_value IS NOT NULL AND pm.meta_value != '' AND (CHAR_LENGTH(pm.meta_value) > 70 OR CHAR_LENGTH(pm.meta_value) < 15))
                 OR
                 (pm.meta_value IS NULL AND (CHAR_LENGTH(p.post_title) > 70 OR CHAR_LENGTH(p.post_title) < 15))
             )
             ORDER BY p.ID DESC LIMIT 20",
            ARRAY_A
        );

        $bad_titles = [];
        if (is_array($title_issues)) {
            foreach ($title_issues as $r) {
                $actual_title = !empty($r['seo_title']) ? $r['seo_title'] : $r['post_title'];
                $len = mb_strlen($actual_title);
                $bad_titles[] = [
                    'id'     => (int) $r['ID'],
                    'title'  => sanitize_text_field($r['post_title']),
                    'type'   => sanitize_key($r['post_type']),
                    'length' => $len,
                    'issue'  => $len > 70 ? 'too_long' : 'too_short',
                    'url'    => get_permalink((int) $r['ID']),
                ];
            }
        }

        // Posts with fewest internal links (link counter data)
        $low_links = [];
        $lc_table = $wpdb->prefix . 'rank_math_internal_links';
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $lc_table))) {
            $low_link_posts = $wpdb->get_results(
                "SELECT p.ID, p.post_title, p.post_type,
                        COALESCE(il.internal_link_count, 0) AS link_count
                 FROM {$wpdb->posts} p
                 LEFT JOIN {$wpdb->prefix}rank_math_internal_meta il ON p.ID = il.object_id
                 WHERE p.post_status = 'publish' AND p.post_type IN ({$types})
                 ORDER BY link_count ASC, p.ID DESC
                 LIMIT 20",
                ARRAY_A
            );
            if (is_array($low_link_posts)) {
                foreach ($low_link_posts as $r) {
                    $low_links[] = [
                        'id'        => (int) $r['ID'],
                        'title'     => sanitize_text_field($r['post_title']),
                        'type'      => sanitize_key($r['post_type']),
                        'linkCount' => (int) ($r['link_count'] ?? 0),
                        'url'       => get_permalink((int) $r['ID']),
                    ];
                }
            }
        }

        return [
            'lowScorePosts'      => $low_scores,
            'missingDescription' => ['count' => $missing_desc_count, 'posts' => $missing_desc],
            'titleIssues'        => $bad_titles,
            'lowInternalLinks'   => $low_links,
        ];
    }

    // ─── Phase 3: Internal Link Stats ───────────────────────────────────────

    /**
     * Internal link statistics from Rank Math Link Counter module
     */
    private static function get_internal_link_stats(): array {
        global $wpdb;
        $types = "'post', 'page', 'product'";

        $meta_table = $wpdb->prefix . 'rank_math_internal_meta';
        if (!$wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $meta_table))) {
            return ['available' => false];
        }

        // Detect available columns (Rank Math versions differ)
        $columns = $wpdb->get_col("SHOW COLUMNS FROM `{$meta_table}`", 0);
        $has_internal  = in_array('internal_link_count', $columns, true);
        $has_external  = in_array('external_link_count', $columns, true);
        $has_incoming  = in_array('incoming_internal_link_count', $columns, true);

        // Build safe SELECT for totals
        $select_parts = [];
        if ($has_internal)  $select_parts[] = "SUM(CAST(internal_link_count AS UNSIGNED)) AS total_internal";
        if ($has_external)  $select_parts[] = "SUM(CAST(external_link_count AS UNSIGNED)) AS total_external";
        if ($has_incoming)  $select_parts[] = "SUM(CAST(incoming_internal_link_count AS UNSIGNED)) AS total_incoming";

        $totals = ['total_internal' => 0, 'total_external' => 0, 'total_incoming' => 0];
        if (!empty($select_parts)) {
            $row = $wpdb->get_row(
                "SELECT " . implode(', ', $select_parts) . "
                 FROM `{$meta_table}` im
                 INNER JOIN {$wpdb->posts} p ON im.object_id = p.ID
                 WHERE p.post_status = 'publish' AND p.post_type IN ({$types})",
                ARRAY_A
            );
            if (is_array($row)) $totals = array_merge($totals, $row);
        }

        // Orphan pages (only if incoming column exists)
        $orphan_count = 0;
        $orphans = [];
        if ($has_incoming) {
            $orphan_count = (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM `{$meta_table}` im
                 INNER JOIN {$wpdb->posts} p ON im.object_id = p.ID
                 WHERE p.post_status = 'publish' AND p.post_type IN ({$types})
                 AND (im.incoming_internal_link_count IS NULL OR im.incoming_internal_link_count = 0)"
            );

            $orphan_posts = $wpdb->get_results(
                "SELECT p.ID, p.post_title, p.post_type,
                        COALESCE(im.internal_link_count, 0) AS outgoing
                 FROM `{$meta_table}` im
                 INNER JOIN {$wpdb->posts} p ON im.object_id = p.ID
                 WHERE p.post_status = 'publish' AND p.post_type IN ({$types})
                 AND (im.incoming_internal_link_count IS NULL OR im.incoming_internal_link_count = 0)
                 ORDER BY p.ID DESC LIMIT 20",
                ARRAY_A
            );

            if (is_array($orphan_posts)) {
                foreach ($orphan_posts as $r) {
                    $orphans[] = [
                        'id'       => (int) $r['ID'],
                        'title'    => sanitize_text_field($r['post_title']),
                        'type'     => sanitize_key($r['post_type']),
                        'outgoing' => (int) $r['outgoing'],
                        'url'      => get_permalink((int) $r['ID']),
                    ];
                }
            }
        }

        // Top pages by most incoming links (only if column exists)
        $top = [];
        if ($has_incoming) {
            $top_linked = $wpdb->get_results(
                "SELECT p.ID, p.post_title, p.post_type,
                        CAST(im.incoming_internal_link_count AS UNSIGNED) AS incoming,
                        CAST(im.internal_link_count AS UNSIGNED) AS outgoing
                 FROM `{$meta_table}` im
                 INNER JOIN {$wpdb->posts} p ON im.object_id = p.ID
                 WHERE p.post_status = 'publish' AND p.post_type IN ({$types})
                 ORDER BY incoming DESC LIMIT 20",
                ARRAY_A
            );

            if (is_array($top_linked)) {
                foreach ($top_linked as $r) {
                    $top[] = [
                        'id'       => (int) $r['ID'],
                        'title'    => sanitize_text_field($r['post_title']),
                        'type'     => sanitize_key($r['post_type']),
                        'incoming' => (int) $r['incoming'],
                        'outgoing' => (int) $r['outgoing'],
                        'url'      => get_permalink((int) $r['ID']),
                    ];
                }
            }
        }

        return [
            'available'      => true,
            'columns'        => ['internal' => $has_internal, 'external' => $has_external, 'incoming' => $has_incoming],
            'totalInternal'  => (int) ($totals['total_internal'] ?? 0),
            'totalExternal'  => (int) ($totals['total_external'] ?? 0),
            'totalIncoming'  => (int) ($totals['total_incoming'] ?? 0),
            'orphanCount'    => $orphan_count,
            'orphanPosts'    => $orphans,
            'topLinkedPosts' => $top,
        ];
    }

    // ─── Phase 3: Content Stats ─────────────────────────────────────────────

    /**
     * Content statistics: post types, counts, product categories
     */
    private static function get_content_stats(): array {
        global $wpdb;

        // All public post types with counts
        $post_types = get_post_types(['public' => true], 'objects');
        $type_stats = [];
        $total_published = 0;

        foreach ($post_types as $pt) {
            if ($pt->name === 'attachment') continue;
            $count = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'",
                $pt->name
            ));
            $total_published += $count;
            $type_stats[] = [
                'name'  => sanitize_key($pt->name),
                'label' => sanitize_text_field($pt->label),
                'count' => $count,
            ];
        }

        // Total pages (type = page)
        $total_pages = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status = 'publish'"
        );

        // Draft + pending counts
        $total_draft = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'draft' AND post_type IN ('post', 'page', 'product')"
        );

        // Product categories (WooCommerce taxonomy)
        $product_categories = [];
        if (taxonomy_exists('product_cat')) {
            $cats = get_terms([
                'taxonomy'   => 'product_cat',
                'hide_empty' => false,
                'orderby'    => 'count',
                'order'      => 'DESC',
            ]);
            if (is_array($cats) && !is_wp_error($cats)) {
                foreach ($cats as $cat) {
                    $product_categories[] = [
                        'id'    => (int) $cat->term_id,
                        'name'  => sanitize_text_field($cat->name),
                        'slug'  => sanitize_text_field($cat->slug),
                        'count' => (int) $cat->count,
                        'parent'=> (int) $cat->parent,
                    ];
                }
            }
        }

        // Media count
        $total_media = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_status = 'inherit'"
        );

        return [
            'totalPublished'    => $total_published,
            'totalPages'        => $total_pages,
            'totalDraft'        => $total_draft,
            'totalMedia'        => $total_media,
            'postTypes'         => $type_stats,
            'productCategories' => $product_categories,
        ];
    }

    // ─── Phase 3: SEO Health ─────────────────────────────────────────────────

    /**
     * SEO health: keyword stats, duplicate detection, score distribution
     */
    private static function get_seo_health(): array {
        global $wpdb;
        $types = "'post', 'page', 'product'";

        // Focus keyword coverage (same as focusKeywords but richer)
        $total = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status='publish' AND post_type IN ({$types})"
        );
        $with_keyword = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_status='publish' AND p.post_type IN ({$types})
             AND pm.meta_key='rank_math_focus_keyword' AND pm.meta_value != ''"
        );
        $without_keyword = $total - $with_keyword;

        // Duplicate focus keywords — keywords used by 2+ posts
        $duplicates = $wpdb->get_results(
            "SELECT pm.meta_value AS keyword, COUNT(*) AS usage_count,
                    GROUP_CONCAT(p.ID ORDER BY p.ID SEPARATOR ',') AS post_ids
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE pm.meta_key = 'rank_math_focus_keyword'
             AND pm.meta_value != ''
             AND p.post_status = 'publish'
             AND p.post_type IN ({$types})
             GROUP BY pm.meta_value
             HAVING COUNT(*) > 1
             ORDER BY COUNT(*) DESC
             LIMIT 30",
            ARRAY_A
        );

        $duplicate_keywords = [];
        if (is_array($duplicates)) {
            foreach ($duplicates as $d) {
                $ids = array_map('intval', explode(',', $d['post_ids']));
                $posts_info = [];
                foreach (array_slice($ids, 0, 5) as $pid) {
                    $posts_info[] = [
                        'id'    => $pid,
                        'title' => sanitize_text_field(get_the_title($pid)),
                        'url'   => get_permalink($pid),
                    ];
                }
                $duplicate_keywords[] = [
                    'keyword'    => sanitize_text_field($d['keyword']),
                    'count'      => (int) $d['usage_count'],
                    'posts'      => $posts_info,
                ];
            }
        }

        // SEO Score distribution
        $score_ranges = [
            'excellent' => 0, // 80-100
            'good'      => 0, // 50-79
            'poor'      => 0, // 1-49
            'noScore'   => 0, // 0 or no score
        ];

        $scores = $wpdb->get_results(
            "SELECT CAST(pm.meta_value AS UNSIGNED) AS score
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE pm.meta_key = 'rank_math_seo_score'
             AND p.post_status = 'publish'
             AND p.post_type IN ({$types})",
            ARRAY_A
        );

        $score_sum = 0;
        $score_count = 0;
        if (is_array($scores)) {
            foreach ($scores as $s) {
                $val = (int) $s['score'];
                if ($val >= 80) $score_ranges['excellent']++;
                elseif ($val >= 50) $score_ranges['good']++;
                elseif ($val > 0) $score_ranges['poor']++;
                else $score_ranges['noScore']++;
                if ($val > 0) {
                    $score_sum += $val;
                    $score_count++;
                }
            }
        }

        // Posts without any score at all
        $no_score_count = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'rank_math_seo_score'
             WHERE p.post_status = 'publish' AND p.post_type IN ({$types})
             AND (pm.meta_value IS NULL OR pm.meta_value = '' OR pm.meta_value = '0')"
        );
        $score_ranges['noScore'] = $no_score_count;

        return [
            'keywordCoverage' => [
                'total'          => $total,
                'withKeyword'    => $with_keyword,
                'withoutKeyword' => $without_keyword,
                'percentage'     => $total > 0 ? (int) round(($with_keyword / $total) * 100) : 0,
            ],
            'duplicateKeywords' => $duplicate_keywords,
            'duplicateCount'    => count($duplicate_keywords),
            'scoreDistribution' => $score_ranges,
            'averageScore'      => $score_count > 0 ? (int) round($score_sum / $score_count) : 0,
        ];
    }

    // ─── Phase 2: 404 Monitor Detail ──────────────────────────────────────────

    /**
     * Recent 404 errors from Rank Math 404 monitor
     */
    private static function get_404_monitor_detail(): array {
        global $wpdb;
        $table = $wpdb->prefix . 'rank_math_404_logs';

        if (!$wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table))) {
            return ['available' => false, 'total' => 0, 'items' => []];
        }

        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$table}`");

        $items = $wpdb->get_results(
            "SELECT uri, accessed, times_accessed, user_agent
             FROM `{$table}`
             ORDER BY accessed DESC
             LIMIT 30",
            ARRAY_A
        );

        if (!is_array($items)) $items = [];

        $cleaned = [];
        foreach ($items as $item) {
            $cleaned[] = [
                'url'       => sanitize_text_field($item['uri'] ?? ''),
                'lastSeen'  => sanitize_text_field($item['accessed'] ?? ''),
                'hitCount'  => (int) ($item['times_accessed'] ?? 1),
                'userAgent' => sanitize_text_field(mb_substr($item['user_agent'] ?? '', 0, 100)),
            ];
        }

        return [
            'available' => true,
            'total'     => $total,
            'items'     => $cleaned,
        ];
    }
}
