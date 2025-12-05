<?php
/**
 * StyleGenius Pro Affiliates Integration
 *
 * Handles affiliate links and partner integrations
 *
 * @package    StyleGenius_Pro
 * @subpackage StyleGenius_Pro/includes/integrations
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Affiliates Integration Class
 */
class StyleGenius_Affiliates {

    /**
     * Settings instance
     *
     * @var StyleGenius_Settings
     */
    private $settings;

    /**
     * Affiliate partners configuration
     *
     * @var array
     */
    private $partners;

    /**
     * Constructor
     */
    public function __construct() {
        $this->settings = new StyleGenius_Settings();
        $this->load_partners();
    }

    /**
     * Initialize hooks
     */
    public function init() {
        // Track clicks
        add_action('wp_ajax_sg_track_affiliate_click', array($this, 'track_click'));
        add_action('wp_ajax_nopriv_sg_track_affiliate_click', array($this, 'track_click'));

        // Redirect handler
        add_action('init', array($this, 'handle_redirect'));

        // Cron job for affiliate stats
        add_action('stylegenius_affiliate_stats_sync', array($this, 'sync_affiliate_stats'));

        // Schedule cron
        if (!wp_next_scheduled('stylegenius_affiliate_stats_sync')) {
            wp_schedule_event(time(), 'daily', 'stylegenius_affiliate_stats_sync');
        }
    }

    /**
     * Load affiliate partners configuration
     */
    private function load_partners() {
        $this->partners = array(
            'zalando' => array(
                'name' => 'Zalando',
                'base_url' => 'https://www.zalando.de',
                'affiliate_param' => 'partner_id',
                'default_id' => '',
                'categories' => array(
                    'damen' => '/damen-bekleidung/',
                    'herren' => '/herren-bekleidung/',
                    'schuhe-damen' => '/damenschuhe/',
                    'schuhe-herren' => '/herrenschuhe/',
                    'accessoires' => '/accessoires/',
                ),
                'search_url' => '/suche/',
                'search_param' => 'q',
                'logo' => STYLEGENIUS_PLUGIN_URL . 'assets/images/partners/zalando.svg',
                'color' => '#FF6900',
                'description' => 'Europas führende Online-Plattform für Mode',
            ),
            'aboutyou' => array(
                'name' => 'AboutYou',
                'base_url' => 'https://www.aboutyou.de',
                'affiliate_param' => 'utm_source',
                'secondary_params' => array(
                    'utm_medium' => 'affiliate',
                    'utm_campaign' => 'stylegenius',
                ),
                'default_id' => '',
                'categories' => array(
                    'damen' => '/frauen/bekleidung/',
                    'herren' => '/maenner/bekleidung/',
                    'schuhe-damen' => '/frauen/schuhe/',
                    'schuhe-herren' => '/maenner/schuhe/',
                ),
                'search_url' => '/suche/',
                'search_param' => 'term',
                'logo' => STYLEGENIUS_PLUGIN_URL . 'assets/images/partners/aboutyou.svg',
                'color' => '#E6007E',
                'description' => 'Dein persönlicher Fashion-Shop',
            ),
            'amazon' => array(
                'name' => 'Amazon Fashion',
                'base_url' => 'https://www.amazon.de',
                'affiliate_param' => 'tag',
                'default_id' => '',
                'categories' => array(
                    'damen' => '/b?node=78689031',
                    'herren' => '/b?node=78689131',
                    'schuhe' => '/b?node=355007011',
                    'accessoires' => '/b?node=193708031',
                ),
                'search_url' => '/s',
                'search_param' => 'k',
                'additional_params' => array(
                    'i' => 'fashion',
                ),
                'logo' => STYLEGENIUS_PLUGIN_URL . 'assets/images/partners/amazon.svg',
                'color' => '#FF9900',
                'description' => 'Die größte Auswahl an Mode',
            ),
            'hm' => array(
                'name' => 'H&M',
                'base_url' => 'https://www2.hm.com/de_de',
                'affiliate_param' => 'utm_source',
                'secondary_params' => array(
                    'utm_medium' => 'affiliate',
                    'utm_campaign' => 'stylegenius',
                ),
                'default_id' => 'stylegenius',
                'categories' => array(
                    'damen' => '/damen.html',
                    'herren' => '/herren.html',
                    'divided' => '/divided.html',
                ),
                'search_url' => '/search-results.html',
                'search_param' => 'q',
                'logo' => STYLEGENIUS_PLUGIN_URL . 'assets/images/partners/hm.svg',
                'color' => '#E50010',
                'description' => 'Fashion und Qualität zum besten Preis',
            ),
            'asos' => array(
                'name' => 'ASOS',
                'base_url' => 'https://www.asos.com/de',
                'affiliate_param' => 'affid',
                'secondary_params' => array(
                    'channelref' => 'affiliate',
                    'pubref' => 'stylegenius',
                ),
                'default_id' => '',
                'categories' => array(
                    'damen' => '/frauen/',
                    'herren' => '/maenner/',
                    'schuhe' => '/frauen/schuhe/',
                    'accessoires' => '/frauen/accessoires/',
                ),
                'search_url' => '/search/',
                'search_param' => 'q',
                'logo' => STYLEGENIUS_PLUGIN_URL . 'assets/images/partners/asos.svg',
                'color' => '#2D2D2D',
                'description' => 'Entdecke Fashion online',
            ),
            'otto' => array(
                'name' => 'OTTO',
                'base_url' => 'https://www.otto.de',
                'affiliate_param' => 'partner',
                'default_id' => '',
                'categories' => array(
                    'damen' => '/mode/damenmode/',
                    'herren' => '/mode/herrenmode/',
                    'schuhe' => '/mode/schuhe/',
                ),
                'search_url' => '/suche/',
                'search_param' => 'q',
                'logo' => STYLEGENIUS_PLUGIN_URL . 'assets/images/partners/otto.svg',
                'color' => '#D42124',
                'description' => 'Online shoppen bei OTTO',
            ),
        );
    }

