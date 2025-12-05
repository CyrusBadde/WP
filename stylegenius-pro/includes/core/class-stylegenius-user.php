<?php
/**
 * User-Klasse
 *
 * Verwaltet StyleGenius-Benutzer mit erweiterter Funktionalität.
 *
 * @package StyleGenius_Pro
 * @subpackage StyleGenius_Pro/includes/core
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class StyleGenius_User
 */
class StyleGenius_User {

    /**
     * Benutzer-ID
     *
     * @var int|null
     */
    private ?int $user_id;

    /**
     * WordPress-User-Objekt
     *
     * @var WP_User|null
     */
    private ?WP_User $user;

    /**
     * Meta-Objekt
     *
     * @var StyleGenius_Meta|null
     */
    private ?StyleGenius_Meta $meta;

    /**
     * Tiers-Instanz
     *
     * @var StyleGenius_Tiers
     */
    private StyleGenius_Tiers $tiers;

    /**
     * Konstruktor
     *
     * @param int|null $user_id Benutzer-ID oder null für aktuellen Benutzer.
     */
    public function __construct( ?int $user_id = null ) {
        $this->user_id = $user_id ?? get_current_user_id();
        $this->user    = $this->user_id ? get_user_by( 'id', $this->user_id ) : null;
        $this->meta    = $this->user_id ? new StyleGenius_Meta( $this->user_id ) : null;
        $this->tiers   = new StyleGenius_Tiers();
    }

    /**
     * Gibt die Benutzer-ID zurück
     *
     * @return int|null
     */
    public function get_id(): ?int {
        return $this->user_id ?: null;
    }

    /**
     * Gibt das WordPress-User-Objekt zurück
     *
     * @return WP_User|null
     */
    public function get_user(): ?WP_User {
        return $this->user;
    }

    /**
     * Prüft ob der Benutzer existiert
     *
     * @return bool
     */
    public function exists(): bool {
        return null !== $this->user && $this->user instanceof WP_User;
    }

    /**
     * Prüft ob der Benutzer eingeloggt ist
     *
     * @return bool
     */
    public function is_logged_in(): bool {
        return $this->user_id > 0 && is_user_logged_in() && get_current_user_id() === $this->user_id;
    }

    /**
     * Gibt die Mitgliedsstufe zurück
     *
     * @return string
     */
    public function get_tier(): string {
        if ( ! $this->meta ) {
            return StyleGenius_Tiers::TIER_FREE;
        }

        $tier = $this->meta->get( 'tier', StyleGenius_Tiers::TIER_FREE );

        // Sync mit WooCommerce wenn nötig
        if ( class_exists( 'WooCommerce' ) && class_exists( 'WC_Subscriptions' ) ) {
            $wc = new StyleGenius_WooCommerce();
            if ( $wc->has_active_subscription( $this->user_id ) ) {
                $subscription_tier = $wc->get_subscription_tier( $wc->get_user_subscription( $this->user_id ) );
                if ( $subscription_tier && $subscription_tier !== $tier ) {
                    $this->set_tier( $subscription_tier );
                    $tier = $subscription_tier;
                }
            }
        }

        return $tier;
    }

    /**
     * Setzt die Mitgliedsstufe
     *
     * @param string $tier Neue Stufe.
     * @return bool
     */
    public function set_tier( string $tier ): bool {
        if ( ! $this->meta ) {
            return false;
        }

        if ( ! StyleGenius_Tiers::is_valid_tier( $tier ) ) {
            return false;
        }

        $old_tier = $this->get_tier();
        $result   = $this->meta->set( 'tier', $tier );

        if ( $result && $old_tier !== $tier ) {
            /**
             * Fires when a user's tier changes.
             *
             * @param int    $user_id  User ID.
             * @param string $new_tier New tier.
             * @param string $old_tier Old tier.
             */
            do_action( 'stylegenius_tier_changed', $this->user_id, $tier, $old_tier );

            // Usage-Counter zurücksetzen bei Upgrade
            if ( StyleGenius_Tiers::compare_tiers( $tier, $old_tier ) > 0 ) {
                $this->reset_usage();
            }
        }

        return $result;
    }

