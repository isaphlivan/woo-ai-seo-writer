<?php

class WASW_Admin
{

    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('add_meta_boxes', [$this, 'add_meta_box']);
        add_filter('bulk_actions-edit-product', [$this, 'register_bulk_action']);
        add_filter('bulk_actions-edit-post', [$this, 'register_bulk_action']);
        add_filter('handle_bulk_actions-edit-product', [$this, 'handle_bulk_action'], 10, 3);
        add_filter('handle_bulk_actions-edit-post', [$this, 'handle_bulk_action'], 10, 3);
        add_action('admin_post_wasw_export_report', [$this, 'wasw_export_report_handler']);
    }

    public function add_menu()
    {
        $icon = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0ibm9uZSIgc3Ryb2tlPSJ3aGl0ZSIgc3Ryb2tlLXdpZHRoPSIyIiBzdHJva2UtbGluZWNhcD0icm91bmQiIHN0cm9rZS1saW5lam9pbj0icm91bmQiPjxwYXRoIGQ9Ik0xMiAyYS41LjUgMCAwIDEgLjUuNXYzYS41LjUgMCAwIDEgLS41LjV2MGEuNS41IDAgMCAxLS41LS41VjIuNWEuNS41IDAgMCAxIC41LS41Ii8+PHBhdGggZD0iTTIgMTJhLjUuNSAwIDAgMSAuNS0uNWgzYS41LjUgMCAwIDEgLjUuNXYwYS41LjUgMCAwIDEgLS41LjVIMi41YS41LjUgMCAwIDEgLS41LS41Ii8+PHBhdGggZD0iTTIxLjUgMTJhLjUuNSAwIDAgMSAuNS0uNWgyIi8+PHBhdGggZD0iTTIyIDEyaC0uNSIvPjxwYXRoIGQ9Ik0xMiAyMS41YS41LjUgMCAwIDEgLjUuNXYyIi8+PHBhdGggZD0iTTEyIDIydi0uNSIvPjxwYXRoIGQ9Ik0xMiAxN2EzIDMgMCAxIDAtMy0zIDMgMyAwIDAgMCAzIDMiLz48cGF0aCBkPSJNMCAwIiBzdHJva2U9Im5vbmUiLz48cmVjdCB4PSI1IiB5PSI1IiB3aWR0aD0iMTQiIGhlaWdodD0iMTQiIHJ4PSIyIi8+PHBhdGggZD0iTTkgOWgwIi8+PHBhdGggZD0iTTE1IDloMCIvPjxwYXRoIGQ9Ik05IDE1aDAiLz48cGF0aCBkPSJNMTUgMTVoMCIvPjwvc3ZnPg==';
        add_menu_page('Woo AI SEO', 'Woo AI SEO', 'manage_options', 'woo-ai-seo', [$this, 'render_page'], $icon, 56);
    }

    public function register_settings()
    {
        register_setting('wasw_plugin', 'wasw_openai_api_key');
        register_setting('wasw_plugin', 'wasw_model_selection');
        register_setting('wasw_plugin', 'wasw_external_link');
    }

    public function enqueue_scripts($hook)
    {
        if ($hook === 'toplevel_page_woo-ai-seo' || $hook === 'post.php' || $hook === 'post-new.php') {
            wp_enqueue_style('wasw-admin-css', WASW_PLUGIN_URL . 'assets/css/admin.css', [], WASW_VERSION);
            wp_register_script('wasw-bulk-js', WASW_PLUGIN_URL . 'assets/js/bulk-process.js', ['jquery'], WASW_VERSION, true);
            wp_register_script('wasw-admin-js', WASW_PLUGIN_URL . 'assets/js/admin.js', ['jquery'], WASW_VERSION, true);
            $localize_data = ['ajax_url' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('wasw_nonce')];
            wp_localize_script('wasw-bulk-js', 'wasw_vars', $localize_data);
            wp_localize_script('wasw-admin-js', 'wasw_vars', $localize_data);
            if ($hook === 'toplevel_page_woo-ai-seo')
                wp_enqueue_script('wasw-bulk-js');
            if ($hook === 'post.php' || $hook === 'post-new.php') {
                wp_enqueue_media(); // PDF yükleme için Media Library
                wp_enqueue_script('wasw-admin-js');
            }
        }
    }

    public function add_meta_box()
    {
        add_meta_box('wasw_box', 'SEO+GEO AI Asistanı', [$this, 'wasw_meta_box_html'], ['product', 'post'], 'side', 'high');
    }

    public function wasw_meta_box_html($post)
    {
        ?>
        <div class="wasw-box-container">
            <div class="wasw-toggle-row">
                <span class="wasw-toggle-label">Yapay Zeka Görsel Oluştur</span>
                <label class="switch"><input type="checkbox" id="wasw_img_toggle"><span class="slider"></span></label>
            </div>
            <div class="wasw-toggle-row">
                <span class="wasw-toggle-label">Kısa Açıklama Oluştur (AI)</span>
                <label class="switch"><input type="checkbox" id="wasw_short_desc_toggle"><span class="slider"></span></label>
            </div>
            <p style="font-size:11px; color:#64748b; margin-top:-10px; margin-bottom:15px; text-align:left;">*Görsel kapalıysa
                mevcut resim SEO uyumlu hale getirilir.<br>*Kısa açıklama kapalıysa mevcuda dokunulmaz.</p>

            <!-- PDF Referans Alanı -->
            <div class="wasw-pdf-section"
                style="margin:15px 0; padding:12px; background:#f8fafc; border-radius:8px; border:1px solid #e2e8f0;">
                <label style="font-size:12px; font-weight:600; color:#334155; display:block; margin-bottom:8px;">📄 Teknik PDF
                    Referansı (Opsiyonel)</label>
                <div style="display:flex; flex-direction:column; gap:6px;">
                    <input type="url" id="wasw_pdf_url" class="widefat" placeholder="PDF URL'si yapıştırın..."
                        style="font-size:11px; padding:6px 8px;">
                    <span style="text-align:center; color:#94a3b8; font-size:10px;">veya</span>
                    <input type="button" id="wasw_pdf_upload" class="button button-secondary" value="📁 PDF Dosyası Seç"
                        style="width:100%; font-size:11px;">
                    <input type="hidden" id="wasw_pdf_attachment_id" value="">
                </div>
                <p style="font-size:10px; color:#64748b; margin:6px 0 0 0;">Teknik PDF yüklerseniz içerik bu dokümana göre
                    oluşturulur ve hata oranı azalır.</p>
                <div id="wasw-pdf-preview"
                    style="display:none; margin-top:8px; padding:8px; background:#ecfdf5; border-radius:4px; border:1px solid #a7f3d0;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span id="wasw-pdf-name" style="font-size:11px; color:#047857; font-weight:500;"></span>
                        <button type="button" id="wasw-pdf-remove"
                            style="color:#dc2626; border:none; background:none; cursor:pointer; font-size:14px;">✕</button>
                    </div>
                </div>
            </div>

            <button type="button" id="wasw-start-btn" class="wasw-magic-btn">✨ GEO İçerik Oluştur</button>
            <div id="wasw-status-area" style="display:none;" class="wasw-status-area">
                <div class="wasw-step"><span id="wasw-step-text">Başlatılıyor...</span><span id="wasw-percent">0%</span></div>
                <div class="wasw-progress-bg">
                    <div id="wasw-progress-bar" class="wasw-progress-bar"></div>
                </div>
            </div>
            <div id="wasw-preview-area" class="wasw-preview" style="display:none;">
                <h4 style="margin:0 0 10px 0; font-size:13px;">Oluşturulan Görsel:</h4><img id="wasw-preview-img"
                    src="" /><input type="hidden" id="wasw_temp_img_id" value=""><button type="button" id="wasw-approve-img"
                    class="wasw-btn wasw-btn-success" style="width:100%; margin-top:10px;">✅ Görseli Onayla</button>
            </div>
            <div id="wasw-msg" style="margin-top:15px; font-size:13px; line-height:1.4;"></div>
        </div>
        <?php
    }

    public function register_bulk_action($bulk_actions)
    {
        $bulk_actions['wasw_bulk_generate'] = '✨ AI ile İçerik Oluştur (Woo AI SEO)';
        return $bulk_actions;
    }

    public function handle_bulk_action($redirect_to, $action, $post_ids)
    {
        if ($action !== 'wasw_bulk_generate')
            return $redirect_to;
        $ids_string = implode(',', $post_ids);
        $redirect_url = admin_url('admin.php?page=woo-ai-seo&tab=bulk&ids=' . $ids_string);
        return $redirect_url;
    }

    public function wasw_export_report_handler()
    {
        // GÜVENLİK: Yetki kontrolü
        if (!current_user_can('manage_options')) {
            wp_die('Yetkisiz işlem.');
        }

        // GÜVENLİK: Nonce doğrulama
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'wasw_export_nonce')) {
            wp_die('Güvenlik doğrulaması başarısız.');
        }

        $format = isset($_GET['format']) && $_GET['format'] === 'xls' ? 'xls' : 'csv';
        $filename = 'seo-rapor-' . current_time('Y-m-d') . '.' . $format;
        $args = ['post_type' => ['product', 'post'], 'posts_per_page' => -1, 'meta_query' => [['key' => '_wasw_processed_date', 'compare' => 'EXISTS']]];
        $posts = get_posts($args);

        if ($format === 'xls') {
            header("Content-Type: application/vnd.ms-excel; charset=utf-8");
            header("Content-Disposition: attachment; filename=\"$filename\"");
            echo "\xEF\xBB\xBF";
            echo '<table border="1"><tr style="background:#e0e7ff;"><th>ID</th><th>Başlık</th><th>Tür</th><th>Odak Kelime</th><th>Skor</th><th>Tarih</th></tr>';
            foreach ($posts as $p) {
                $score = get_post_meta($p->ID, 'rank_math_seo_score', true) ?: '0';
                $focus_kw = get_post_meta($p->ID, 'rank_math_focus_keyword', true);
                $date = get_post_meta($p->ID, '_wasw_processed_date', true);
                // GÜVENLİK: XSS koruması için esc_html
                echo "<tr><td>" . intval($p->ID) . "</td><td>" . esc_html($p->post_title) . "</td><td>" . esc_html($p->post_type) . "</td><td>" . esc_html($focus_kw) . "</td><td>" . esc_html($score) . "</td><td>" . esc_html($date) . "</td></tr>";
            }
            echo '</table>';
        } else {
            header('Content-Type: text/csv; charset=utf-8');
            header("Content-Disposition: attachment; filename=\"$filename\"");
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, ['ID', 'Başlık', 'Tür', 'Odak Kelime', 'Puan', 'Tarih']);
            foreach ($posts as $p) {
                fputcsv($out, [$p->ID, $p->post_title, $p->post_type, get_post_meta($p->ID, 'rank_math_focus_keyword', true), get_post_meta($p->ID, 'rank_math_seo_score', true), get_post_meta($p->ID, '_wasw_processed_date', true)]);
            }
            fclose($out);
        }
        exit;
    }

    public function render_page()
    {
        $tab = $_GET['tab'] ?? 'settings';
        $pre_selected_ids = isset($_GET['ids']) ? explode(',', $_GET['ids']) : [];
        ?>
        <div class="wasw-wrap">
            <div class="wasw-header">
                <div>
                    <h1>Woo AI SEO Writer <span class="wasw-badge">V5.0 SEO+GEO Suite</span></h1>
                    <p>Rank Math & Yoast 100/100, Schema Markup, E-E-A-T & GEO Optimizasyonu</p>
                </div>
                <div style="display:flex; align-items:center; gap:15px;">
                    <?php echo WASW_SEO_Handler::get_plugin_status_html(); ?>
                    <a href="https://isapehlivan.com" target="_blank"
                        style="color:white; font-weight:bold; text-decoration:none; background:rgba(255,255,255,0.2); padding:8px 16px; border-radius:8px;">Destek</a>
                </div>
            </div>

            <div class="wasw-nav">
                <a href="?page=woo-ai-seo&tab=settings" class="wasw-nav-btn <?php echo $tab == 'settings' ? 'active' : ''; ?>">
                    <span class="dashicons dashicons-admin-settings"></span> Ayarlar
                </a>
                <a href="?page=woo-ai-seo&tab=bulk" class="wasw-nav-btn <?php echo $tab == 'bulk' ? 'active' : ''; ?>">
                    <span class="dashicons dashicons-networking"></span> Toplu İşlem Paneli
                </a>
                <a href="?page=woo-ai-seo&tab=report" class="wasw-nav-btn <?php echo $tab == 'report' ? 'active' : ''; ?>">
                    <span class="dashicons dashicons-chart-bar"></span> Geçmiş (İşlemler)
                </a>
            </div>

            <?php if ($tab == 'settings'): ?>
                <form method="post" action="options.php">
                    <?php settings_fields('wasw_plugin'); ?>
                    <div class="wasw-card">
                        <h3>🔑 API Yapılandırması</h3>

                        <!-- OpenAI Alanı -->
                        <div class="wasw-form-group">
                            <div style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                <label class="wasw-label" style="margin:0;"><span class="dashicons dashicons-admin-network"></span>
                                    OpenAI API Key (ChatGPT)</label>
                                <a href="https://platform.openai.com/api-keys" target="_blank" class="wasw-link-btn">
                                    <span class="dashicons dashicons-external"></span> Anahtar Al
                                </a>
                            </div>
                            <div class="wasw-input-container">
                                <input type="password" id="wasw_openai_key" name="wasw_openai_api_key"
                                    value="<?php echo esc_attr(get_option('wasw_openai_api_key')); ?>" class="wasw-input-field"
                                    placeholder="sk-..." />
                                <span class="wasw-toggle-password" title="Göster/Gizle">
                                    <span class="dashicons dashicons-visibility"></span>
                                </span>
                            </div>
                            <p style="font-size:12px; color:#94a3b8; margin-top:8px;">API anahtarınız şifreli olarak saklanır.</p>
                        </div>
                    </div>

                    <div class="wasw-card">
                        <h3>⚙️ Strateji ve Yapay Zeka Modeli</h3>
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
                            <div class="wasw-form-group">
                                <label class="wasw-label">Aktif Yapay Zeka Modeli</label>
                                <div class="wasw-select-wrapper">
                                    <select name="wasw_model_selection" class="wasw-input-field">
                                        <option value="gpt-4o" <?php selected(get_option('wasw_model_selection'), 'gpt-4o'); ?>>
                                            GPT-4o (En İyi & Hızlı)</option>
                                        <option value="gpt-4-turbo" <?php selected(get_option('wasw_model_selection'), 'gpt-4-turbo'); ?>>GPT-4 Turbo</option>
                                        <option value="gpt-4" <?php selected(get_option('wasw_model_selection'), 'gpt-4'); ?>>GPT-4
                                            (Standart)</option>
                                        <option value="gpt-3.5-turbo" <?php selected(get_option('wasw_model_selection'), 'gpt-3.5-turbo'); ?>>GPT-3.5 Turbo (Ekonomik)</option>
                                        <option value="gpt-5-preview" <?php selected(get_option('wasw_model_selection'), 'gpt-5-preview'); ?>>GPT-5.0 (Preview / Gelecek)</option>
                                        <option value="gpt-5.2-preview" <?php selected(get_option('wasw_model_selection'), 'gpt-5.2-preview'); ?>>GPT-5.2 (Alpha / Gelecek)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="wasw-form-group">
                                <label class="wasw-label">Dış Link Kaynağı</label>
                                <input type="text" name="wasw_external_link"
                                    value="<?php echo esc_attr(get_option('wasw_external_link')); ?>" class="wasw-input-field"
                                    placeholder="https://tr.wikipedia.org" />
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="wasw-btn">Ayarları Kaydet</button>
                </form>

            <?php elseif ($tab == 'bulk'): ?>
                <div class="wasw-card">
                    <h3>🚀 Toplu İçerik Oluşturucu</h3>
                    <?php if (!empty($pre_selected_ids)): ?>
                        <div style="margin:10px 0; padding:10px; background:#dcfce7; color:#166534; border-radius:4px;">
                            <strong><?php echo count($pre_selected_ids); ?> adet içerik aktarıldı.</strong>
                        </div>
                    <?php endif; ?>
                    <p>Aşağıdaki listedeki içerikler işlenecektir.</p>
                    <div id="bulk-progress-container" style="display:none; margin-bottom:20px;">
                        <div
                            style="display:flex; justify-content:space-between; margin-bottom:5px; font-weight:bold; color:#475569;">
                            <span>İlerleme: <span id="bulk-current">0</span> / <span id="bulk-total">0</span></span><span
                                id="bulk-percent-text">0%</span>
                        </div>
                        <div style="background:#e2e8f0; height:12px; border-radius:99px; overflow:hidden;">
                            <div id="bulk-overall-bar" style="background:#4f46e5; height:100%; width:0%; transition:width 0.3s;">
                            </div>
                        </div>
                    </div>
                    <div style="margin-bottom:15px; display:flex; gap:10px; align-items:center;">
                        <label style="margin-right:20px;"><input type="checkbox" id="bulk_create_img"> Yapay Zeka Görsel
                            Oluştur</label>
                        <label style="margin-right:20px;"><input type="checkbox" id="bulk_create_short"> Kısa Açıklama
                            Oluştur</label>
                        <button id="start-bulk-btn" class="wasw-btn wasw-btn-success">Seçilenleri Başlat</button>
                        <button id="stop-bulk-btn" class="wasw-btn wasw-btn-danger" style="display:none;">Durdur</button>
                    </div>
                    <div style="max-height:500px; overflow-y:auto; border:1px solid #e2e8f0;">
                        <table class="wasw-table" style="margin-top:0;">
                            <thead>
                                <tr>
                                    <th width="30"><input type="checkbox" id="select-all" <?php echo !empty($pre_selected_ids) ? 'checked' : ''; ?>></th>
                                    <th>ID</th>
                                    <th>Başlık</th>
                                    <th>Tür</th>
                                    <th>Durum</th>
                                </tr>
                            </thead>
                            <tbody id="bulk-list">
                                <?php
                                if (!empty($pre_selected_ids)) {
                                    $args = ['post_type' => ['product', 'post'], 'post__in' => $pre_selected_ids, 'posts_per_page' => -1];
                                } else {
                                    $args = ['post_type' => ['product', 'post'], 'posts_per_page' => 50, 'post_status' => 'publish', 'meta_query' => [['key' => '_wasw_processed_date', 'compare' => 'NOT EXISTS']]];
                                }
                                $bulk_posts = get_posts($args);
                                if ($bulk_posts):
                                    foreach ($bulk_posts as $bp):
                                        $is_checked = (!empty($pre_selected_ids) && in_array($bp->ID, $pre_selected_ids)) ? 'checked' : ''; ?>
                                        <tr id="row-<?php echo $bp->ID; ?>" data-id="<?php echo $bp->ID; ?>"
                                            data-title="<?php echo esc_attr($bp->post_title); ?>">
                                            <td><input type="checkbox" class="bulk-item" value="<?php echo $bp->ID; ?>" <?php echo $is_checked; ?>></td>
                                            <td>#<?php echo $bp->ID; ?></td>
                                            <td><?php echo $bp->post_title; ?></td>
                                            <td><?php echo $bp->post_type == 'product' ? 'Ürün' : 'Yazı'; ?></td>
                                            <td><span class="wasw-bulk-status">Bekliyor</span></td>
                                        </tr>
                                    <?php endforeach; else: ?>
                                    <tr>
                                        <td colspan="5" style="padding:20px; text-align:center;">İşlenecek içerik bulunamadı.</td>
                                    </tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php else:
                $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
                $search_query = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
                $filter_type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : '';
                $filter_start = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : '';
                $filter_end = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : '';
                $filter_score = isset($_GET['score_filter']) ? sanitize_text_field($_GET['score_filter']) : '';

                $meta_query = ['relation' => 'AND'];
                $meta_query[] = ['key' => '_wasw_processed_date', 'compare' => 'EXISTS'];

                if ($filter_start && $filter_end) {
                    $meta_query[] = [
                        'key' => '_wasw_processed_date',
                        'value' => [$filter_start . ' 00:00:00', $filter_end . ' 23:59:59'],
                        'compare' => 'BETWEEN',
                        'type' => 'DATETIME'
                    ];
                }

                if ($filter_score) {
                    $range = [];
                    if ($filter_score === 'good')
                        $range = [80, 100];
                    elseif ($filter_score === 'avg')
                        $range = [50, 79];
                    elseif ($filter_score === 'bad')
                        $range = [0, 49];

                    if (!empty($range)) {
                        $meta_query[] = [
                            'key' => 'rank_math_seo_score',
                            'value' => $range,
                            'compare' => 'BETWEEN',
                            'type' => 'NUMERIC'
                        ];
                    }
                }

                $args = [
                    'post_type' => $filter_type ? [$filter_type] : ['product', 'post'],
                    'posts_per_page' => 20,
                    'paged' => $paged,
                    's' => $search_query,
                    'orderby' => 'meta_value',
                    'meta_key' => '_wasw_processed_date',
                    'order' => 'DESC',
                    'meta_query' => $meta_query
                ];

                $query = new WP_Query($args);
                ?>
                <div class="wasw-card">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                        <h3>İşlem Geçmişi (<?php echo $query->found_posts; ?> Kayıt)</h3>
                        <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=wasw_export_report&format=xls'), 'wasw_export_nonce')); ?>"
                            class="wasw-btn wasw-btn-success">Excel İndir</a>
                    </div>

                    <div class="wasw-filter-bar">
                        <form method="get" style="display:flex; flex-wrap:wrap; gap:10px; width:100%; align-items:center;">
                            <input type="hidden" name="page" value="woo-ai-seo">
                            <input type="hidden" name="tab" value="report">
                            <div class="wasw-filter-group"><span class="wasw-filter-label">Arama:</span><input type="text" name="s"
                                    placeholder="Başlık..." value="<?php echo esc_attr($search_query); ?>"
                                    class="wasw-filter-input"></div>
                            <div class="wasw-filter-group"><span class="wasw-filter-label">Tip:</span><select name="type"
                                    class="wasw-filter-input">
                                    <option value="">Tümü</option>
                                    <option value="product" <?php selected($filter_type, 'product'); ?>>Ürün</option>
                                    <option value="post" <?php selected($filter_type, 'post'); ?>>Yazı</option>
                                </select></div>
                            <div class="wasw-filter-group"><span class="wasw-filter-label">Tarih Aralığı:</span><input type="date"
                                    name="start_date" value="<?php echo esc_attr($filter_start); ?>" class="wasw-filter-input"><span
                                    style="color:#aaa;">-</span><input type="date" name="end_date"
                                    value="<?php echo esc_attr($filter_end); ?>" class="wasw-filter-input"></div>
                            <div class="wasw-filter-group"><span class="wasw-filter-label">SEO Skor:</span><select
                                    name="score_filter" class="wasw-filter-input">
                                    <option value="">Tümü</option>
                                    <option value="good" <?php selected($filter_score, 'good'); ?>>🟢 İyi (80-100)</option>
                                    <option value="avg" <?php selected($filter_score, 'avg'); ?>>🟡 Orta (50-79)</option>
                                    <option value="bad" <?php selected($filter_score, 'bad'); ?>>🔴 Kötü (0-49)</option>
                                </select></div>
                            <button type="submit" class="wasw-btn">Filtrele</button>
                            <?php if ($search_query || $filter_type || $filter_start || $filter_score): ?><a
                                    href="?page=woo-ai-seo&tab=report" class="wasw-btn wasw-btn-danger"
                                    style="padding:10px 15px;">Temizle</a><?php endif; ?>
                        </form>
                    </div>

                    <table class="wasw-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Başlık</th>
                                <th>Tür</th>
                                <th>Odak KW</th>
                                <th>Skor</th>
                                <th>İşlem Tarihi</th>
                                <th>Aksiyon</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($query->have_posts()):
                                while ($query->have_posts()):
                                    $query->the_post();
                                    $pid = get_the_ID();
                                    $score = get_post_meta($pid, 'rank_math_seo_score', true);
                                    $cls = $score >= 80 ? 'score-good' : ($score >= 50 ? 'score-avg' : 'score-bad');
                                    $date = get_post_meta($pid, '_wasw_processed_date', true);
                                    ?>
                                    <tr>
                                        <td>#<?php echo $pid; ?></td>
                                        <td><a href="<?php echo get_edit_post_link($pid); ?>"
                                                style="font-weight:600; text-decoration:none; color:#2563eb;"><?php the_title(); ?></a></td>
                                        <td><span
                                                style="text-transform:capitalize; background:#e0f2fe; color:#0369a1; padding:2px 8px; border-radius:99px; font-size:11px;"><?php echo get_post_type(); ?></span>
                                        </td>
                                        <td><?php echo get_post_meta($pid, 'rank_math_focus_keyword', true) ?: '-'; ?></td>
                                        <td><span class="score-badge <?php echo $cls; ?>"><?php echo $score ?: 'N/A'; ?></span></td>
                                        <td style="font-family:monospace; color:#64748b;"><?php echo $date; ?></td>
                                        <td><a href="<?php echo get_edit_post_link($pid); ?>" class="button button-small">Düzenle</a></td>
                                    </tr>
                                <?php endwhile; else: ?>
                                <tr>
                                    <td colspan="7" style="text-align:center; padding:30px; color:#64748b;">Kayıt bulunamadı.</td>
                                </tr><?php endif;
                            wp_reset_postdata(); ?>
                        </tbody>
                    </table>

                    <?php if ($query->max_num_pages > 1): ?>
                        <div class="wasw-pagination">
                            <?php echo paginate_links(['base' => add_query_arg('paged', '%#%'), 'format' => '', 'current' => $paged, 'total' => $query->max_num_pages, 'prev_text' => '&laquo; Önceki', 'next_text' => 'Sonraki &raquo;', 'type' => 'list', 'mid_size' => 2]); ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}