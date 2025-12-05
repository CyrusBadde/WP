<?php
/**
 * StyleGenius Pro Shortcodes Class
 *
 * Handles all plugin shortcodes
 *
 * @package    StyleGenius_Pro
 * @subpackage StyleGenius_Pro/includes/public
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shortcodes Class
 */
class StyleGenius_Shortcodes {

    /**
     * Content gate instance
     *
     * @var StyleGenius_Content_Gate
     */
    private $gate;

    /**
     * Teaser instance
     *
     * @var StyleGenius_Teaser
     */
    private $teaser;

    /**
     * Constructor
     */
    public function __construct() {
        $this->gate = new StyleGenius_Content_Gate();
        $this->teaser = new StyleGenius_Teaser();
    }

    /**
     * Register all shortcodes
     */
    public function register() {
        // Main features
        add_shortcode('stylegenius_quiz', array($this, 'render_quiz'));
        add_shortcode('stylegenius_chat', array($this, 'render_chat'));
        add_shortcode('stylegenius_wardrobe', array($this, 'render_wardrobe'));
        add_shortcode('stylegenius_upload', array($this, 'render_upload'));
        add_shortcode('stylegenius_color_analysis', array($this, 'render_color_analysis'));
        add_shortcode('stylegenius_capsule', array($this, 'render_capsule'));
        add_shortcode('stylegenius_shopping', array($this, 'render_shopping'));
        add_shortcode('stylegenius_before_after', array($this, 'render_before_after'));

        // Dashboard & Profile
        add_shortcode('stylegenius_dashboard', array($this, 'render_dashboard'));
        add_shortcode('stylegenius_profile', array($this, 'render_profile'));
        add_shortcode('stylegenius_style_profile', array($this, 'render_style_profile'));

        // Gamification
        add_shortcode('stylegenius_points', array($this, 'render_points'));
        add_shortcode('stylegenius_level', array($this, 'render_level'));
        add_shortcode('stylegenius_streak', array($this, 'render_streak'));
        add_shortcode('stylegenius_badges', array($this, 'render_badges'));
        add_shortcode('stylegenius_leaderboard', array($this, 'render_leaderboard'));
        add_shortcode('stylegenius_progress', array($this, 'render_progress'));

        // Social & Community
        add_shortcode('stylegenius_challenges', array($this, 'render_challenges'));
        add_shortcode('stylegenius_challenge', array($this, 'render_single_challenge'));
        add_shortcode('stylegenius_referral', array($this, 'render_referral'));
        add_shortcode('stylegenius_share', array($this, 'render_share_buttons'));

        // Utility
        add_shortcode('stylegenius_login_form', array($this, 'render_login_form'));
        add_shortcode('stylegenius_register_form', array($this, 'render_register_form'));
        add_shortcode('stylegenius_tier_comparison', array($this, 'render_tier_comparison'));
        add_shortcode('stylegenius_upgrade_button', array($this, 'render_upgrade_button'));

        // Conditional
        add_shortcode('stylegenius_if_tier', array($this, 'render_if_tier'));
        add_shortcode('stylegenius_if_logged_in', array($this, 'render_if_logged_in'));
        add_shortcode('stylegenius_if_quiz_completed', array($this, 'render_if_quiz_completed'));
    }

    /**
     * Check login and return login prompt if needed
     *
     * @param string $feature Feature name
     * @return string|null Login prompt HTML or null if logged in
     */
    private function check_login($feature = '') {
        if (!is_user_logged_in()) {
            return $this->render_login_prompt($feature);
        }
        return null;
    }

