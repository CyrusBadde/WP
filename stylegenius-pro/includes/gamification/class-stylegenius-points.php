<?php
/**
 * Points-Klasse
 *
 * Verwaltet das Punkte-System.
 *
 * @package StyleGenius_Pro
 * @subpackage StyleGenius_Pro/includes/gamification
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class StyleGenius_Points
 */
class StyleGenius_Points {

    /**
     * Database-Objekt
     *
     * @var StyleGenius_Database
     */
    private StyleGenius_Database $db;

    /**
     * Benutzer-ID
     *
     * @var int
     */
    private int $user_id;

    /**
     * Meta-Objekt
     *
     * @var StyleGenius_Meta
     */
    private StyleGenius_Meta $meta;

    /**
     * Konstruktor
     *
     * @param int $user_id Benutzer-ID.
     */
    public function __construct( int $user_id ) {
        $this->user_id = $user_id;
        $this->db      = new StyleGenius_Database();
        $this->meta    = new StyleGenius_Meta( $user_id );
    }

    /**
     * Gibt die aktuellen Punkte zurück
     *
     * @return int
     */
    public function get_points(): int {
        return (int) $this->meta->get( 'points', 0 );
    }

    /**
     * Vergibt Punkte für eine Aktion
     *
     * @param string   $action Aktionsname.
     * @param int|null $amount Punktzahl (optional, Standard aus Einstellungen).
     * @return int Neue Punktzahl.
     */
    public function award_points( string $action, ?int $amount = null ): int {
        // Prüfe Cooldown
        if ( ! $this->can_earn_points( $action ) ) {
            return $this->get_points();
        }

        // Punktwert ermitteln
        if ( null === $amount ) {
            $amount = $this->get_action_points_value( $action );
        }

        if ( $amount <= 0 ) {
            return $this->get_points();
        }

        // Streak-Multiplikator anwenden
        $amount = $this->apply_multiplier( $amount, $action );

        // Punkte zum User hinzufügen
        $current_points = $this->get_points();
        $new_points     = $current_points + $amount;

        $this->meta->set( 'points', $new_points );

        // Transaktion loggen
        $this->log_transaction( $action, $amount, $new_points );

        // Cooldown setzen
        $this->set_cooldown( $action );

        // Level-Check
        $levels = new StyleGenius_Levels( $this->user_id );
        $levels->check_level_up();

        // Badges prüfen
        $badges = new StyleGenius_Badges( $this->user_id );
        $badges->trigger_badge_check( 'points_earned' );

        /**
         * Fires after points are awarded.
         *
         * @param int    $user_id User ID.
         * @param string $action  Action name.
         * @param int    $amount  Points awarded.
         * @param int    $new_points New total.
         */
        do_action( 'stylegenius_points_awarded', $this->user_id, $action, $amount, $new_points );

        return $new_points;
    }

    /**
     * Zieht Punkte ab
     *
     * @param int    $amount Punktzahl.
     * @param string $reason Grund.
     * @return int|WP_Error Neue Punktzahl oder Fehler.
     */
    public function deduct_points( int $amount, string $reason ): int|WP_Error {
        $current = $this->get_points();

        if ( $amount > $current ) {
            return new WP_Error( 'insufficient_points', __( 'Nicht genügend Punkte.', 'stylegenius-pro' ) );
        }

        $new_points = $current - $amount;
        $this->meta->set( 'points', $new_points );

        $this->log_transaction( 'deduct_' . $reason, -$amount, $new_points );

        return $new_points;
    }

    /**
     * Setzt Punkte auf einen bestimmten Wert
     *
     * @param int $amount Neue Punktzahl.
     * @return bool
     */
    public function set_points( int $amount ): bool {
        $old_points = $this->get_points();
        $result     = $this->meta->set( 'points', max( 0, $amount ) );

        if ( $result ) {
            $this->log_transaction( 'admin_set', $amount - $old_points, $amount );
        }

        return $result;
    }

    /**
     * Gibt die Punkte-Historie zurück
     *
     * @param int $limit Limit.
     * @return array
     */
    public function get_points_history( int $limit = 50 ): array {
        return $this->db->get_user_points_history( $this->user_id, $limit );
    }

