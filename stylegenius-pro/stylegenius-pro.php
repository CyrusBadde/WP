<?php
/**
 * Plugin Name: StyleGenius Pro
 * Plugin URI: https://businessstylist.de/stylegenius-pro
 * Description: KI-gestützter Styling-Berater mit Gamification - Dein persönlicher Style-Assistent
 * Version: 1.0.0
 * Author: BusinessStylist.de
 * Author URI: https://businessstylist.de
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: stylegenius-pro
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * WC requires at least: 7.0
 * WC tested up to: 8.0
 *
 * @package StyleGenius_Pro
 */

// Verhindere direkten Zugriff
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Plugin-Konstanten definieren
 */
define( 'STYLEGENIUS_VERSION', '1.0.0' );
define( 'STYLEGENIUS_PLUGIN_NAME', 'stylegenius-pro' );
define( 'STYLEGENIUS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'STYLEGENIUS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'STYLEGENIUS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'STYLEGENIUS_DB_VERSION', '1.0.0' );
define( 'STYLEGENIUS_MIN_WP_VERSION', '6.0' );
define( 'STYLEGENIUS_MIN_PHP_VERSION', '8.0' );

/**
 * Autoloader für Plugin-Klassen
 *
 * @param string $class_name Der Klassenname.
 */
function stylegenius_autoloader( string $class_name ): void {
    // Nur StyleGenius-Klassen laden
    if ( strpos( $class_name, 'StyleGenius_' ) !== 0 ) {
        return;
    }

    // Klassenname in Dateinamen konvertieren
    $class_file = 'class-' . str_replace( '_', '-', strtolower( $class_name ) ) . '.php';

    // Verzeichnisse zum Durchsuchen
    $directories = array(
        STYLEGENIUS_PLUGIN_DIR . 'includes/',
        STYLEGENIUS_PLUGIN_DIR . 'includes/core/',
        STYLEGENIUS_PLUGIN_DIR . 'includes/ai/',
        STYLEGENIUS_PLUGIN_DIR . 'includes/features/',
        STYLEGENIUS_PLUGIN_DIR . 'includes/gamification/',
        STYLEGENIUS_PLUGIN_DIR . 'includes/social/',
        STYLEGENIUS_PLUGIN_DIR . 'includes/integrations/',
        STYLEGENIUS_PLUGIN_DIR . 'includes/gating/',
        STYLEGENIUS_PLUGIN_DIR . 'includes/api/',
        STYLEGENIUS_PLUGIN_DIR . 'admin/',
        STYLEGENIUS_PLUGIN_DIR . 'public/',
    );

    // Datei in den Verzeichnissen suchen
    foreach ( $directories as $directory ) {
        $file_path = $directory . $class_file;
        if ( file_exists( $file_path ) ) {
            require_once $file_path;
            return;
        }
    }
}

spl_autoload_register( 'stylegenius_autoloader' );

/**
 * Aktivierung des Plugins
 */
function stylegenius_activate(): void {
    require_once STYLEGENIUS_PLUGIN_DIR . 'includes/class-stylegenius-activator.php';
    StyleGenius_Activator::activate();
}

/**
 * Deaktivierung des Plugins
 */
function stylegenius_deactivate(): void {
    require_once STYLEGENIUS_PLUGIN_DIR . 'includes/class-stylegenius-deactivator.php';
    StyleGenius_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'stylegenius_activate' );
register_deactivation_hook( __FILE__, 'stylegenius_deactivate' );

/**
 * Prüft, ob alle Anforderungen erfüllt sind
 *
 * @return bool True wenn erfüllt, false sonst.
 */
function stylegenius_check_requirements(): bool {
    // PHP-Version prüfen
    if ( version_compare( PHP_VERSION, STYLEGENIUS_MIN_PHP_VERSION, '<' ) ) {
        add_action( 'admin_notices', 'stylegenius_php_version_notice' );
        return false;
    }

    // WordPress-Version prüfen
    if ( version_compare( get_bloginfo( 'version' ), STYLEGENIUS_MIN_WP_VERSION, '<' ) ) {
        add_action( 'admin_notices', 'stylegenius_wp_version_notice' );
        return false;
    }

    // WooCommerce prüfen
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', 'stylegenius_woocommerce_notice' );
        return false;
    }

    return true;
}

