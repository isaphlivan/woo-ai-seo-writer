<?php

class WASW_Ajax
{

    public function __construct()
    {
        add_action('wp_ajax_wasw_generate_chain', [$this, 'wasw_generate_chain_ajax']);
        add_action('wp_ajax_wasw_save_final', [$this, 'wasw_save_final_ajax']);
        add_action('wp_ajax_wasw_approve_img', [$this, 'wasw_approve_img_ajax']);
    }

    public function wasw_generate_chain_ajax()
    {
        check_ajax_referer('wasw_nonce', 'nonce');
        if (!current_user_can('edit_posts'))
            wp_send_json_error('Yetki hatası.');

        // Lisans kontrolü
        $license_check = WASW_License::can_generate_content();
        if (!$license_check['allowed']) {
            wp_send_json_error($license_check['message']);
        }

        set_time_limit(900);

        $pid = intval($_POST['product_id']);
        $title = sanitize_text_field($_POST['title']);
        $step = sanitize_text_field($_POST['step']);

        // Pro özellik kontrolleri
        $create_image = isset($_POST['create_image']) && $_POST['create_image'] === 'true';
        $create_short = isset($_POST['create_short']) && $_POST['create_short'] === 'true';

        // Free plan'da görsel oluşturma kapalı
        if ($create_image && !WASW_License::can_generate_image()) {
            $create_image = false;
        }

        // PDF Referans İşleme - Pro özelliği
        $pdf_url = '';
        $pdf_attachment_id = 0;
        $pdf_content = '';

        if (WASW_License::can_use_pdf()) {
            $pdf_url = isset($_POST['pdf_url']) ? esc_url_raw($_POST['pdf_url']) : '';
            $pdf_attachment_id = isset($_POST['pdf_attachment_id']) ? intval($_POST['pdf_attachment_id']) : 0;
        }

        // Phase 1'de PDF içeriğini çıkar
        if ($step === 'phase_1' && ($pdf_url || $pdf_attachment_id)) {
            $pdf_content = WASW_API::extract_pdf_content($pdf_url, $pdf_attachment_id);
            // PDF içeriğini session'a kaydet (sonraki fazlarda kullanmak için)
            if (!empty($pdf_content)) {
                update_post_meta($pid, '_wasw_temp_pdf_content', $pdf_content);
            }
        } elseif ($step !== 'phase_1') {
            // Sonraki fazlarda session'dan al
            $pdf_content = get_post_meta($pid, '_wasw_temp_pdf_content', true);
        }

        $model = get_option('wasw_model_selection') ?: 'gpt-3.5-turbo';
        $api_key = get_option('wasw_openai_api_key');
        $ext_link = get_option('wasw_external_link') ?: 'https://tr.wikipedia.org';
        $prod_link = get_permalink($pid);

        // GEO-OPTIMIZED PROMPT MÜHENDİSLİĞİ: SEO + GEO + E-E-A-T
        $geo_instructions = "SİSTEM TALİMATI: Sen profesyonel bir teknik yazar, SEO uzmanı ve GEO (Generative Engine Optimization) stratejistisin.\n";
        $geo_instructions .= "HEDEF: Rank Math & Yoast 100/100 + AI Arama Motorları (ChatGPT, Perplexity, Gemini) uyumlu içerik.\n\n";

        // PDF Referans varsa ekle
        if (!empty($pdf_content)) {
            $geo_instructions .= "=== TEKNİK DÖKÜMAN REFERANSI (ÇOK ÖNEMLİ) ===\n";
            $geo_instructions .= "Aşağıdaki teknik döküman içeriğini referans alarak içerik üret.\n";
            $geo_instructions .= "KRİTİK KURALLAR:\n";
            $geo_instructions .= "1. Tüm teknik veriler (ölçüler, spesifikasyonlar, özellikler) bu dökümandan alınmalıdır.\n";
            $geo_instructions .= "2. Dökümanda olmayan teknik bilgileri ASLA UYDURMA.\n";
            $geo_instructions .= "3. Tablolardaki değerleri aynen kullan.\n";
            $geo_instructions .= "4. Ürün kodları, model numaraları ve teknik terimler dökümandaki gibi olmalı.\n\n";
            $geo_instructions .= "--- DÖKÜMAN İÇERİĞİ BAŞLANGICI ---\n";
            $geo_instructions .= mb_substr($pdf_content, 0, 8000); // Token limiti için
            $geo_instructions .= "\n--- DÖKÜMAN İÇERİĞİ SONU ---\n\n";
        }

        $geo_instructions .= "=== FORMAT KURALLARI (ÇOK ÖNEMLİ) ===\n";
        $geo_instructions .= "1. ASLA Markdown formatı (#, ##, ***, **, - ) KULLANMA. Sadece saf HTML etiketleri kullan.\n";
        $geo_instructions .= "2. Başlıklar: <h2> ve <h3> kullan, <h1> ASLA kullanma.\n";
        $geo_instructions .= "3. Anahtar kelimeler: <strong> ile vurgula.\n";
        $geo_instructions .= "4. Teknik veriler: HTML <table border='1'> formatında sun.\n";
        $geo_instructions .= "5. Listeler: <ul><li> formatında.\n\n";

        $geo_instructions .= "=== E-E-A-T SİNYALLERİ (Google Kalite Kriterleri) ===\n";
        $geo_instructions .= "- Experience (Deneyim): İlk elden kullanım deneyimi içeren ifadeler kullan.\n";
        $geo_instructions .= "- Expertise (Uzmanlık): Teknik terminoloji ve detaylı bilgiler içer.\n";
        $geo_instructions .= "- Authoritativeness (Otorite): Güvenilir kaynak referansları ver.\n";
        $geo_instructions .= "- Trustworthiness (Güvenilirlik): Somut veriler, istatistikler ve kanıtlar kullan.\n\n";

        $geo_instructions .= "=== GEO OPTİMİZASYON (AI Arama Motorları İçin) ===\n";
        $geo_instructions .= "- Semantic HTML yapısı kullan (article, section, aside).\n";
        $geo_instructions .= "- Net ve özlü cevaplar ver (AI snippet'ları için).\n";
        $geo_instructions .= "- İlgili entity'leri ve co-occurring term'leri doğal kullan.\n";
        $geo_instructions .= "- Sorular için direkt cevap formatı kullan.\n";
        $geo_instructions .= "- Yapısal veri işaretçileri için uygun format kullan (SSS bölümü için <p><strong>Soru:</strong>...<br>Cevap:...</p>).\n\n";

        $geo_instructions .= "=== SEO OPTİMİZASYON ===\n";
        $geo_instructions .= "- Odak anahtar kelime '{$title}' metin boyunca doğal dağıtılmalı (%1-2 yoğunluk).\n";
        $geo_instructions .= "- İlk 100 karakterde anahtar kelime geçmeli.\n";
        $geo_instructions .= "- Her H2'de veya hemen sonrasında anahtar kelime bulunmalı.\n";
        $geo_instructions .= "- İçerik akıcı, profesyonel ve insani olmalı.\n";


        if ($step === 'phase_1') {
            $prompt = "Konu: '{$title}'. 1000 KELİMELİK GİRİŞ BÖLÜMÜ.\n$geo_instructions\n";
            $prompt .= "YAPILACAKLAR:\n";
            $prompt .= "- Giriş Paragrafı: '{$title}' nedir sorusuna net bir cevap vererek başla. Anahtar kelimeyi ilk cümlede kalın (strong) kullan.\n";
            $prompt .= "- H2: '{$title} Nedir ve Ne İşe Yarar?' (En az 400 kelime, akıcı bir dille anlat).\n";
            $prompt .= "- Tarihçe ve Gelişim: Ürünün veya teknolojinin tarihçesini kısaca anlat (300 Kelime).\n";

            $content = WASW_API::call_api($model, $api_key, $prompt);
            $img_data = [];
            if ($create_image) {
                if (!empty($api_key))
                    $img_data = WASW_API::generate_image($title, $api_key, $pid);
            }
            wp_send_json_success(['content' => $content, 'image' => $img_data]);
        }
        if ($step === 'phase_2') {
            $prompt = "Konu: '{$title}'. TEKNİK DETAYLAR VE PERFORMANS.\n$geo_instructions\n";
            $prompt .= "YAPILACAKLAR:\n";
            $prompt .= "- H2: '{$title} Teknik Özellikleri ve Detayları'.\n";
            $prompt .= "- TABLO: '{$title}' için detaylı bir teknik özellikler tablosu oluştur (HTML <table> formatında, en az 8-10 satır).\n";
            $prompt .= "- H3: '{$title} Performans Analizi' (400 kelime, teknik terimler kullanarak detaylandır).\n";
            $prompt .= "- H2: '{$title} Kullanıcı Yorumları'.\n";

            $content = WASW_API::call_api($model, $api_key, $prompt);
            wp_send_json_success(['content' => $content]);
        }
        if ($step === 'phase_3') {
            $prompt = "Konu: '{$title}'. SONUÇ VE SIKÇA SORULAN SORULAR.\n$geo_instructions\n";
            $prompt .= "YAPILACAKLAR:\n";
            $prompt .= "- H2: '{$title} Hakkında Sıkça Sorulan Sorular' (En az 5 soru-cevap, <ul><li> formatında değil, <p><strong>Soru:</strong>...<br>Cevap:...</p> formatında).\n";
            $prompt .= "- Sonuç: '{$title}' kelimesini içeren güçlü bir kapanış paragrafı yaz.\n";
            $prompt .= "- Linkleme: Metnin uygun bir yerine <a href='{$prod_link}'>{$title}</a> linkini ve <a href='{$ext_link}' rel='nofollow'>Kaynak</a> linkini ekle.\n";

            // KISA AÇIKLAMA (SHORT DESC)
            if ($create_short) {
                $prompt .= "SEO_SHORT: {$title} hakkında satış odaklı, 3-4 cümlelik, emojili ve ilgi çekici bir kısa açıklama yaz.\n";
            } else {
                $prompt .= "SEO_SHORT: [KORU]\n";
            }

            // META VERİLERİ (KESİN FORMAT)
            $prompt .= "SEO_KEYWORD: {$title}\n";
            $prompt .= "SEO_BASLIK: {$title} - En Uygun Fiyat ve Detaylı İnceleme\n";
            $prompt .= "SEO_ACIKLAMA: {$title} teknik özellikleri, fiyatları ve kullanım alanları hakkında detaylı bilgi alın. Uzman incelememizi hemen okuyun.\n";
            $prompt .= "SEO_SLUG: " . sanitize_title($title) . "\n";
            $prompt .= "SEO_IMAGE_ALT: {$title} detaylı ürün görseli ve teknik şema\n";
            $prompt .= "SKOR_TAHMINI: 100\n";

            $content = WASW_API::call_api($model, $api_key, $prompt);
            wp_send_json_success(['content' => $content]);
        }
        wp_send_json_error('Invalid step');
    }