    /**
     * Prüft ob Benutzer Free-Tier hat
     *
     * @return bool
     */
    public function is_free(): bool {
        return $this->get_tier() === StyleGenius_Tiers::TIER_FREE;
    }

    /**
     * Prüft ob Benutzer Premium hat
     *
     * @return bool
     */
    public function is_premium(): bool {
        return StyleGenius_Tiers::get_tier_level( $this->get_tier() ) >= StyleGenius_Tiers::get_tier_level( StyleGenius_Tiers::TIER_PREMIUM );
    }

    /**
     * Prüft ob Benutzer VIP ist
     *
     * @return bool
     */
    public function is_vip(): bool {
        return $this->get_tier() === StyleGenius_Tiers::TIER_VIP;
    }

    /**
     * Gibt den Style-Typ zurück
     *
     * @return string|null
     */
    public function get_style_type(): ?string {
        return $this->meta ? $this->meta->get( 'style_type' ) : null;
    }

    /**
     * Setzt den Style-Typ
     *
     * @param string $type Style-Typ.
     * @return bool
     */
    public function set_style_type( string $type ): bool {
        if ( ! $this->meta ) {
            return false;
        }

        return $this->meta->set( 'style_type', $type );
    }

    /**
     * Gibt den Nutzungszähler zurück
     *
     * @return int
     */
    public function get_usage_count(): int {
        if ( ! $this->meta ) {
            return 0;
        }

        // Prüfe ob Reset nötig
        $this->maybe_reset_usage();

        return (int) $this->meta->get( 'usage_count', 0 );
    }

    /**
     * Erhöht den Nutzungszähler
     *
     * @return int Neuer Zählerstand.
     */
    public function increment_usage(): int {
        if ( ! $this->meta ) {
            return 0;
        }

        $this->maybe_reset_usage();
        return $this->meta->increment( 'usage_count' );
    }

    /**
     * Setzt den Nutzungszähler zurück
     */
    public function reset_usage(): void {
        if ( ! $this->meta ) {
            return;
        }

        $this->meta->set( 'usage_count', 0 );
        $this->meta->set( 'usage_reset_date', current_time( 'mysql' ) );
    }

    /**
     * Setzt ggf. den Nutzungszähler zurück (monatlich)
     */
    private function maybe_reset_usage(): void {
        if ( ! $this->meta ) {
            return;
        }

        $reset_date = $this->meta->get( 'usage_reset_date' );

        if ( empty( $reset_date ) ) {
            $this->reset_usage();
            return;
        }

        $reset_time   = strtotime( $reset_date );
        $current_time = current_time( 'timestamp' );

        // Zurücksetzen wenn der letzte Reset vor dem 1. dieses Monats war
        $first_of_month = strtotime( 'first day of this month midnight' );

        if ( $reset_time < $first_of_month ) {
            $this->reset_usage();
        }
    }

    /**
     * Gibt die verbleibenden Nutzungen zurück
     *
     * @return int -1 für unbegrenzt.
     */
    public function get_remaining_uses(): int {
        $tier_limits = StyleGenius_Tiers::get_tier_limits( $this->get_tier() );
        $limit       = $tier_limits['ai_requests'] ?? 10;

        if ( -1 === $limit ) {
            return -1;
        }

        $used = $this->get_usage_count();
        return max( 0, $limit - $used );
    }

