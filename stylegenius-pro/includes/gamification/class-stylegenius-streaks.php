<?php
/**
 * Streaks-Klasse
 *
 * Verwaltet das Streak-System für tägliche Aktivität.
 *
 * @package StyleGenius_Pro
 * @subpackage StyleGenius_Pro/includes/gamification
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class StyleGenius_Streaks
 */
class StyleGenius_Streaks {

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
     * Konstruktor
     *
     * @param int $user_id Benutzer-ID.
     */
    public function __construct( int $user_id ) {
        $this->user_id = $user_id;
        $this->db      = new StyleGenius_Database();
    }

    /**
     * Gibt den aktuellen Streak zurück
     *
     * @return int
     */
    public function get_current_streak(): int {
        $streak = $this->get_streak_data();
        return $streak->current_streak ?? 0;
    }

    /**
     * Gibt den längsten Streak zurück
     *
     * @return int
     */
    public function get_longest_streak(): int {
        $streak = $this->get_streak_data();
        return $streak->longest_streak ?? 0;
    }

    /**
     * Gibt alle Streak-Informationen zurück
     *
     * @return array
     */
    public function get_streak_info(): array {
        $streak = $this->get_streak_data();

        $current = $streak->current_streak ?? 0;
        $longest = $streak->longest_streak ?? 0;

        return array(
            'current_streak'       => $current,
            'longest_streak'       => $longest,
            'last_activity'        => $streak->last_activity ?? null,
            'streak_start'         => $streak->streak_start ?? null,
            'is_active_today'      => $this->is_active_today(),
            'at_risk'              => $this->is_streak_at_risk(),
            'hours_until_break'    => $this->get_hours_until_streak_break(),
            'multiplier'           => $this->get_streak_bonus_multiplier(),
            'next_milestone'       => $this->get_next_milestone( $current ),
            'days_to_milestone'    => $this->get_days_to_next_milestone( $current ),
        );
    }

    /**
     * Aktualisiert den Streak
     *
     * @return array Aktualisierte Streak-Info.
     */
    public function update_streak(): array {
        $streak = $this->get_streak_data();
        $today  = current_time( 'Y-m-d' );

        // Bereits heute aktiv?
        if ( ! empty( $streak->last_activity ) && $streak->last_activity === $today ) {
            return $this->get_streak_info();
        }

        $yesterday = wp_date( 'Y-m-d', strtotime( '-1 day' ) );

        // Streak fortführen oder neu starten
        if ( empty( $streak->last_activity ) || $streak->last_activity < $yesterday ) {
            // Streak unterbrochen - neu starten
            $new_streak = 1;
            $streak_start = $today;
        } else {
            // Streak fortführen
            $new_streak   = ( $streak->current_streak ?? 0 ) + 1;
            $streak_start = $streak->streak_start ?? $today;
        }

        // Längsten Streak aktualisieren
        $longest = max( $streak->longest_streak ?? 0, $new_streak );

        // In DB speichern
        $this->save_streak_data( $new_streak, $longest, $today, $streak_start );

        // Streak-Badges prüfen
        $this->check_streak_badges();

        // Daily-Login-Punkte vergeben
        $points = new StyleGenius_Points( $this->user_id );
        $points->award_points( 'daily_login' );

        /**
         * Fires when a streak is updated.
         *
         * @param int    $user_id    User ID.
         * @param int    $new_streak New streak count.
         * @param string $today      Today's date.
         */
        do_action( 'stylegenius_streak_updated', $this->user_id, $new_streak, $today );

        return $this->get_streak_info();
    }

    /**
     * Prüft ob der Streak noch gültig ist
     *
     * @return bool
     */
    public function check_streak(): bool {
        $streak = $this->get_streak_data();

        if ( empty( $streak->last_activity ) ) {
            return true; // Kein Streak vorhanden
        }

        $yesterday = wp_date( 'Y-m-d', strtotime( '-1 day' ) );

        return $streak->last_activity >= $yesterday;
    }