    public function wasw_save_final_ajax()
    {
        check_ajax_referer('wasw_nonce', 'nonce');

        // GÜVENLİK: Yetki kontrolü
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Yetki hatası.');
        }

        // Lisans kullanım sayacını artır (Free plan için)
        WASW_License::increment_usage();

        $pid = intval($_POST['product_id']);

        // GÜVENLİK: Post sahibi veya admin kontrolü
        $post = get_post($pid);
        if (!$post || (!current_user_can('edit_post', $pid))) {
            wp_send_json_error('Bu içeriği düzenleme yetkiniz yok.');
        }

        // GÜVENLİK: İçerik sanitizasyonu (XSS koruması)
        $full_content = wp_kses_post($_POST['content']);
        $title = sanitize_text_field($_POST['title']);

        $extract = function ($tag, &$text) {
            if (preg_match('/' . $tag . ':\s*(.*)/i', $text, $m)) {
                $text = str_replace($m[0], '', $text);
                return trim($m[1]);
            }return '';
        };

        $seo_kw = $extract('SEO_KEYWORD', $full_content);
        $seo_title = $extract('SEO_BASLIK', $full_content);
        $seo_desc = $extract('SEO_ACIKLAMA', $full_content);
        $short_desc = $extract('SEO_SHORT', $full_content);
        $seo_slug = $extract('SEO_SLUG', $full_content);
        $seo_img_alt = $extract('SEO_IMAGE_ALT', $full_content);
        $extract('SKOR_TAHMINI', $full_content);