    /**
     * Get all active partners
     *
     * @return array Active partners
     */
    public function get_active_partners() {
        $active = array();

        foreach ($this->partners as $key => $partner) {
            $affiliate_id = $this->settings->get("affiliate_{$key}_id", $partner['default_id']);
            if (!empty($affiliate_id) || !empty($partner['default_id'])) {
                $partner['affiliate_id'] = $affiliate_id ?: $partner['default_id'];
                $partner['key'] = $key;
                $active[$key] = $partner;
            }
        }

        return $active;
    }

    /**
     * Get partner info
     *
     * @param string $partner_key Partner key
     * @return array|null Partner info
     */
    public function get_partner($partner_key) {
        if (!isset($this->partners[$partner_key])) {
            return null;
        }

        $partner = $this->partners[$partner_key];
        $partner['affiliate_id'] = $this->settings->get(
            "affiliate_{$partner_key}_id",
            $partner['default_id']
        );
        $partner['key'] = $partner_key;

        return $partner;
    }

    /**
     * Generate affiliate link
     *
     * @param string $partner_key Partner key
     * @param string $url         Target URL (optional)
     * @param array  $params      Additional parameters
     * @return string Affiliate link
     */
    public function generate_link($partner_key, $url = '', $params = array()) {
        $partner = $this->get_partner($partner_key);

        if (!$partner) {
            return $url ?: '#';
        }

        // Build base URL
        $base_url = $url ?: $partner['base_url'];

        // If URL doesn't include the partner's domain, construct it
        if (!empty($url) && strpos($url, $partner['base_url']) === false) {
            // Assume it's a relative path
            $base_url = rtrim($partner['base_url'], '/') . '/' . ltrim($url, '/');
        }

        // Parse existing URL parameters
        $url_parts = wp_parse_url($base_url);
        $existing_params = array();
        if (!empty($url_parts['query'])) {
            parse_str($url_parts['query'], $existing_params);
        }

        // Add affiliate parameter
        $affiliate_params = array();
        if (!empty($partner['affiliate_id'])) {
            $affiliate_params[$partner['affiliate_param']] = $partner['affiliate_id'];
        }

        // Add secondary params
        if (!empty($partner['secondary_params'])) {
            $affiliate_params = array_merge($affiliate_params, $partner['secondary_params']);
        }

        // Merge all parameters
        $all_params = array_merge($existing_params, $affiliate_params, $params);

        // Construct final URL
        $scheme = $url_parts['scheme'] ?? 'https';
        $host = $url_parts['host'] ?? '';
        $path = $url_parts['path'] ?? '';

        $final_url = "{$scheme}://{$host}{$path}";
        if (!empty($all_params)) {
            $final_url .= '?' . http_build_query($all_params);
        }

        // Use redirect for tracking
        return $this->get_tracking_url($partner_key, $final_url);
    }