    /**
     * Unterbricht den Streak
     */
    public function break_streak(): void {
        $streak = $this->get_streak_data();

        $this->save_streak_data(
            0,
            $streak->longest_streak ?? 0,
            null,
            null
        );

        /**
         * Fires when a streak is broken.
         *
         * @param int $user_id       User ID.
         * @param int $broken_streak The streak that was broken.
         */
        do_action( 'stylegenius_streak_broken', $this->user_id, $streak->current_streak ?? 0 );
    }

    /**
     * Stellt einen Streak wieder her
     *
     * @param int|null $days Wiederherzustellende Tage (null = vorheriger Streak).
     * @return bool
     */
    public function restore_streak( ?int $days = null ): bool {
        $streak = $this->get_streak_data();

        if ( null === $days ) {
            // Vorherigen Streak wiederherstellen
            $days = $streak->current_streak ?? 0;
        }

        if ( $days <= 0 ) {
            return false;
        }

        $today        = current_time( 'Y-m-d' );
        $streak_start = wp_date( 'Y-m-d', strtotime( "-{$days} days" ) );

        $this->save_streak_data(
            $days,
            max( $streak->longest_streak ?? 0, $days ),
            $today,
            $streak_start
        );

        return true;
    }

    /**
     * Gibt den Streak-Bonus-Multiplikator zurück
     *
     * @return float
     */
    public function get_streak_bonus_multiplier(): float {
        $streak = $this->get_current_streak();

        if ( $streak < 3 ) {
            return 1.0;
        } elseif ( $streak < 7 ) {
            return 1.1; // 10% Bonus
        } elseif ( $streak < 14 ) {
            return 1.2; // 20% Bonus
        } elseif ( $streak < 30 ) {
            return 1.3; // 30% Bonus
        } elseif ( $streak < 60 ) {
            return 1.5; // 50% Bonus
        } elseif ( $streak < 100 ) {
            return 1.75; // 75% Bonus
        } else {
            return 2.0; // 100% Bonus
        }
    }

    /**
     * Gibt das letzte Aktivitätsdatum zurück
     *
     * @return DateTime|null
     */
    public function get_last_activity_date(): ?DateTime {
        $streak = $this->get_streak_data();

        if ( empty( $streak->last_activity ) ) {
            return null;
        }

        return new DateTime( $streak->last_activity );
    }

    /**
     * Prüft ob der Streak gefährdet ist
     *
     * @return bool
     */
    public function is_streak_at_risk(): bool {
        if ( $this->get_current_streak() === 0 ) {
            return false;
        }

        // Gefährdet wenn weniger als 6 Stunden bis Mitternacht
        return $this->get_hours_until_streak_break() <= 6 && ! $this->is_active_today();
    }

    /**
     * Gibt die Stunden bis zum Streak-Verlust zurück
     *
     * @return int
     */
    public function get_hours_until_streak_break(): int {
        if ( $this->is_active_today() ) {
            // 24 Stunden + Zeit bis Mitternacht morgen
            $tomorrow_midnight = strtotime( 'tomorrow midnight' ) + 86400;
            $now               = current_time( 'timestamp' );

            return (int) ceil( ( $tomorrow_midnight - $now ) / 3600 );
        }

        // Zeit bis Mitternacht heute
        $midnight = strtotime( 'tomorrow midnight' );
        $now      = current_time( 'timestamp' );

        return max( 0, (int) ceil( ( $midnight - $now ) / 3600 ) );
    }

    /**
     * Prüft Streak-Badges
     *
     * @return array Verdiente Badges.
     */
    public function check_streak_badges(): array {
        $badges = new StyleGenius_Badges( $this->user_id );
        $earned = array();

        $streak     = $this->get_current_streak();
        $milestones = self::get_streak_milestones();

        foreach ( $milestones as $days => $badge_id ) {
            if ( $streak >= $days && ! $badges->has_badge( $badge_id ) ) {
                $badges->award_badge( $badge_id );
                $earned[] = $badge_id;
            }
        }

        return $earned;
    }

    /**
     * Gibt die Streak-Historie zurück
     *
     * @return array
     */
    public function get_streak_history(): array {
        // Placeholder für Historie-Implementierung
        return array();
    }