        $seo_kw = str_replace(['[', ']'], '', $seo_kw) ?: $title;
        $seo_title = str_replace(['[', ']'], '', $seo_title) ?: "$title - Fiyat ve İnceleme";
        $seo_desc = str_replace(['[', ']'], '', $seo_desc);
        $seo_slug = sanitize_title(str_replace(['[', ']'], '', $seo_slug));
        $seo_img_alt = str_replace(['[', ']'], '', $seo_img_alt) ?: "$title inceleme";

        // RANK MATH 100/100: SEO meta açıklamasında MUTLAKA odak anahtar kelime olmalı
        if (empty($seo_desc) || stripos($seo_desc, $seo_kw) === false) {
            $seo_desc = "$seo_kw hakkında detaylı bilgi, teknik özellikler, fiyat karşılaştırması ve uzman değerlendirmesi. " . date("Y") . " güncel rehberi.";
        }

        // 1. GİRİŞ PARAGRAFI MANİPÜLASYONU (Rank Math: Keyword at start & Bold)
        if (stripos(strip_tags($full_content), $seo_kw) === false || stripos(strip_tags($full_content), $seo_kw) > 100) {
            $intro_html = "<p><strong>$seo_kw</strong>, " . date("Y") . " yılında en çok tercih edilen ürünler arasında yer almaktadır. Bu kapsamlı rehberde <strong>$seo_kw</strong> hakkında merak ettiğiniz tüm teknik detayları, fiyat avantajlarını ve kullanım alanlarını bulacaksınız.</p>";
            $full_content = $intro_html . $full_content;
        }