/**
 * Admin-Hinweis: PHP-Version zu niedrig
 */
function stylegenius_php_version_notice(): void {
    $message = sprintf(
        /* translators: 1: Benötigte PHP-Version, 2: Aktuelle PHP-Version */
        __( 'StyleGenius Pro benötigt PHP %1$s oder höher. Deine aktuelle Version ist %2$s. Bitte aktualisiere PHP.', 'stylegenius-pro' ),
        STYLEGENIUS_MIN_PHP_VERSION,
        PHP_VERSION
    );
    echo '<div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div>';
}

/**
 * Admin-Hinweis: WordPress-Version zu niedrig
 */
function stylegenius_wp_version_notice(): void {
    $message = sprintf(
        /* translators: 1: Benötigte WP-Version, 2: Aktuelle WP-Version */
        __( 'StyleGenius Pro benötigt WordPress %1$s oder höher. Deine aktuelle Version ist %2$s. Bitte aktualisiere WordPress.', 'stylegenius-pro' ),
        STYLEGENIUS_MIN_WP_VERSION,
        get_bloginfo( 'version' )
    );
    echo '<div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div>';
}

/**
 * Admin-Hinweis: WooCommerce nicht installiert
 */
function stylegenius_woocommerce_notice(): void {
    $message = __( 'StyleGenius Pro benötigt WooCommerce. Bitte installiere und aktiviere WooCommerce.', 'stylegenius-pro' );
    echo '<div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div>';
}

/**
 * Initialisiert das Plugin
 *
 * @return void
 */
function stylegenius_init(): void {
    // Anforderungen prüfen
    if ( ! stylegenius_check_requirements() ) {
        return;
    }

    // Übersetzungen laden
    load_plugin_textdomain(
        'stylegenius-pro',
        false,
        dirname( STYLEGENIUS_PLUGIN_BASENAME ) . '/languages'
    );

    // Plugin starten
    $plugin = new StyleGenius();
    $plugin->run();
}

add_action( 'plugins_loaded', 'stylegenius_init' );

/**
 * WooCommerce HPOS-Kompatibilität deklarieren
 */
add_action( 'before_woocommerce_init', function() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true
        );
    }
} );

/**
 * Hauptklasse des Plugins
 */
class StyleGenius {

    /**
     * Der Loader zum Registrieren von Hooks
     *
     * @var StyleGenius_Loader
     */
    protected StyleGenius_Loader $loader;

    /**
     * Plugin-Name
     *
     * @var string
     */
    protected string $plugin_name;

    /**
     * Plugin-Version
     *
     * @var string
     */
    protected string $version;

    /**
     * Konstruktor
     */
    public function __construct() {
        $this->plugin_name = STYLEGENIUS_PLUGIN_NAME;
        $this->version     = STYLEGENIUS_VERSION;

        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->define_api_hooks();
    }

    /**
     * Lädt alle benötigten Abhängigkeiten
     */
    private function load_dependencies(): void {
        // Loader
        require_once STYLEGENIUS_PLUGIN_DIR . 'includes/class-stylegenius-loader.php';
        $this->loader = new StyleGenius_Loader();

        // Core-Klassen werden durch Autoloader geladen
    }

    /**
     * Registriert alle Admin-Hooks
     */
    private function define_admin_hooks(): void {
        $admin = new StyleGenius_Admin( $this->plugin_name, $this->version );

        $this->loader->add_action( 'admin_enqueue_scripts', $admin, 'enqueue_styles' );
        $this->loader->add_action( 'admin_enqueue_scripts', $admin, 'enqueue_scripts' );
        $this->loader->add_action( 'admin_menu', $admin, 'add_admin_menu' );
        $this->loader->add_filter( 'plugin_action_links_' . STYLEGENIUS_PLUGIN_BASENAME, $admin, 'add_plugin_action_links' );
        $this->loader->add_action( 'admin_notices', $admin, 'admin_notices' );

        // AJAX-Handler für Admin
        $this->loader->add_action( 'wp_ajax_stylegenius_get_stats', $admin, 'ajax_get_stats' );
        $this->loader->add_action( 'wp_ajax_stylegenius_test_api', $admin, 'ajax_test_api' );
        $this->loader->add_action( 'wp_ajax_stylegenius_clear_cache', $admin, 'ajax_clear_cache' );

        // Settings
        $settings = new StyleGenius_Settings();
        $this->loader->add_action( 'admin_init', $settings, 'register_settings' );
    }

