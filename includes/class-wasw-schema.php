<?php
/**
 * Schema Markup Generator - GEO Optimizasyonu
 * 
 * JSON-LD yapısal veri oluşturma (Product, Article, FAQ, HowTo)
 * Generative Engine Optimization için schema markup
 * 
 * @package WASW
 * @since 5.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class WASW_Schema
{

    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('wp_head', [$this, 'output_schema'], 1);
    }

    /**
     * Schema çıktısını head'e ekle
     */
    public function output_schema()
    {
        if (!is_singular(['post', 'product'])) {
            return;
        }

        global $post;
        $schema = $this->generate_schema($post->ID);

        if (!empty($schema)) {
            echo "\n<!-- WASW Schema Markup - GEO Optimized -->\n";
            echo '<script type="application/ld+json">' . "\n";
            echo wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            echo "\n</script>\n";
        }
    }

    /**
     * Post için schema oluştur
     * 
     * @param int $post_id Post ID
     * @return array Schema data
     */
    public function generate_schema($post_id)
    {
        $post = get_post($post_id);
        $post_type = $post->post_type;

        // Ana schema
        $schema = [
            '@context' => 'https://schema.org',
            '@graph' => []
        ];

        // Post tipine göre schema ekle
        if ($post_type === 'product' && class_exists('WooCommerce')) {
            $schema['@graph'][] = $this->get_product_schema($post_id);
        } else {
            $schema['@graph'][] = $this->get_article_schema($post_id);
        }

        // FAQ Schema (içerikte FAQ varsa)
        $faq_schema = $this->get_faq_schema($post_id);
        if (!empty($faq_schema)) {
            $schema['@graph'][] = $faq_schema;
        }

        // HowTo Schema (içerikte nasıl yapılır varsa)
        $howto_schema = $this->get_howto_schema($post_id);
        if (!empty($howto_schema)) {
            $schema['@graph'][] = $howto_schema;
        }

        // Breadcrumb Schema
        $schema['@graph'][] = $this->get_breadcrumb_schema($post_id);

        // WebPage Schema
        $schema['@graph'][] = $this->get_webpage_schema($post_id);

        return $schema;
    }

    /**
     * Product Schema (WooCommerce)
     */
    private function get_product_schema($post_id)
    {
        $product = wc_get_product($post_id);

        if (!$product) {
            return [];
        }

        $schema = [
            '@type' => 'Product',
            '@id' => get_permalink($post_id) . '#product',
            'name' => $product->get_name(),
            'description' => wp_strip_all_tags($product->get_short_description() ?: $product->get_description()),
            'url' => get_permalink($post_id),
            'sku' => $product->get_sku() ?: 'SKU-' . $post_id,
        ];

        // Görsel
        $image_id = $product->get_image_id();
        if ($image_id) {
            $schema['image'] = [
                '@type' => 'ImageObject',
                'url' => wp_get_attachment_url($image_id),
                'width' => 1200,
                'height' => 1200
            ];
        }

        // Marka
        $brands = wp_get_post_terms($post_id, 'product_brand', ['fields' => 'names']);
        if (!is_wp_error($brands) && !empty($brands)) {
            $schema['brand'] = [
                '@type' => 'Brand',
                'name' => $brands[0]
            ];
        } else {
            $schema['brand'] = [
                '@type' => 'Brand',
                'name' => get_bloginfo('name')
            ];
        }

        // Fiyat
        if ($product->get_price()) {
            $schema['offers'] = [
                '@type' => 'Offer',
                'url' => get_permalink($post_id),
                'priceCurrency' => get_woocommerce_currency(),
                'price' => $product->get_price(),
                'availability' => $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                'seller' => [
                    '@type' => 'Organization',
                    'name' => get_bloginfo('name')
                ]
            ];

            // Fiyat geçerlilik
            $sale_end = $product->get_date_on_sale_to();
            if ($sale_end) {
                $schema['offers']['priceValidUntil'] = $sale_end->date('Y-m-d');
            }
        }

        // Değerlendirmeler
        $average_rating = $product->get_average_rating();
        $review_count = $product->get_review_count();

        if ($average_rating > 0 && $review_count > 0) {
            $schema['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => $average_rating,
                'reviewCount' => $review_count,
                'bestRating' => '5',
                'worstRating' => '1'
            ];
        }

        // GTIN/EAN/ISBN
        $gtin = get_post_meta($post_id, '_gtin', true) ?: get_post_meta($post_id, 'gtin', true);
        if ($gtin) {
            $schema['gtin13'] = $gtin;
        }

        return $schema;
    }

    /**
     * Article Schema
     */
    private function get_article_schema($post_id)
    {
        $post = get_post($post_id);
        $author = get_the_author_meta('display_name', $post->post_author);
        $focus_kw = get_post_meta($post_id, 'rank_math_focus_keyword', true)
            ?: get_post_meta($post_id, '_yoast_wpseo_focuskw', true);

        $schema = [
            '@type' => 'Article',
            '@id' => get_permalink($post_id) . '#article',
            'headline' => get_the_title($post_id),
            'description' => wp_strip_all_tags(get_the_excerpt($post_id)),
            'url' => get_permalink($post_id),
            'datePublished' => get_the_date('c', $post_id),
            'dateModified' => get_the_modified_date('c', $post_id),
            'author' => [
                '@type' => 'Person',
                'name' => $author,
                'url' => get_author_posts_url($post->post_author)
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => get_bloginfo('name'),
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => $this->get_site_logo()
                ]
            ],
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id' => get_permalink($post_id)
            ]
        ];

        // Öne çıkan görsel
        if (has_post_thumbnail($post_id)) {
            $image_url = get_the_post_thumbnail_url($post_id, 'full');
            $schema['image'] = [
                '@type' => 'ImageObject',
                'url' => $image_url,
                'width' => 1200,
                'height' => 628
            ];
        }

        // Anahtar kelimeler
        if ($focus_kw) {
            $schema['keywords'] = $focus_kw;
        }

        // Kelime sayısı
        $word_count = str_word_count(strip_tags($post->post_content));
        $schema['wordCount'] = $word_count;

        // Kategoriler
        $categories = get_the_category($post_id);
        if (!empty($categories)) {
            $schema['articleSection'] = $categories[0]->name;
        }

        return $schema;
    }

    /**
     * FAQ Schema - İçerikten otomatik çıkar
     */
    private function get_faq_schema($post_id)
    {
        $post = get_post($post_id);
        $content = $post->post_content;

        // SSS pattern'leri ara
        $faqs = [];

        // Pattern 1: <p><strong>Soru:</strong>...<br>Cevap:...</p>
        if (preg_match_all('/<p>\s*<strong>(?:Soru|S):\s*<\/strong>\s*(.*?)<br\s*\/?>\s*(?:Cevap|C):\s*(.*?)<\/p>/is', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $faqs[] = [
                    'question' => wp_strip_all_tags($match[1]),
                    'answer' => wp_strip_all_tags($match[2])
                ];
            }
        }

        // Pattern 2: H3/H4 soru + P cevap
        if (preg_match_all('/<h[34][^>]*>(.*?\?)<\/h[34]>\s*<p>(.*?)<\/p>/is', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $faqs[] = [
                    'question' => wp_strip_all_tags($match[1]),
                    'answer' => wp_strip_all_tags($match[2])
                ];
            }
        }

        if (empty($faqs)) {
            return null;
        }

        $schema = [
            '@type' => 'FAQPage',
            '@id' => get_permalink($post_id) . '#faq',
            'mainEntity' => []
        ];

        foreach ($faqs as $faq) {
            $schema['mainEntity'][] = [
                '@type' => 'Question',
                'name' => $faq['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $faq['answer']
                ]
            ];
        }

        return $schema;
    }

    /**
     * HowTo Schema - İçerikten otomatik çıkar
     */
    private function get_howto_schema($post_id)
    {
        $post = get_post($post_id);
        $content = $post->post_content;
        $title = get_the_title($post_id);

        // "Nasıl" veya "Adım" içeren içerik kontrolü
        if (stripos($title, 'nasıl') === false && stripos($content, 'Adım 1') === false) {
            return null;
        }

        // Adımları çıkar
        $steps = [];

        // Pattern: numaralı liste veya "Adım X:" formatı
        if (preg_match_all('/<li[^>]*>(.*?)<\/li>/is', $content, $matches)) {
            $step_num = 1;
            foreach ($matches[1] as $step_content) {
                $clean_step = wp_strip_all_tags($step_content);
                if (strlen($clean_step) > 10) {
                    $steps[] = [
                        '@type' => 'HowToStep',
                        'position' => $step_num,
                        'text' => $clean_step
                    ];
                    $step_num++;
                }
            }
        }

        if (count($steps) < 2) {
            return null;
        }

        return [
            '@type' => 'HowTo',
            '@id' => get_permalink($post_id) . '#howto',
            'name' => $title,
            'description' => wp_strip_all_tags(get_the_excerpt($post_id)),
            'step' => $steps
        ];
    }

    /**
     * Breadcrumb Schema
     */
    private function get_breadcrumb_schema($post_id)
    {
        $items = [];
        $position = 1;

        // Ana Sayfa
        $items[] = [
            '@type' => 'ListItem',
            'position' => $position++,
            'name' => 'Ana Sayfa',
            'item' => home_url('/')
        ];

        // Kategori (varsa)
        $post = get_post($post_id);
        if ($post->post_type === 'product') {
            $terms = get_the_terms($post_id, 'product_cat');
            if (!is_wp_error($terms) && !empty($terms)) {
                $term = $terms[0];
                $items[] = [
                    '@type' => 'ListItem',
                    'position' => $position++,
                    'name' => $term->name,
                    'item' => get_term_link($term)
                ];
            }
        } else {
            $categories = get_the_category($post_id);
            if (!empty($categories)) {
                $items[] = [
                    '@type' => 'ListItem',
                    'position' => $position++,
                    'name' => $categories[0]->name,
                    'item' => get_category_link($categories[0]->term_id)
                ];
            }
        }

        // Mevcut sayfa
        $items[] = [
            '@type' => 'ListItem',
            'position' => $position,
            'name' => get_the_title($post_id),
            'item' => get_permalink($post_id)
        ];

        return [
            '@type' => 'BreadcrumbList',
            '@id' => get_permalink($post_id) . '#breadcrumb',
            'itemListElement' => $items
        ];
    }

    /**
     * WebPage Schema
     */
    private function get_webpage_schema($post_id)
    {
        $post = get_post($post_id);

        return [
            '@type' => 'WebPage',
            '@id' => get_permalink($post_id),
            'name' => get_the_title($post_id),
            'description' => wp_strip_all_tags(get_the_excerpt($post_id)),
            'url' => get_permalink($post_id),
            'datePublished' => get_the_date('c', $post_id),
            'dateModified' => get_the_modified_date('c', $post_id),
            'inLanguage' => get_bloginfo('language'),
            'isPartOf' => [
                '@type' => 'WebSite',
                'name' => get_bloginfo('name'),
                'url' => home_url('/')
            ]
        ];
    }

    /**
     * Site logo URL
     */
    private function get_site_logo()
    {
        $custom_logo_id = get_theme_mod('custom_logo');
        if ($custom_logo_id) {
            return wp_get_attachment_url($custom_logo_id);
        }
        return home_url('/favicon.ico');
    }

    /**
     * Schema'yı post meta olarak kaydet
     */
    public static function save_schema_to_meta($post_id, $schema_type = 'auto')
    {
        update_post_meta($post_id, '_wasw_schema_type', $schema_type);
        update_post_meta($post_id, '_wasw_schema_enabled', 'yes');
    }

    /**
     * Schema preview HTML
     */
    public static function get_schema_preview_html($post_id)
    {
        $instance = new self();
        $schema = $instance->generate_schema($post_id);

        if (empty($schema)) {
            return '<p style="color:#94a3b8;">Schema verisi oluşturulamadı.</p>';
        }

        $html = '<div class="wasw-schema-preview">';
        $html .= '<div style="background:#1e293b; color:#e2e8f0; padding:12px; border-radius:6px; font-family:monospace; font-size:11px; max-height:200px; overflow-y:auto;">';
        $html .= '<pre style="margin:0; white-space:pre-wrap;">';
        $html .= esc_html(wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        $html .= '</pre></div></div>';

        return $html;
    }

    /**
     * Schema türlerini listele
     */
    public static function get_schema_types()
    {
        return [
            'auto' => 'Otomatik Algıla',
            'article' => 'Article (Makale)',
            'product' => 'Product (Ürün)',
            'faq' => 'FAQPage (SSS)',
            'howto' => 'HowTo (Nasıl Yapılır)',
            'review' => 'Review (İnceleme)',
            'local' => 'LocalBusiness (Yerel İşletme)',
        ];
    }
}