    /**
     * Generate search link
     *
     * @param string $partner_key Partner key
     * @param string $query       Search query
     * @param string $category    Category (optional)
     * @return string Search URL
     */
    public function generate_search_link($partner_key, $query, $category = '') {
        $partner = $this->get_partner($partner_key);

        if (!$partner) {
            return '#';
        }

        // Build search URL
        $search_url = $partner['base_url'] . $partner['search_url'];

        // Add search query
        $params = array(
            $partner['search_param'] => $query,
        );

        // Add additional search params
        if (!empty($partner['additional_params'])) {
            $params = array_merge($params, $partner['additional_params']);
        }

        return $this->generate_link($partner_key, $search_url, $params);
    }

    /**
     * Generate category link
     *
     * @param string $partner_key  Partner key
     * @param string $category_key Category key
     * @return string Category URL
     */
    public function generate_category_link($partner_key, $category_key) {
        $partner = $this->get_partner($partner_key);

        if (!$partner || !isset($partner['categories'][$category_key])) {
            return '#';
        }

        $category_path = $partner['categories'][$category_key];
        return $this->generate_link($partner_key, $category_path);
    }

    /**
     * Get tracking URL for clicks
     *
     * @param string $partner_key Partner key
     * @param string $target_url  Target URL
     * @return string Tracking URL
     */
    public function get_tracking_url($partner_key, $target_url) {
        $tracking_enabled = $this->settings->get('affiliate_tracking_enabled', true);

        if (!$tracking_enabled) {
            return $target_url;
        }

        return add_query_arg(array(
            'sg_affiliate' => $partner_key,
            'sg_redirect' => urlencode($target_url),
            'sg_nonce' => wp_create_nonce('sg_affiliate_redirect'),
        ), home_url('/'));
    }

    /**
     * Handle redirect requests
     */
    public function handle_redirect() {
        if (!isset($_GET['sg_affiliate']) || !isset($_GET['sg_redirect'])) {
            return;
        }

        // Verify nonce
        if (!wp_verify_nonce($_GET['sg_nonce'] ?? '', 'sg_affiliate_redirect')) {
            return;
        }

        $partner_key = sanitize_text_field($_GET['sg_affiliate']);
        $target_url = urldecode($_GET['sg_redirect']);

        // Validate partner
        if (!isset($this->partners[$partner_key])) {
            return;
        }

        // Track the click
        $this->log_click($partner_key, $target_url);

        // Redirect
        wp_redirect($target_url);
        exit;
    }

    /**
     * Log affiliate click
     *
     * @param string $partner_key Partner key
     * @param string $target_url  Target URL
     */
    private function log_click($partner_key, $target_url) {
        global $wpdb;

        $db = new StyleGenius_Database();
        $tables = $db->get_table_names();

        $user_id = get_current_user_id();

        $wpdb->insert(
            $tables['affiliate_clicks'],
            array(
                'user_id' => $user_id ?: null,
                'partner' => $partner_key,
                'target_url' => $target_url,
                'referrer' => wp_get_referer(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'ip_hash' => md5($_SERVER['REMOTE_ADDR'] ?? ''),
                'created_at' => current_time('mysql'),
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        // Award points for using shopping links (once per day per partner)
        if ($user_id) {
            $today_clicks = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$tables['affiliate_clicks']}
                     WHERE user_id = %d AND partner = %s AND DATE(created_at) = %s",
                    $user_id,
                    $partner_key,
                    date('Y-m-d')
                )
            );

            if ($today_clicks <= 1) {
                $points = new StyleGenius_Points();
                $points->add_points(
                    $user_id,
                    5,
                    sprintf(__('Shopping bei %s', 'stylegenius-pro'), $this->partners[$partner_key]['name']),
                    'affiliate_click'
                );
            }
        }
    }