    /**
     * Registriert alle Public-Hooks
     */
    private function define_public_hooks(): void {
        $public = new StyleGenius_Public( $this->plugin_name, $this->version );

        $this->loader->add_action( 'wp_enqueue_scripts', $public, 'enqueue_styles' );
        $this->loader->add_action( 'wp_enqueue_scripts', $public, 'enqueue_scripts' );
        $this->loader->add_action( 'init', $public, 'register_rewrite_rules' );
        $this->loader->add_filter( 'query_vars', $public, 'add_query_vars' );
        $this->loader->add_action( 'template_redirect', $public, 'handle_share_page' );
        $this->loader->add_action( 'template_redirect', $public, 'handle_referral_code' );
        $this->loader->add_action( 'wp_head', $public, 'add_og_meta_tags' );
        $this->loader->add_filter( 'body_class', $public, 'add_body_classes' );

        // Shortcodes
        $shortcodes = new StyleGenius_Shortcodes();
        $shortcodes->register_shortcodes();

        // AJAX-Handler
        $this->define_ajax_hooks();
    }

    /**
     * Registriert AJAX-Hooks für eingeloggte und nicht eingeloggte Benutzer
     */
    private function define_ajax_hooks(): void {
        // Quiz
        $quiz = new StyleGenius_Quiz();
        $this->loader->add_action( 'wp_ajax_stylegenius_submit_quiz', $quiz, 'ajax_submit_quiz' );
        $this->loader->add_action( 'wp_ajax_nopriv_stylegenius_get_quiz_questions', $quiz, 'ajax_get_questions' );
        $this->loader->add_action( 'wp_ajax_stylegenius_get_quiz_questions', $quiz, 'ajax_get_questions' );

        // Chat
        $chat = new StyleGenius_Chat();
        $this->loader->add_action( 'wp_ajax_stylegenius_send_message', $chat, 'ajax_send_message' );
        $this->loader->add_action( 'wp_ajax_stylegenius_get_chat_history', $chat, 'ajax_get_history' );

        // Upload
        $upload = new StyleGenius_Upload();
        $this->loader->add_action( 'wp_ajax_stylegenius_upload', $upload, 'ajax_handle_upload' );
        $this->loader->add_action( 'wp_ajax_stylegenius_delete_upload', $upload, 'ajax_delete_upload' );

        // Wardrobe
        $wardrobe_ajax = new StyleGenius_Wardrobe_Ajax();
        $this->loader->add_action( 'wp_ajax_stylegenius_get_wardrobe', $wardrobe_ajax, 'ajax_get_wardrobe' );
        $this->loader->add_action( 'wp_ajax_stylegenius_add_wardrobe_item', $wardrobe_ajax, 'ajax_add_item' );
        $this->loader->add_action( 'wp_ajax_stylegenius_update_wardrobe_item', $wardrobe_ajax, 'ajax_update_item' );
        $this->loader->add_action( 'wp_ajax_stylegenius_delete_wardrobe_item', $wardrobe_ajax, 'ajax_delete_item' );
        $this->loader->add_action( 'wp_ajax_stylegenius_get_outfit_suggestion', $wardrobe_ajax, 'ajax_get_outfit_suggestion' );

        // Gamification
        $this->loader->add_action( 'wp_ajax_stylegenius_get_points', array( $this, 'ajax_get_points' ) );
        $this->loader->add_action( 'wp_ajax_stylegenius_get_badges', array( $this, 'ajax_get_badges' ) );
        $this->loader->add_action( 'wp_ajax_stylegenius_get_leaderboard', array( $this, 'ajax_get_leaderboard' ) );

        // Challenges
        $challenges = new StyleGenius_Challenges();
        $this->loader->add_action( 'wp_ajax_stylegenius_submit_challenge_entry', $challenges, 'ajax_submit_entry' );
        $this->loader->add_action( 'wp_ajax_stylegenius_vote_entry', $challenges, 'ajax_vote_entry' );

        // Referral
        $referral_ajax = new StyleGenius_Referral_Ajax();
        $this->loader->add_action( 'wp_ajax_stylegenius_get_referral_code', $referral_ajax, 'ajax_get_code' );
        $this->loader->add_action( 'wp_ajax_stylegenius_get_referral_stats', $referral_ajax, 'ajax_get_stats' );

        // Color Analysis
        $color = new StyleGenius_Color_Analysis_Ajax();
        $this->loader->add_action( 'wp_ajax_stylegenius_analyze_colors', $color, 'ajax_analyze' );

        // Before/After
        $before_after = new StyleGenius_Before_After_Ajax();
        $this->loader->add_action( 'wp_ajax_stylegenius_analyze_outfit', $before_after, 'ajax_analyze' );

        // Capsule
        $capsule_ajax = new StyleGenius_Capsule_Ajax();
        $this->loader->add_action( 'wp_ajax_stylegenius_generate_capsule', $capsule_ajax, 'ajax_generate' );
        $this->loader->add_action( 'wp_ajax_stylegenius_save_capsule', $capsule_ajax, 'ajax_save' );

        // Shopping
        $shopping = new StyleGenius_Shopping_Ajax();
        $this->loader->add_action( 'wp_ajax_stylegenius_search_products', $shopping, 'ajax_search' );
        $this->loader->add_action( 'wp_ajax_stylegenius_track_click', $shopping, 'ajax_track_click' );

        // User Profile
        $this->loader->add_action( 'wp_ajax_stylegenius_update_profile', array( $this, 'ajax_update_profile' ) );
        $this->loader->add_action( 'wp_ajax_stylegenius_export_data', array( $this, 'ajax_export_data' ) );
        $this->loader->add_action( 'wp_ajax_stylegenius_delete_data', array( $this, 'ajax_delete_data' ) );

        // Sharing
        $sharing = new StyleGenius_Sharing();
        $this->loader->add_action( 'wp_ajax_stylegenius_track_share', $sharing, 'ajax_track_share' );
        $this->loader->add_action( 'wp_ajax_nopriv_stylegenius_track_share', $sharing, 'ajax_track_share' );
    }

