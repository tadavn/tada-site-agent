<?php
/**
 * Tada_Site_Agent_Seo_Scorer — chấm điểm SEO độc lập trên HTML ĐÃ RENDER.
 *
 * Port từ Brain `analyze-url` (cheerio/JS) → PHP (DOMDocument/DOMXPath).
 * Vì chấm trên HTML render (không đọc editor) → phủ MỌI trang kể cả template
 * riêng (Elementor/code tay) mà Rank Math không chấm được.
 *
 * Thiết kế: `analyze_html()` THUẦN (test được, không cần WP) + `score_url()`
 * bọc thêm loopback fetch.
 *
 * @since 1.5.0
 */

defined('ABSPATH') || exit;

class Tada_Site_Agent_Seo_Scorer {

    /** Loopback fetch HTML render của URL rồi chấm. */
    public static function score_url(string $url, string $keyword = ''): array {
        $resp = wp_remote_get($url, [
            'timeout'     => 15,
            'redirection' => 3,
            'user-agent'  => 'Mozilla/5.0 (TADA Site Agent SEO Scorer)',
            'sslverify'   => false, // loopback tới chính site (staging cert self-signed)
        ]);
        if (is_wp_error($resp)) {
            return ['error' => $resp->get_error_message()];
        }
        $code = (int) wp_remote_retrieve_response_code($resp);
        if ($code < 200 || $code >= 400) {
            return ['error' => "HTTP {$code}"];
        }
        return self::analyze_html(wp_remote_retrieve_body($resp), $url, $keyword);
    }