    /**
     * Gibt den Streak-Kalender zurück
     *
     * @param int $days Anzahl Tage.
     * @return array
     */
    public function get_streak_calendar( int $days = 30 ): array {
        global $wpdb;

        $calendar = array();
        $db       = new StyleGenius_Database();
        $table    = $db->get_table_name( 'points_log' );

        // Alle Tage mit Aktivität laden
        $results = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT DATE(created_at) as activity_date
                FROM {$table}
                WHERE user_id = %d
                AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
                ORDER BY activity_date ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $this->user_id,
                $days
            )
        );

        $active_days = array_flip( $results );

        // Kalender erstellen
        for ( $i = $days - 1; $i >= 0; $i-- ) {
            $date          = wp_date( 'Y-m-d', strtotime( "-{$i} days" ) );
            $calendar[]    = array(
                'date'   => $date,
                'active' => isset( $active_days[ $date ] ),
            );
        }

        return $calendar;
    }

    /**
     * Gibt die Streak-Meilensteine zurück
     *
     * @return array
     */
    public static function get_streak_milestones(): array {
        return array(
            3   => 'streak_3',
            7   => 'streak_7',
            14  => 'streak_14',
            30  => 'streak_30',
            60  => 'streak_60',
            100 => 'streak_100',
            365 => 'streak_365',
        );
    }

    /**
     * Führt die tägliche Streak-Prüfung für alle Benutzer durch
     */
    public static function run_daily_check(): void {
        global $wpdb;

        $db    = new StyleGenius_Database();
        $table = $db->get_table_name( 'streaks' );

        $yesterday = wp_date( 'Y-m-d', strtotime( '-1 day' ) );

        // Alle Streaks finden, die unterbrochen wurden
        $broken_streaks = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT user_id, current_streak FROM {$table}
                WHERE current_streak > 0 AND last_activity < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $yesterday
            )
        );

        foreach ( $broken_streaks as $streak ) {
            $streaks = new self( $streak->user_id );
            $streaks->break_streak();
        }
    }

    /**
     * Gibt die Streak-Daten zurück
     *
     * @return object|null
     */
    private function get_streak_data(): ?object {
        return $this->db->get_row( 'streaks', array( 'user_id' => $this->user_id ) );
    }

    /**
     * Speichert Streak-Daten
     *
     * @param int         $current      Aktueller Streak.
     * @param int         $longest      Längster Streak.
     * @param string|null $last_activity Letzte Aktivität.
     * @param string|null $streak_start Streak-Start.
     */
    private function save_streak_data( int $current, int $longest, ?string $last_activity, ?string $streak_start ): void {
        global $wpdb;

        $table = $this->db->get_table_name( 'streaks' );

        $data = array(
            'user_id'        => $this->user_id,
            'current_streak' => $current,
            'longest_streak' => $longest,
            'last_activity'  => $last_activity,
            'streak_start'   => $streak_start,
            'updated_at'     => current_time( 'mysql' ),
        );

        $existing = $this->get_streak_data();

        if ( $existing ) {
            $wpdb->update(
                $table,
                $data,
                array( 'user_id' => $this->user_id ),
                array( '%d', '%d', '%d', '%s', '%s', '%s' ),
                array( '%d' )
            );
        } else {
            $this->db->insert( 'streaks', $data, array( '%d', '%d', '%d', '%s', '%s', '%s' ) );
        }
    }

    /**
     * Prüft ob der Benutzer heute schon aktiv war
     *
     * @return bool
     */
    private function is_active_today(): bool {
        $streak = $this->get_streak_data();
        $today  = current_time( 'Y-m-d' );

        return ! empty( $streak->last_activity ) && $streak->last_activity === $today;
    }

    /**
     * Gibt den nächsten Meilenstein zurück
     *
     * @param int $current Aktueller Streak.
     * @return int
     */
    private function get_next_milestone( int $current ): int {
        $milestones = array_keys( self::get_streak_milestones() );

        foreach ( $milestones as $milestone ) {
            if ( $milestone > $current ) {
                return $milestone;
            }
        }

        return 0;
    }

    /**
     * Gibt die Tage bis zum nächsten Meilenstein zurück
     *
     * @param int $current Aktueller Streak.
     * @return int
     */
    private function get_days_to_next_milestone( int $current ): int {
        $next = $this->get_next_milestone( $current );
        return max( 0, $next - $current );
    }
}