    /**
     * Registriert REST API Hooks
     */
    private function define_api_hooks(): void {
        $api = new StyleGenius_REST_API();
        $this->loader->add_action( 'rest_api_init', $api, 'register_routes' );
    }

    /**
     * AJAX: Punkte abrufen
     */
    public function ajax_get_points(): void {
        check_ajax_referer( 'stylegenius_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => __( 'Nicht eingeloggt.', 'stylegenius-pro' ) ) );
        }

        $points = new StyleGenius_Points( get_current_user_id() );
        wp_send_json_success( array(
            'points' => $points->get_points(),
            'today'  => $points->get_points_today(),
            'week'   => $points->get_points_this_week(),
        ) );
    }

    /**
     * AJAX: Badges abrufen
     */
    public function ajax_get_badges(): void {
        check_ajax_referer( 'stylegenius_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => __( 'Nicht eingeloggt.', 'stylegenius-pro' ) ) );
        }

        $badges = new StyleGenius_Badges( get_current_user_id() );
        wp_send_json_success( array(
            'earned' => $badges->get_user_badges(),
            'all'    => $badges->get_all_badges(),
        ) );
    }

    /**
     * AJAX: Leaderboard abrufen
     */
    public function ajax_get_leaderboard(): void {
        check_ajax_referer( 'stylegenius_nonce', 'nonce' );

        $type   = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : 'points';
        $period = isset( $_POST['period'] ) ? sanitize_text_field( wp_unslash( $_POST['period'] ) ) : 'all';

        $leaderboard = new StyleGenius_Leaderboard();
        wp_send_json_success( array(
            'leaderboard' => $leaderboard->get_leaderboard( $type, $period ),
            'user_rank'   => is_user_logged_in() ? $leaderboard->get_user_rank( get_current_user_id(), $type, $period ) : null,
        ) );
    }

