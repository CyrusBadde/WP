<?php
/**
 * Plugin-Aktivierung
 *
 * Wird bei der Aktivierung des Plugins ausgeführt.
 *
 * @package StyleGenius_Pro
 * @subpackage StyleGenius_Pro/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class StyleGenius_Activator
 */
class StyleGenius_Activator {

    /**
     * Datenbank-Version
     *
     * @var string
     */
    private static string $db_version = '1.0.0';

    /**
     * Aktiviert das Plugin
     */
    public static function activate(): void {
        // Anforderungen prüfen
        if ( ! self::check_requirements() ) {
            wp_die(
                esc_html__( 'StyleGenius Pro benötigt PHP 8.0+, WordPress 6.0+ und WooCommerce.', 'stylegenius-pro' ),
                esc_html__( 'Plugin-Aktivierung fehlgeschlagen', 'stylegenius-pro' ),
                array( 'back_link' => true )
            );
        }

        // Datenbank-Tabellen erstellen
        self::create_tables();

        // Upload-Verzeichnis erstellen
        self::create_upload_directory();

        // Standard-Optionen setzen
        self::set_default_options();

        // Cron-Jobs planen
        self::schedule_events();

        // Seiten erstellen
        self::create_pages();

        // Capabilities setzen
        self::set_capabilities();

        // DB-Version speichern
        update_option( 'stylegenius_db_version', self::$db_version );

        // Rewrite-Rules flushen
        flush_rewrite_rules();
    }

    /**
     * Prüft die Systemanforderungen
     *
     * @return bool
     */
    private static function check_requirements(): bool {
        // PHP-Version
        if ( version_compare( PHP_VERSION, '8.0', '<' ) ) {
            return false;
        }

        // WordPress-Version
        if ( version_compare( get_bloginfo( 'version' ), '6.0', '<' ) ) {
            return false;
        }

        return true;
    }

    /**
     * Erstellt alle Datenbank-Tabellen
     */
    private static function create_tables(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // SQL für alle Tabellen
        $sql = array(
            self::create_points_log_table( $charset_collate ),
            self::create_achievements_table( $charset_collate ),
            self::create_streaks_table( $charset_collate ),
            self::create_referrals_table( $charset_collate ),
            self::create_shares_table( $charset_collate ),
            self::create_capsules_table( $charset_collate ),
            self::create_color_profiles_table( $charset_collate ),
            self::create_before_after_table( $charset_collate ),
            self::create_challenges_table( $charset_collate ),
            self::create_challenge_entries_table( $charset_collate ),
            self::create_challenge_votes_table( $charset_collate ),
            self::create_user_progress_table( $charset_collate ),
            self::create_affiliate_clicks_table( $charset_collate ),
            self::create_chat_history_table( $charset_collate ),
            self::create_wardrobe_table( $charset_collate ),
        );

        foreach ( $sql as $query ) {
            dbDelta( $query );
        }
    }

