<?php
/**
 * StyleGenius Pro WooCommerce Integration
 *
 * Handles WooCommerce Subscriptions integration for tier management
 *
 * @package    StyleGenius_Pro
 * @subpackage StyleGenius_Pro/includes/integrations
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WooCommerce Integration Class
 */
class StyleGenius_WooCommerce {

    /**
     * Settings instance
     *
     * @var StyleGenius_Settings
     */
    private $settings;

    /**
     * Tiers instance
     *
     * @var StyleGenius_Tiers
     */
    private $tiers;

    /**
     * Product IDs mapping
     *
     * @var array
     */
    private $product_ids;

    /**
     * Constructor
     */
    public function __construct() {
        $this->settings = new StyleGenius_Settings();
        $this->tiers = new StyleGenius_Tiers();
        $this->product_ids = $this->get_product_ids();
    }

    /**
     * Initialize hooks
     */
    public function init() {
        // Check if WooCommerce is active
        if (!$this->is_woocommerce_active()) {
            return;
        }

        // Subscription status changes
        add_action('woocommerce_subscription_status_active', array($this, 'on_subscription_activated'));
        add_action('woocommerce_subscription_status_on-hold', array($this, 'on_subscription_paused'));
        add_action('woocommerce_subscription_status_cancelled', array($this, 'on_subscription_cancelled'));
        add_action('woocommerce_subscription_status_expired', array($this, 'on_subscription_expired'));
        add_action('woocommerce_subscription_status_pending-cancel', array($this, 'on_subscription_pending_cancel'));

        // Order completion
        add_action('woocommerce_order_status_completed', array($this, 'on_order_completed'));
        add_action('woocommerce_thankyou', array($this, 'on_thank_you_page'));

        // Product display customization
        add_filter('woocommerce_get_price_html', array($this, 'customize_price_html'), 10, 2);
        add_action('woocommerce_single_product_summary', array($this, 'display_tier_benefits'), 25);

        // Cart validation
        add_filter('woocommerce_add_to_cart_validation', array($this, 'validate_cart_tier'), 10, 3);

        // Checkout fields
        add_action('woocommerce_checkout_before_customer_details', array($this, 'display_checkout_info'));

        // Account page
        add_action('woocommerce_account_dashboard', array($this, 'display_stylegenius_status'));

        // REST API
        add_action('woocommerce_rest_api_subscription_status_changed', array($this, 'on_api_subscription_changed'), 10, 3);

        // Coupons for referrals
        add_action('woocommerce_coupon_options', array($this, 'add_referral_coupon_options'), 10, 2);
        add_action('woocommerce_coupon_options_save', array($this, 'save_referral_coupon_options'), 10, 2);
    }

    /**
     * Check if WooCommerce is active
     *
     * @return bool
     */
    public function is_woocommerce_active() {
        return class_exists('WooCommerce');
    }

    /**
     * Check if WooCommerce Subscriptions is active
     *
     * @return bool
     */
    public function is_subscriptions_active() {
        return class_exists('WC_Subscriptions');
    }

    /**
     * Get product IDs from settings
     *
     * @return array Product IDs
     */
    private function get_product_ids() {
        return array(
            'premium' => (int) $this->settings->get('wc_premium_product_id', 0),
            'vip' => (int) $this->settings->get('wc_vip_product_id', 0),
        );
    }

    /**
     * Get tier from product ID
     *
     * @param int $product_id Product ID
     * @return string|null Tier name or null
     */
    public function get_tier_from_product($product_id) {
        foreach ($this->product_ids as $tier => $pid) {
            if ($pid === $product_id) {
                return $tier;
            }
        }
        return null;
    }

    /**
     * Get product ID for tier
     *
     * @param string $tier Tier name
     * @return int Product ID
     */
    public function get_product_for_tier($tier) {
        return $this->product_ids[$tier] ?? 0;
    }

    /**
     * On subscription activated
     *
     * @param WC_Subscription $subscription Subscription object
     */
    public function on_subscription_activated($subscription) {
        $user_id = $subscription->get_user_id();

        if (!$user_id) {
            return;
        }

        // Get tier from subscription items
        $tier = $this->get_subscription_tier($subscription);

        if ($tier) {
            // Upgrade user tier
            $this->tiers->set_user_tier($user_id, $tier);

            // Track subscription ID
            update_user_meta($user_id, 'sg_subscription_id', $subscription->get_id());
            update_user_meta($user_id, 'sg_subscription_status', 'active');
            update_user_meta($user_id, 'sg_subscription_start', current_time('mysql'));

            // Award points for subscription
            $points = new StyleGenius_Points();
            $point_values = array(
                'premium' => 200,
                'vip' => 500,
            );
            if (isset($point_values[$tier])) {
                $points->add_points(
                    $user_id,
                    $point_values[$tier],
                    sprintf(__('%s-Abo gestartet', 'stylegenius-pro'), ucfirst($tier)),
                    'subscription'
                );
            }

            // Log event
            do_action('stylegenius_subscription_activated', $user_id, $tier, $subscription);
        }
    }