        // 2. RANK MATH UYUMLU TABLE OF CONTENTS (wp-block-toc sınıfı ile)
        $toc_html = '<nav class="wp-block-table-of-contents" style="background:#f8f9fa; padding:20px; border:1px solid #e2e8f0; border-radius:8px; margin:20px 0;">';
        $toc_html .= '<h2 class="wp-block-heading" style="font-size:18px; margin:0 0 15px 0;">📑 İçindekiler</h2>';
        $toc_html .= '<ul class="wp-block-list">';
        $toc_html .= '<li><a href="#bolum-1">' . esc_html($seo_kw) . ' Nedir?</a></li>';
        $toc_html .= '<li><a href="#bolum-2">' . esc_html($seo_kw) . ' Teknik Özellikleri</a></li>';
        $toc_html .= '<li><a href="#bolum-3">Sıkça Sorulan Sorular</a></li>';
        $toc_html .= '</ul></nav>';

        // TOC'u ilk paragraftan sonraya ekleyelim
        $full_content = preg_replace('/<\/p>/', '</p>' . $toc_html, $full_content, 1);

        // 3. ID ATAMA (TOC Bağlantıları İçin)
        $count = 1;
        $full_content = preg_replace_callback('/<h2(.*?)>/', function ($m) use (&$count) {
            return '<h2 id="bolum-' . $count++ . '"' . $m[1] . '>';
        }, $full_content);

        // 4. RANK MATH 100/100: İÇ BAĞLANTI EKLEME (Internal Link)
        $site_url = get_site_url();
        $internal_link = '<p style="margin:20px 0; padding:15px; background:#e0f2fe; border-radius:8px; border-left:4px solid #0284c7;">';
        $internal_link .= '🔗 <strong>İlgili İçerikler:</strong> ';
        $internal_link .= '<a href="' . esc_url($site_url) . '" title="Ana Sayfa">Tüm ürünlerimizi inceleyin</a>';
        $internal_link .= '</p>';

        // İç bağlantı kontrolü - yoksa ekle
        if (strpos($full_content, 'href="' . $site_url) === false && strpos($full_content, "href='" . $site_url) === false) {
            // Son paragraftan önce ekle
            $full_content = preg_replace('/<\/p>\s*$/', '</p>' . $internal_link, $full_content);
            if (strpos($full_content, $internal_link) === false) {
                $full_content .= $internal_link;
            }
        }

        // 5. RANK MATH 100/100: HARİCİ LİNK DOFOLLOW (en az bir tane)
        // Mevcut nofollow linkleri kontrol et ve bir tanesini dofollow yap
        $full_content = preg_replace('/rel=["\']nofollow["\']/', '', $full_content, 1);

