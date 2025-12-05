<?php
/**
 * StyleGenius Content Gate Class
 *
 * Handles access control for premium content based on subscription tiers.
 *
 * @package    StyleGenius_Pro
 * @subpackage StyleGenius_Pro/includes/gating
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Content gating management class.
 */
class StyleGenius_Content_Gate {

    /**
     * Tiers instance.
     *
     * @var StyleGenius_Tiers
     */
    private $tiers;

    /**
     * Feature access rules.
     *
     * @var array
     */
    private $access_rules = array(
        // Feature => minimum tier required
        'style_quiz'          => 'free',
        'chat_basic'          => 'free',
        'wardrobe_basic'      => 'free',
        'leaderboard'         => 'free',

        'photo_analysis'      => 'premium',
        'color_analysis'      => 'premium',
        'capsule_wardrobe'    => 'premium',
        'unlimited_wardrobe'  => 'premium',
        'advanced_chat'       => 'premium',
        'before_after'        => 'premium',
        'challenge_create'    => 'premium',

        'shopping_assistant'  => 'vip',
        'ai_unlimited'        => 'vip',
        'priority_support'    => 'vip',
        'exclusive_challenges' => 'vip',
        'personal_stylist'    => 'vip',
    );

    /**
     * Tier hierarchy.
     *
     * @var array
     */
    private $tier_hierarchy = array(
        'free'    => 0,
        'premium' => 1,
        'vip'     => 2,
    );

    /**
     * Constructor.
     */
    public function __construct() {
        $this->tiers = new StyleGenius_Tiers();
    }

    /**
     * Check if user can access a feature.
     *
     * @param int    $user_id User ID.
     * @param string $feature Feature key.
     * @return bool
     */
    public function can_access(int $user_id, string $feature): bool {
        if (!$user_id) {
            return false;
        }

        // Admin always has access
        if (user_can($user_id, 'manage_options')) {
            return true;
        }

        $user_tier = get_user_meta($user_id, 'sg_tier', true) ?: 'free';
        $required_tier = $this->access_rules[$feature] ?? 'free';

        $user_level = $this->tier_hierarchy[$user_tier] ?? 0;
        $required_level = $this->tier_hierarchy[$required_tier] ?? 0;

        return $user_level >= $required_level;
    }

    /**
     * Get required tier for a feature.
     *
     * @param string $feature Feature key.
     * @return string Tier name.
     */
    public function get_required_tier(string $feature): string {
        return $this->access_rules[$feature] ?? 'free';
    }

    /**
     * Get all features accessible by tier.
     *
     * @param string $tier Tier name.
     * @return array
     */
    public function get_tier_features(string $tier): array {
        $tier_level = $this->tier_hierarchy[$tier] ?? 0;
        $features = array();

        foreach ($this->access_rules as $feature => $required_tier) {
            $required_level = $this->tier_hierarchy[$required_tier] ?? 0;
            if ($tier_level >= $required_level) {
                $features[] = $feature;
            }
        }

        return $features;
    }

    /**
     * Get locked features for user.
     *
     * @param int $user_id User ID.
     * @return array
     */
    public function get_locked_features(int $user_id): array {
        $user_tier = get_user_meta($user_id, 'sg_tier', true) ?: 'free';
        $user_level = $this->tier_hierarchy[$user_tier] ?? 0;
        $locked = array();

        foreach ($this->access_rules as $feature => $required_tier) {
            $required_level = $this->tier_hierarchy[$required_tier] ?? 0;
            if ($user_level < $required_level) {
                $locked[$feature] = array(
                    'feature'       => $feature,
                    'required_tier' => $required_tier,
                    'tier_name'     => $this->tiers->get_tier_name($required_tier),
                );
            }
        }

        return $locked;
    }

    /**
     * Gate content wrapper.
     *
     * @param string   $feature         Feature key.
     * @param callable $content_callback Callback to render content.
     * @param array    $options          Options.
     * @return string HTML output.
     */
    public function gate_content(string $feature, callable $content_callback, array $options = array()): string {
        $user_id = get_current_user_id();

        if ($this->can_access($user_id, $feature)) {
            ob_start();
            call_user_func($content_callback);
            return ob_get_clean();
        }

        // Show locked content / teaser
        $teaser = new StyleGenius_Teaser();
        return $teaser->render($feature, $options);
    }

    /**
     * Shortcode wrapper for gated content.
     *
     * @param array  $atts    Shortcode attributes.
     * @param string $content Enclosed content.
     * @return string
     */
    public function gate_shortcode(array $atts, string $content = ''): string {
        $atts = shortcode_atts(array(
            'feature'      => '',
            'tier'         => '',
            'message'      => '',
            'show_upgrade' => 'true',
        ), $atts);

        $user_id = get_current_user_id();

        // Check by feature or tier
        $has_access = false;

        if (!empty($atts['feature'])) {
            $has_access = $this->can_access($user_id, $atts['feature']);
        } elseif (!empty($atts['tier'])) {
            $user_tier = get_user_meta($user_id, 'sg_tier', true) ?: 'free';
            $user_level = $this->tier_hierarchy[$user_tier] ?? 0;
            $required_level = $this->tier_hierarchy[$atts['tier']] ?? 0;
            $has_access = $user_level >= $required_level;
        }

        if ($has_access) {
            return do_shortcode($content);
        }

        // Show upgrade message
        $teaser = new StyleGenius_Teaser();
        return $teaser->render($atts['feature'] ?: $atts['tier'], array(
            'message'      => $atts['message'],
            'show_upgrade' => $atts['show_upgrade'] === 'true',
        ));
    }