    /**
     * Erstellt die Punkte-Log-Tabelle
     *
     * @param string $charset_collate Zeichensatz.
     * @return string SQL-Statement.
     */
    private static function create_points_log_table( string $charset_collate ): string {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sg_points_log';

        return "CREATE TABLE {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            action VARCHAR(50) NOT NULL,
            points INT(11) NOT NULL,
            balance INT(11) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY action (action),
            KEY created_at (created_at)
        ) {$charset_collate};";
    }

    /**
     * Erstellt die Achievements-Tabelle
     *
     * @param string $charset_collate Zeichensatz.
     * @return string SQL-Statement.
     */
    private static function create_achievements_table( string $charset_collate ): string {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sg_achievements';

        return "CREATE TABLE {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            achievement_id VARCHAR(50) NOT NULL,
            earned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_achievement (user_id, achievement_id),
            KEY user_id (user_id),
            KEY achievement_id (achievement_id)
        ) {$charset_collate};";
    }

    /**
     * Erstellt die Streaks-Tabelle
     *
     * @param string $charset_collate Zeichensatz.
     * @return string SQL-Statement.
     */
    private static function create_streaks_table( string $charset_collate ): string {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sg_streaks';

        return "CREATE TABLE {$table_name} (
            user_id BIGINT(20) UNSIGNED NOT NULL,
            current_streak INT(11) NOT NULL DEFAULT 0,
            longest_streak INT(11) NOT NULL DEFAULT 0,
            last_activity DATE DEFAULT NULL,
            streak_start DATE DEFAULT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id)
        ) {$charset_collate};";
    }

    /**
     * Erstellt die Referrals-Tabelle
     *
     * @param string $charset_collate Zeichensatz.
     * @return string SQL-Statement.
     */
    private static function create_referrals_table( string $charset_collate ): string {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sg_referrals';

        return "CREATE TABLE {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            referrer_id BIGINT(20) UNSIGNED NOT NULL,
            referred_id BIGINT(20) UNSIGNED DEFAULT NULL,
            referral_code VARCHAR(20) NOT NULL,
            status ENUM('pending', 'completed', 'rewarded') NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            completed_at DATETIME DEFAULT NULL,
            reward_tier INT(11) DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            PRIMARY KEY (id),
            KEY referrer_id (referrer_id),
            KEY referral_code (referral_code),
            KEY status (status)
        ) {$charset_collate};";
    }

    /**
     * Erstellt die Shares-Tabelle
     *
     * @param string $charset_collate Zeichensatz.
     * @return string SQL-Statement.
     */
    private static function create_shares_table( string $charset_collate ): string {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sg_shares';

        return "CREATE TABLE {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            content_type VARCHAR(50) NOT NULL,
            content_id BIGINT(20) UNSIGNED DEFAULT NULL,
            platform VARCHAR(30) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY content_type (content_type),
            KEY platform (platform)
        ) {$charset_collate};";
    }

    /**
     * Erstellt die Capsules-Tabelle
     *
     * @param string $charset_collate Zeichensatz.
     * @return string SQL-Statement.
     */
    private static function create_capsules_table( string $charset_collate ): string {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sg_capsules';

        return "CREATE TABLE {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            name VARCHAR(255) NOT NULL,
            description TEXT DEFAULT NULL,
            season VARCHAR(20) DEFAULT NULL,
            occasion VARCHAR(50) DEFAULT NULL,
            items LONGTEXT DEFAULT NULL,
            outfit_combinations LONGTEXT DEFAULT NULL,
            ai_generated TINYINT(1) NOT NULL DEFAULT 0,
            is_public TINYINT(1) NOT NULL DEFAULT 0,
            share_token VARCHAR(32) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY season (season),
            KEY share_token (share_token)
        ) {$charset_collate};";
    }

    /**
     * Erstellt die Color-Profiles-Tabelle
     *
     * @param string $charset_collate Zeichensatz.
     * @return string SQL-Statement.
     */
    private static function create_color_profiles_table( string $charset_collate ): string {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sg_color_profiles';

        return "CREATE TABLE {$table_name} (
            user_id BIGINT(20) UNSIGNED NOT NULL,
            undertone VARCHAR(20) DEFAULT NULL,
            contrast_level VARCHAR(20) DEFAULT NULL,
            color_type VARCHAR(30) DEFAULT NULL,
            color_subtype VARCHAR(30) DEFAULT NULL,
            palette LONGTEXT DEFAULT NULL,
            neutral_colors LONGTEXT DEFAULT NULL,
            accent_colors LONGTEXT DEFAULT NULL,
            avoid_colors LONGTEXT DEFAULT NULL,
            metal_recommendation VARCHAR(20) DEFAULT NULL,
            analysis_image_id BIGINT(20) UNSIGNED DEFAULT NULL,
            ai_analysis LONGTEXT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id)
        ) {$charset_collate};";
    }

    /**
     * Erstellt die Before-After-Tabelle
     *
     * @param string $charset_collate Zeichensatz.
     * @return string SQL-Statement.
     */
    private static function create_before_after_table( string $charset_collate ): string {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sg_before_after';

        return "CREATE TABLE {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            image_id BIGINT(20) UNSIGNED NOT NULL,
            occasion VARCHAR(50) NOT NULL DEFAULT 'business',
            overall_score INT(3) NOT NULL DEFAULT 0,
            fit_score INT(3) DEFAULT NULL,
            color_score INT(3) DEFAULT NULL,
            style_score INT(3) DEFAULT NULL,
            accessories_score INT(3) DEFAULT NULL,
            analysis LONGTEXT DEFAULT NULL,
            improvements LONGTEXT DEFAULT NULL,
            share_image_url VARCHAR(500) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY occasion (occasion),
            KEY overall_score (overall_score)
        ) {$charset_collate};";
    }

    /**
     * Erstellt die Challenges-Tabelle
     *
     * @param string $charset_collate Zeichensatz.
     * @return string SQL-Statement.
     */
    private static function create_challenges_table( string $charset_collate ): string {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sg_challenges';

        return "CREATE TABLE {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL,
            description TEXT DEFAULT NULL,
            challenge_type VARCHAR(50) NOT NULL DEFAULT 'outfit',
            requirements TEXT DEFAULT NULL,
            start_date DATETIME NOT NULL,
            end_date DATETIME NOT NULL,
            voting_end_date DATETIME DEFAULT NULL,
            status ENUM('draft', 'upcoming', 'active', 'voting', 'completed') NOT NULL DEFAULT 'draft',
            min_tier VARCHAR(20) NOT NULL DEFAULT 'free',
            prizes LONGTEXT DEFAULT NULL,
            image_id BIGINT(20) UNSIGNED DEFAULT NULL,
            max_entries INT(11) DEFAULT NULL,
            created_by BIGINT(20) UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY start_date (start_date),
            KEY end_date (end_date)
        ) {$charset_collate};";
    }

    /**
     * Erstellt die Challenge-Entries-Tabelle
     *
     * @param string $charset_collate Zeichensatz.
     * @return string SQL-Statement.
     */
    private static function create_challenge_entries_table( string $charset_collate ): string {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sg_challenge_entries';

        return "CREATE TABLE {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            challenge_id BIGINT(20) UNSIGNED NOT NULL,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            image_id BIGINT(20) UNSIGNED NOT NULL,
            description TEXT DEFAULT NULL,
            vote_count INT(11) NOT NULL DEFAULT 0,
            is_winner TINYINT(1) NOT NULL DEFAULT 0,
            placement INT(11) DEFAULT NULL,
            disqualified TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_challenge (user_id, challenge_id),
            KEY challenge_id (challenge_id),
            KEY vote_count (vote_count)
        ) {$charset_collate};";
    }

    /**
     * Erstellt die Challenge-Votes-Tabelle
     *
     * @param string $charset_collate Zeichensatz.
     * @return string SQL-Statement.
     */
    private static function create_challenge_votes_table( string $charset_collate ): string {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sg_challenge_votes';

        return "CREATE TABLE {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            entry_id BIGINT(20) UNSIGNED NOT NULL,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY entry_user (entry_id, user_id),
            KEY entry_id (entry_id),
            KEY user_id (user_id)
        ) {$charset_collate};";
    }

    /**
     * Erstellt die User-Progress-Tabelle
     *
     * @param string $charset_collate Zeichensatz.
     * @return string SQL-Statement.
     */
    private static function create_user_progress_table( string $charset_collate ): string {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sg_user_progress';

        return "CREATE TABLE {$table_name} (
            user_id BIGINT(20) UNSIGNED NOT NULL,
            total_consultations INT(11) NOT NULL DEFAULT 0,
            total_uploads INT(11) NOT NULL DEFAULT 0,
            total_outfits_created INT(11) NOT NULL DEFAULT 0,
            total_challenges_entered INT(11) NOT NULL DEFAULT 0,
            total_challenges_won INT(11) NOT NULL DEFAULT 0,
            avg_outfit_score DECIMAL(5,2) DEFAULT NULL,
            skill_color INT(3) NOT NULL DEFAULT 0,
            skill_combination INT(3) NOT NULL DEFAULT 0,
            skill_consistency INT(3) NOT NULL DEFAULT 0,
            milestones LONGTEXT DEFAULT NULL,
            goals LONGTEXT DEFAULT NULL,
            first_activity_at DATETIME DEFAULT NULL,
            last_activity_at DATETIME DEFAULT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id)
        ) {$charset_collate};";
    }

    /**
     * Erstellt die Affiliate-Clicks-Tabelle
     *
     * @param string $charset_collate Zeichensatz.
     * @return string SQL-Statement.
     */
    private static function create_affiliate_clicks_table( string $charset_collate ): string {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sg_affiliate_clicks';

        return "CREATE TABLE {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED DEFAULT NULL,
            product_id VARCHAR(100) NOT NULL,
            product_name VARCHAR(255) DEFAULT NULL,
            provider VARCHAR(50) NOT NULL,
            affiliate_url VARCHAR(500) NOT NULL,
            clicked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            converted TINYINT(1) NOT NULL DEFAULT 0,
            conversion_amount DECIMAL(10,2) DEFAULT NULL,
            conversion_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY provider (provider),
            KEY clicked_at (clicked_at)
        ) {$charset_collate};";
    }

    /**
     * Erstellt die Chat-History-Tabelle
     *
     * @param string $charset_collate Zeichensatz.
     * @return string SQL-Statement.
     */
    private static function create_chat_history_table( string $charset_collate ): string {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sg_chat_history';

        return "CREATE TABLE {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            role ENUM('user', 'assistant', 'system') NOT NULL,
            content LONGTEXT NOT NULL,
            tokens_used INT(11) NOT NULL DEFAULT 0,
            rating TINYINT(1) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) {$charset_collate};";
    }

    /**
     * Erstellt die Wardrobe-Tabelle
     *
     * @param string $charset_collate Zeichensatz.
     * @return string SQL-Statement.
     */
    private static function create_wardrobe_table( string $charset_collate ): string {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sg_wardrobe';

        return "CREATE TABLE {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            attachment_id BIGINT(20) UNSIGNED NOT NULL,
            category VARCHAR(50) NOT NULL,
            subcategory VARCHAR(50) DEFAULT NULL,
            name VARCHAR(255) DEFAULT NULL,
            brand VARCHAR(100) DEFAULT NULL,
            color VARCHAR(50) DEFAULT NULL,
            colors_secondary LONGTEXT DEFAULT NULL,
            pattern VARCHAR(50) DEFAULT NULL,
            material VARCHAR(50) DEFAULT NULL,
            season VARCHAR(50) DEFAULT NULL,
            occasions LONGTEXT DEFAULT NULL,
            tags LONGTEXT DEFAULT NULL,
            ai_analysis LONGTEXT DEFAULT NULL,
            purchase_price DECIMAL(10,2) DEFAULT NULL,
            purchase_date DATE DEFAULT NULL,
            times_worn INT(11) NOT NULL DEFAULT 0,
            last_worn DATE DEFAULT NULL,
            is_favorite TINYINT(1) NOT NULL DEFAULT 0,
            notes TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY category (category),
            KEY color (color),
            KEY season (season),
            KEY is_favorite (is_favorite)
        ) {$charset_collate};";
    }

    /**
     * Erstellt das Upload-Verzeichnis
     *
     * @return bool
     */
    private static function create_upload_directory(): bool {
        $upload_dir = wp_upload_dir();
        $sg_dir     = $upload_dir['basedir'] . '/stylegenius';

        if ( ! file_exists( $sg_dir ) ) {
            wp_mkdir_p( $sg_dir );

            // Unterordner erstellen
            wp_mkdir_p( $sg_dir . '/wardrobe' );
            wp_mkdir_p( $sg_dir . '/avatars' );
            wp_mkdir_p( $sg_dir . '/analysis' );
            wp_mkdir_p( $sg_dir . '/challenges' );
            wp_mkdir_p( $sg_dir . '/share-images' );
            wp_mkdir_p( $sg_dir . '/temp' );

            // .htaccess für Schutz
            $htaccess_content = "Options -Indexes\n";
            $htaccess_content .= "<FilesMatch '\.(php|php5|phtml)$'>\n";
            $htaccess_content .= "Order Deny,Allow\n";
            $htaccess_content .= "Deny from all\n";
            $htaccess_content .= "</FilesMatch>\n";
            file_put_contents( $sg_dir . '/.htaccess', $htaccess_content );

            // Index-Datei
            file_put_contents( $sg_dir . '/index.php', '<?php // Silence is golden.' );
        }

        return true;
    }

    /**
     * Setzt die Standard-Optionen
     */
    private static function set_default_options(): void {
        $defaults = self::get_default_options();

        foreach ( $defaults as $key => $value ) {
            if ( get_option( $key ) === false ) {
                add_option( $key, $value );
            }
        }
    }

    /**
     * Gibt die Standard-Optionen zurück
     *
     * @return array
     */
    private static function get_default_options(): array {
        return array(
            'stylegenius_options' => array(
                // AI-Provider
                'ai_provider'              => 'claude',
                'claude_api_key'           => '',
                'claude_model'             => 'claude-sonnet-4-20250514',
                'openai_api_key'           => '',
                'openai_model'             => 'gpt-4o',

                // Tier-Limits
                'free_tier_limit'          => 10,
                'premium_tier_limit'       => 100,
                'vip_tier_limit'           => -1, // Unbegrenzt

                // Preise
                'premium_price_monthly'    => 9.90,
                'premium_price_yearly'     => 99.00,
                'vip_price_monthly'        => 29.90,
                'vip_price_yearly'         => 299.00,

                // Punkte-Werte
                'points_quiz'              => 100,
                'points_consultation'      => 50,
                'points_upload'            => 25,
                'points_wardrobe'          => 10,
                'points_challenge'         => 50,
                'points_challenge_win'     => 200,
                'points_referral'          => 100,
                'points_daily_login'       => 5,

                // Affiliate-IDs
                'zalando_affiliate_id'     => '',
                'aboutyou_affiliate_id'    => '',
                'amazon_affiliate_tag'     => '',

                // Feature-Toggles
                'enable_challenges'        => true,
                'enable_referrals'         => true,
                'enable_leaderboard'       => true,
                'enable_shopping'          => true,
                'enable_social_sharing'    => true,

                // Limits
                'wardrobe_limit_free'      => 20,
                'wardrobe_limit_premium'   => 100,
                'wardrobe_limit_vip'       => -1,
                'capsule_limit_free'       => 1,
                'capsule_limit_premium'    => 5,
                'capsule_limit_vip'        => -1,

                // Erweitert
                'delete_on_uninstall'      => false,
                'debug_mode'               => false,
                'cache_duration'           => 3600,
            ),
        );
    }

    /**
     * Erstellt WooCommerce-Produkte für die Subscriptions
     */
    private static function create_woocommerce_products(): void {
        if ( ! class_exists( 'WooCommerce' ) || ! class_exists( 'WC_Subscriptions' ) ) {
            return;
        }

        // Premium Monthly
        self::maybe_create_product(
            'StyleGenius Premium (Monatlich)',
            'stylegenius_premium_monthly',
            9.90,
            'month'
        );

        // Premium Yearly
        self::maybe_create_product(
            'StyleGenius Premium (Jährlich)',
            'stylegenius_premium_yearly',
            99.00,
            'year'
        );

        // VIP Monthly
        self::maybe_create_product(
            'StyleGenius VIP (Monatlich)',
            'stylegenius_vip_monthly',
            29.90,
            'month'
        );

        // VIP Yearly
        self::maybe_create_product(
            'StyleGenius VIP (Jährlich)',
            'stylegenius_vip_yearly',
            299.00,
            'year'
        );
    }

    /**
     * Erstellt ein Produkt wenn es nicht existiert
     *
     * @param string $title  Produkttitel.
     * @param string $sku    SKU.
     * @param float  $price  Preis.
     * @param string $period Abrechnungsperiode.
     */
    private static function maybe_create_product( string $title, string $sku, float $price, string $period ): void {
        $existing = wc_get_product_id_by_sku( $sku );
        if ( $existing ) {
            return;
        }

        $product = new WC_Product_Subscription();
        $product->set_name( $title );
        $product->set_status( 'publish' );
        $product->set_catalog_visibility( 'visible' );
        $product->set_sku( $sku );
        $product->set_regular_price( $price );
        $product->set_virtual( true );
        $product->update_meta_data( '_subscription_period', $period );
        $product->update_meta_data( '_subscription_period_interval', 1 );
        $product->save();
    }

    /**
     * Plant Cron-Events
     */
    private static function schedule_events(): void {
        // Tägliche Streak-Prüfung
        if ( ! wp_next_scheduled( 'stylegenius_daily_cron' ) ) {
            wp_schedule_event( strtotime( 'tomorrow 00:00:00' ), 'daily', 'stylegenius_daily_cron' );
        }

        // Stündliche Challenge-Status-Prüfung
        if ( ! wp_next_scheduled( 'stylegenius_hourly_cron' ) ) {
            wp_schedule_event( time(), 'hourly', 'stylegenius_hourly_cron' );
        }

        // Wöchentlicher Bericht
        if ( ! wp_next_scheduled( 'stylegenius_weekly_cron' ) ) {
            wp_schedule_event( strtotime( 'next monday 08:00:00' ), 'weekly', 'stylegenius_weekly_cron' );
        }

        // Temp-Dateien bereinigen (täglich)
        if ( ! wp_next_scheduled( 'stylegenius_cleanup_cron' ) ) {
            wp_schedule_event( time(), 'daily', 'stylegenius_cleanup_cron' );
        }
    }

    /**
     * Erstellt benötigte Seiten
     */
    private static function create_pages(): void {
        $pages = array(
            'stylegenius-dashboard' => array(
                'title'   => __( 'Style Dashboard', 'stylegenius-pro' ),
                'content' => '[stylegenius_dashboard]',
            ),
            'stylegenius-quiz'      => array(
                'title'   => __( 'Style-Quiz', 'stylegenius-pro' ),
                'content' => '[stylegenius_quiz]',
            ),
            'stylegenius-beratung'  => array(
                'title'   => __( 'KI-Styling-Beratung', 'stylegenius-pro' ),
                'content' => '[stylegenius_chat]',
            ),
            'stylegenius-garderobe' => array(
                'title'   => __( 'Meine Garderobe', 'stylegenius-pro' ),
                'content' => '[stylegenius_wardrobe]',
            ),
            'stylegenius-challenges' => array(
                'title'   => __( 'Style Challenges', 'stylegenius-pro' ),
                'content' => '[stylegenius_challenges]',
            ),
            'stylegenius-preise'    => array(
                'title'   => __( 'Preise & Pakete', 'stylegenius-pro' ),
                'content' => '[stylegenius_pricing]',
            ),
        );

        foreach ( $pages as $slug => $page_data ) {
            // Prüfen ob Seite existiert
            $existing = get_page_by_path( $slug );
            if ( $existing ) {
                continue;
            }

            wp_insert_post( array(
                'post_title'     => $page_data['title'],
                'post_content'   => $page_data['content'],
                'post_status'    => 'publish',
                'post_type'      => 'page',
                'post_name'      => $slug,
                'comment_status' => 'closed',
            ) );
        }

        // Seiten-IDs speichern
        update_option( 'stylegenius_pages', array(
            'dashboard'  => get_page_by_path( 'stylegenius-dashboard' )->ID ?? 0,
            'quiz'       => get_page_by_path( 'stylegenius-quiz' )->ID ?? 0,
            'chat'       => get_page_by_path( 'stylegenius-beratung' )->ID ?? 0,
            'wardrobe'   => get_page_by_path( 'stylegenius-garderobe' )->ID ?? 0,
            'challenges' => get_page_by_path( 'stylegenius-challenges' )->ID ?? 0,
            'pricing'    => get_page_by_path( 'stylegenius-preise' )->ID ?? 0,
        ) );
    }

    /**
     * Setzt Capabilities für Admin-Rollen
     */
    private static function set_capabilities(): void {
        $admin = get_role( 'administrator' );
        if ( $admin ) {
            $admin->add_cap( 'manage_stylegenius' );
            $admin->add_cap( 'manage_stylegenius_challenges' );
            $admin->add_cap( 'view_stylegenius_stats' );
        }

        $shop_manager = get_role( 'shop_manager' );
        if ( $shop_manager ) {
            $shop_manager->add_cap( 'manage_stylegenius' );
            $shop_manager->add_cap( 'manage_stylegenius_challenges' );
            $shop_manager->add_cap( 'view_stylegenius_stats' );
        }
    }

    /**
     * Prüft und aktualisiert die Datenbank bei Bedarf
     */
    public static function update_db_check(): void {
        $installed_version = get_option( 'stylegenius_db_version', '0' );

        if ( version_compare( $installed_version, self::$db_version, '<' ) ) {
            self::create_tables();
            update_option( 'stylegenius_db_version', self::$db_version );
        }
    }
}