    /**
     * AJAX: Profil aktualisieren
     */
    public function ajax_update_profile(): void {
        check_ajax_referer( 'stylegenius_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => __( 'Nicht eingeloggt.', 'stylegenius-pro' ) ) );
        }

        $user = new StyleGenius_User( get_current_user_id() );
        $meta = new StyleGenius_Meta( get_current_user_id() );

        // Sanitize und speichere die Felder
        $fields = array(
            'favorite_brands'    => 'sanitize_text_field',
            'budget_min'         => 'absint',
            'budget_max'         => 'absint',
            'body_type'          => 'sanitize_text_field',
            'profession'         => 'sanitize_text_field',
            'typical_occasions'  => 'sanitize_text_field',
            'disliked_styles'    => 'sanitize_text_field',
            'disliked_colors'    => 'sanitize_text_field',
        );

        foreach ( $fields as $field => $sanitize_callback ) {
            if ( isset( $_POST[ $field ] ) ) {
                $value = wp_unslash( $_POST[ $field ] );
                if ( is_array( $value ) ) {
                    $value = array_map( $sanitize_callback, $value );
                } else {
                    $value = call_user_func( $sanitize_callback, $value );
                }
                $meta->set( $field, $value );
            }
        }

        wp_send_json_success( array( 'message' => __( 'Profil aktualisiert.', 'stylegenius-pro' ) ) );
    }

    /**
     * AJAX: Daten exportieren (DSGVO)
     */
    public function ajax_export_data(): void {
        check_ajax_referer( 'stylegenius_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => __( 'Nicht eingeloggt.', 'stylegenius-pro' ) ) );
        }

        $privacy = new StyleGenius_Privacy( get_current_user_id() );
        $data    = $privacy->export_user_data();

        wp_send_json_success( array( 'data' => $data ) );
    }

    /**
     * AJAX: Alle Daten löschen (DSGVO)
     */
    public function ajax_delete_data(): void {
        check_ajax_referer( 'stylegenius_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => __( 'Nicht eingeloggt.', 'stylegenius-pro' ) ) );
        }

        $privacy = new StyleGenius_Privacy( get_current_user_id() );
        $result  = $privacy->delete_all_data();

        if ( $result ) {
            wp_send_json_success( array( 'message' => __( 'Alle Daten wurden gelöscht.', 'stylegenius-pro' ) ) );
        } else {
            wp_send_json_error( array( 'message' => __( 'Fehler beim Löschen der Daten.', 'stylegenius-pro' ) ) );
        }
    }

    /**
     * Führt das Plugin aus
     */
    public function run(): void {
        $this->loader->run();

        // WooCommerce-Integration
        if ( class_exists( 'WooCommerce' ) ) {
            $woocommerce = new StyleGenius_WooCommerce();
            $woocommerce->init_hooks();
        }

        // Elementor-Integration
        if ( did_action( 'elementor/loaded' ) ) {
            $elementor = new StyleGenius_Elementor();
            $elementor->init();
        }

        // Cron-Jobs für Streak-Prüfung und Challenge-Status
        add_action( 'stylegenius_daily_cron', array( 'StyleGenius_Streaks', 'run_daily_check' ) );
        add_action( 'stylegenius_hourly_cron', array( $this, 'check_challenge_status' ) );

        // DB-Update prüfen
        add_action( 'init', array( 'StyleGenius_Activator', 'update_db_check' ) );
    }

    /**
     * Prüft und aktualisiert Challenge-Status
     */
    public function check_challenge_status(): void {
        $challenges = new StyleGenius_Challenges();
        $challenges->check_and_update_statuses();
    }

    /**
     * Gibt den Plugin-Namen zurück
     *
     * @return string
     */
    public function get_plugin_name(): string {
        return $this->plugin_name;
    }

    /**
     * Gibt den Loader zurück
     *
     * @return StyleGenius_Loader
     */
    public function get_loader(): StyleGenius_Loader {
        return $this->loader;
    }

    /**
     * Gibt die Version zurück
     *
     * @return string
     */
    public function get_version(): string {
        return $this->version;
    }
}

/**
 * Globale Funktion zum Zugriff auf die Plugin-Instanz
 *
 * @return StyleGenius
 */
function stylegenius(): StyleGenius {
    static $instance = null;
    if ( null === $instance ) {
        $instance = new StyleGenius();
    }
    return $instance;
}