    /**
     * Check access and redirect if needed.
     *
     * @param string $feature       Feature key.
     * @param string $redirect_url  URL to redirect to if no access.
     * @return bool True if access granted.
     */
    public function check_and_redirect(string $feature, string $redirect_url = ''): bool {
        $user_id = get_current_user_id();

        if ($this->can_access($user_id, $feature)) {
            return true;
        }

        if (empty($redirect_url)) {
            $redirect_url = add_query_arg(
                array(
                    'upgrade' => 'required',
                    'feature' => $feature,
                ),
                home_url('/mitgliedschaft/')
            );
        }

        wp_redirect($redirect_url);
        exit;
    }

    /**
     * AJAX access check.
     *
     * @param string $feature Feature key.
     * @return array
     */
    public function ajax_check(string $feature): array {
        $user_id = get_current_user_id();

        if (!$user_id) {
            return array(
                'access'  => false,
                'error'   => 'not_logged_in',
                'message' => 'Bitte melde dich an, um diese Funktion zu nutzen.',
                'action'  => 'login',
            );
        }

        if ($this->can_access($user_id, $feature)) {
            return array(
                'access' => true,
            );
        }

        $required_tier = $this->get_required_tier($feature);
        $tier_info = $this->tiers->get_tier($required_tier);

        return array(
            'access'        => false,
            'error'         => 'tier_required',
            'message'       => sprintf(
                'Diese Funktion ist nur für %s-Mitglieder verfügbar.',
                $tier_info['name']
            ),
            'required_tier' => $required_tier,
            'tier_name'     => $tier_info['name'],
            'tier_price'    => $tier_info['price'],
            'action'        => 'upgrade',
            'upgrade_url'   => $this->get_upgrade_url($required_tier),
        );
    }

    /**
     * Get upgrade URL for tier.
     *
     * @param string $tier Target tier.
     * @return string
     */
    public function get_upgrade_url(string $tier): string {
        $product_id = get_option('sg_woocommerce_' . $tier . '_product_id');

        if ($product_id) {
            return add_query_arg('add-to-cart', $product_id, wc_get_checkout_url());
        }

        return add_query_arg('tier', $tier, home_url('/mitgliedschaft/'));
    }

    /**
     * Usage-based access check.
     *
     * @param int    $user_id User ID.
     * @param string $usage   Usage type (ai_requests, wardrobe_items, etc.).
     * @return array
     */
    public function check_usage_limit(int $user_id, string $usage): array {
        $tier = get_user_meta($user_id, 'sg_tier', true) ?: 'free';
        $limits = $this->tiers->get_tier_limits($tier);

        $current_usage = 0;
        $limit = $limits[$usage] ?? -1;

        switch ($usage) {
            case 'ai_requests':
                $current_usage = intval(get_user_meta($user_id, 'sg_monthly_ai_usage', true));
                break;

            case 'wardrobe_items':
                global $wpdb;
                $table = $wpdb->prefix . 'sg_wardrobe';
                $current_usage = intval($wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM {$table} WHERE user_id = %d",
                        $user_id
                    )
                ));
                break;

            case 'photo_uploads':
                $current_usage = intval(get_user_meta($user_id, 'sg_monthly_uploads', true));
                break;
        }

        // -1 means unlimited
        if ($limit < 0) {
            return array(
                'allowed'   => true,
                'current'   => $current_usage,
                'limit'     => -1,
                'unlimited' => true,
            );
        }

        $allowed = $current_usage < $limit;

        return array(
            'allowed'   => $allowed,
            'current'   => $current_usage,
            'limit'     => $limit,
            'remaining' => max(0, $limit - $current_usage),
            'unlimited' => false,
            'upgrade'   => !$allowed ? $this->get_upgrade_suggestion($tier, $usage) : null,
        );
    }

    /**
     * Get upgrade suggestion based on usage.
     *
     * @param string $current_tier Current tier.
     * @param string $usage        Usage type.
     * @return array|null
     */
    private function get_upgrade_suggestion(string $current_tier, string $usage): ?array {
        $tier_order = array('free', 'premium', 'vip');
        $current_index = array_search($current_tier, $tier_order);

        if ($current_index === false || $current_index >= count($tier_order) - 1) {
            return null;
        }

        $next_tier = $tier_order[$current_index + 1];
        $tier_info = $this->tiers->get_tier($next_tier);
        $limits = $this->tiers->get_tier_limits($next_tier);

        $benefit = $limits[$usage] ?? -1;
        $benefit_text = $benefit < 0 ? 'unbegrenzt' : $benefit;

        return array(
            'tier'        => $next_tier,
            'name'        => $tier_info['name'],
            'price'       => $tier_info['price'],
            'benefit'     => $benefit_text,
            'upgrade_url' => $this->get_upgrade_url($next_tier),
        );
    }

    /**
     * Register access rules dynamically.
     *
     * @param string $feature      Feature key.
     * @param string $minimum_tier Minimum required tier.
     * @return void
     */
    public function register_rule(string $feature, string $minimum_tier): void {
        $this->access_rules[$feature] = $minimum_tier;
    }

    /**
     * Get all access rules.
     *
     * @return array
     */
    public function get_all_rules(): array {
        return $this->access_rules;
    }

    /**
     * Filter for content visibility.
     *
     * @param string $content  Content to filter.
     * @param string $feature  Feature key.
     * @param int    $user_id  User ID.
     * @return string
     */
    public function filter_content(string $content, string $feature, int $user_id = 0): string {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        if ($this->can_access($user_id, $feature)) {
            return $content;
        }

        return '';
    }

    /**
     * Hook into template loading.
     *
     * @param string $template Template path.
     * @param string $feature  Required feature.
     * @return string
     */
    public function gate_template(string $template, string $feature): string {
        if (!$this->can_access(get_current_user_id(), $feature)) {
            return STYLEGENIUS_PATH . 'templates/upgrade-required.php';
        }

        return $template;
    }
}
