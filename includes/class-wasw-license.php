<?php
/**
 * License Handler - Lisans Yönetim Sistemi
 * 
 * Free (15 gün trial) ve Pro (aylık ücretli) plan yönetimi
 * 
 * @package WASW
 * @since 5.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class WASW_License
{
    // Lisans tipleri
    const LICENSE_FREE_TRIAL = 'free_trial';
    const LICENSE_PRO = 'pro';
    const LICENSE_EXPIRED = 'expired';

    // Limitler
    const TRIAL_DAYS = 15;
    const FREE_DAILY_LIMIT = 5;

    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('admin_init', [$this, 'check_trial_start']);
        add_action('wp_ajax_wasw_activate_license', [$this, 'ajax_activate_license']);
        add_action('wp_ajax_wasw_deactivate_license', [$this, 'ajax_deactivate_license']);
    }

    /**
     * Trial otomatik başlat (ilk kurulumda)
     */
    public function check_trial_start()
    {
        $trial_start = get_option('wasw_trial_start_date');
        if (empty($trial_start)) {
            update_option('wasw_trial_start_date', current_time('mysql'));
        }
    }

    /**
     * Lisans durumunu al
     * 
     * @return string Lisans tipi
     */
    public static function get_license_status()
    {
        // Pro lisans kontrolü
        $license_key = get_option('wasw_license_key');
        $license_valid = get_option('wasw_license_valid');

        if (!empty($license_key) && $license_valid === 'yes') {
            $expiry = get_option('wasw_license_expiry');
            if ($expiry && strtotime($expiry) > time()) {
                return self::LICENSE_PRO;
            }
        }

        // Trial kontrolü
        $trial_start = get_option('wasw_trial_start_date');
        if (!empty($trial_start)) {
            $trial_end = strtotime($trial_start) + (self::TRIAL_DAYS * DAY_IN_SECONDS);
            if (time() < $trial_end) {
                return self::LICENSE_FREE_TRIAL;
            }
        }

        return self::LICENSE_EXPIRED;
    }

    /**
     * Pro lisans aktif mi?
     * 
     * @return bool
     */
    public static function is_pro()
    {
        return self::get_license_status() === self::LICENSE_PRO;
    }

    /**
     * Trial aktif mi?
     * 
     * @return bool
     */
    public static function is_trial_active()
    {
        return self::get_license_status() === self::LICENSE_FREE_TRIAL;
    }

    /**
     * Lisans aktif mi? (Trial veya Pro)
     * 
     * @return bool
     */
    public static function is_active()
    {
        $status = self::get_license_status();
        return $status === self::LICENSE_PRO || $status === self::LICENSE_FREE_TRIAL;
    }

    /**
     * Kalan trial günü
     * 
     * @return int
     */
    public static function get_trial_days_left()
    {
        $trial_start = get_option('wasw_trial_start_date');
        if (empty($trial_start)) {
            return 0;
        }

        $trial_end = strtotime($trial_start) + (self::TRIAL_DAYS * DAY_IN_SECONDS);
        $remaining = $trial_end - time();

        return max(0, ceil($remaining / DAY_IN_SECONDS));
    }

    /**
     * Bugünkü kullanım sayısı
     * 
     * @return int
     */
    public static function get_daily_usage_count()
    {
        $today = current_time('Y-m-d');
        $usage = get_option('wasw_daily_usage', []);

        return isset($usage[$today]) ? intval($usage[$today]) : 0;
    }

    /**
     * Kullanım sayısını artır
     */
    public static function increment_usage()
    {
        $today = current_time('Y-m-d');
        $usage = get_option('wasw_daily_usage', []);

        // Eski günleri temizle
        $usage = array_filter($usage, function ($key) use ($today) {
            return $key === $today;
        }, ARRAY_FILTER_USE_KEY);

        $usage[$today] = isset($usage[$today]) ? intval($usage[$today]) + 1 : 1;
        update_option('wasw_daily_usage', $usage);
    }

    /**
     * İçerik oluşturabilir mi?
     * 
     * @return array ['allowed' => bool, 'message' => string]
     */
    public static function can_generate_content()
    {
        $status = self::get_license_status();

        if ($status === self::LICENSE_EXPIRED) {
            return [
                'allowed' => false,
                'message' => 'Deneme süreniz doldu. Pro plana yükselterek devam edebilirsiniz.',
                'upgrade_required' => true
            ];
        }

        if ($status === self::LICENSE_PRO) {
            return [
                'allowed' => true,
                'message' => 'Pro lisans aktif - sınırsız kullanım.',
                'upgrade_required' => false
            ];
        }

        // Free trial - günlük limit kontrolü
        $usage = self::get_daily_usage_count();
        if ($usage >= self::FREE_DAILY_LIMIT) {
            return [
                'allowed' => false,
                'message' => 'Günlük ' . self::FREE_DAILY_LIMIT . ' içerik limitine ulaştınız. Pro plana yükselterek sınırsız kullanabilirsiniz.',
                'upgrade_required' => true
            ];
        }

        $remaining = self::FREE_DAILY_LIMIT - $usage;
        return [
            'allowed' => true,
            'message' => 'Bugün ' . $remaining . ' içerik hakkınız kaldı.',
            'upgrade_required' => false
        ];
    }

    /**
     * AI görsel oluşturabilir mi?
     * 
     * @return bool
     */
    public static function can_generate_image()
    {
        return self::is_pro();
    }

    /**
     * Toplu işlem yapabilir mi?
     * 
     * @return bool
     */
    public static function can_bulk_process()
    {
        return self::is_pro();
    }

    /**
     * PDF referans kullanabilir mi?
     * 
     * @return bool
     */
    public static function can_use_pdf()
    {
        return self::is_pro();
    }

    /**
     * Lisans aktivasyonu (AJAX)
     */
    public function ajax_activate_license()
    {
        check_ajax_referer('wasw_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Yetki hatası.');
        }

        $license_key = sanitize_text_field($_POST['license_key']);

        if (empty($license_key)) {
            wp_send_json_error('Lisans anahtarı gerekli.');
        }

        // Basit doğrulama (gerçek sistemde API çağrısı yapılır)
        $validation = self::validate_license_key($license_key);

        if ($validation['valid']) {
            update_option('wasw_license_key', $license_key);
            update_option('wasw_license_valid', 'yes');
            update_option('wasw_license_expiry', $validation['expiry']);
            update_option('wasw_license_email', $validation['email']);

            wp_send_json_success([
                'message' => 'Lisans başarıyla aktive edildi!',
                'expiry' => $validation['expiry'],
                'plan' => 'Pro'
            ]);
        } else {
            wp_send_json_error($validation['message']);
        }
    }

    /**
     * Lisans deaktivasyonu (AJAX)
     */
    public function ajax_deactivate_license()
    {
        check_ajax_referer('wasw_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Yetki hatası.');
        }

        delete_option('wasw_license_key');
        delete_option('wasw_license_valid');
        delete_option('wasw_license_expiry');
        delete_option('wasw_license_email');

        wp_send_json_success(['message' => 'Lisans deaktive edildi.']);
    }

    /**
     * Lisans anahtarı doğrulama
     * 
     * @param string $key Lisans anahtarı
     * @return array
     */
    private static function validate_license_key($key)
    {
        // Demo/Test anahtarları
        $demo_keys = [
            'WASW-PRO-TEST-2024' => [
                'valid' => true,
                'expiry' => date('Y-m-d', strtotime('+1 year')),
                'email' => 'test@example.com',
                'message' => 'Test lisansı aktif.'
            ],
            'WASW-PRO-MONTHLY' => [
                'valid' => true,
                'expiry' => date('Y-m-d', strtotime('+30 days')),
                'email' => 'monthly@example.com',
                'message' => 'Aylık lisans aktif.'
            ]
        ];

        if (isset($demo_keys[$key])) {
            return $demo_keys[$key];
        }

        // Gerçek API doğrulaması için buraya kod eklenebilir
        // Örnek: isapehlivan.com/api/license/validate endpoint'i

        // Format kontrolü: WASW-XXXX-XXXX-XXXX
        if (preg_match('/^WASW-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $key)) {
            // Geçerli format, ama doğrulanmamış
            return [
                'valid' => false,
                'message' => 'Lisans anahtarı geçersiz veya süresi dolmuş. Destek için iletişime geçin.'
            ];
        }

        return [
            'valid' => false,
            'message' => 'Geçersiz lisans anahtarı formatı.'
        ];
    }

    /**
     * Lisans durumu HTML badge
     * 
     * @return string
     */
    public static function get_license_badge_html()
    {
        $status = self::get_license_status();

        switch ($status) {
            case self::LICENSE_PRO:
                $expiry = get_option('wasw_license_expiry');
                $expiry_text = $expiry ? date_i18n('d M Y', strtotime($expiry)) : '';
                return '<span class="wasw-license-badge wasw-license-pro">👑 PRO' . ($expiry_text ? ' <small>(' . $expiry_text . ' kadar)</small>' : '') . '</span>';

            case self::LICENSE_FREE_TRIAL:
                $days = self::get_trial_days_left();
                return '<span class="wasw-license-badge wasw-license-trial">🎁 Deneme (' . $days . ' gün kaldı)</span>';

            default:
                return '<span class="wasw-license-badge wasw-license-expired">⚠️ Süresi Doldu</span>';
        }
    }

    /**
     * Plan karşılaştırma tablosu
     * 
     * @return string HTML
     */
    public static function get_plans_comparison_html()
    {
        $html = '<div class="wasw-plans-grid">';

        // Free Plan
        $html .= '<div class="wasw-plan-card wasw-plan-free">';
        $html .= '<div class="wasw-plan-header"><h3>🎁 Deneme</h3><div class="wasw-plan-price">₺0<span>/15 gün</span></div></div>';
        $html .= '<ul class="wasw-plan-features">';
        $html .= '<li>✅ Günde ' . self::FREE_DAILY_LIMIT . ' içerik</li>';
        $html .= '<li>✅ Temel SEO optimizasyonu</li>';
        $html .= '<li>✅ Rank Math & Yoast desteği</li>';
        $html .= '<li>❌ AI görsel oluşturma</li>';
        $html .= '<li>❌ Toplu işlem</li>';
        $html .= '<li>❌ PDF referans</li>';
        $html .= '</ul>';
        $html .= '</div>';

        // Pro Plan
        $html .= '<div class="wasw-plan-card wasw-plan-pro">';
        $html .= '<div class="wasw-plan-ribbon">Önerilen</div>';
        $html .= '<div class="wasw-plan-header"><h3>👑 Pro</h3><div class="wasw-plan-price">₺100<span>/ay</span></div></div>';
        $html .= '<ul class="wasw-plan-features">';
        $html .= '<li>✅ <strong>Sınırsız</strong> içerik</li>';
        $html .= '<li>✅ Gelişmiş SEO optimizasyonu</li>';
        $html .= '<li>✅ Rank Math & Yoast desteği</li>';
        $html .= '<li>✅ AI görsel oluşturma</li>';
        $html .= '<li>✅ Toplu işlem</li>';
        $html .= '<li>✅ PDF referans</li>';
        $html .= '<li>✅ Öncelikli destek</li>';
        $html .= '</ul>';
        $html .= '<a href="https://isapehlivan.com/woo-ai-seo-pro" target="_blank" class="wasw-btn wasw-btn-pro">Pro\'ya Yükselt</a>';
        $html .= '</div>';

        $html .= '</div>';

        return $html;
    }

    /**
     * Lisans yönetim sayfası HTML
     * 
     * @return string
     */
    public static function render_license_page()
    {
        $status = self::get_license_status();
        $license_key = get_option('wasw_license_key');
        $license_email = get_option('wasw_license_email');
        ?>
        <div class="wasw-card">
            <h3>🔐 Lisans Durumu</h3>

            <div class="wasw-license-status-box">
                <?php echo self::get_license_badge_html(); ?>

                <?php if ($status === self::LICENSE_PRO): ?>
                    <div class="wasw-license-info">
                        <p><strong>Lisans Anahtarı:</strong>
                            <?php echo esc_html(substr($license_key, 0, 9) . '****-****'); ?></p>
                        <?php if ($license_email): ?>
                            <p><strong>Kayıtlı E-posta:</strong> <?php echo esc_html($license_email); ?></p>
                        <?php endif; ?>
                        <p><strong>Bitiş Tarihi:</strong>
                            <?php echo date_i18n('d F Y', strtotime(get_option('wasw_license_expiry'))); ?></p>
                    </div>
                    <button type="button" id="wasw-deactivate-license" class="wasw-btn wasw-btn-danger"
                        style="margin-top:15px;">Lisansı Deaktive Et</button>

                <?php elseif ($status === self::LICENSE_FREE_TRIAL): ?>
                    <div class="wasw-trial-info">
                        <div class="wasw-trial-progress">
                            <div class="wasw-trial-bar">
                                <div class="wasw-trial-fill"
                                    style="width: <?php echo (self::get_trial_days_left() / self::TRIAL_DAYS) * 100; ?>%"></div>
                            </div>
                            <p><?php echo self::get_trial_days_left(); ?> gün kaldı</p>
                        </div>
                        <p style="margin-top:10px; color:#64748b;">Deneme süreniz dolmadan Pro plana yükselterek tüm
                            özelliklere sınırsız erişin!</p>
                    </div>

                <?php else: ?>
                    <div class="wasw-expired-info">
                        <p style="color:#dc2626; font-weight:600;">Deneme süreniz doldu!</p>
                        <p>Eklentiyi kullanmaya devam etmek için Pro plana yükseltin.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($status !== self::LICENSE_PRO): ?>
            <div class="wasw-card">
                <h3>🔑 Lisans Aktive Et</h3>
                <p style="color:#64748b; margin-bottom:20px;">Pro lisans anahtarınız varsa aşağıya girin:</p>

                <div class="wasw-license-form">
                    <div class="wasw-form-group">
                        <input type="text" id="wasw-license-key" class="wasw-input-field"
                            placeholder="WASW-XXXX-XXXX-XXXX" style="max-width:400px;">
                    </div>
                    <button type="button" id="wasw-activate-license" class="wasw-btn">Aktive Et</button>
                </div>

                <div id="wasw-license-message" style="margin-top:15px;"></div>
            </div>

            <div class="wasw-card">
                <h3>📊 Plan Karşılaştırması</h3>
                <?php echo self::get_plans_comparison_html(); ?>
            </div>
        <?php endif; ?>

        <script>
            jQuery(document).ready(function ($) {
                $('#wasw-activate-license').on('click', function () {
                    var key = $('#wasw-license-key').val().trim();
                    var $btn = $(this);
                    var $msg = $('#wasw-license-message');

                    if (!key) {
                        $msg.html('<div class="wasw-notice wasw-notice-error">Lütfen lisans anahtarı girin.</div>');
                        return;
                    }

                    $btn.prop('disabled', true).text('Kontrol ediliyor...');

                    $.post(wasw_vars.ajax_url, {
                        action: 'wasw_activate_license',
                        nonce: wasw_vars.nonce,
                        license_key: key
                    }, function (res) {
                        if (res.success) {
                            $msg.html('<div class="wasw-notice wasw-notice-success">' + res.data.message + '</div>');
                            setTimeout(function () { location.reload(); }, 1500);
                        } else {
                            $msg.html('<div class="wasw-notice wasw-notice-error">' + res.data + '</div>');
                            $btn.prop('disabled', false).text('Aktive Et');
                        }
                    });
                });

                $('#wasw-deactivate-license').on('click', function () {
                    if (!confirm('Lisansı deaktive etmek istediğinize emin misiniz?')) return;

                    var $btn = $(this);
                    $btn.prop('disabled', true).text('Deaktive ediliyor...');

                    $.post(wasw_vars.ajax_url, {
                        action: 'wasw_deactivate_license',
                        nonce: wasw_vars.nonce
                    }, function (res) {
                        if (res.success) {
                            location.reload();
                        }
                    });
                });
            });
        </script>
        <?php
    }
}