    /**
     * Gibt die heute verdienten Punkte zurück
     *
     * @return int
     */
    public function get_points_today(): int {
        global $wpdb;

        $table = $this->db->get_table_name( 'points_log' );
        $today = current_time( 'Y-m-d' );

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(points), 0) FROM {$table}
                WHERE user_id = %d AND points > 0 AND DATE(created_at) = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $this->user_id,
                $today
            )
        );
    }

    /**
     * Gibt die diese Woche verdienten Punkte zurück
     *
     * @return int
     */
    public function get_points_this_week(): int {
        global $wpdb;

        $table = $this->db->get_table_name( 'points_log' );

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(points), 0) FROM {$table}
                WHERE user_id = %d AND points > 0
                AND created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $this->user_id
            )
        );
    }

    /**
     * Gibt die diesen Monat verdienten Punkte zurück
     *
     * @return int
     */
    public function get_points_this_month(): int {
        global $wpdb;

        $table = $this->db->get_table_name( 'points_log' );

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(points), 0) FROM {$table}
                WHERE user_id = %d AND points > 0
                AND MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $this->user_id
            )
        );
    }

    /**
     * Gibt Punkte nach Zeitraum zurück
     *
     * @param string $period Zeitraum (day, week, month, year).
     * @return array
     */
    public function get_points_by_period( string $period ): array {
        global $wpdb;

        $table = $this->db->get_table_name( 'points_log' );

        switch ( $period ) {
            case 'day':
                $group_by = 'DATE(created_at)';
                $interval = 'INTERVAL 30 DAY';
                break;
            case 'week':
                $group_by = 'YEARWEEK(created_at)';
                $interval = 'INTERVAL 12 WEEK';
                break;
            case 'month':
                $group_by = 'DATE_FORMAT(created_at, "%Y-%m")';
                $interval = 'INTERVAL 12 MONTH';
                break;
            default:
                $group_by = 'DATE(created_at)';
                $interval = 'INTERVAL 30 DAY';
        }

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT {$group_by} as period, SUM(points) as total
                FROM {$table}
                WHERE user_id = %d AND created_at >= DATE_SUB(NOW(), {$interval})
                GROUP BY {$group_by}
                ORDER BY period ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $this->user_id
            )
        );
    }

    /**
     * Gibt den Punktwert für eine Aktion zurück
     *
     * @param string $action Aktionsname.
     * @return int
     */
    public function get_action_points_value( string $action ): int {
        $options = get_option( 'stylegenius_options', array() );

        $values = array(
            'quiz'          => $options['points_quiz'] ?? 100,
            'consultation'  => $options['points_consultation'] ?? 50,
            'upload'        => $options['points_upload'] ?? 25,
            'wardrobe'      => $options['points_wardrobe'] ?? 10,
            'challenge'     => $options['points_challenge'] ?? 50,
            'challenge_win' => $options['points_challenge_win'] ?? 200,
            'referral'      => $options['points_referral'] ?? 100,
            'daily_login'   => $options['points_daily_login'] ?? 5,
            'badge_earned'  => 50,
            'level_up'      => 100,
            'profile_complete' => 75,
            'first_outfit'  => 30,
            'capsule_create'=> 40,
            'share'         => 10,
            'vote'          => 5,
        );

        return $values[ $action ] ?? 0;
    }

    /**
     * Prüft ob Punkte verdient werden können (Cooldown)
     *
     * @param string $action Aktionsname.
     * @return bool
     */
    public function can_earn_points( string $action ): bool {
        $cooldowns = self::get_action_cooldowns();

        if ( ! isset( $cooldowns[ $action ] ) ) {
            return true;
        }

        $cooldown       = $cooldowns[ $action ];
        $last_earned    = get_user_meta( $this->user_id, 'sg_cooldown_' . $action, true );

        if ( empty( $last_earned ) ) {
            return true;
        }

        $last_time    = strtotime( $last_earned );
        $current_time = current_time( 'timestamp' );

        return ( $current_time - $last_time ) >= $cooldown;
    }

    /**
     * Gibt die verbleibende Cooldown-Zeit zurück
     *
     * @param string $action Aktionsname.
     * @return int Sekunden bis Cooldown abläuft.
     */
    public function get_cooldown_remaining( string $action ): int {
        $cooldowns = self::get_action_cooldowns();

        if ( ! isset( $cooldowns[ $action ] ) ) {
            return 0;
        }

        $cooldown    = $cooldowns[ $action ];
        $last_earned = get_user_meta( $this->user_id, 'sg_cooldown_' . $action, true );

        if ( empty( $last_earned ) ) {
            return 0;
        }

        $last_time    = strtotime( $last_earned );
        $current_time = current_time( 'timestamp' );
        $elapsed      = $current_time - $last_time;

        return max( 0, $cooldown - $elapsed );
    }

    /**
     * Setzt den Cooldown für eine Aktion
     *
     * @param string $action Aktionsname.
     */
    private function set_cooldown( string $action ): void {
        $cooldowns = self::get_action_cooldowns();

        if ( isset( $cooldowns[ $action ] ) ) {
            update_user_meta( $this->user_id, 'sg_cooldown_' . $action, current_time( 'mysql' ) );
        }
    }

    /**
     * Loggt eine Punkte-Transaktion
     *
     * @param string $action  Aktionsname.
     * @param int    $points  Punkte.
     * @param int    $balance Neuer Stand.
     * @return bool
     */
    private function log_transaction( string $action, int $points, int $balance ): bool {
        return false !== $this->db->insert(
            'points_log',
            array(
                'user_id'    => $this->user_id,
                'action'     => $action,
                'points'     => $points,
                'balance'    => $balance,
                'created_at' => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%d', '%d', '%s' )
        );
    }

    /**
     * Wendet den Streak-Multiplikator an
     *
     * @param int    $points Punkte.
     * @param string $action Aktionsname.
     * @return int Angepasste Punkte.
     */
    private function apply_multiplier( int $points, string $action ): int {
        // Bestimmte Aktionen bekommen keinen Multiplikator
        $no_multiplier_actions = array( 'admin_set', 'referral', 'challenge_win' );

        if ( in_array( $action, $no_multiplier_actions, true ) ) {
            return $points;
        }

        $streaks    = new StyleGenius_Streaks( $this->user_id );
        $multiplier = $streaks->get_streak_bonus_multiplier();

        return (int) round( $points * $multiplier );
    }

    /**
     * Gibt die Cooldowns für Aktionen zurück
     *
     * @return array
     */
    public static function get_action_cooldowns(): array {
        return array(
            'daily_login'  => 86400,  // 24 Stunden
            'consultation' => 300,    // 5 Minuten
            'upload'       => 60,     // 1 Minute
            'share'        => 3600,   // 1 Stunde
            'vote'         => 0,      // Kein Cooldown
        );
    }

    /**
     * Gibt das Leaderboard zurück
     *
     * @param string $period Zeitraum (week, month, all).
     * @param int    $limit  Limit.
     * @return array
     */
    public static function get_leaderboard( string $period = 'all', int $limit = 10 ): array {
        $db = new StyleGenius_Database();
        return $db->get_leaderboard( 'points', $period, $limit );
    }

    /**
     * Gibt die Gesamtpunkte aller Benutzer zurück
     *
     * @return int
     */
    public static function get_total_points_awarded(): int {
        global $wpdb;

        $db    = new StyleGenius_Database();
        $table = $db->get_table_name( 'points_log' );

        return (int) $wpdb->get_var(
            "SELECT COALESCE(SUM(points), 0) FROM {$table} WHERE points > 0" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        );
    }

    /**
     * Gibt den Aktionsnamen auf Deutsch zurück
     *
     * @param string $action Aktionsname.
     * @return string
     */
    public static function get_action_label( string $action ): string {
        $labels = array(
            'quiz'             => __( 'Quiz abgeschlossen', 'stylegenius-pro' ),
            'consultation'     => __( 'KI-Beratung', 'stylegenius-pro' ),
            'upload'           => __( 'Foto hochgeladen', 'stylegenius-pro' ),
            'wardrobe'         => __( 'Garderobe-Eintrag', 'stylegenius-pro' ),
            'challenge'        => __( 'Challenge-Teilnahme', 'stylegenius-pro' ),
            'challenge_win'    => __( 'Challenge gewonnen', 'stylegenius-pro' ),
            'referral'         => __( 'Empfehlung', 'stylegenius-pro' ),
            'daily_login'      => __( 'Täglicher Login', 'stylegenius-pro' ),
            'badge_earned'     => __( 'Badge verdient', 'stylegenius-pro' ),
            'level_up'         => __( 'Level-Aufstieg', 'stylegenius-pro' ),
            'profile_complete' => __( 'Profil vervollständigt', 'stylegenius-pro' ),
            'first_outfit'     => __( 'Erstes Outfit', 'stylegenius-pro' ),
            'capsule_create'   => __( 'Capsule erstellt', 'stylegenius-pro' ),
            'share'            => __( 'Geteilt', 'stylegenius-pro' ),
            'vote'             => __( 'Abstimmung', 'stylegenius-pro' ),
        );

        return $labels[ $action ] ?? $action;
    }
}
