<?php
/**
 * StyleGenius Admin Class
 *
 * Handles all admin-side functionality.
 *
 * @package    StyleGenius_Pro
 * @subpackage StyleGenius_Pro/includes/admin
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin management class.
 */
class StyleGenius_Admin {

    /**
     * Plugin name.
     *
     * @var string
     */
    private $plugin_name;

    /**
     * Plugin version.
     *
     * @var string
     */
    private $version;

    /**
     * Constructor.
     *
     * @param string $plugin_name Plugin name.
     * @param string $version     Plugin version.
     */
    public function __construct(string $plugin_name = 'stylegenius-pro', string $version = '1.0.0') {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Initialize admin hooks.
     *
     * @return void
     */
    public function init(): void {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_styles'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    /**
     * Register admin menu.
     *
     * @return void
     */
    public function add_admin_menu(): void {
        // Main menu
        add_menu_page(
            'StyleGenius Pro',
            'StyleGenius',
            'manage_options',
            'stylegenius',
            array($this, 'render_dashboard'),
            'dashicons-admin-appearance',
            30
        );

        // Dashboard submenu
        add_submenu_page(
            'stylegenius',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'stylegenius',
            array($this, 'render_dashboard')
        );

        // Settings
        add_submenu_page(
            'stylegenius',
            'Einstellungen',
            'Einstellungen',
            'manage_options',
            'stylegenius-settings',
            array($this, 'render_settings')
        );

        // Statistics
        add_submenu_page(
            'stylegenius',
            'Statistiken',
            'Statistiken',
            'manage_options',
            'stylegenius-stats',
            array($this, 'render_stats')
        );

        // Challenges
        add_submenu_page(
            'stylegenius',
            'Challenges',
            'Challenges',
            'manage_options',
            'stylegenius-challenges',
            array($this, 'render_challenges')
        );

        // Users
        add_submenu_page(
            'stylegenius',
            'Benutzer',
            'Benutzer',
            'manage_options',
            'stylegenius-users',
            array($this, 'render_users')
        );
    }

    /**
     * Register admin styles.
     *
     * @return void
     */
    public function enqueue_styles(): void {
        $screen = get_current_screen();

        if (!$screen || strpos($screen->id, 'stylegenius') === false) {
            return;
        }

        wp_enqueue_style(
            $this->plugin_name . '-admin',
            STYLEGENIUS_URL . 'assets/css/admin.css',
            array(),
            $this->version
        );
    }

    /**
     * Register admin scripts.
     *
     * @return void
     */
    public function enqueue_scripts(): void {
        $screen = get_current_screen();

        if (!$screen || strpos($screen->id, 'stylegenius') === false) {
            return;
        }

        wp_enqueue_media();

        wp_enqueue_script(
            $this->plugin_name . '-admin',
            STYLEGENIUS_URL . 'assets/js/admin.js',
            array('jquery', 'wp-util'),
            $this->version,
            true
        );

        wp_localize_script($this->plugin_name . '-admin', 'stylegeniusAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('stylegenius_admin'),
            'i18n'    => array(
                'confirm_delete' => 'Bist du sicher?',
                'saving'         => 'Speichern...',
                'saved'          => 'Gespeichert!',
                'error'          => 'Fehler!',
            ),
        ));
    }

    /**
     * Render dashboard page.
     *
     * @return void
     */
    public function render_dashboard(): void {
        $stats = new StyleGenius_Stats();
        $overview = $stats->get_overview();
        ?>
        <div class="wrap sg-admin-wrap">
            <h1>StyleGenius Pro Dashboard</h1>

            <div class="sg-admin-dashboard">
                <!-- Overview Cards -->
                <div class="sg-dashboard-cards">
                    <div class="sg-card sg-card-users">
                        <h3>Benutzer</h3>
                        <div class="sg-card-value"><?php echo number_format($overview['total_users']); ?></div>
                        <div class="sg-card-detail">
                            <span class="premium"><?php echo number_format($overview['premium_users']); ?> Premium</span>
                            <span class="vip"><?php echo number_format($overview['vip_users']); ?> VIP</span>
                        </div>
                    </div>

                    <div class="sg-card sg-card-quiz">
                        <h3>Quiz-Teilnahmen</h3>
                        <div class="sg-card-value"><?php echo number_format($overview['quiz_completions']); ?></div>
                    </div>

                    <div class="sg-card sg-card-chat">
                        <h3>AI-Chats</h3>
                        <div class="sg-card-value"><?php echo number_format($overview['chat_messages']); ?></div>
                        <div class="sg-card-detail">Diese Woche</div>
                    </div>

                    <div class="sg-card sg-card-wardrobe">
                        <h3>Kleidungsstücke</h3>
                        <div class="sg-card-value"><?php echo number_format($overview['wardrobe_items']); ?></div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="sg-dashboard-section">
                    <h2>Schnellaktionen</h2>
                    <div class="sg-quick-actions">
                        <a href="<?php echo admin_url('admin.php?page=stylegenius-challenges&action=new'); ?>" class="button button-primary">
                            Neue Challenge erstellen
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=stylegenius-settings'); ?>" class="button">
                            Einstellungen
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=stylegenius-stats'); ?>" class="button">
                            Statistiken
                        </a>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="sg-dashboard-section">
                    <h2>Letzte Aktivitäten</h2>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Benutzer</th>
                                <th>Aktion</th>
                                <th>Details</th>
                                <th>Zeitpunkt</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $activities = $stats->get_recent_activities(10);
                            foreach ($activities as $activity):
                            ?>
                            <tr>
                                <td><?php echo esc_html($activity['user_name']); ?></td>
                                <td><?php echo esc_html($activity['action']); ?></td>
                                <td><?php echo esc_html($activity['details']); ?></td>
                                <td><?php echo esc_html($activity['time_ago']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- System Status -->
                <div class="sg-dashboard-section">
                    <h2>Systemstatus</h2>
                    <?php $this->render_system_status(); ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render settings page.
     *
     * @return void
     */
    public function render_settings(): void {
        $settings = new StyleGenius_Settings();

        if (isset($_POST['sg_save_settings']) && check_admin_referer('sg_settings_nonce')) {
            $settings->save($_POST);
            echo '<div class="notice notice-success"><p>Einstellungen gespeichert!</p></div>';
        }

        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
        ?>
        <div class="wrap sg-admin-wrap">
            <h1>StyleGenius Pro Einstellungen</h1>

            <nav class="nav-tab-wrapper">
                <a href="?page=stylegenius-settings&tab=general"
                   class="nav-tab <?php echo $current_tab === 'general' ? 'nav-tab-active' : ''; ?>">
                    Allgemein
                </a>
                <a href="?page=stylegenius-settings&tab=api"
                   class="nav-tab <?php echo $current_tab === 'api' ? 'nav-tab-active' : ''; ?>">
                    API-Einstellungen
                </a>
                <a href="?page=stylegenius-settings&tab=tiers"
                   class="nav-tab <?php echo $current_tab === 'tiers' ? 'nav-tab-active' : ''; ?>">
                    Mitgliedschaftsstufen
                </a>
                <a href="?page=stylegenius-settings&tab=gamification"
                   class="nav-tab <?php echo $current_tab === 'gamification' ? 'nav-tab-active' : ''; ?>">
                    Gamification
                </a>
                <a href="?page=stylegenius-settings&tab=affiliates"
                   class="nav-tab <?php echo $current_tab === 'affiliates' ? 'nav-tab-active' : ''; ?>">
                    Affiliates
                </a>
            </nav>

            <form method="post" action="">
                <?php wp_nonce_field('sg_settings_nonce'); ?>

                <div class="sg-settings-content">
                    <?php
                    switch ($current_tab) {
                        case 'api':
                            $settings->render_api_settings();
                            break;
                        case 'tiers':
                            $settings->render_tier_settings();
                            break;
                        case 'gamification':
                            $settings->render_gamification_settings();
                            break;
                        case 'affiliates':
                            $settings->render_affiliate_settings();
                            break;
                        default:
                            $settings->render_general_settings();
                    }
                    ?>
                </div>

                <p class="submit">
                    <input type="submit" name="sg_save_settings" class="button button-primary" value="Einstellungen speichern">
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Render statistics page.
     *
     * @return void
     */
    public function render_stats(): void {
        $stats = new StyleGenius_Stats();
        $period = isset($_GET['period']) ? sanitize_text_field($_GET['period']) : 'month';
        ?>
        <div class="wrap sg-admin-wrap">
            <h1>StyleGenius Pro Statistiken</h1>

            <div class="sg-stats-filters">
                <a href="?page=stylegenius-stats&period=week" class="button <?php echo $period === 'week' ? 'button-primary' : ''; ?>">Woche</a>
                <a href="?page=stylegenius-stats&period=month" class="button <?php echo $period === 'month' ? 'button-primary' : ''; ?>">Monat</a>
                <a href="?page=stylegenius-stats&period=year" class="button <?php echo $period === 'year' ? 'button-primary' : ''; ?>">Jahr</a>
                <a href="?page=stylegenius-stats&period=all" class="button <?php echo $period === 'all' ? 'button-primary' : ''; ?>">Gesamt</a>
            </div>

            <div class="sg-stats-grid">
                <?php $stats->render_charts($period); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render challenges page.
     *
     * @return void
     */
    public function render_challenges(): void {
        $challenges = new StyleGenius_Challenges();
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';

        // Handle form submissions
        if (isset($_POST['sg_save_challenge']) && check_admin_referer('sg_challenge_nonce')) {
            $result = $challenges->create_challenge($_POST);
            if ($result['success']) {
                echo '<div class="notice notice-success"><p>Challenge erstellt!</p></div>';
                $action = 'list';
            } else {
                echo '<div class="notice notice-error"><p>' . esc_html($result['error']) . '</p></div>';
            }
        }
        ?>
        <div class="wrap sg-admin-wrap">
            <h1>
                Challenges
                <?php if ($action === 'list'): ?>
                    <a href="?page=stylegenius-challenges&action=new" class="page-title-action">Neue Challenge</a>
                <?php endif; ?>
            </h1>

            <?php if ($action === 'new' || $action === 'edit'): ?>
                <?php $this->render_challenge_form($action); ?>
            <?php else: ?>
                <?php $this->render_challenges_list($challenges); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render challenge form.
     *
     * @param string $action Form action.
     * @return void
     */
    private function render_challenge_form(string $action): void {
        $challenge = null;
        if ($action === 'edit' && isset($_GET['id'])) {
            $challenges = new StyleGenius_Challenges();
            $challenge = $challenges->get_challenge(intval($_GET['id']));
        }
        ?>
        <form method="post" action="">
            <?php wp_nonce_field('sg_challenge_nonce'); ?>

            <table class="form-table">
                <tr>
                    <th><label for="title">Titel</label></th>
                    <td>
                        <input type="text" name="title" id="title" class="regular-text"
                               value="<?php echo esc_attr($challenge['title'] ?? ''); ?>" required>
                    </td>
                </tr>
                <tr>
                    <th><label for="description">Beschreibung</label></th>
                    <td>
                        <textarea name="description" id="description" rows="4" class="large-text"><?php
                            echo esc_textarea($challenge['description'] ?? '');
                        ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th><label for="type">Typ</label></th>
                    <td>
                        <select name="type" id="type">
                            <option value="outfit">Outfit-Challenge</option>
                            <option value="color">Farb-Challenge</option>
                            <option value="capsule">Capsule-Challenge</option>
                            <option value="accessory">Accessoire-Challenge</option>
                            <option value="seasonal">Saisonale Challenge</option>
                            <option value="theme">Themen-Challenge</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="start_date">Startdatum</label></th>
                    <td>
                        <input type="datetime-local" name="start_date" id="start_date"
                               value="<?php echo esc_attr($challenge['start_date'] ?? ''); ?>" required>
                    </td>
                </tr>
                <tr>
                    <th><label for="end_date">Enddatum</label></th>
                    <td>
                        <input type="datetime-local" name="end_date" id="end_date"
                               value="<?php echo esc_attr($challenge['end_date'] ?? ''); ?>" required>
                    </td>
                </tr>
                <tr>
                    <th><label for="points">Punkte für Teilnahme</label></th>
                    <td>
                        <input type="number" name="points" id="points" min="0"
                               value="<?php echo esc_attr($challenge['points'] ?? '50'); ?>">
                    </td>
                </tr>
                <tr>
                    <th><label for="winner_points">Punkte für Gewinner</label></th>
                    <td>
                        <input type="number" name="winner_points" id="winner_points" min="0"
                               value="<?php echo esc_attr($challenge['winner_points'] ?? '200'); ?>">
                    </td>
                </tr>
                <tr>
                    <th><label for="rules">Regeln</label></th>
                    <td>
                        <textarea name="rules" id="rules" rows="4" class="large-text"><?php
                            echo esc_textarea($challenge['rules'] ?? '');
                        ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th><label for="prize_info">Preis-Information</label></th>
                    <td>
                        <textarea name="prize_info" id="prize_info" rows="2" class="large-text"><?php
                            echo esc_textarea($challenge['prize_info'] ?? '');
                        ?></textarea>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" name="sg_save_challenge" class="button button-primary" value="Challenge speichern">
                <a href="?page=stylegenius-challenges" class="button">Abbrechen</a>
            </p>
        </form>
        <?php
    }

    /**
     * Render challenges list.
     *
     * @param StyleGenius_Challenges $challenges Challenges instance.
     * @return void
     */
    private function render_challenges_list(StyleGenius_Challenges $challenges): void {
        $active = $challenges->get_active_challenges();
        $upcoming = $challenges->get_upcoming_challenges();
        $past = $challenges->get_past_challenges(10);
        ?>
        <h2>Aktive Challenges</h2>
        <?php if (empty($active)): ?>
            <p>Keine aktiven Challenges.</p>
        <?php else: ?>
            <?php $this->render_challenge_table($active); ?>
        <?php endif; ?>

        <h2>Kommende Challenges</h2>
        <?php if (empty($upcoming)): ?>
            <p>Keine geplanten Challenges.</p>
        <?php else: ?>
            <?php $this->render_challenge_table($upcoming); ?>
        <?php endif; ?>

        <h2>Vergangene Challenges</h2>
        <?php if (empty($past)): ?>
            <p>Keine vergangenen Challenges.</p>
        <?php else: ?>
            <?php $this->render_challenge_table($past); ?>
        <?php endif; ?>
        <?php
    }

    /**
     * Render challenge table.
     *
     * @param array $challenges Challenges array.
     * @return void
     */
    private function render_challenge_table(array $challenges): void {
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Titel</th>
                    <th>Typ</th>
                    <th>Zeitraum</th>
                    <th>Einreichungen</th>
                    <th>Status</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($challenges as $challenge): ?>
                <tr>
                    <td><strong><?php echo esc_html($challenge['title']); ?></strong></td>
                    <td><?php echo esc_html($challenge['type_label']); ?></td>
                    <td>
                        <?php echo esc_html(date('d.m.Y', strtotime($challenge['start_date']))); ?> -
                        <?php echo esc_html(date('d.m.Y', strtotime($challenge['end_date']))); ?>
                    </td>
                    <td><?php echo esc_html($challenge['entry_count']); ?></td>
                    <td>
                        <span class="sg-status sg-status-<?php echo esc_attr($challenge['status']); ?>">
                            <?php echo esc_html(ucfirst($challenge['status'])); ?>
                        </span>
                    </td>
                    <td>
                        <a href="?page=stylegenius-challenges&action=edit&id=<?php echo $challenge['id']; ?>">Bearbeiten</a> |
                        <a href="?page=stylegenius-challenges&action=entries&id=<?php echo $challenge['id']; ?>">Einreichungen</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Render users page.
     *
     * @return void
     */
    public function render_users(): void {
        $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 20;
        $offset = ($paged - 1) * $per_page;

        $tier_filter = isset($_GET['tier']) ? sanitize_text_field($_GET['tier']) : '';
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

        $users = $this->get_stylegenius_users($per_page, $offset, $tier_filter, $search);
        ?>
        <div class="wrap sg-admin-wrap">
            <h1>StyleGenius Benutzer</h1>

            <form method="get" action="">
                <input type="hidden" name="page" value="stylegenius-users">
                <div class="tablenav top">
                    <div class="alignleft actions">
                        <select name="tier">
                            <option value="">Alle Stufen</option>
                            <option value="free" <?php selected($tier_filter, 'free'); ?>>Free</option>
                            <option value="premium" <?php selected($tier_filter, 'premium'); ?>>Premium</option>
                            <option value="vip" <?php selected($tier_filter, 'vip'); ?>>VIP</option>
                        </select>
                        <input type="submit" class="button" value="Filtern">
                    </div>
                    <div class="alignright">
                        <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Benutzer suchen...">
                        <input type="submit" class="button" value="Suchen">
                    </div>
                </div>
            </form>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Benutzer</th>
                        <th>E-Mail</th>
                        <th>Stufe</th>
                        <th>Punkte</th>
                        <th>Level</th>
                        <th>Style-Typ</th>
                        <th>Registriert</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users['users'] as $user): ?>
                    <tr>
                        <td>
                            <?php echo get_avatar($user['id'], 32); ?>
                            <strong><?php echo esc_html($user['display_name']); ?></strong>
                        </td>
                        <td><?php echo esc_html($user['email']); ?></td>
                        <td>
                            <span class="sg-tier sg-tier-<?php echo esc_attr($user['tier']); ?>">
                                <?php echo esc_html(ucfirst($user['tier'])); ?>
                            </span>
                        </td>
                        <td><?php echo number_format($user['points']); ?></td>
                        <td><?php echo esc_html($user['level']); ?></td>
                        <td><?php echo esc_html($user['style_type'] ?: '-'); ?></td>
                        <td><?php echo esc_html(date('d.m.Y', strtotime($user['registered']))); ?></td>
                        <td>
                            <a href="<?php echo get_edit_user_link($user['id']); ?>">Bearbeiten</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php
            $total_pages = ceil($users['total'] / $per_page);
            if ($total_pages > 1):
                $page_links = paginate_links(array(
                    'base'    => add_query_arg('paged', '%#%'),
                    'format'  => '',
                    'current' => $paged,
                    'total'   => $total_pages,
                ));
                echo '<div class="tablenav bottom"><div class="tablenav-pages">' . $page_links . '</div></div>';
            endif;
            ?>
        </div>
        <?php
    }

    /**
     * Get StyleGenius users.
     *
     * @param int    $per_page Number per page.
     * @param int    $offset   Offset.
     * @param string $tier     Tier filter.
     * @param string $search   Search term.
     * @return array
     */
    private function get_stylegenius_users(int $per_page, int $offset, string $tier = '', string $search = ''): array {
        global $wpdb;

        $where = "WHERE 1=1";
        $params = array();

        if ($tier) {
            $where .= " AND um_tier.meta_value = %s";
            $params[] = $tier;
        }

        if ($search) {
            $where .= " AND (u.user_login LIKE %s OR u.user_email LIKE %s OR u.display_name LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
        }

        $query = "
            SELECT
                u.ID,
                u.user_login,
                u.user_email,
                u.display_name,
                u.user_registered,
                COALESCE(um_tier.meta_value, 'free') as tier,
                COALESCE(um_points.meta_value, 0) as points,
                COALESCE(um_level.meta_value, 1) as level,
                um_style.meta_value as style_type
            FROM {$wpdb->users} u
            LEFT JOIN {$wpdb->usermeta} um_tier ON u.ID = um_tier.user_id AND um_tier.meta_key = 'sg_tier'
            LEFT JOIN {$wpdb->usermeta} um_points ON u.ID = um_points.user_id AND um_points.meta_key = 'sg_total_points'
            LEFT JOIN {$wpdb->usermeta} um_level ON u.ID = um_level.user_id AND um_level.meta_key = 'sg_level'
            LEFT JOIN {$wpdb->usermeta} um_style ON u.ID = um_style.user_id AND um_style.meta_key = 'sg_style_type'
            {$where}
            ORDER BY um_points.meta_value DESC
            LIMIT %d OFFSET %d
        ";

        $params[] = $per_page;
        $params[] = $offset;

        $results = $wpdb->get_results(
            $wpdb->prepare($query, $params),
            ARRAY_A
        );

        // Get total count
        $count_query = "
            SELECT COUNT(DISTINCT u.ID)
            FROM {$wpdb->users} u
            LEFT JOIN {$wpdb->usermeta} um_tier ON u.ID = um_tier.user_id AND um_tier.meta_key = 'sg_tier'
            {$where}
        ";

        array_pop($params); // Remove LIMIT
        array_pop($params); // Remove OFFSET

        $total = $wpdb->get_var(
            empty($params) ? $count_query : $wpdb->prepare($count_query, $params)
        );

        $users = array();
        foreach ($results as $row) {
            $users[] = array(
                'id'           => intval($row['ID']),
                'username'     => $row['user_login'],
                'email'        => $row['user_email'],
                'display_name' => $row['display_name'],
                'registered'   => $row['user_registered'],
                'tier'         => $row['tier'] ?: 'free',
                'points'       => intval($row['points']),
                'level'        => intval($row['level'] ?: 1),
                'style_type'   => $row['style_type'],
            );
        }

        return array(
            'users' => $users,
            'total' => intval($total),
        );
    }

    /**
     * Render system status.
     *
     * @return void
     */
    private function render_system_status(): void {
        $status = array();

        // PHP Version
        $status['php'] = array(
            'label'  => 'PHP Version',
            'value'  => PHP_VERSION,
            'status' => version_compare(PHP_VERSION, '8.0', '>=') ? 'good' : 'error',
        );

        // WordPress Version
        $status['wp'] = array(
            'label'  => 'WordPress Version',
            'value'  => get_bloginfo('version'),
            'status' => version_compare(get_bloginfo('version'), '6.0', '>=') ? 'good' : 'warning',
        );

        // WooCommerce
        $status['woo'] = array(
            'label'  => 'WooCommerce',
            'value'  => class_exists('WooCommerce') ? WC()->version : 'Nicht installiert',
            'status' => class_exists('WooCommerce') ? 'good' : 'warning',
        );

        // Claude API
        $claude_key = get_option('sg_claude_api_key');
        $status['claude'] = array(
            'label'  => 'Claude API',
            'value'  => $claude_key ? 'Konfiguriert' : 'Nicht konfiguriert',
            'status' => $claude_key ? 'good' : 'warning',
        );

        // OpenAI API
        $openai_key = get_option('sg_openai_api_key');
        $status['openai'] = array(
            'label'  => 'OpenAI API',
            'value'  => $openai_key ? 'Konfiguriert' : 'Nicht konfiguriert',
            'status' => $openai_key ? 'good' : 'neutral',
        );

        // Upload Directory
        $upload_dir = wp_upload_dir();
        $sg_dir = $upload_dir['basedir'] . '/stylegenius/';
        $status['uploads'] = array(
            'label'  => 'Upload-Verzeichnis',
            'value'  => is_writable($sg_dir) ? 'Beschreibbar' : 'Nicht beschreibbar',
            'status' => is_writable($sg_dir) ? 'good' : 'error',
        );
        ?>
        <table class="sg-system-status">
            <?php foreach ($status as $item): ?>
            <tr class="status-<?php echo esc_attr($item['status']); ?>">
                <td><?php echo esc_html($item['label']); ?></td>
                <td><?php echo esc_html($item['value']); ?></td>
                <td class="status-icon">
                    <?php if ($item['status'] === 'good'): ?>
                        <span class="dashicons dashicons-yes-alt"></span>
                    <?php elseif ($item['status'] === 'warning'): ?>
                        <span class="dashicons dashicons-warning"></span>
                    <?php elseif ($item['status'] === 'error'): ?>
                        <span class="dashicons dashicons-dismiss"></span>
                    <?php else: ?>
                        <span class="dashicons dashicons-minus"></span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php
    }
}