    /**
     * On subscription paused
     *
     * @param WC_Subscription $subscription Subscription object
     */
    public function on_subscription_paused($subscription) {
        $user_id = $subscription->get_user_id();

        if (!$user_id) {
            return;
        }

        update_user_meta($user_id, 'sg_subscription_status', 'paused');

        // Don't downgrade immediately - give grace period
        // The tier will be maintained until renewal fails

        do_action('stylegenius_subscription_paused', $user_id, $subscription);
    }

    /**
     * On subscription cancelled
     *
     * @param WC_Subscription $subscription Subscription object
     */
    public function on_subscription_cancelled($subscription) {
        $user_id = $subscription->get_user_id();

        if (!$user_id) {
            return;
        }

        // Downgrade to free tier
        $this->tiers->set_user_tier($user_id, 'free');

        update_user_meta($user_id, 'sg_subscription_status', 'cancelled');
        update_user_meta($user_id, 'sg_subscription_end', current_time('mysql'));

        do_action('stylegenius_subscription_cancelled', $user_id, $subscription);
    }

    /**
     * On subscription expired
     *
     * @param WC_Subscription $subscription Subscription object
     */
    public function on_subscription_expired($subscription) {
        $user_id = $subscription->get_user_id();

        if (!$user_id) {
            return;
        }

        // Downgrade to free tier
        $this->tiers->set_user_tier($user_id, 'free');

        update_user_meta($user_id, 'sg_subscription_status', 'expired');
        update_user_meta($user_id, 'sg_subscription_end', current_time('mysql'));

        do_action('stylegenius_subscription_expired', $user_id, $subscription);
    }

    /**
     * On subscription pending cancel
     *
     * @param WC_Subscription $subscription Subscription object
     */
    public function on_subscription_pending_cancel($subscription) {
        $user_id = $subscription->get_user_id();

        if (!$user_id) {
            return;
        }

        update_user_meta($user_id, 'sg_subscription_status', 'pending_cancel');

        // User keeps tier until end of billing period
        $end_date = $subscription->get_date('end');
        if ($end_date) {
            update_user_meta($user_id, 'sg_tier_valid_until', $end_date);
        }

        do_action('stylegenius_subscription_pending_cancel', $user_id, $subscription);
    }

    /**
     * Get tier from subscription
     *
     * @param WC_Subscription $subscription Subscription object
     * @return string|null Tier name
     */
    private function get_subscription_tier($subscription) {
        foreach ($subscription->get_items() as $item) {
            $product_id = $item->get_product_id();
            $tier = $this->get_tier_from_product($product_id);
            if ($tier) {
                return $tier;
            }
        }
        return null;
    }

    /**
     * On order completed (for one-time upgrades)
     *
     * @param int $order_id Order ID
     */
    public function on_order_completed($order_id) {
        $order = wc_get_order($order_id);

        if (!$order) {
            return;
        }

        $user_id = $order->get_user_id();

        if (!$user_id) {
            return;
        }

        // Check for StyleGenius products
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $tier = $this->get_tier_from_product($product_id);

            if ($tier) {
                // For non-subscription products, set tier
                $product = wc_get_product($product_id);
                if (!$product->is_type('subscription')) {
                    $this->tiers->set_user_tier($user_id, $tier);

                    // Set expiration for fixed-term upgrades
                    $duration = $product->get_meta('_sg_tier_duration');
                    if ($duration) {
                        $expiry = date('Y-m-d H:i:s', strtotime("+{$duration} days"));
                        update_user_meta($user_id, 'sg_tier_valid_until', $expiry);
                    }
                }
            }
        }

