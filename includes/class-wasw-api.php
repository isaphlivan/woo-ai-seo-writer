<?php

class WASW_API
{

    public static function call_api($model, $key, $prompt)
    {
        $args = ['timeout' => 120, 'sslverify' => false, 'headers' => ['Content-Type' => 'application/json']];

        $args['headers']['Authorization'] = 'Bearer ' . $key;
        $args['body'] = json_encode(['model' => $model, 'messages' => [['role' => 'user', 'content' => $prompt]], 'temperature' => 0.7, 'max_tokens' => 4000]);
        $url = 'https://api.openai.com/v1/chat/completions';

        $res = wp_remote_post($url, $args);
        if (is_wp_error($res))
            return "Hata: " . $res->get_error_message();

        $body = json_decode(wp_remote_retrieve_body($res), true);

        return $body['choices'][0]['message']['content'] ?? 'API Hatası: ' . ($body['error']['message'] ?? 'Bilinmeyen Hata');
    }

    public static function generate_image($title, $key, $pid)
    {
        if (empty($key))
            return null;

        $res = wp_remote_post('https://api.openai.com/v1/images/generations', [
            'body' => json_encode([
                'model' => 'dall-e-3',
                'prompt' => "High quality product photo of $title, white background, studio lighting, highly detailed",
                'n' => 1,
                'size' => '1024x1024'
            ]),
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $key
            ],
            'timeout' => 50,
            'sslverify' => false
        ]);

        if (!is_wp_error($res)) {
            $data = json_decode(wp_remote_retrieve_body($res), true);
            if (isset($data['data'][0]['url'])) {
                require_once(ABSPATH . 'wp-admin/includes/media.php');
                require_once(ABSPATH . 'wp-admin/includes/file.php');
                require_once(ABSPATH . 'wp-admin/includes/image.php');

                $id = media_handle_sideload([
                    'name' => sanitize_title($title) . '.png',
                    'tmp_name' => download_url($data['data'][0]['url'])
                ], $pid);

                if (!is_wp_error($id)) {
                    update_post_meta($id, '_wp_attachment_image_alt', $title);
                    return ['url' => wp_get_attachment_url($id), 'id' => $id];
                }
            }
        }
        return null;
    }

    /**
     * PDF içeriğini metin olarak çıkarır
     * 
     * @param string $pdf_url Harici PDF URL'si
     * @param int $attachment_id WordPress medya ID'si
     * @return string PDF metin içeriği
     */
    public static function extract_pdf_content($pdf_url = '', $attachment_id = 0)
    {
        // Öncelik: Attachment ID
        if ($attachment_id > 0) {
            $file_path = get_attached_file($attachment_id);
            if ($file_path && file_exists($file_path)) {
                // Önce basit text extraction dene
                $text = self::simple_pdf_text_extract($file_path);
                if (!empty($text) && strlen($text) > 100) {
                    return $text;
                }
                // Başarısız olursa URL ile OpenAI Vision kullan
                $pdf_url = wp_get_attachment_url($attachment_id);
            }
        }

        if (empty($pdf_url)) {
            return '';
        }

        // OpenAI Vision ile PDF analiz et
        $api_key = get_option('wasw_openai_api_key');
        if (!empty($api_key)) {
            return self::analyze_pdf_with_vision($pdf_url, $api_key);
        }

        return '';
    }

    /**
     * Basit PDF text extraction (text-based PDF'ler için)
     */
    private static function simple_pdf_text_extract($file_path)
    {
        if (!file_exists($file_path)) {
            return '';
        }

        $content = file_get_contents($file_path);
        if ($content === false) {
            return '';
        }

        // PDF stream içinden text çıkar
        $text = '';

        // Text nesnelerini bul
        preg_match_all('/\((.*?)\)\s*Tj/s', $content, $matches);
        if (!empty($matches[1])) {
            $text .= implode(' ', $matches[1]);
        }

        // TJ operatörü ile text array'leri
        preg_match_all('/\[(.*?)\]\s*TJ/s', $content, $matches);
        if (!empty($matches[1])) {
            foreach ($matches[1] as $match) {
                preg_match_all('/\((.*?)\)/', $match, $submatches);
                if (!empty($submatches[1])) {
                    $text .= implode('', $submatches[1]) . ' ';
                }
            }
        }

        // Temizle
        $text = preg_replace('/[\x00-\x1F\x7F]/', ' ', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);

        // Octal escape'leri decode et
        $text = preg_replace_callback('/\\\\([0-7]{1,3})/', function ($m) {
            return chr(octdec($m[1]));
        }, $text);

        return $text;
    }

    /**
     * OpenAI Vision ile PDF analizi
     * PDF URL'yi direkt olarak veya PDF'in ilk sayfasının görselini analiz eder
     */
    public static function analyze_pdf_with_vision($pdf_url, $api_key)
    {
        // PDF URL kontrolü
        if (empty($pdf_url) || empty($api_key)) {
            return '';
        }

        // OpenAI'a PDF içeriğini açıklat (GPT-4o doğrudan URL analiz edebilir)
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key
            ],
            'body' => json_encode([
                'model' => 'gpt-4o',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Sen bir teknik döküman analiz uzmanısın. PDF dökümanlarından teknik spesifikasyonları, ölçüleri, özellikleri ve önemli bilgileri çıkarırsın. Çıktını düz metin olarak ver, markdown kullanma.'
                    ],
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => 'Bu teknik PDF dökümanındaki tüm metin içeriğini, tabloları, teknik spesifikasyonları, ölçüleri ve önemli bilgileri detaylı olarak çıkar. Her bir teknik özelliği ve değerini listele. Türkçe yanıt ver.'
                            ],
                            [
                                'type' => 'image_url',
                                'image_url' => [
                                    'url' => $pdf_url,
                                    'detail' => 'high'
                                ]
                            ]
                        ]
                    ]
                ],
                'max_tokens' => 4000
            ]),
            'timeout' => 120,
            'sslverify' => false
        ]);

        if (is_wp_error($response)) {
            error_log('WASW PDF Vision Error: ' . $response->get_error_message());
            return '';
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            error_log('WASW PDF Vision API Error: ' . ($body['error']['message'] ?? 'Unknown error'));
            return '';
        }

        return $body['choices'][0]['message']['content'] ?? '';
    }
}