    /**
     * Prüft ob der Benutzer ein Feature nutzen kann
     *
     * @param string $feature Feature-Name.
     * @return bool
     */
    public function can_use_feature( string $feature ): bool {
        $tier = $this->get_tier();

        // Basis-Features prüfen
        if ( ! StyleGenius_Tiers::has_feature( $tier, $feature ) ) {
            return false;
        }

        // Für AI-Features: Nutzungslimit prüfen
        if ( in_array( $feature, array( 'ai_chat', 'ai_analysis', 'ai_recommendations' ), true ) ) {
            $remaining = $this->get_remaining_uses();
            if ( 0 === $remaining ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Gibt den Anzeigenamen zurück
     *
     * @return string
     */
    public function get_display_name(): string {
        if ( ! $this->user ) {
            return __( 'Gast', 'stylegenius-pro' );
        }

        return $this->user->display_name ?: $this->user->user_login;
    }

    /**
     * Gibt die E-Mail zurück
     *
     * @return string
     */
    public function get_email(): string {
        return $this->user ? $this->user->user_email : '';
    }

    /**
     * Gibt die Avatar-URL zurück
     *
     * @param int $size Avatar-Größe.
     * @return string
     */
    public function get_avatar_url( int $size = 96 ): string {
        return get_avatar_url( $this->user_id, array( 'size' => $size ) );
    }

    /**
     * Gibt das Registrierungsdatum zurück
     *
     * @return DateTime
     */
    public function get_registration_date(): DateTime {
        $date = $this->user ? $this->user->user_registered : current_time( 'mysql' );
        return new DateTime( $date );
    }

    /**
     * Gibt die Profil-URL zurück
     *
     * @return string
     */
    public function get_profile_url(): string {
        $pages = get_option( 'stylegenius_pages', array() );
        $dashboard_id = $pages['dashboard'] ?? 0;

        if ( $dashboard_id ) {
            return add_query_arg( 'tab', 'profile', get_permalink( $dashboard_id ) );
        }

        return '#';
    }

    /**
     * Gibt die Dashboard-URL zurück
     *
     * @return string
     */
    public function get_dashboard_url(): string {
        $pages = get_option( 'stylegenius_pages', array() );
        $dashboard_id = $pages['dashboard'] ?? 0;

        if ( $dashboard_id ) {
            return get_permalink( $dashboard_id );
        }

        return '#';
    }

    /**
     * Prüft ob das Quiz abgeschlossen wurde
     *
     * @return bool
     */
    public function has_completed_quiz(): bool {
        if ( ! $this->meta ) {
            return false;
        }

        return ! empty( $this->meta->get( 'quiz_completed_at' ) );
    }

    /**
     * Prüft ob die Farbanalyse abgeschlossen wurde
     *
     * @return bool
     */
    public function has_completed_color_analysis(): bool {
        global $wpdb;

        $db    = new StyleGenius_Database();
        $table = $db->get_table_name( 'color_profiles' );

        $result = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE user_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $this->user_id
            )
        );

        return $result > 0;
    }

    /**
     * Gibt den Profil-Vervollständigungsgrad zurück
     *
     * @return int Prozent (0-100).
     */
    public function get_profile_completion_percentage(): int {
        if ( ! $this->meta ) {
            return 0;
        }

        $total    = 0;
        $complete = 0;

        // Quiz
        $total++;
        if ( $this->has_completed_quiz() ) {
            $complete++;
        }

        // Farbanalyse
        $total++;
        if ( $this->has_completed_color_analysis() ) {
            $complete++;
        }

        // Budget
        $total++;
        if ( $this->meta->get( 'budget_min' ) || $this->meta->get( 'budget_max' ) ) {
            $complete++;
        }

        // Beruf
        $total++;
        if ( $this->meta->get( 'profession' ) ) {
            $complete++;
        }

        // Körpertyp
        $total++;
        if ( $this->meta->get( 'body_type' ) ) {
            $complete++;
        }

        // Lieblingsmarken
        $total++;
        $brands = $this->meta->get( 'favorite_brands', array() );
        if ( ! empty( $brands ) ) {
            $complete++;
        }

        // Typische Anlässe
        $total++;
        $occasions = $this->meta->get( 'typical_occasions', array() );
        if ( ! empty( $occasions ) ) {
            $complete++;
        }

        // Garderobe (mindestens 5 Teile)
        $total++;
        $db         = new StyleGenius_Database();
        $wardrobe_count = $db->get_count( 'wardrobe', array( 'user_id' => $this->user_id ) );
        if ( $wardrobe_count >= 5 ) {
            $complete++;
        }

        return (int) round( ( $complete / $total ) * 100 );
    }

    /**
     * Gibt alle Benutzerdaten zurück
     *
     * @return array
     */
    public function get_all_data(): array {
        if ( ! $this->user || ! $this->meta ) {
            return array();
        }

        return array(
            'id'                     => $this->user_id,
            'email'                  => $this->get_email(),
            'display_name'           => $this->get_display_name(),
            'registered'             => $this->get_registration_date()->format( 'Y-m-d H:i:s' ),
            'tier'                   => $this->get_tier(),
            'style_type'             => $this->get_style_type(),
            'usage_count'            => $this->get_usage_count(),
            'remaining_uses'         => $this->get_remaining_uses(),
            'quiz_completed'         => $this->has_completed_quiz(),
            'color_analysis_done'    => $this->has_completed_color_analysis(),
            'profile_completion'     => $this->get_profile_completion_percentage(),
            'points'                 => (int) $this->meta->get( 'points', 0 ),
            'level'                  => (int) $this->meta->get( 'level', 1 ),
            'referral_code'          => $this->meta->get( 'referral_code', '' ),
            'referral_count'         => (int) $this->meta->get( 'referral_count', 0 ),
            'meta'                   => $this->meta->get_all(),
        );
    }

    /**
     * Findet Benutzer anhand des Referral-Codes
     *
     * @param string $code Referral-Code.
     * @return StyleGenius_User|null
     */
    public static function get_by_referral_code( string $code ): ?StyleGenius_User {
        global $wpdb;

        $user_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'sg_referral_code' AND meta_value = %s LIMIT 1",
                $code
            )
        );