        // Process referral reward if applicable
        $this->process_order_referral($order);
    }

    /**
     * Process referral for order
     *
     * @param WC_Order $order Order object
     */
    private function process_order_referral($order) {
        $user_id = $order->get_user_id();

        if (!$user_id) {
            return;
        }

        $referral = new StyleGenius_Referral();
        $referrer_id = get_user_meta($user_id, 'sg_referred_by', true);

        if ($referrer_id) {
            // Check if this is first paid order
            $order_count = wc_get_customer_order_count($user_id);
            if ($order_count === 1) {
                $referral->complete_referral($referrer_id, $user_id);
            }
        }

        // Apply coupon-based referral tracking
        foreach ($order->get_coupon_codes() as $code) {
            $referrer = $referral->get_referrer_by_code($code);
            if ($referrer && $referrer !== $user_id) {
                $referral->complete_referral($referrer, $user_id);
            }
        }
    }

    /**
     * Thank you page message
     *
     * @param int $order_id Order ID
     */
    public function on_thank_you_page($order_id) {
        $order = wc_get_order($order_id);

        if (!$order) {
            return;
        }

        // Check if StyleGenius product was purchased
        $tier_purchased = null;
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $tier = $this->get_tier_from_product($product_id);
            if ($tier) {
                $tier_purchased = $tier;
                break;
            }
        }

        if ($tier_purchased) {
            ?>
            <div class="sg-thank-you-message">
                <div class="sg-thank-you-icon">
                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 2l2 7h7l-5.5 4 2 7L12 16l-5.5 4 2-7L3 9h7z"></path>
                    </svg>
                </div>
                <h3><?php printf(__('Willkommen bei StyleGenius %s!', 'stylegenius-pro'), ucfirst($tier_purchased)); ?></h3>
                <p><?php _e('Dein Account wurde erfolgreich aufgewertet. Entdecke jetzt alle neuen Funktionen!', 'stylegenius-pro'); ?></p>
                <div class="sg-thank-you-buttons">
                    <a href="<?php echo home_url('/style-quiz/'); ?>" class="sg-button sg-button--primary">
                        <?php _e('Style-Quiz starten', 'stylegenius-pro'); ?>
                    </a>
                    <a href="<?php echo home_url('/dashboard/'); ?>" class="sg-button sg-button--secondary">
                        <?php _e('Zum Dashboard', 'stylegenius-pro'); ?>
                    </a>
                </div>
            </div>
            <?php
        }
    }

    /**
     * Customize price HTML
     *
     * @param string     $price   Price HTML
     * @param WC_Product $product Product object
     * @return string Modified price HTML
     */
    public function customize_price_html($price, $product) {
        $tier = $this->get_tier_from_product($product->get_id());

        if ($tier) {
            $tier_info = $this->tiers->get_tier_info($tier);
            if ($tier_info && !empty($tier_info['price_display'])) {
                return '<span class="sg-price">' . $tier_info['price_display'] . '</span>';
            }
        }

        return $price;
    }

    /**
     * Display tier benefits on product page
     */
    public function display_tier_benefits() {
        global $product;

        $tier = $this->get_tier_from_product($product->get_id());

        if (!$tier) {
            return;
        }

        $tier_info = $this->tiers->get_tier_info($tier);

        if (!$tier_info) {
            return;
        }

        ?>
        <div class="sg-product-benefits">
            <h4><?php printf(__('Mit %s erhältst du:', 'stylegenius-pro'), $tier_info['name']); ?></h4>
            <ul>
                <?php foreach ($tier_info['features'] as $feature): ?>
                    <li>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                        <?php echo esc_html($feature); ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php
    }

    /**
     * Validate cart for tier conflicts
     *
     * @param bool $passed     Validation result
     * @param int  $product_id Product ID
     * @param int  $quantity   Quantity
     * @return bool
     */
    public function validate_cart_tier($passed, $product_id, $quantity) {
        $tier = $this->get_tier_from_product($product_id);

        if (!$tier) {
            return $passed;
        }

        $user_id = get_current_user_id();

        if (!$user_id) {
            return $passed;
        }

        // Check if user already has this tier or higher
        $current_tier = $this->tiers->get_user_tier($user_id);

        if ($current_tier === $tier) {
            wc_add_notice(
                sprintf(__('Du hast bereits ein aktives %s-Abo.', 'stylegenius-pro'), ucfirst($tier)),
                'error'
            );
            return false;
        }

        // VIP user trying to add Premium
        if ($current_tier === 'vip' && $tier === 'premium') {
            wc_add_notice(
                __('Du hast bereits VIP-Status. Ein Premium-Abo ist nicht erforderlich.', 'stylegenius-pro'),
                'error'
            );
            return false;
        }

        // Check for duplicate tier products in cart
        foreach (WC()->cart->get_cart() as $cart_item) {
            $cart_tier = $this->get_tier_from_product($cart_item['product_id']);
            if ($cart_tier && $cart_tier !== $tier) {
                wc_add_notice(
                    __('Du kannst nur ein Abo-Produkt gleichzeitig kaufen.', 'stylegenius-pro'),
                    'error'
                );
                return false;
            }
        }

        return $passed;
    }

    /**
     * Display checkout info
     */
    public function display_checkout_info() {
        $has_tier_product = false;

        foreach (WC()->cart->get_cart() as $cart_item) {
            $tier = $this->get_tier_from_product($cart_item['product_id']);
            if ($tier) {
                $has_tier_product = true;
                break;
            }
        }

        if ($has_tier_product) {
            ?>
            <div class="sg-checkout-info">
                <h4><?php _e('StyleGenius Abo-Informationen', 'stylegenius-pro'); ?></h4>
                <p><?php _e('Nach Abschluss der Bestellung wird dein Account sofort aufgewertet.', 'stylegenius-pro'); ?></p>
                <ul>
                    <li><?php _e('Alle Premium-Funktionen werden sofort freigeschaltet', 'stylegenius-pro'); ?></li>
                    <li><?php _e('Du erhältst Bonus-Punkte für dein Gamification-Profil', 'stylegenius-pro'); ?></li>
                    <li><?php _e('Jederzeit kündbar, keine versteckten Kosten', 'stylegenius-pro'); ?></li>
                </ul>
            </div>
            <?php
        }
    }

    /**
     * Display StyleGenius status on account dashboard
     */
    public function display_stylegenius_status() {
        $user_id = get_current_user_id();

        if (!$user_id) {
            return;
        }

        $tier = $this->tiers->get_user_tier($user_id);
        $tier_info = $this->tiers->get_tier_info($tier);
        $status = get_user_meta($user_id, 'sg_subscription_status', true);
        $valid_until = get_user_meta($user_id, 'sg_tier_valid_until', true);

        ?>
        <div class="sg-account-status">
            <h3><?php _e('Dein StyleGenius Status', 'stylegenius-pro'); ?></h3>
            <div class="sg-account-status-card">
                <div class="sg-account-tier">
                    <span class="sg-tier-badge sg-tier-badge--<?php echo esc_attr($tier); ?>">
                        <?php echo esc_html($tier_info['name']); ?>
                    </span>
                    <?php if ($status && $status !== 'active'): ?>
                        <span class="sg-status-badge sg-status-badge--<?php echo esc_attr($status); ?>">
                            <?php echo $this->get_status_label($status); ?>
                        </span>
                    <?php endif; ?>
                </div>

                <?php if ($valid_until && $status === 'pending_cancel'): ?>
                    <p class="sg-valid-until">
                        <?php printf(
                            __('Dein %s-Status ist gültig bis %s', 'stylegenius-pro'),
                            $tier_info['name'],
                            date_i18n('d.m.Y', strtotime($valid_until))
                        ); ?>
                    </p>
                <?php endif; ?>

                <div class="sg-account-links">
                    <a href="<?php echo home_url('/dashboard/'); ?>" class="sg-button sg-button--small">
                        <?php _e('Zum StyleGenius Dashboard', 'stylegenius-pro'); ?>
                    </a>
                    <?php if ($tier === 'free'): ?>
                        <a href="<?php echo home_url('/preise/'); ?>" class="sg-button sg-button--primary sg-button--small">
                            <?php _e('Jetzt upgraden', 'stylegenius-pro'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Get status label
     *
     * @param string $status Status key
     * @return string Label
     */
    private function get_status_label($status) {
        $labels = array(
            'active' => __('Aktiv', 'stylegenius-pro'),
            'paused' => __('Pausiert', 'stylegenius-pro'),
            'cancelled' => __('Gekündigt', 'stylegenius-pro'),
            'expired' => __('Abgelaufen', 'stylegenius-pro'),
            'pending_cancel' => __('Kündigung ausstehend', 'stylegenius-pro'),
        );

        return $labels[$status] ?? ucfirst($status);
    }

    /**
     * API subscription status changed
     *
     * @param WC_Subscription $subscription Subscription object
     * @param string          $new_status   New status
     * @param string          $old_status   Old status
     */
    public function on_api_subscription_changed($subscription, $new_status, $old_status) {
        // Handle API-triggered status changes
        switch ($new_status) {
            case 'active':
                $this->on_subscription_activated($subscription);
                break;
            case 'on-hold':
                $this->on_subscription_paused($subscription);
                break;
            case 'cancelled':
                $this->on_subscription_cancelled($subscription);
                break;
            case 'expired':
                $this->on_subscription_expired($subscription);
                break;
        }
    }

    /**
     * Add referral coupon options
     *
     * @param int       $coupon_id Coupon ID
     * @param WC_Coupon $coupon    Coupon object
     */
    public function add_referral_coupon_options($coupon_id, $coupon) {
        woocommerce_wp_checkbox(array(
            'id' => '_sg_referral_coupon',
            'label' => __('StyleGenius Empfehlungsgutschein', 'stylegenius-pro'),
            'description' => __('Aktivieren, wenn dieser Gutschein für das Empfehlungsprogramm verwendet wird.', 'stylegenius-pro'),
        ));

        woocommerce_wp_text_input(array(
            'id' => '_sg_referrer_user_id',
            'label' => __('Empfehlender Nutzer ID', 'stylegenius-pro'),
            'type' => 'number',
            'description' => __('Die User-ID des Nutzers, der empfohlen hat.', 'stylegenius-pro'),
        ));
    }

    /**
     * Save referral coupon options
     *
     * @param int       $coupon_id Coupon ID
     * @param WC_Coupon $coupon    Coupon object
     */
    public function save_referral_coupon_options($coupon_id, $coupon) {
        $is_referral = isset($_POST['_sg_referral_coupon']) ? 'yes' : 'no';
        update_post_meta($coupon_id, '_sg_referral_coupon', $is_referral);

        if (isset($_POST['_sg_referrer_user_id'])) {
            update_post_meta($coupon_id, '_sg_referrer_user_id', absint($_POST['_sg_referrer_user_id']));
        }
    }

    /**
     * Create upgrade URL
     *
     * @param string $tier Target tier
     * @return string Checkout URL
     */
    public function get_upgrade_url($tier) {
        $product_id = $this->get_product_for_tier($tier);

        if (!$product_id) {
            return home_url('/preise/');
        }

        return add_query_arg('add-to-cart', $product_id, wc_get_checkout_url());
    }

    /**
     * Create subscription products programmatically
     *
     * @return array Created product IDs
     */
    public function create_subscription_products() {
        if (!$this->is_woocommerce_active() || !$this->is_subscriptions_active()) {
            return array();
        }

        $products = array();

        // Premium subscription
        $premium_id = $this->create_product(array(
            'name' => __('StyleGenius Premium', 'stylegenius-pro'),
            'description' => __('Unbegrenzter Zugang zu allen Premium-Funktionen', 'stylegenius-pro'),
            'price' => '9.90',
            'period' => 'month',
            'tier' => 'premium',
        ));
        if ($premium_id) {
            $products['premium'] = $premium_id;
            $this->settings->set('wc_premium_product_id', $premium_id);
        }

        // VIP subscription
        $vip_id = $this->create_product(array(
            'name' => __('StyleGenius VIP', 'stylegenius-pro'),
            'description' => __('Alle Features plus exklusive VIP-Vorteile', 'stylegenius-pro'),
            'price' => '29.90',
            'period' => 'month',
            'tier' => 'vip',
        ));
        if ($vip_id) {
            $products['vip'] = $vip_id;
            $this->settings->set('wc_vip_product_id', $vip_id);
        }

        return $products;
    }

    /**
     * Create a subscription product
     *
     * @param array $args Product arguments
     * @return int|false Product ID or false on failure
     */
    private function create_product($args) {
        $product = new WC_Product_Subscription();

        $product->set_name($args['name']);
        $product->set_description($args['description']);
        $product->set_regular_price($args['price']);
        $product->set_catalog_visibility('visible');
        $product->set_status('publish');

        // Subscription settings
        update_post_meta($product->get_id(), '_subscription_price', $args['price']);
        update_post_meta($product->get_id(), '_subscription_period', $args['period']);
        update_post_meta($product->get_id(), '_subscription_period_interval', '1');
        update_post_meta($product->get_id(), '_subscription_length', '0');

        // Custom meta
        $product->update_meta_data('_sg_tier', $args['tier']);

        $product_id = $product->save();

        return $product_id;
    }

    /**
     * Get user's active subscription
     *
     * @param int $user_id User ID
     * @return WC_Subscription|null
     */
    public function get_user_subscription($user_id) {
        if (!$this->is_subscriptions_active()) {
            return null;
        }

        $subscriptions = wcs_get_users_subscriptions($user_id);

        foreach ($subscriptions as $subscription) {
            if ($subscription->has_status('active')) {
                $tier = $this->get_subscription_tier($subscription);
                if ($tier) {
                    return $subscription;
                }
            }
        }

        return null;
    }

    /**
     * Check if user has active subscription
     *
     * @param int $user_id User ID
     * @return bool
     */
    public function has_active_subscription($user_id) {
        return $this->get_user_subscription($user_id) !== null;
    }
}