        $has_thumb = has_post_thumbnail($pid);
        if ($has_thumb) {
            $thumb_id = get_post_thumbnail_id($pid);
            update_post_meta($thumb_id, '_wp_attachment_image_alt', $seo_kw . " " . $seo_img_alt);
            if (strpos($full_content, '<img') === false) {
                $img_url = wp_get_attachment_url($thumb_id);
                $full_content = '<figure class="wp-block-image"><img src="' . $img_url . '" alt="' . esc_attr($seo_kw . ' ' . $seo_img_alt) . '" class="wp-image-' . $thumb_id . '"/></figure>' . "\n\n" . $full_content;
            }
        }

        $post_data = ['ID' => $pid, 'post_content' => $full_content];
        $short_desc = str_replace(['[', ']'], '', $short_desc);
        if (!empty($short_desc) && $short_desc !== 'KORU' && $short_desc !== 'NULL') {
            $post_data['post_excerpt'] = $short_desc;
        }

        $current_post = get_post($pid);
        if (($current_post->post_status == 'auto-draft' || $current_post->post_status == 'draft' || empty($current_post->post_name)) && !empty($seo_slug)) {
            $post_data['post_name'] = $seo_slug;
        }
        wp_update_post($post_data);

        // UNIFIED SEO HANDLER - Rank Math & Yoast Uyumlu Meta Kaydetme
        $seo_data = [
            'focus_keyword' => $seo_kw,
            'seo_title' => $seo_title,
            'seo_description' => $seo_desc,
            'og_title' => $seo_title,
            'og_description' => $seo_desc,
            'twitter_title' => $seo_title,
            'twitter_description' => $seo_desc,
            'seo_score' => 100, // Tahmini skor
            'readability_score' => 90, // Tahmini okunabilirlik
        ];

        // Primary Category ayarla (varsa)
        $post_type = get_post_type($pid);
        if ($post_type === 'product') {
            $terms = get_the_terms($pid, 'product_cat');
            if (!is_wp_error($terms) && !empty($terms)) {
                $seo_data['primary_category'] = $terms[0]->term_id;
            }
        } else {
            $categories = get_the_category($pid);
            if (!empty($categories)) {
                $seo_data['primary_category'] = $categories[0]->term_id;
            }
        }

        // Unified SEO Handler ile meta kaydet
        WASW_SEO_Handler::save_seo_meta($pid, $seo_data);

        // Schema markup aktifleştir
        WASW_Schema::save_schema_to_meta($pid, 'auto');

        // Aktif SEO plugin bilgisi
        $active_plugin = WASW_SEO_Handler::get_active_seo_plugin();

        wp_send_json_success([
            'message' => 'Tamamlandı!',
            'seo_keyword' => $seo_kw,
            'seo_plugin' => $active_plugin,
            'schema_enabled' => true
        ]);
    }


    public function wasw_approve_img_ajax()
    {
        check_ajax_referer('wasw_nonce', 'nonce');

        // GÜVENLİK: Yetki kontrolü
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Yetki hatası.');
        }

        $pid = intval($_POST['product_id']);
        $iid = intval($_POST['image_id']);

        // GÜVENLİK: Post düzenleme yetkisi kontrolü
        if (!current_user_can('edit_post', $pid)) {
            wp_send_json_error('Bu içeriği düzenleme yetkiniz yok.');
        }

        if ($pid && $iid) {
            set_post_thumbnail($pid, $iid);
            $post = get_post($pid);
            update_post_meta($iid, '_wp_attachment_image_alt', esc_attr(get_the_title($pid)));
            $html = '<figure class="wp-block-image"><img src="' . esc_url(wp_get_attachment_url($iid)) . '" alt="' . esc_attr(get_the_title($pid)) . '" class="wp-image-' . intval($iid) . '"/></figure>';
            if (strpos($post->post_content, 'wp-image-' . $iid) === false) {
                wp_update_post(['ID' => $pid, 'post_content' => $html . "\n\n" . $post->post_content]);
            }
            wp_send_json_success();
        }
        wp_send_json_error();
    }
}