        if ( $user_id ) {
            return new self( (int) $user_id );
        }

        return null;
    }

    /**
     * Erstellt einen neuen Benutzer aus Registrierungsdaten
     *
     * @param array $data Registrierungsdaten.
     * @return StyleGenius_User|WP_Error
     */
    public static function create_from_registration( array $data ): StyleGenius_User|WP_Error {
        $email    = sanitize_email( $data['email'] ?? '' );
        $password = $data['password'] ?? wp_generate_password();
        $username = sanitize_user( $data['username'] ?? $email );

        if ( ! is_email( $email ) ) {
            return new WP_Error( 'invalid_email', __( 'Ungültige E-Mail-Adresse.', 'stylegenius-pro' ) );
        }

        if ( email_exists( $email ) ) {
            return new WP_Error( 'email_exists', __( 'Diese E-Mail-Adresse wird bereits verwendet.', 'stylegenius-pro' ) );
        }

        if ( username_exists( $username ) ) {
            // Username mit Zufallszahl versehen
            $username = $username . wp_rand( 100, 999 );
        }

        $user_id = wp_create_user( $username, $password, $email );

        if ( is_wp_error( $user_id ) ) {
            return $user_id;
        }

        // Standarddaten setzen
        $user = new self( $user_id );
        $meta = new StyleGenius_Meta( $user_id );

        $meta->set( 'tier', StyleGenius_Tiers::TIER_FREE );
        $meta->set( 'usage_count', 0 );
        $meta->set( 'usage_reset_date', current_time( 'mysql' ) );
        $meta->set( 'points', 0 );
        $meta->set( 'level', 1 );
        $meta->set( 'onboarding_completed', false );

        // Referral-Code generieren
        $referral = new StyleGenius_Referral( $user_id );
        $referral->generate_referral_code();

        // Referral verarbeiten wenn vorhanden
        if ( ! empty( $data['referral_code'] ) ) {
            $referral->process_referral( $data['referral_code'], $user_id );
        }

        /**
         * Fires after a new StyleGenius user is created.
         *
         * @param int   $user_id User ID.
         * @param array $data    Registration data.
         */
        do_action( 'stylegenius_user_created', $user_id, $data );

        return $user;
    }

    /**
     * Gibt das Meta-Objekt zurück
     *
     * @return StyleGenius_Meta|null
     */
    public function get_meta(): ?StyleGenius_Meta {
        return $this->meta;
    }

    /**
     * Aktualisiert die letzte Aktivität
     */
    public function update_last_activity(): void {
        if ( $this->meta ) {
            $this->meta->set( 'last_activity', current_time( 'mysql' ) );
        }
    }
}
