<?php
/**
 * StyleGenius Pro Public Class
 *
 * Handles frontend functionality
 *
 * @package    StyleGenius_Pro
 * @subpackage StyleGenius_Pro/includes/public
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Public Class
 */
class StyleGenius_Public {

    /**
     * Plugin version
     *
     * @var string
     */
    private $version;

    /**
     * Constructor
     *
     * @param string $version Plugin version
     */
    public function __construct($version = STYLEGENIUS_VERSION) {
        $this->version = $version;
    }

    /**
     * Initialize hooks
     */
    public function init() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_head', array($this, 'add_og_meta_tags'));
        add_action('wp_footer', array($this, 'render_chat_widget'));
        add_filter('body_class', array($this, 'add_body_classes'));
    }

    /**
     * Enqueue styles
     */
    public function enqueue_styles() {
        // Main stylesheet
        wp_enqueue_style(
            'stylegenius-pro',
            STYLEGENIUS_PLUGIN_URL . 'assets/css/stylegenius-public.css',
            array(),
            $this->version
        );

        // Icon font (Lucide)
        wp_enqueue_style(
            'lucide-icons',
            'https://unpkg.com/lucide-static@latest/font/lucide.css',
            array(),
            $this->version
        );

        // Custom properties / theme
        wp_add_inline_style('stylegenius-pro', $this->get_custom_properties());
    }

    /**
     * Enqueue scripts
     */
    public function enqueue_scripts() {
        // Main script
        wp_enqueue_script(
            'stylegenius-pro',
            STYLEGENIUS_PLUGIN_URL . 'assets/js/stylegenius-public.js',
            array('jquery'),
            $this->version,
            true
        );

        // Localize script
        wp_localize_script('stylegenius-pro', 'styleGeniusData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('stylegenius/v1/'),
            'nonce' => wp_create_nonce('stylegenius_nonce'),
            'restNonce' => wp_create_nonce('wp_rest'),
            'isLoggedIn' => is_user_logged_in(),
            'userId' => get_current_user_id(),
            'userTier' => $this->get_user_tier(),
            'strings' => $this->get_js_strings(),
            'settings' => $this->get_public_settings(),
        ));

        // Conditionally load feature-specific scripts
        if ($this->is_stylegenius_page()) {
            // Chart.js for statistics
            wp_enqueue_script(
                'chartjs',
                'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
                array(),
                '4.4.0',
                true
            );

            // Confetti for celebrations
            wp_enqueue_script(
                'canvas-confetti',
                'https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.2/dist/confetti.browser.min.js',
                array(),
                '1.9.2',
                true
            );
        }
    }

    /**
     * Get custom CSS properties
     *
     * @return string CSS
     */
    private function get_custom_properties() {
        $settings = new StyleGenius_Settings();
        $primary_color = $settings->get('primary_color', '#9333ea');
        $secondary_color = $settings->get('secondary_color', '#ec4899');

        return "
            :root {
                --sg-primary: {$primary_color};
                --sg-primary-rgb: " . $this->hex_to_rgb($primary_color) . ";
                --sg-secondary: {$secondary_color};
                --sg-secondary-rgb: " . $this->hex_to_rgb($secondary_color) . ";
                --sg-gradient: linear-gradient(135deg, {$primary_color}, {$secondary_color});
                --sg-font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                --sg-border-radius: 12px;
                --sg-shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.05);
                --sg-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
                --sg-shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
                --sg-transition: all 0.2s ease;
            }
        ";
    }

    /**
     * Convert hex color to RGB
     *
     * @param string $hex Hex color
     * @return string RGB values
     */
    private function hex_to_rgb($hex) {
        $hex = ltrim($hex, '#');
        return hexdec(substr($hex, 0, 2)) . ', ' .
               hexdec(substr($hex, 2, 2)) . ', ' .
               hexdec(substr($hex, 4, 2));
    }

    /**
     * Get user tier
     *
     * @return string User tier
     */
    private function get_user_tier() {
        if (!is_user_logged_in()) {
            return 'guest';
        }

        $tiers = new StyleGenius_Tiers();
        return $tiers->get_user_tier(get_current_user_id());
    }

    /**
     * Get JavaScript strings for translations
     *
     * @return array Strings
     */
    private function get_js_strings() {
        return array(
            // General
            'loading' => __('Lädt...', 'stylegenius-pro'),
            'error' => __('Ein Fehler ist aufgetreten', 'stylegenius-pro'),
            'success' => __('Erfolgreich!', 'stylegenius-pro'),
            'save' => __('Speichern', 'stylegenius-pro'),
            'cancel' => __('Abbrechen', 'stylegenius-pro'),
            'close' => __('Schließen', 'stylegenius-pro'),
            'confirm' => __('Bestätigen', 'stylegenius-pro'),
            'delete' => __('Löschen', 'stylegenius-pro'),
            'edit' => __('Bearbeiten', 'stylegenius-pro'),

            // Chat
            'chatPlaceholder' => __('Frag mich etwas zum Thema Style...', 'stylegenius-pro'),
            'chatThinking' => __('Denke nach...', 'stylegenius-pro'),
            'chatError' => __('Fehler beim Senden. Bitte versuche es erneut.', 'stylegenius-pro'),
            'chatWelcome' => __('Hallo! Ich bin dein persönlicher Style-Berater. Wie kann ich dir helfen?', 'stylegenius-pro'),
            'chatLimitReached' => __('Du hast dein tägliches Chat-Limit erreicht.', 'stylegenius-pro'),

            // Quiz
            'quizNext' => __('Weiter', 'stylegenius-pro'),
            'quizPrev' => __('Zurück', 'stylegenius-pro'),
            'quizSubmit' => __('Auswertung anzeigen', 'stylegenius-pro'),
            'quizProcessing' => __('Dein Stil wird analysiert...', 'stylegenius-pro'),

            // Wardrobe
            'wardrobeAdded' => __('Zur Garderobe hinzugefügt!', 'stylegenius-pro'),
            'wardrobeRemoved' => __('Aus Garderobe entfernt', 'stylegenius-pro'),
            'wardrobeEmpty' => __('Deine Garderobe ist noch leer.', 'stylegenius-pro'),

            // Upload
            'uploadDragDrop' => __('Bild hierher ziehen oder klicken', 'stylegenius-pro'),
            'uploadProcessing' => __('Bild wird analysiert...', 'stylegenius-pro'),
            'uploadError' => __('Fehler beim Hochladen', 'stylegenius-pro'),

            // Gamification
            'pointsEarned' => __('Punkte verdient!', 'stylegenius-pro'),
            'levelUp' => __('Level aufgestiegen!', 'stylegenius-pro'),
            'badgeEarned' => __('Neues Abzeichen verdient!', 'stylegenius-pro'),
            'streakContinued' => __('Streak fortgesetzt!', 'stylegenius-pro'),

            // Challenges
            'challengeJoined' => __('Du nimmst jetzt teil!', 'stylegenius-pro'),
            'voteSuccess' => __('Danke für deine Stimme!', 'stylegenius-pro'),
            'alreadyVoted' => __('Du hast bereits abgestimmt.', 'stylegenius-pro'),

            // Premium
            'premiumRequired' => __('Premium erforderlich', 'stylegenius-pro'),
            'upgradeNow' => __('Jetzt upgraden', 'stylegenius-pro'),

            // Sharing
            'copied' => __('In Zwischenablage kopiert!', 'stylegenius-pro'),
            'shareSuccess' => __('Erfolgreich geteilt!', 'stylegenius-pro'),
        );
    }

    /**
     * Get public settings
     *
     * @return array Settings
     */
    private function get_public_settings() {
        $settings = new StyleGenius_Settings();

        return array(
            'chatEnabled' => (bool) $settings->get('chat_enabled', true),
            'quizEnabled' => (bool) $settings->get('quiz_enabled', true),
            'gamificationEnabled' => (bool) $settings->get('gamification_enabled', true),
            'challengesEnabled' => (bool) $settings->get('challenges_enabled', true),
            'referralEnabled' => (bool) $settings->get('referral_enabled', true),
            'sharingEnabled' => (bool) $settings->get('sharing_enabled', true),
            'chatWidgetEnabled' => (bool) $settings->get('chat_widget_enabled', true),
            'animationsEnabled' => (bool) $settings->get('animations_enabled', true),
            'soundsEnabled' => (bool) $settings->get('sounds_enabled', false),
        );
    }

    /**
     * Check if current page is a StyleGenius page
     *
     * @return bool
     */
    private function is_stylegenius_page() {
        global $post;

        if (!$post) {
            return false;
        }

        // Check for shortcodes
        $shortcodes = array(
            'stylegenius_quiz',
            'stylegenius_chat',
            'stylegenius_wardrobe',
            'stylegenius_capsule',
            'stylegenius_challenges',
            'stylegenius_dashboard',
            'stylegenius_profile',
            'stylegenius_leaderboard',
        );

        foreach ($shortcodes as $shortcode) {
            if (has_shortcode($post->post_content, $shortcode)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Add OG meta tags
     */
    public function add_og_meta_tags() {
        // Handle dynamic OG images for shared content
        if (isset($_GET['sg_share'])) {
            $share_type = sanitize_text_field($_GET['sg_share']);
            $share_id = isset($_GET['sg_id']) ? absint($_GET['sg_id']) : 0;
            $user_id = isset($_GET['sg_user']) ? absint($_GET['sg_user']) : 0;

            if ($share_type && $user_id) {
                $og_images = new StyleGenius_OG_Images();
                $image_url = $og_images->get_image_url($share_type, $user_id, $share_id);
                $share_data = $og_images->get_share_meta($share_type, $user_id, $share_id);

                if ($image_url && $share_data) {
                    echo '<meta property="og:image" content="' . esc_url($image_url) . '" />' . "\n";
                    echo '<meta property="og:image:width" content="1200" />' . "\n";
                    echo '<meta property="og:image:height" content="630" />' . "\n";
                    echo '<meta property="og:title" content="' . esc_attr($share_data['title']) . '" />' . "\n";
                    echo '<meta property="og:description" content="' . esc_attr($share_data['description']) . '" />' . "\n";
                    echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
                    echo '<meta name="twitter:image" content="' . esc_url($image_url) . '" />' . "\n";
                }
            }
        }
    }

    /**
     * Render floating chat widget
     */
    public function render_chat_widget() {
        $settings = new StyleGenius_Settings();

        if (!$settings->get('chat_widget_enabled', true)) {
            return;
        }

        // Don't show in admin
        if (is_admin()) {
            return;
        }

        // Check if user can access chat
        if (is_user_logged_in()) {
            $gate = new StyleGenius_Content_Gate();
            if (!$gate->can_access('chat')) {
                return;
            }
        }

        ?>
        <div id="sg-chat-widget" class="sg-chat-widget" style="display: none;">
            <button type="button" class="sg-chat-widget-toggle" aria-label="<?php _e('Chat öffnen', 'stylegenius-pro'); ?>">
                <span class="sg-chat-widget-icon sg-chat-widget-icon--open">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                    </svg>
                </span>
                <span class="sg-chat-widget-icon sg-chat-widget-icon--close" style="display: none;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </span>
            </button>

            <div class="sg-chat-widget-container">
                <div class="sg-chat-widget-header">
                    <div class="sg-chat-widget-avatar">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 2l2 7h7l-5.5 4 2 7L12 16l-5.5 4 2-7L3 9h7z"></path>
                        </svg>
                    </div>
                    <div class="sg-chat-widget-title">
                        <strong><?php _e('Style-Beraterin', 'stylegenius-pro'); ?></strong>
                        <span><?php _e('Online', 'stylegenius-pro'); ?></span>
                    </div>
                </div>

                <div class="sg-chat-widget-messages" id="sg-chat-widget-messages">
                    <div class="sg-chat-message sg-chat-message--assistant">
                        <div class="sg-chat-message-content">
                            <?php _e('Hallo! 👋 Ich bin deine persönliche Style-Beraterin. Wie kann ich dir heute helfen?', 'stylegenius-pro'); ?>
                        </div>
                    </div>
                </div>

                <div class="sg-chat-widget-input">
                    <input type="text" id="sg-chat-widget-input"
                           placeholder="<?php _e('Nachricht eingeben...', 'stylegenius-pro'); ?>"
                           autocomplete="off">
                    <button type="button" id="sg-chat-widget-send" aria-label="<?php _e('Senden', 'stylegenius-pro'); ?>">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="22" y1="2" x2="11" y2="13"></line>
                            <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <?php if (!is_user_logged_in()): ?>
        <div id="sg-login-prompt" class="sg-login-prompt" style="display: none;">
            <div class="sg-login-prompt-content">
                <h3><?php _e('Anmelden für Style-Beratung', 'stylegenius-pro'); ?></h3>
                <p><?php _e('Melde dich an, um kostenlose Style-Beratung zu erhalten!', 'stylegenius-pro'); ?></p>
                <a href="<?php echo wp_login_url(get_permalink()); ?>" class="sg-button sg-button--primary">
                    <?php _e('Anmelden', 'stylegenius-pro'); ?>
                </a>
                <a href="<?php echo wp_registration_url(); ?>" class="sg-button sg-button--secondary">
                    <?php _e('Registrieren', 'stylegenius-pro'); ?>
                </a>
            </div>
        </div>
        <?php endif; ?>
        <?php
    }

    /**
     * Add body classes
     *
     * @param array $classes Existing classes
     * @return array Modified classes
     */
    public function add_body_classes($classes) {
        if (is_user_logged_in()) {
            $classes[] = 'sg-logged-in';
            $classes[] = 'sg-tier-' . $this->get_user_tier();
        } else {
            $classes[] = 'sg-logged-out';
        }

        if ($this->is_stylegenius_page()) {
            $classes[] = 'sg-page';
        }

        return $classes;
    }

    /**
     * Render notification toast
     *
     * @param string $message Message
     * @param string $type    Type: success, error, warning, info
     * @param array  $extra   Extra data
     */
    public static function render_toast($message, $type = 'info', $extra = array()) {
        $icons = array(
            'success' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>',
            'error' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>',
            'warning' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>',
            'info' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>',
            'points' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>',
            'badge' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="6"></circle><path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"></path></svg>',
            'level' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="13 17 18 12 13 7"></polyline><polyline points="6 17 11 12 6 7"></polyline></svg>',
        );

        ?>
        <div class="sg-toast sg-toast--<?php echo esc_attr($type); ?>" data-toast>
            <div class="sg-toast-icon">
                <?php echo $icons[$type] ?? $icons['info']; ?>
            </div>
            <div class="sg-toast-content">
                <p class="sg-toast-message"><?php echo esc_html($message); ?></p>
                <?php if (!empty($extra['subtitle'])): ?>
                    <p class="sg-toast-subtitle"><?php echo esc_html($extra['subtitle']); ?></p>
                <?php endif; ?>
            </div>
            <button type="button" class="sg-toast-close" aria-label="<?php _e('Schließen', 'stylegenius-pro'); ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
        <?php
    }

    /**
     * Render gamification popup
     *
     * @param string $type Type: points, level, badge, streak
     * @param array  $data Data
     */
    public static function render_gamification_popup($type, $data) {
        ?>
        <div class="sg-gamification-popup sg-gamification-popup--<?php echo esc_attr($type); ?>" data-gamification-popup>
            <div class="sg-gamification-popup-overlay"></div>
            <div class="sg-gamification-popup-content">
                <?php if ($type === 'points'): ?>
                    <div class="sg-gamification-popup-icon">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                        </svg>
                    </div>
                    <h3>+<?php echo absint($data['points']); ?> <?php _e('Punkte', 'stylegenius-pro'); ?></h3>
                    <p><?php echo esc_html($data['reason'] ?? ''); ?></p>

                <?php elseif ($type === 'level'): ?>
                    <div class="sg-gamification-popup-icon sg-gamification-popup-icon--level">
                        <span class="sg-level-badge"><?php echo absint($data['level']); ?></span>
                    </div>
                    <h3><?php _e('Level Up!', 'stylegenius-pro'); ?></h3>
                    <p><?php printf(__('Du hast Level %d erreicht!', 'stylegenius-pro'), $data['level']); ?></p>
                    <?php if (!empty($data['title'])): ?>
                        <p class="sg-gamification-popup-title"><?php echo esc_html($data['title']); ?></p>
                    <?php endif; ?>

                <?php elseif ($type === 'badge'): ?>
                    <div class="sg-gamification-popup-icon sg-gamification-popup-icon--badge">
                        <?php if (!empty($data['icon'])): ?>
                            <img src="<?php echo esc_url($data['icon']); ?>" alt="">
                        <?php else: ?>
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="8" r="6"></circle>
                                <path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"></path>
                            </svg>
                        <?php endif; ?>
                    </div>
                    <h3><?php _e('Neues Abzeichen!', 'stylegenius-pro'); ?></h3>
                    <p class="sg-badge-name"><?php echo esc_html($data['name']); ?></p>
                    <?php if (!empty($data['description'])): ?>
                        <p><?php echo esc_html($data['description']); ?></p>
                    <?php endif; ?>

                <?php elseif ($type === 'streak'): ?>
                    <div class="sg-gamification-popup-icon sg-gamification-popup-icon--streak">
                        <span class="sg-streak-count"><?php echo absint($data['days']); ?></span>
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 23c-1.66 0-3-1.34-3-3 0-1.31.84-2.42 2-2.83V15c-4.42 0-8-3.58-8-8 0-.55.45-1 1-1s1 .45 1 1c0 3.31 2.69 6 6 6V2l3.5 6 3.5-6v11c3.31 0 6-2.69 6-6 0-.55.45-1 1-1s1 .45 1 1c0 4.42-3.58 8-8 8v2.17c1.16.41 2 1.52 2 2.83 0 1.66-1.34 3-3 3z"/>
                        </svg>
                    </div>
                    <h3><?php _e('Streak fortgesetzt!', 'stylegenius-pro'); ?></h3>
                    <p><?php printf(__('%d Tage in Folge!', 'stylegenius-pro'), $data['days']); ?></p>
                <?php endif; ?>

                <button type="button" class="sg-button sg-button--primary sg-gamification-popup-close">
                    <?php _e('Super!', 'stylegenius-pro'); ?>
                </button>
            </div>
        </div>
        <?php
    }

    /**
     * Get user dashboard data
     *
     * @param int $user_id User ID
     * @return array Dashboard data
     */
    public function get_dashboard_data($user_id) {
        $user = new StyleGenius_User($user_id);
        $points = new StyleGenius_Points();
        $levels = new StyleGenius_Levels();
        $streaks = new StyleGenius_Streaks();
        $achievements = new StyleGenius_Achievements();

        return array(
            'profile' => array(
                'name' => $user->get_display_name(),
                'tier' => $user->get_tier(),
                'style_type' => $user->get_style_type(),
                'color_profile' => $user->get_color_profile(),
                'joined' => $user->get_registration_date(),
            ),
            'gamification' => array(
                'points' => $points->get_user_points($user_id),
                'level' => $levels->get_user_level($user_id),
                'level_progress' => $levels->get_progress_percentage($user_id),
                'streak' => $streaks->get_current_streak($user_id),
                'badges' => $achievements->get_user_achievements($user_id),
                'badges_count' => count($achievements->get_user_achievements($user_id)),
            ),
            'stats' => array(
                'wardrobe_items' => $this->count_user_wardrobe($user_id),
                'chat_sessions' => $this->count_user_chats($user_id),
                'challenges_entered' => $this->count_user_challenges($user_id),
                'referrals' => $this->count_user_referrals($user_id),
            ),
        );
    }

    /**
     * Count user wardrobe items
     *
     * @param int $user_id User ID
     * @return int Count
     */
    private function count_user_wardrobe($user_id) {
        global $wpdb;
        $db = new StyleGenius_Database();
        $tables = $db->get_table_names();

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$tables['wardrobe']} WHERE user_id = %d",
                $user_id
            )
        );
    }

    /**
     * Count user chat sessions
     *
     * @param int $user_id User ID
     * @return int Count
     */
    private function count_user_chats($user_id) {
        global $wpdb;
        $db = new StyleGenius_Database();
        $tables = $db->get_table_names();

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT session_id) FROM {$tables['chat_history']} WHERE user_id = %d",
                $user_id
            )
        );
    }

    /**
     * Count user challenge entries
     *
     * @param int $user_id User ID
     * @return int Count
     */
    private function count_user_challenges($user_id) {
        global $wpdb;
        $db = new StyleGenius_Database();
        $tables = $db->get_table_names();

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$tables['challenge_entries']} WHERE user_id = %d",
                $user_id
            )
        );
    }

    /**
     * Count user referrals
     *
     * @param int $user_id User ID
     * @return int Count
     */
    private function count_user_referrals($user_id) {
        global $wpdb;
        $db = new StyleGenius_Database();
        $tables = $db->get_table_names();

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$tables['referrals']}
                 WHERE referrer_id = %d AND status = 'completed'",
                $user_id
            )
        );
    }
}