    /**
     * Render login prompt
     *
     * @param string $feature Feature name
     * @return string HTML
     */
    private function render_login_prompt($feature = '') {
        ob_start();
        ?>
        <div class="sg-login-prompt-card">
            <div class="sg-login-prompt-icon">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                    <polyline points="10 17 15 12 10 7"></polyline>
                    <line x1="15" y1="12" x2="3" y2="12"></line>
                </svg>
            </div>
            <h3><?php _e('Anmeldung erforderlich', 'stylegenius-pro'); ?></h3>
            <p>
                <?php
                if ($feature) {
                    printf(
                        __('Melde dich an, um %s zu nutzen.', 'stylegenius-pro'),
                        $feature
                    );
                } else {
                    _e('Melde dich an, um diese Funktion zu nutzen.', 'stylegenius-pro');
                }
                ?>
            </p>
            <div class="sg-login-prompt-buttons">
                <a href="<?php echo wp_login_url(get_permalink()); ?>" class="sg-button sg-button--primary">
                    <?php _e('Anmelden', 'stylegenius-pro'); ?>
                </a>
                <a href="<?php echo wp_registration_url(); ?>" class="sg-button sg-button--secondary">
                    <?php _e('Kostenlos registrieren', 'stylegenius-pro'); ?>
                </a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render Style Quiz
     */
    public function render_quiz($atts) {
        $atts = shortcode_atts(array(
            'show_results' => 'true',
            'redirect' => '',
        ), $atts);

        // Quiz is available to all users
        $quiz = new StyleGenius_Quiz();

        ob_start();
        include STYLEGENIUS_PLUGIN_PATH . 'templates/quiz/quiz-form.php';
        return ob_get_clean();
    }

    /**
     * Render AI Chat
     */
    public function render_chat($atts) {
        $atts = shortcode_atts(array(
            'style' => 'full', // full, compact
            'height' => '500px',
        ), $atts);

        // Check access
        if (!$this->gate->can_access('chat')) {
            return $this->teaser->render('chat');
        }

        $chat = new StyleGenius_Chat();

        ob_start();
        include STYLEGENIUS_PLUGIN_PATH . 'templates/chat/chat-interface.php';
        return ob_get_clean();
    }

    /**
     * Render Virtual Wardrobe
     */
    public function render_wardrobe($atts) {
        $atts = shortcode_atts(array(
            'view' => 'grid', // grid, list
            'category' => '',
        ), $atts);

        $login_check = $this->check_login(__('die virtuelle Garderobe', 'stylegenius-pro'));
        if ($login_check) {
            return $login_check;
        }

        if (!$this->gate->can_access('wardrobe')) {
            return $this->teaser->render('wardrobe');
        }

        $wardrobe = new StyleGenius_Wardrobe();
        $user_id = get_current_user_id();

        ob_start();
        include STYLEGENIUS_PLUGIN_PATH . 'templates/wardrobe/wardrobe-view.php';
        return ob_get_clean();
    }

    /**
     * Render Photo Upload & Analysis
     */
    public function render_upload($atts) {
        $atts = shortcode_atts(array(
            'type' => 'outfit', // outfit, selfie
            'show_history' => 'true',
        ), $atts);

        $login_check = $this->check_login(__('die Foto-Analyse', 'stylegenius-pro'));
        if ($login_check) {
            return $login_check;
        }

        if (!$this->gate->can_access('photo_analysis')) {
            return $this->teaser->render('photo_analysis');
        }

        $upload = new StyleGenius_Upload();
        $user_id = get_current_user_id();

        ob_start();
        include STYLEGENIUS_PLUGIN_PATH . 'templates/upload/upload-form.php';
        return ob_get_clean();
    }

    /**
     * Render Color Analysis
     */
    public function render_color_analysis($atts) {
        $atts = shortcode_atts(array(
            'show_palette' => 'true',
        ), $atts);

        $login_check = $this->check_login(__('die Farbanalyse', 'stylegenius-pro'));
        if ($login_check) {
            return $login_check;
        }

        if (!$this->gate->can_access('color_analysis')) {
            return $this->teaser->render('color_analysis');
        }

        $color_analysis = new StyleGenius_Color_Analysis();
        $user_id = get_current_user_id();

        ob_start();
        include STYLEGENIUS_PLUGIN_PATH . 'templates/color/color-analysis.php';
        return ob_get_clean();
    }

    /**
     * Render Capsule Wardrobe
     */
    public function render_capsule($atts) {
        $atts = shortcode_atts(array(
            'season' => '',
            'style' => '',
        ), $atts);

        $login_check = $this->check_login(__('die Capsule Wardrobe', 'stylegenius-pro'));
        if ($login_check) {
            return $login_check;
        }

        if (!$this->gate->can_access('capsule_wardrobe')) {
            return $this->teaser->render('capsule_wardrobe');
        }

        $capsule = new StyleGenius_Capsule();
        $user_id = get_current_user_id();

        ob_start();
        include STYLEGENIUS_PLUGIN_PATH . 'templates/capsule/capsule-builder.php';
        return ob_get_clean();
    }

    /**
     * Render Shopping Assistant
     */
    public function render_shopping($atts) {
        $atts = shortcode_atts(array(
            'category' => '',
            'budget' => '',
        ), $atts);

        $login_check = $this->check_login(__('den Shopping-Assistenten', 'stylegenius-pro'));
        if ($login_check) {
            return $login_check;
        }

        if (!$this->gate->can_access('shopping_assistant')) {
            return $this->teaser->render('shopping_assistant');
        }

        $shopping = new StyleGenius_Shopping();
        $user_id = get_current_user_id();

        ob_start();
        include STYLEGENIUS_PLUGIN_PATH . 'templates/shopping/shopping-assistant.php';
        return ob_get_clean();
    }

    /**
     * Render Before/After Generator
     */
    public function render_before_after($atts) {
        $atts = shortcode_atts(array(
            'user_id' => '',
        ), $atts);

        $login_check = $this->check_login(__('den Vorher/Nachher-Generator', 'stylegenius-pro'));
        if ($login_check) {
            return $login_check;
        }

        if (!$this->gate->can_access('before_after')) {
            return $this->teaser->render('before_after');
        }

        $before_after = new StyleGenius_Before_After();
        $user_id = get_current_user_id();

        ob_start();
        include STYLEGENIUS_PLUGIN_PATH . 'templates/before-after/generator.php';
        return ob_get_clean();
    }

    /**
     * Render User Dashboard
     */
    public function render_dashboard($atts) {
        $login_check = $this->check_login(__('dein Dashboard', 'stylegenius-pro'));
        if ($login_check) {
            return $login_check;
        }

        $user_id = get_current_user_id();
        $public = new StyleGenius_Public();
        $dashboard_data = $public->get_dashboard_data($user_id);

        ob_start();
        include STYLEGENIUS_PLUGIN_PATH . 'templates/dashboard/dashboard.php';
        return ob_get_clean();
    }

    /**
     * Render User Profile
     */
    public function render_profile($atts) {
        $atts = shortcode_atts(array(
            'show_privacy' => 'true',
        ), $atts);

        $login_check = $this->check_login(__('dein Profil', 'stylegenius-pro'));
        if ($login_check) {
            return $login_check;
        }

        $user_id = get_current_user_id();
        $user = new StyleGenius_User($user_id);

        ob_start();
        include STYLEGENIUS_PLUGIN_PATH . 'templates/profile/profile-edit.php';
        return ob_get_clean();
    }

    /**
     * Render Style Profile Display
     */
    public function render_style_profile($atts) {
        $atts = shortcode_atts(array(
            'user_id' => get_current_user_id(),
            'show_share' => 'true',
        ), $atts);

        $user_id = absint($atts['user_id']);

        if (!$user_id) {
            return '';
        }

        $user = new StyleGenius_User($user_id);

        // Check privacy settings for viewing other profiles
        if ($user_id !== get_current_user_id()) {
            $privacy = new StyleGenius_Privacy();
            if (!$privacy->is_profile_public($user_id)) {
                return '<p class="sg-notice">' . __('Dieses Profil ist privat.', 'stylegenius-pro') . '</p>';
            }
        }

        ob_start();
        include STYLEGENIUS_PLUGIN_PATH . 'templates/profile/style-profile.php';
        return ob_get_clean();
    }

    /**
     * Render Points Display
     */
    public function render_points($atts) {
        $atts = shortcode_atts(array(
            'style' => 'badge', // badge, inline, full
        ), $atts);

        if (!is_user_logged_in()) {
            return '';
        }

        $points = new StyleGenius_Points();
        $user_points = $points->get_user_points(get_current_user_id());

        ob_start();
        ?>
        <div class="sg-points sg-points--<?php echo esc_attr($atts['style']); ?>">
            <?php if ($atts['style'] === 'badge'): ?>
                <span class="sg-points-badge">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                    </svg>
                    <?php echo number_format_i18n($user_points['total_points']); ?>
                </span>
            <?php elseif ($atts['style'] === 'inline'): ?>
                <span class="sg-points-inline">
                    <?php printf(__('%s Punkte', 'stylegenius-pro'), number_format_i18n($user_points['total_points'])); ?>
                </span>
            <?php else: ?>
                <div class="sg-points-full">
                    <div class="sg-points-total">
                        <span class="sg-points-value"><?php echo number_format_i18n($user_points['total_points']); ?></span>
                        <span class="sg-points-label"><?php _e('Punkte', 'stylegenius-pro'); ?></span>
                    </div>
                    <div class="sg-points-week">
                        <span class="sg-points-value"><?php echo number_format_i18n($user_points['weekly_points']); ?></span>
                        <span class="sg-points-label"><?php _e('Diese Woche', 'stylegenius-pro'); ?></span>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render Level Display
     */
    public function render_level($atts) {
        $atts = shortcode_atts(array(
            'show_progress' => 'true',
        ), $atts);

        if (!is_user_logged_in()) {
            return '';
        }

        $levels = new StyleGenius_Levels();
        $user_id = get_current_user_id();
        $level_data = $levels->get_user_level($user_id);
        $progress = $levels->get_progress_percentage($user_id);

        ob_start();
        ?>
        <div class="sg-level-display">
            <div class="sg-level-badge">
                <span class="sg-level-number"><?php echo absint($level_data['level']); ?></span>
            </div>
            <div class="sg-level-info">
                <span class="sg-level-title"><?php echo esc_html($level_data['title']); ?></span>
                <?php if ($atts['show_progress'] === 'true'): ?>
                    <div class="sg-level-progress">
                        <div class="sg-level-progress-bar" style="width: <?php echo $progress; ?>%;"></div>
                    </div>
                    <span class="sg-level-progress-text">
                        <?php printf(__('%d%% bis Level %d', 'stylegenius-pro'), $progress, $level_data['level'] + 1); ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render Streak Display
     */
    public function render_streak($atts) {
        $atts = shortcode_atts(array(
            'style' => 'badge', // badge, full
        ), $atts);

        if (!is_user_logged_in()) {
            return '';
        }

        $streaks = new StyleGenius_Streaks();
        $user_id = get_current_user_id();
        $streak = $streaks->get_current_streak($user_id);
        $longest = $streaks->get_longest_streak($user_id);

        ob_start();
        ?>
        <div class="sg-streak sg-streak--<?php echo esc_attr($atts['style']); ?>">
            <?php if ($atts['style'] === 'badge'): ?>
                <span class="sg-streak-badge <?php echo $streak > 0 ? 'sg-streak-badge--active' : ''; ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 23c-1.66 0-3-1.34-3-3 0-1.31.84-2.42 2-2.83V15c-4.42 0-8-3.58-8-8 0-.55.45-1 1-1s1 .45 1 1c0 3.31 2.69 6 6 6V2l3.5 6 3.5-6v11c3.31 0 6-2.69 6-6 0-.55.45-1 1-1s1 .45 1 1c0 4.42-3.58 8-8 8v2.17c1.16.41 2 1.52 2 2.83 0 1.66-1.34 3-3 3z"/>
                    </svg>
                    <?php echo absint($streak); ?> <?php _e('Tage', 'stylegenius-pro'); ?>
                </span>
            <?php else: ?>
                <div class="sg-streak-full">
                    <div class="sg-streak-current">
                        <span class="sg-streak-icon">🔥</span>
                        <span class="sg-streak-value"><?php echo absint($streak); ?></span>
                        <span class="sg-streak-label"><?php _e('Tage Streak', 'stylegenius-pro'); ?></span>
                    </div>
                    <div class="sg-streak-longest">
                        <span class="sg-streak-label"><?php _e('Längster Streak:', 'stylegenius-pro'); ?></span>
                        <span class="sg-streak-value"><?php echo absint($longest); ?> <?php _e('Tage', 'stylegenius-pro'); ?></span>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render Badges Display
     */
    public function render_badges($atts) {
        $atts = shortcode_atts(array(
            'limit' => 0,
            'show_locked' => 'true',
        ), $atts);

        if (!is_user_logged_in()) {
            return '';
        }

        $achievements = new StyleGenius_Achievements();
        $user_id = get_current_user_id();
        $earned = $achievements->get_user_achievements($user_id);
        $all_badges = $achievements->get_all_achievements();

        ob_start();
        include STYLEGENIUS_PLUGIN_PATH . 'templates/gamification/badges.php';
        return ob_get_clean();
    }

    /**
     * Render Leaderboard
     */
    public function render_leaderboard($atts) {
        $atts = shortcode_atts(array(
            'type' => 'points', // points, level, streak, challenges
            'period' => 'weekly', // daily, weekly, monthly, all
            'limit' => 10,
        ), $atts);

        $leaderboard = new StyleGenius_Leaderboard();
        $data = $leaderboard->get_leaderboard($atts['type'], $atts['period'], (int) $atts['limit']);
        $user_rank = is_user_logged_in() ?
            $leaderboard->get_user_rank(get_current_user_id(), $atts['type'], $atts['period']) : null;

        ob_start();
        include STYLEGENIUS_PLUGIN_PATH . 'templates/gamification/leaderboard.php';
        return ob_get_clean();
    }

    /**
     * Render Progress Overview
     */
    public function render_progress($atts) {
        if (!is_user_logged_in()) {
            return '';
        }

        $user_id = get_current_user_id();
        $points = new StyleGenius_Points();
        $levels = new StyleGenius_Levels();
        $streaks = new StyleGenius_Streaks();
        $achievements = new StyleGenius_Achievements();

        $data = array(
            'points' => $points->get_user_points($user_id),
            'level' => $levels->get_user_level($user_id),
            'progress' => $levels->get_progress_percentage($user_id),
            'streak' => $streaks->get_current_streak($user_id),
            'badges' => count($achievements->get_user_achievements($user_id)),
            'total_badges' => count($achievements->get_all_achievements()),
        );

        ob_start();
        include STYLEGENIUS_PLUGIN_PATH . 'templates/gamification/progress.php';
        return ob_get_clean();
    }

    /**
     * Render Challenges List
     */
    public function render_challenges($atts) {
        $atts = shortcode_atts(array(
            'status' => 'active', // active, all, past
            'limit' => 6,
        ), $atts);

        $challenges = new StyleGenius_Challenges();
        $list = $challenges->get_challenges($atts['status'], (int) $atts['limit']);

        ob_start();
        include STYLEGENIUS_PLUGIN_PATH . 'templates/challenges/challenges-list.php';
        return ob_get_clean();
    }

    /**
     * Render Single Challenge
     */
    public function render_single_challenge($atts) {
        $atts = shortcode_atts(array(
            'id' => 0,
        ), $atts);

        $challenge_id = absint($atts['id']);

        if (!$challenge_id) {
            // Try to get from URL
            $challenge_id = isset($_GET['challenge']) ? absint($_GET['challenge']) : 0;
        }

        if (!$challenge_id) {
            return '<p class="sg-notice">' . __('Keine Challenge angegeben.', 'stylegenius-pro') . '</p>';
        }

        $challenges = new StyleGenius_Challenges();
        $challenge = $challenges->get_challenge($challenge_id);

        if (!$challenge) {
            return '<p class="sg-notice">' . __('Challenge nicht gefunden.', 'stylegenius-pro') . '</p>';
        }

        $entries = $challenges->get_entries($challenge_id);
        $user_entry = is_user_logged_in() ?
            $challenges->get_user_entry($challenge_id, get_current_user_id()) : null;

        ob_start();
        include STYLEGENIUS_PLUGIN_PATH . 'templates/challenges/single-challenge.php';
        return ob_get_clean();
    }

    /**
     * Render Referral Section
     */
    public function render_referral($atts) {
        $atts = shortcode_atts(array(
            'show_rewards' => 'true',
        ), $atts);

        $login_check = $this->check_login(__('das Empfehlungsprogramm', 'stylegenius-pro'));
        if ($login_check) {
            return $login_check;
        }

        $referral = new StyleGenius_Referral();
        $user_id = get_current_user_id();

        ob_start();
        include STYLEGENIUS_PLUGIN_PATH . 'templates/referral/referral-section.php';
        return ob_get_clean();
    }

    /**
     * Render Share Buttons
     */
    public function render_share_buttons($atts) {
        $atts = shortcode_atts(array(
            'platforms' => 'facebook,twitter,whatsapp,email',
            'style' => 'buttons', // buttons, icons
            'title' => '',
            'text' => '',
            'url' => '',
        ), $atts);

        $sharing = new StyleGenius_Sharing();
        $platforms = array_map('trim', explode(',', $atts['platforms']));

        $share_data = array(
            'title' => $atts['title'] ?: get_the_title(),
            'text' => $atts['text'] ?: get_the_excerpt(),
            'url' => $atts['url'] ?: get_permalink(),
        );

        ob_start();
        ?>
        <div class="sg-share-buttons sg-share-buttons--<?php echo esc_attr($atts['style']); ?>">
            <?php foreach ($platforms as $platform): ?>
                <?php
                $share_url = $sharing->get_share_url($platform, $share_data['url'], $share_data['title'], $share_data['text']);
                if (!$share_url) continue;
                ?>
                <a href="<?php echo esc_url($share_url); ?>"
                   class="sg-share-button sg-share-button--<?php echo esc_attr($platform); ?>"
                   target="_blank"
                   rel="noopener noreferrer">
                    <?php echo $this->get_platform_icon($platform); ?>
                    <?php if ($atts['style'] === 'buttons'): ?>
                        <span><?php echo esc_html($this->get_platform_name($platform)); ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get platform icon
     */
    private function get_platform_icon($platform) {
        $icons = array(
            'facebook' => '<svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>',
            'twitter' => '<svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>',
            'pinterest' => '<svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0C5.373 0 0 5.372 0 12c0 5.084 3.163 9.426 7.627 11.174-.105-.949-.2-2.405.042-3.441.218-.937 1.407-5.965 1.407-5.965s-.359-.719-.359-1.782c0-1.668.967-2.914 2.171-2.914 1.023 0 1.518.769 1.518 1.69 0 1.029-.655 2.568-.994 3.995-.283 1.194.599 2.169 1.777 2.169 2.133 0 3.772-2.249 3.772-5.495 0-2.873-2.064-4.882-5.012-4.882-3.414 0-5.418 2.561-5.418 5.207 0 1.031.397 2.138.893 2.738.098.119.112.224.083.345l-.333 1.36c-.053.22-.174.267-.402.161-1.499-.698-2.436-2.889-2.436-4.649 0-3.785 2.75-7.262 7.929-7.262 4.163 0 7.398 2.967 7.398 6.931 0 4.136-2.607 7.464-6.227 7.464-1.216 0-2.359-.631-2.75-1.378l-.748 2.853c-.271 1.043-1.002 2.35-1.492 3.146C9.57 23.812 10.763 24 12 24c6.627 0 12-5.373 12-12 0-6.628-5.373-12-12-12z"/></svg>',
            'linkedin' => '<svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>',
            'whatsapp' => '<svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>',
            'telegram' => '<svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>',
            'email' => '<svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>',
            'copy' => '<svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>',
        );

        return $icons[$platform] ?? '';
    }

    /**
     * Get platform name
     */
    private function get_platform_name($platform) {
        $names = array(
            'facebook' => 'Facebook',
            'twitter' => 'X (Twitter)',
            'pinterest' => 'Pinterest',
            'linkedin' => 'LinkedIn',
            'whatsapp' => 'WhatsApp',
            'telegram' => 'Telegram',
            'email' => __('E-Mail', 'stylegenius-pro'),
            'copy' => __('Link kopieren', 'stylegenius-pro'),
        );

        return $names[$platform] ?? ucfirst($platform);
    }

    /**
     * Render Login Form
     */
    public function render_login_form($atts) {
        $atts = shortcode_atts(array(
            'redirect' => '',
        ), $atts);

        if (is_user_logged_in()) {
            return '<p class="sg-notice">' . __('Du bist bereits angemeldet.', 'stylegenius-pro') . '</p>';
        }

        $redirect = $atts['redirect'] ?: get_permalink();

        ob_start();
        include STYLEGENIUS_PLUGIN_PATH . 'templates/auth/login-form.php';
        return ob_get_clean();
    }

    /**
     * Render Register Form
     */
    public function render_register_form($atts) {
        $atts = shortcode_atts(array(
            'redirect' => '',
        ), $atts);

        if (is_user_logged_in()) {
            return '<p class="sg-notice">' . __('Du bist bereits angemeldet.', 'stylegenius-pro') . '</p>';
        }

        $redirect = $atts['redirect'] ?: get_permalink();

        ob_start();
        include STYLEGENIUS_PLUGIN_PATH . 'templates/auth/register-form.php';
        return ob_get_clean();
    }

    /**
     * Render Tier Comparison
     */
    public function render_tier_comparison($atts) {
        $tiers = new StyleGenius_Tiers();
        $all_tiers = $tiers->get_all_tiers();

        ob_start();
        include STYLEGENIUS_PLUGIN_PATH . 'templates/tiers/tier-comparison.php';
        return ob_get_clean();
    }

    /**
     * Render Upgrade Button
     */
    public function render_upgrade_button($atts) {
        $atts = shortcode_atts(array(
            'tier' => 'premium',
            'text' => '',
            'style' => 'primary', // primary, secondary, gradient
        ), $atts);

        $tiers = new StyleGenius_Tiers();
        $tier_data = $tiers->get_tier_info($atts['tier']);

        if (!$tier_data) {
            return '';
        }

        $button_text = $atts['text'] ?: sprintf(__('Upgrade auf %s', 'stylegenius-pro'), $tier_data['name']);
        $upgrade_url = $tiers->get_upgrade_url($atts['tier']);

        ob_start();
        ?>
        <a href="<?php echo esc_url($upgrade_url); ?>" class="sg-button sg-button--<?php echo esc_attr($atts['style']); ?> sg-upgrade-button">
            <?php echo esc_html($button_text); ?>
        </a>
        <?php
        return ob_get_clean();
    }

    /**
     * Render content conditionally based on tier
     */
    public function render_if_tier($atts, $content = null) {
        $atts = shortcode_atts(array(
            'is' => '',      // Exact tier match
            'min' => '',     // Minimum tier required
            'not' => '',     // Not this tier
        ), $atts);

        if (!is_user_logged_in()) {
            return '';
        }

        $tiers = new StyleGenius_Tiers();
        $user_tier = $tiers->get_user_tier(get_current_user_id());

        // Check exact match
        if (!empty($atts['is'])) {
            $allowed = array_map('trim', explode(',', $atts['is']));
            if (!in_array($user_tier, $allowed)) {
                return '';
            }
        }

        // Check minimum tier
        if (!empty($atts['min'])) {
            if (!$tiers->has_tier_access($user_tier, $atts['min'])) {
                return '';
            }
        }

        // Check not tier
        if (!empty($atts['not'])) {
            $excluded = array_map('trim', explode(',', $atts['not']));
            if (in_array($user_tier, $excluded)) {
                return '';
            }
        }

        return do_shortcode($content);
    }

    /**
     * Render content conditionally based on login status
     */
    public function render_if_logged_in($atts, $content = null) {
        $atts = shortcode_atts(array(
            'show' => 'logged_in', // logged_in, logged_out
        ), $atts);

        $is_logged_in = is_user_logged_in();

        if ($atts['show'] === 'logged_in' && !$is_logged_in) {
            return '';
        }

        if ($atts['show'] === 'logged_out' && $is_logged_in) {
            return '';
        }

        return do_shortcode($content);
    }

    /**
     * Render content conditionally based on quiz completion
     */
    public function render_if_quiz_completed($atts, $content = null) {
        $atts = shortcode_atts(array(
            'show' => 'completed', // completed, not_completed
        ), $atts);

        if (!is_user_logged_in()) {
            return '';
        }

        $user = new StyleGenius_User(get_current_user_id());
        $has_completed = !empty($user->get_style_type());

        if ($atts['show'] === 'completed' && !$has_completed) {
            return '';
        }

        if ($atts['show'] === 'not_completed' && $has_completed) {
            return '';
        }

        return do_shortcode($content);
    }
}
