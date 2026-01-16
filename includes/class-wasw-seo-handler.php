<?php
/**
 * SEO Handler - Rank Math ve Yoast SEO Unified API
 * 
 * Otomatik SEO plugin algılama ve her iki plugin için uyumlu meta işlemleri
 * 
 * @package WASW
 * @since 5.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class WASW_SEO_Handler {

    /**
     * Aktif SEO plugin türü
     */
    private static $active_plugin = null;

    /**
     * Plugin sabitleri
     */
    const PLUGIN_RANK_MATH = 'rank_math';
    const PLUGIN_YOAST = 'yoast';
    const PLUGIN_NONE = 'none';

    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', [$this, 'detect_seo_plugin'], 1);
    }

    /**
     * Aktif SEO pluginini algıla
     */
    public function detect_seo_plugin() {
        self::$active_plugin = self::get_active_seo_plugin();
    }

    /**
     * Aktif SEO pluginini döndür
     * 
     * @return string Plugin türü (rank_math, yoast, none)
     */
    public static function get_active_seo_plugin() {
        if (self::$active_plugin !== null) {
            return self::$active_plugin;
        }

        // Rank Math kontrolü
        if (self::is_rank_math_active()) {
            return self::PLUGIN_RANK_MATH;
        }

        // Yoast kontrolü
        if (self::is_yoast_active()) {
            return self::PLUGIN_YOAST;
        }

        return self::PLUGIN_NONE;
    }

    /**
     * Rank Math aktif mi?
     */
    public static function is_rank_math_active() {
        return class_exists('RankMath') || defined('RANK_MATH_VERSION');
    }

    /**
     * Yoast SEO aktif mi?
     */
    public static function is_yoast_active() {
        return defined('WPSEO_VERSION') || class_exists('WPSEO_Options');
    }

    /**
     * SEO meta verilerini kaydet (unified API)
     * 
     * @param int $post_id Post ID
     * @param array $data SEO verileri
     */
    public static function save_seo_meta($post_id, $data) {
        $plugin = self::get_active_seo_plugin();

        // Her iki plugin için de kaydet (uyumluluk)
        self::save_rank_math_meta($post_id, $data);
        self::save_yoast_meta($post_id, $data);

        // Plugin özel işlemler
        if ($plugin === self::PLUGIN_RANK_MATH) {
            self::trigger_rank_math_analysis($post_id);
        } elseif ($plugin === self::PLUGIN_YOAST) {
            self::trigger_yoast_analysis($post_id);
        }

        // Ortak işlem tarihi
        update_post_meta($post_id, '_wasw_processed_date', current_time('mysql'));
        update_post_meta($post_id, '_wasw_seo_plugin', $plugin);
    }

    /**
     * Rank Math meta verilerini kaydet
     */
    private static function save_rank_math_meta($post_id, $data) {
        // Temel SEO meta
        if (!empty($data['focus_keyword'])) {
            update_post_meta($post_id, 'rank_math_focus_keyword', sanitize_text_field($data['focus_keyword']));
        }
        
        if (!empty($data['seo_title'])) {
            update_post_meta($post_id, 'rank_math_title', sanitize_text_field($data['seo_title']));
        }
        
        if (!empty($data['seo_description'])) {
            update_post_meta($post_id, 'rank_math_description', sanitize_textarea_field($data['seo_description']));
        }

        // Advanced SEO meta
        if (!empty($data['canonical_url'])) {
            update_post_meta($post_id, 'rank_math_canonical_url', esc_url_raw($data['canonical_url']));
        }

        // Robots meta
        if (isset($data['robots'])) {
            $robots = is_array($data['robots']) ? $data['robots'] : ['index' => 'index'];
            update_post_meta($post_id, 'rank_math_robots', $robots);
        }

        // Primary category
        if (!empty($data['primary_category'])) {
            update_post_meta($post_id, 'rank_math_primary_category', absint($data['primary_category']));
        }

        // OpenGraph
        if (!empty($data['og_title'])) {
            update_post_meta($post_id, 'rank_math_facebook_title', sanitize_text_field($data['og_title']));
        }
        if (!empty($data['og_description'])) {
            update_post_meta($post_id, 'rank_math_facebook_description', sanitize_textarea_field($data['og_description']));
        }

        // Twitter
        if (!empty($data['twitter_title'])) {
            update_post_meta($post_id, 'rank_math_twitter_title', sanitize_text_field($data['twitter_title']));
        }
        if (!empty($data['twitter_description'])) {
            update_post_meta($post_id, 'rank_math_twitter_description', sanitize_textarea_field($data['twitter_description']));
        }
    }

    /**
     * Yoast SEO meta verilerini kaydet
     */
    private static function save_yoast_meta($post_id, $data) {
        // Temel SEO meta
        if (!empty($data['focus_keyword'])) {
            update_post_meta($post_id, '_yoast_wpseo_focuskw', sanitize_text_field($data['focus_keyword']));
        }
        
        if (!empty($data['seo_title'])) {
            update_post_meta($post_id, '_yoast_wpseo_title', sanitize_text_field($data['seo_title']));
        }
        
        if (!empty($data['seo_description'])) {
            update_post_meta($post_id, '_yoast_wpseo_metadesc', sanitize_textarea_field($data['seo_description']));
        }

        // Canonical URL
        if (!empty($data['canonical_url'])) {
            update_post_meta($post_id, '_yoast_wpseo_canonical', esc_url_raw($data['canonical_url']));
        }

        // Primary category
        if (!empty($data['primary_category'])) {
            update_post_meta($post_id, '_yoast_wpseo_primary_category', absint($data['primary_category']));
        }

        // OpenGraph
        if (!empty($data['og_title'])) {
            update_post_meta($post_id, '_yoast_wpseo_opengraph-title', sanitize_text_field($data['og_title']));
        }
        if (!empty($data['og_description'])) {
            update_post_meta($post_id, '_yoast_wpseo_opengraph-description', sanitize_textarea_field($data['og_description']));
        }

        // Twitter
        if (!empty($data['twitter_title'])) {
            update_post_meta($post_id, '_yoast_wpseo_twitter-title', sanitize_text_field($data['twitter_title']));
        }
        if (!empty($data['twitter_description'])) {
            update_post_meta($post_id, '_yoast_wpseo_twitter-description', sanitize_textarea_field($data['twitter_description']));
        }

        // SEO Score (tahmini)
        if (!empty($data['seo_score'])) {
            update_post_meta($post_id, '_yoast_wpseo_linkdex', absint($data['seo_score']));
        }

        // Readability Score
        if (!empty($data['readability_score'])) {
            update_post_meta($post_id, '_yoast_wpseo_content_score', absint($data['readability_score']));
        }
    }

    /**
     * Rank Math analizini tetikle
     */
    private static function trigger_rank_math_analysis($post_id) {
        // Rank Math skor güncellemesi için hook
        if (class_exists('RankMath\\Paper\\Paper')) {
            do_action('rank_math/post/updated', $post_id);
        }
    }

    /**
     * Yoast analizini tetikle
     */
    private static function trigger_yoast_analysis($post_id) {
        // Yoast analiz güncellemesi
        if (class_exists('WPSEO_Meta')) {
            do_action('wpseo_saved_postdata', $post_id);
        }
    }

    /**
     * SEO meta verilerini oku
     * 
     * @param int $post_id Post ID
     * @return array SEO verileri
     */
    public static function get_seo_meta($post_id) {
        $plugin = self::get_active_seo_plugin();

        if ($plugin === self::PLUGIN_RANK_MATH) {
            return self::get_rank_math_meta($post_id);
        } elseif ($plugin === self::PLUGIN_YOAST) {
            return self::get_yoast_meta($post_id);
        }

        return [];
    }

    /**
     * Rank Math meta verilerini oku
     */
    private static function get_rank_math_meta($post_id) {
        return [
            'focus_keyword' => get_post_meta($post_id, 'rank_math_focus_keyword', true),
            'seo_title' => get_post_meta($post_id, 'rank_math_title', true),
            'seo_description' => get_post_meta($post_id, 'rank_math_description', true),
            'seo_score' => get_post_meta($post_id, 'rank_math_seo_score', true),
            'canonical_url' => get_post_meta($post_id, 'rank_math_canonical_url', true),
        ];
    }

    /**
     * Yoast meta verilerini oku
     */
    private static function get_yoast_meta($post_id) {
        return [
            'focus_keyword' => get_post_meta($post_id, '_yoast_wpseo_focuskw', true),
            'seo_title' => get_post_meta($post_id, '_yoast_wpseo_title', true),
            'seo_description' => get_post_meta($post_id, '_yoast_wpseo_metadesc', true),
            'seo_score' => get_post_meta($post_id, '_yoast_wpseo_linkdex', true),
            'canonical_url' => get_post_meta($post_id, '_yoast_wpseo_canonical', true),
        ];
    }

    /**
     * SEO skoru al (her iki plugin için)
     * 
     * @param int $post_id Post ID
     * @return int SEO skoru (0-100)
     */
    public static function get_seo_score($post_id) {
        $plugin = self::get_active_seo_plugin();

        if ($plugin === self::PLUGIN_RANK_MATH) {
            return absint(get_post_meta($post_id, 'rank_math_seo_score', true));
        } elseif ($plugin === self::PLUGIN_YOAST) {
            return absint(get_post_meta($post_id, '_yoast_wpseo_linkdex', true));
        }

        return 0;
    }

    /**
     * Plugin durumu için admin notice
     * 
     * @return string HTML çıktı
     */
    public static function get_plugin_status_html() {
        $plugin = self::get_active_seo_plugin();

        $icons = [
            self::PLUGIN_RANK_MATH => '🏆',
            self::PLUGIN_YOAST => '🟢',
            self::PLUGIN_NONE => '⚠️',
        ];

        $names = [
            self::PLUGIN_RANK_MATH => 'Rank Math SEO',
            self::PLUGIN_YOAST => 'Yoast SEO',
            self::PLUGIN_NONE => 'SEO Plugin Bulunamadı',
        ];

        $colors = [
            self::PLUGIN_RANK_MATH => '#e040fb',
            self::PLUGIN_YOAST => '#a4286a',
            self::PLUGIN_NONE => '#f59e0b',
        ];

        $icon = $icons[$plugin];
        $name = $names[$plugin];
        $color = $colors[$plugin];

        return sprintf(
            '<span class="wasw-seo-status" style="background:%s; color:white; padding:4px 12px; border-radius:4px; font-size:12px; font-weight:600;">%s %s</span>',
            $color,
            $icon,
            esc_html($name)
        );
    }

    /**
     * GEO uyumlu içerik kontrol listesi
     * 
     * @param int $post_id Post ID
     * @return array Kontrol listesi sonuçları
     */
    public static function get_geo_checklist($post_id) {
        $post = get_post($post_id);
        $content = $post->post_content;
        $focus_kw = self::get_seo_meta($post_id)['focus_keyword'] ?? '';

        $checks = [];

        // 1. Focus keyword ilk 100 karakterde mi?
        $checks['kw_in_intro'] = [
            'label' => 'Anahtar kelime girişte',
            'pass' => stripos(substr(strip_tags($content), 0, 150), $focus_kw) !== false,
        ];

        // 2. H2 başlık var mı?
        $checks['has_h2'] = [
            'label' => 'H2 başlık mevcut',
            'pass' => preg_match('/<h2/i', $content) === 1,
        ];

        // 3. HTML tablo var mı?
        $checks['has_table'] = [
            'label' => 'HTML tablo mevcut',
            'pass' => preg_match('/<table/i', $content) === 1,
        ];

        // 4. İç link var mı?
        $checks['has_internal_link'] = [
            'label' => 'İç link mevcut',
            'pass' => preg_match('/<a[^>]+href=["\'][^"\']*' . preg_quote(home_url(), '/') . '/i', $content) === 1,
        ];

        // 5. Dış link var mı?
        $checks['has_external_link'] = [
            'label' => 'Dış link mevcut',
            'pass' => preg_match('/<a[^>]+rel=["\']nofollow["\']/i', $content) === 1,
        ];

        // 6. Görsel var mı?
        $checks['has_image'] = [
            'label' => 'Görsel mevcut',
            'pass' => preg_match('/<img/i', $content) === 1 || has_post_thumbnail($post_id),
        ];

        // 7. İçerik uzunluğu yeterli mi? (minimum 500 kelime)
        $word_count = str_word_count(strip_tags($content));
        $checks['content_length'] = [
            'label' => 'İçerik uzunluğu (500+ kelime)',
            'pass' => $word_count >= 500,
            'info' => $word_count . ' kelime',
        ];

        // 8. Meta açıklama var mı?
        $meta = self::get_seo_meta($post_id);
        $checks['has_meta_desc'] = [
            'label' => 'Meta açıklama mevcut',
            'pass' => !empty($meta['seo_description']),
        ];

        return $checks;
    }
}