    /**
     * AJAX: Track click
     */
    public function track_click() {
        check_ajax_referer('stylegenius_nonce', 'nonce');

        $partner_key = sanitize_text_field($_POST['partner'] ?? '');
        $target_url = esc_url_raw($_POST['url'] ?? '');

        if (!$partner_key || !$target_url) {
            wp_send_json_error();
        }

        $this->log_click($partner_key, $target_url);

        wp_send_json_success();
    }

    /**
     * Get affiliate statistics
     *
     * @param string $period Period: day, week, month, all
     * @return array Statistics
     */
    public function get_stats($period = 'month') {
        global $wpdb;

        $db = new StyleGenius_Database();
        $tables = $db->get_table_names();

        switch ($period) {
            case 'day':
                $date_filter = "DATE(created_at) = CURDATE()";
                break;
            case 'week':
                $date_filter = "created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                break;
            case 'month':
            default:
                $date_filter = "created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                break;
            case 'all':
                $date_filter = "1=1";
                break;
        }

        // Total clicks
        $total_clicks = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$tables['affiliate_clicks']} WHERE {$date_filter}"
        );

        // Clicks by partner
        $by_partner = $wpdb->get_results(
            "SELECT partner, COUNT(*) as clicks
             FROM {$tables['affiliate_clicks']}
             WHERE {$date_filter}
             GROUP BY partner
             ORDER BY clicks DESC"
        );

        // Clicks by day (last 30 days)
        $by_day = $wpdb->get_results(
            "SELECT DATE(created_at) as date, COUNT(*) as clicks
             FROM {$tables['affiliate_clicks']}
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY DATE(created_at)
             ORDER BY date ASC"
        );

        // Top users
        $top_users = $wpdb->get_results(
            "SELECT user_id, COUNT(*) as clicks
             FROM {$tables['affiliate_clicks']}
             WHERE user_id IS NOT NULL AND {$date_filter}
             GROUP BY user_id
             ORDER BY clicks DESC
             LIMIT 10"
        );

        return array(
            'total_clicks' => (int) $total_clicks,
            'by_partner' => $by_partner,
            'by_day' => $by_day,
            'top_users' => $top_users,
        );
    }

    /**
     * Get product recommendations
     *
     * @param int    $user_id  User ID
     * @param string $category Category
     * @param array  $filters  Filters
     * @return array Product recommendations with affiliate links
     */
    public function get_recommendations($user_id, $category = '', $filters = array()) {
        $recommendations = array();

        // Get user's style profile
        $user = new StyleGenius_User($user_id);
        $style_type = $user->get_style_type();
        $color_profile = $user->get_color_profile();

        // Build search terms based on user profile
        $search_terms = $this->build_search_terms($style_type, $color_profile, $category, $filters);

        // Get active partners
        $active_partners = $this->get_active_partners();

        foreach ($active_partners as $partner_key => $partner) {
            foreach ($search_terms as $term) {
                $recommendations[] = array(
                    'partner' => $partner['name'],
                    'partner_key' => $partner_key,
                    'logo' => $partner['logo'],
                    'color' => $partner['color'],
                    'search_term' => $term,
                    'url' => $this->generate_search_link($partner_key, $term),
                    'category_url' => $category ? $this->generate_category_link($partner_key, $category) : null,
                );
            }
        }

        return $recommendations;
    }

    /**
     * Build search terms from user profile
     *
     * @param string $style_type    Style type
     * @param array  $color_profile Color profile
     * @param string $category      Category
     * @param array  $filters       Additional filters
     * @return array Search terms
     */
    private function build_search_terms($style_type, $color_profile, $category, $filters) {
        $terms = array();

        // Style-specific terms
        $style_terms = array(
            'klassisch' => array('klassisch elegant', 'zeitlos', 'business'),
            'modern' => array('modern minimalistisch', 'clean cut', 'contemporary'),
            'romantisch' => array('romantisch feminin', 'floral', 'verspielt'),
            'sportlich' => array('sportlich casual', 'athleisure', 'active'),
            'kreativ' => array('kreativ bunt', 'statement', 'individuell'),
            'natuerlich' => array('natürlich lässig', 'boho', 'organic'),
        );

        if ($style_type && isset($style_terms[$style_type])) {
            $terms = array_merge($terms, $style_terms[$style_type]);
        }

        // Category terms
        if ($category) {
            $category_mapping = array(
                'tops' => 'oberteile',
                'bottoms' => 'hosen',
                'dresses' => 'kleider',
                'outerwear' => 'jacken mäntel',
                'shoes' => 'schuhe',
                'accessories' => 'accessoires',
            );

            if (isset($category_mapping[$category])) {
                $base_term = $category_mapping[$category];

                // Combine with style
                if ($style_type && isset($style_terms[$style_type][0])) {
                    $terms[] = $base_term . ' ' . $style_terms[$style_type][0];
                }

                $terms[] = $base_term;
            }
        }

        // Color-based terms
        if (!empty($color_profile) && !empty($color_profile['season'])) {
            $season_colors = array(
                'fruehling' => array('warm', 'koralle', 'pfirsich', 'türkis'),
                'sommer' => array('kühl', 'lavendel', 'rosé', 'blaugrau'),
                'herbst' => array('warm', 'rost', 'oliv', 'senf'),
                'winter' => array('kühl', 'schwarz weiß', 'königsblau', 'burgund'),
            );

            $season = $color_profile['season'];
            if (isset($season_colors[$season])) {
                foreach ($season_colors[$season] as $color) {
                    $terms[] = $color;
                }
            }
        }

        // Apply filters
        if (!empty($filters['budget'])) {
            $budget_terms = array(
                'low' => 'günstig',
                'medium' => 'qualität',
                'high' => 'premium luxus',
            );
            if (isset($budget_terms[$filters['budget']])) {
                $terms[] = $budget_terms[$filters['budget']];
            }
        }

        // Ensure we have at least some generic terms
        if (empty($terms)) {
            $terms = array('mode', 'trend', 'outfit');
        }

        return array_unique($terms);
    }

    /**
     * Render affiliate product card
     *
     * @param array $product Product data
     * @return string HTML
     */
    public function render_product_card($product) {
        ob_start();
        ?>
        <div class="sg-affiliate-card" data-partner="<?php echo esc_attr($product['partner_key']); ?>">
            <div class="sg-affiliate-card-header" style="background-color: <?php echo esc_attr($product['color']); ?>">
                <?php if (!empty($product['logo'])): ?>
                    <img src="<?php echo esc_url($product['logo']); ?>" alt="<?php echo esc_attr($product['partner']); ?>" class="sg-affiliate-logo">
                <?php else: ?>
                    <span class="sg-affiliate-partner-name"><?php echo esc_html($product['partner']); ?></span>
                <?php endif; ?>
            </div>
            <div class="sg-affiliate-card-body">
                <p class="sg-affiliate-search-term">
                    <?php printf(__('Suche nach: %s', 'stylegenius-pro'), '<strong>' . esc_html($product['search_term']) . '</strong>'); ?>
                </p>
                <a href="<?php echo esc_url($product['url']); ?>"
                   class="sg-button sg-button--primary sg-affiliate-link"
                   target="_blank"
                   rel="noopener noreferrer sponsored"
                   data-partner="<?php echo esc_attr($product['partner_key']); ?>">
                    <?php printf(__('Bei %s shoppen', 'stylegenius-pro'), $product['partner']); ?>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                        <polyline points="15 3 21 3 21 9"></polyline>
                        <line x1="10" y1="14" x2="21" y2="3"></line>
                    </svg>
                </a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Sync affiliate stats (cron job)
     */
    public function sync_affiliate_stats() {
        // This would integrate with affiliate network APIs
        // to sync conversion data, commissions, etc.
        // Implementation depends on specific network APIs

        do_action('stylegenius_affiliate_stats_synced');
    }
}