    /**
     * THUẦN — phân tích HTML → điểm + issues. Không phụ thuộc WordPress.
     * @return array {url, keyword, score, keywordScore, meta, headings, images, links, content, schema, openGraph, issues, keywordAnalysis}
     */
    public static function analyze_html(string $html, string $url, string $keyword = ''): array {
        $keyword = trim(mb_strtolower($keyword));

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        $xp = new DOMXPath($dom);

        // ── Meta ──
        $title       = self::node_text($xp, '//title');
        $description = self::attr($xp, '//meta[@name="description"]', 'content');
        $canonical   = self::attr($xp, '//link[@rel="canonical"]', 'href');
        $robots      = self::attr($xp, '//meta[@name="robots"]', 'content');

        // ── Headings (h1-h3) ──
        $headings = [];
        foreach ($xp->query('//h1|//h2|//h3') as $h) {
            $text = self::clean($h->textContent);
            if ($text !== '') {
                $headings[] = ['tag' => strtolower($h->nodeName), 'text' => mb_substr($text, 0, 120)];
            }
        }
        $h1Nodes = $xp->query('//h1');
        $h1Count = $h1Nodes->length;
        $h1Text  = $h1Count ? self::clean($h1Nodes->item(0)->textContent) : '';

        // ── Images ──
        $images = []; $imgWithAlt = 0; $imgNoAlt = 0;
        foreach ($xp->query('//img') as $img) {
            $src = $img->getAttribute('src') ?: $img->getAttribute('data-src');
            $alt = trim($img->getAttribute('alt'));
            if ($alt !== '') { $imgWithAlt++; } else { $imgNoAlt++; }
            if (count($images) < 30) {
                $images[] = ['src' => mb_substr($src, 0, 200), 'alt' => mb_substr($alt, 0, 100), 'hasAlt' => $alt !== ''];
            }
        }

        // ── Schema JSON-LD (đọc TRƯỚC khi gỡ script) ──
        $schemas = [];
        foreach ($xp->query('//script[@type="application/ld+json"]') as $s) {
            $json = json_decode(trim($s->textContent), true);
            if (is_array($json)) {
                $type = $json['@type'] ?? ($json['@graph'][0]['@type'] ?? null);
                if ($type) { $schemas[] = is_array($type) ? implode(', ', $type) : (string) $type; }
            }
        }

        // ── Open Graph ──
        $og = [
            'title'       => self::attr($xp, '//meta[@property="og:title"]', 'content'),
            'description' => self::attr($xp, '//meta[@property="og:description"]', 'content'),
            'image'       => self::attr($xp, '//meta[@property="og:image"]', 'content'),
            'type'        => self::attr($xp, '//meta[@property="og:type"]', 'content'),
        ];

        // ── Links (internal/external) ──
        $siteHost = parse_url($url, PHP_URL_HOST);
        $internal = 0; $external = 0; $extDomains = [];
        foreach ($xp->query('//a[@href]') as $a) {
            $href = trim($a->getAttribute('href'));
            if ($href === '' || $href[0] === '#'
                || stripos($href, 'javascript:') === 0
                || stripos($href, 'mailto:') === 0
                || stripos($href, 'tel:') === 0) {
                continue;
            }
            $host = self::link_host($href);
            if ($host === null) {            // relative → internal
                $internal++;
            } elseif ($host === $siteHost) {
                $internal++;
            } else {
                $external++; $extDomains[$host] = true;
            }
        }

        // ── Body text & word count (gỡ script/style/nav/footer/header/noscript) ──
        foreach ($xp->query('//script|//style|//nav|//footer|//header|//noscript') as $n) {
            if ($n->parentNode) { $n->parentNode->removeChild($n); }
        }
        $bodyNode  = $xp->query('//body')->item(0);
        $bodyText  = $bodyNode ? self::clean($bodyNode->textContent) : '';
        $words     = array_values(array_filter(
            preg_split('/\s+/', $bodyText),
            static function ($w) { return mb_strlen($w) > 1; }
        ));
        $wordCount = count($words);

        // ── SEO checks (11) ──
        $issues = [];
        $add = static function ($check, $pass, $failMsg, $warnCond = false, $warnMsg = '') use (&$issues) {
            $issues[] = [
                'check'    => $check,
                'status'   => $pass ? 'pass' : ($warnCond ? 'warn' : 'fail'),
                'message'  => $pass ? '' : ($warnCond ? ($warnMsg !== '' ? $warnMsg : $failMsg) : $failMsg),
                'category' => 'seo',
            ];
        };
        $tl = mb_strlen($title); $dl = mb_strlen($description);
        $add('Title tag', $tl > 0, 'Thiếu title tag');
        $add('Title length', $tl >= 20 && $tl <= 70, "Title {$tl} chars (nên 20-70)", $tl > 0 && $tl < 20, "Title hơi ngắn ({$tl} chars)");
        $add('Meta description', $dl > 0, 'Thiếu meta description');
        $add('Description length', $dl >= 50 && $dl <= 160, "Description {$dl} chars (nên 50-160)", $dl > 0 && $dl < 50, "Description hơi ngắn ({$dl} chars)");
        $add('H1 tag', $h1Count === 1, $h1Count === 0 ? 'Không có H1' : "Có {$h1Count} thẻ H1 (nên chỉ 1)", $h1Count > 1);
        $add('Image alt tags', $imgNoAlt === 0, "{$imgNoAlt} ảnh thiếu alt text", $imgNoAlt > 0 && $imgNoAlt <= 3, "{$imgNoAlt} ảnh thiếu alt");
        $add('Internal links', $internal >= 3, "Chỉ {$internal} internal links (nên ≥3)");
        $add('Word count', $wordCount >= 300, "Chỉ {$wordCount} từ (nên ≥300)", $wordCount >= 100, "{$wordCount} từ — hơi ít");
        $add('Canonical tag', mb_strlen($canonical) > 0, 'Thiếu canonical tag');
        $add('Open Graph', mb_strlen($og['title']) > 0, 'Thiếu Open Graph tags');
        $add('Schema markup', count($schemas) > 0, 'Không có Schema/JSON-LD');
        $add('Robots', strpos($robots, 'noindex') === false, 'Trang bị noindex!');

        // ── Keyword analysis (8 checks, nếu có keyword) ──
        $keywordAnalysis = null;
        if ($keyword !== '') {
            $bodyLower = mb_strtolower($bodyText);
            $quoted    = preg_quote($keyword, '/');
            $occurrences = preg_match_all('/' . $quoted . '/iu', $bodyText, $m);
            $occurrences = $occurrences ?: 0;
            $density = $wordCount > 0 ? round($occurrences / $wordCount * 100, 2) : 0.0;

            $inTitle       = mb_strpos(mb_strtolower($title), $keyword) !== false;
            $inH1          = mb_strpos(mb_strtolower($h1Text), $keyword) !== false;
            $inDescription = mb_strpos(mb_strtolower($description), $keyword) !== false;
            $inUrl         = mb_strpos(mb_strtolower($url), str_replace(' ', '-', $keyword)) !== false;

            $first100 = mb_strtolower(implode(' ', array_slice($words, 0, 100)));
            $inFirstParagraph = mb_strpos($first100, $keyword) !== false;

            $headingsWithKeyword = [];
            foreach ($headings as $h) {
                if ($h['tag'] !== 'h1' && mb_strpos(mb_strtolower($h['text']), $keyword) !== false) {
                    $headingsWithKeyword[] = $h;
                }
            }
            $inSubheadings = count($headingsWithKeyword) > 0;

            $imagesWithKeyword = 0;
            foreach ($images as $img) {
                if (mb_strpos(mb_strtolower($img['alt']), $keyword) !== false) { $imagesWithKeyword++; }
            }
            $inImageAlt = $imagesWithKeyword > 0;

            $addKw = static function ($check, $pass, $failMsg, $warnCond = false, $warnMsg = '') use (&$issues) {
                $issues[] = [
                    'check'    => $check,
                    'status'   => $pass ? 'pass' : ($warnCond ? 'warn' : 'fail'),
                    'message'  => $pass ? '' : ($warnCond ? ($warnMsg !== '' ? $warnMsg : $failMsg) : $failMsg),
                    'category' => 'keyword',
                ];
            };
            $addKw('Keyword in Title', $inTitle, "\"{$keyword}\" không có trong title tag");
            $addKw('Keyword in H1', $inH1, "\"{$keyword}\" không có trong thẻ H1");
            $addKw('Keyword in Description', $inDescription, "\"{$keyword}\" không có trong meta description");
            $addKw('Keyword in URL', $inUrl, "\"{$keyword}\" không có trong URL slug");
            $addKw('Keyword in First 100 Words', $inFirstParagraph, "\"{$keyword}\" không xuất hiện trong 100 từ đầu");
            $addKw('Keyword in Subheadings', $inSubheadings, "\"{$keyword}\" không có trong H2/H3 nào");
            $addKw('Keyword in Image Alt', $inImageAlt, "Không có ảnh nào alt chứa \"{$keyword}\"");
            $addKw(
                'Keyword Density',
                $density >= 0.5 && $density <= 2.5,
                $density < 0.5 ? "Density quá thấp ({$density}%) — nên 0.5-2.5%" : "Density quá cao ({$density}%) — nên 0.5-2.5%",
                $density > 0 && $density < 0.5,
                "Density hơi thấp ({$density}%)"
            );

            $keywordAnalysis = [
                'keyword'             => $keyword,
                'occurrences'         => $occurrences,
                'density'             => $density,
                'inTitle'             => $inTitle,
                'inH1'                => $inH1,
                'inDescription'       => $inDescription,
                'inUrl'               => $inUrl,
                'inFirstParagraph'    => $inFirstParagraph,
                'inSubheadings'       => $inSubheadings,
                'headingsWithKeyword' => $headingsWithKeyword,
                'inImageAlt'          => $inImageAlt,
                'imagesWithKeyword'   => $imagesWithKeyword,
            ];
        }

        // ── Score ──
        $seoIssues = array_values(array_filter($issues, static function ($i) { return $i['category'] === 'seo'; }));
        $seoPass   = count(array_filter($seoIssues, static function ($i) { return $i['status'] === 'pass'; }));
        $seoScore  = count($seoIssues) ? (int) round($seoPass / count($seoIssues) * 100) : 0;

        $keywordScore = null;
        $kwIssues = array_values(array_filter($issues, static function ($i) { return $i['category'] === 'keyword'; }));
        if (count($kwIssues) > 0) {
            $kwPass = count(array_filter($kwIssues, static function ($i) { return $i['status'] === 'pass'; }));
            $kwWarn = count(array_filter($kwIssues, static function ($i) { return $i['status'] === 'warn'; }));
            $keywordScore = (int) round(($kwPass + $kwWarn * 0.5) / count($kwIssues) * 100);
        }

        return [
            'url'          => $url,
            'keyword'      => $keyword !== '' ? $keyword : null,
            'score'        => $seoScore,
            'keywordScore' => $keywordScore,
            'meta'         => ['title' => $title, 'titleLength' => $tl, 'description' => $description, 'descriptionLength' => $dl, 'canonical' => $canonical, 'robots' => $robots],
            'headings'     => ['h1Count' => $h1Count, 'h1Text' => $h1Text, 'list' => array_slice($headings, 0, 30)],
            'images'       => ['total' => $imgWithAlt + $imgNoAlt, 'withAlt' => $imgWithAlt, 'withoutAlt' => $imgNoAlt, 'list' => array_slice($images, 0, 10)],
            'links'        => ['internal' => $internal, 'external' => $external, 'externalDomains' => array_slice(array_keys($extDomains), 0, 20)],
            'content'      => ['wordCount' => $wordCount],
            'schema'       => $schemas,
            'openGraph'    => $og,
            'issues'       => array_values($issues),
            'keywordAnalysis' => $keywordAnalysis,
        ];
    }

    // ── helpers ──

    private static function clean(string $s): string {
        return trim(preg_replace('/\s+/u', ' ', $s));
    }

    private static function node_text(DOMXPath $xp, string $q): string {
        $n = $xp->query($q)->item(0);
        return $n ? self::clean($n->textContent) : '';
    }

    private static function attr(DOMXPath $xp, string $q, string $attr): string {
        $n = $xp->query($q)->item(0);
        return $n instanceof DOMElement ? trim($n->getAttribute($attr)) : '';
    }

    /** Host của link; null nếu tương đối (→ nội bộ). */
    private static function link_host(string $href): ?string {
        if (strpos($href, '//') === 0) { $href = 'http:' . $href; } // protocol-relative
        $host = parse_url($href, PHP_URL_HOST);
        return $host ?: null;
    }
}
