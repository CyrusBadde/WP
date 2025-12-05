<?php
/**
 * Levels-Klasse
 *
 * Verwaltet das Level-System.
 *
 * @package StyleGenius_Pro
 * @subpackage StyleGenius_Pro/includes/gamification
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class StyleGenius_Levels
 */
class StyleGenius_Levels {

    /**
     * Maximales Level
     *
     * @var int
     */
    const MAX_LEVEL = 10;

    /**
     * Benutzer-ID
     *
     * @var int
     */
    private int $user_id;

    /**
     * Points-Instanz
     *
     * @var StyleGenius_Points
     */
    private StyleGenius_Points $points;

    /**
     * Meta-Instanz
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
        $this->points  = new StyleGenius_Points( $user_id );
        $this->meta    = new StyleGenius_Meta( $user_id );
    }

    /**
     * Gibt das aktuelle Level zurück
     *
     * @return int
     */
    public function get_level(): int {
        return (int) $this->meta->get( 'level', 1 );
    }

    /**
     * Gibt Level-Informationen zurück
     *
     * @return array
     */
    public function get_level_info(): array {
        $level    = $this->get_level();
        $levels   = $this->get_all_levels();
        $progress = $this->get_level_progress();

        return array(
            'current_level'       => $level,
            'name'                => $this->get_level_name( $level ),
            'points_required'     => $this->get_points_for_level( $level ),
            'points_for_next'     => $this->get_points_for_next_level(),
            'current_points'      => $this->points->get_points(),
            'progress_percentage' => $progress['percentage'],
            'points_in_level'     => $progress['points_in_level'],
            'points_needed'       => $progress['points_needed'],
            'is_max_level'        => $level >= self::MAX_LEVEL,
            'perks'               => $this->get_level_perks( $level ),
            'badge_url'           => $this->get_level_badge( $level ),
            'icon_url'            => $this->get_level_icon_url( $level ),
        );
    }

    /**
     * Gibt den Fortschritt zum nächsten Level zurück
     *
     * @return array
     */
    public function get_level_progress(): array {
        $current_level  = $this->get_level();
        $current_points = $this->points->get_points();

        if ( $current_level >= self::MAX_LEVEL ) {
            return array(
                'percentage'      => 100,
                'points_in_level' => 0,
                'points_needed'   => 0,
            );
        }

        $level_start   = $this->get_points_for_level( $current_level );
        $level_end     = $this->get_points_for_level( $current_level + 1 );
        $level_range   = $level_end - $level_start;
        $points_in_level = $current_points - $level_start;

        $percentage = ( $level_range > 0 ) ? ( $points_in_level / $level_range ) * 100 : 100;

        return array(
            'percentage'      => min( 100, max( 0, round( $percentage, 1 ) ) ),
            'points_in_level' => max( 0, $points_in_level ),
            'points_needed'   => max( 0, $level_end - $current_points ),
        );
    }

    /**
     * Gibt die Punkte für ein Level zurück
     *
     * @param int $level Level.
     * @return int
     */
    public function get_points_for_level( int $level ): int {
        $level_points = array(
            1  => 0,
            2  => 200,
            3  => 500,
            4  => 1000,
            5  => 2000,
            6  => 5000,
            7  => 10000,
            8  => 25000,
            9  => 50000,
            10 => 100000,
        );

        return $level_points[ $level ] ?? 0;
    }

    /**
     * Gibt die Punkte für das nächste Level zurück
     *
     * @return int
     */
    public function get_points_for_next_level(): int {
        $current_level = $this->get_level();

        if ( $current_level >= self::MAX_LEVEL ) {
            return 0;
        }

        return $this->get_points_for_level( $current_level + 1 );
    }

    /**
     * Gibt den Level-Namen zurück
     *
     * @param int|null $level Level (optional).
     * @return string
     */
    public function get_level_name( ?int $level = null ): string {
        $level = $level ?? $this->get_level();

        $names = array(
            1  => __( 'Style Newbie', 'stylegenius-pro' ),
            2  => __( 'Style Learner', 'stylegenius-pro' ),
            3  => __( 'Style Enthusiast', 'stylegenius-pro' ),
            4  => __( 'Style Adept', 'stylegenius-pro' ),
            5  => __( 'Style Expert', 'stylegenius-pro' ),
            6  => __( 'Style Master', 'stylegenius-pro' ),
            7  => __( 'Style Guru', 'stylegenius-pro' ),
            8  => __( 'Style Legend', 'stylegenius-pro' ),
            9  => __( 'Style Icon', 'stylegenius-pro' ),
            10 => __( 'Style Deity', 'stylegenius-pro' ),
        );

        return $names[ $level ] ?? $names[1];
    }

    /**
     * Gibt die Level-Perks zurück
     *
     * @param int|null $level Level (optional).
     * @return array
     */
    public function get_level_perks( ?int $level = null ): array {
        $level = $level ?? $this->get_level();

        $perks = array(
            1  => array(
                __( 'Zugang zum Style-Quiz', 'stylegenius-pro' ),
                __( 'Basis-KI-Beratung', 'stylegenius-pro' ),
            ),
            2  => array(
                __( '+10% Bonus-Punkte', 'stylegenius-pro' ),
                __( 'Farbtyp-Analyse', 'stylegenius-pro' ),
            ),
            3  => array(
                __( 'Virtuelle Garderobe freigeschaltet', 'stylegenius-pro' ),
                __( 'Outfit-Vorschläge', 'stylegenius-pro' ),
            ),
            4  => array(
                __( '+20% Bonus-Punkte', 'stylegenius-pro' ),
                __( 'Erweiterte Outfit-Analyse', 'stylegenius-pro' ),
            ),
            5  => array(
                __( 'Challenge-Teilnahme', 'stylegenius-pro' ),
                __( 'Capsule Wardrobe Basic', 'stylegenius-pro' ),
            ),
            6  => array(
                __( '+30% Bonus-Punkte', 'stylegenius-pro' ),
                __( 'KI-Capsule-Generator', 'stylegenius-pro' ),
            ),
            7  => array(
                __( 'Exklusive Badges', 'stylegenius-pro' ),
                __( 'Prioritäts-Beratung', 'stylegenius-pro' ),
            ),
            8  => array(
                __( '+50% Bonus-Punkte', 'stylegenius-pro' ),
                __( 'Shopping-Empfehlungen', 'stylegenius-pro' ),
            ),
            9  => array(
                __( 'VIP-Community-Status', 'stylegenius-pro' ),
                __( 'Persönliche Style-Reports', 'stylegenius-pro' ),
            ),
            10 => array(
                __( 'Legende im Leaderboard', 'stylegenius-pro' ),
                __( 'Alle Features unbegrenzt', 'stylegenius-pro' ),
                __( 'Exklusiver "Style Deity" Badge', 'stylegenius-pro' ),
            ),
        );

        return $perks[ $level ] ?? array();
    }

    /**
     * Prüft und verarbeitet Level-Aufstieg
     *
     * @return bool True wenn Level-Up erfolgt.
     */
    public function check_level_up(): bool {
        $current_level  = $this->get_level();
        $current_points = $this->points->get_points();

        if ( $current_level >= self::MAX_LEVEL ) {
            return false;
        }

        $calculated_level = self::calculate_level_from_points( $current_points );

        if ( $calculated_level > $current_level ) {
            $this->meta->set( 'level', $calculated_level );
            $this->process_level_up( $calculated_level );
            return true;
        }

        return false;
    }

    /**
     * Verarbeitet einen Level-Aufstieg
     *
     * @param int $new_level Neues Level.
     */
    public function process_level_up( int $new_level ): void {
        // Level-Up Punkte vergeben
        $bonus_points = $this->get_level_up_bonus( $new_level );
        if ( $bonus_points > 0 ) {
            $this->points->award_points( 'level_up', $bonus_points );
        }

        // Level-Badge vergeben
        $badges = new StyleGenius_Badges( $this->user_id );
        $badges->award_badge( 'level_' . $new_level );

        // Level-Historie speichern
        $this->log_level_up( $new_level );

        /**
         * Fires when a user levels up.
         *
         * @param int $user_id   User ID.
         * @param int $new_level New level.
         */
        do_action( 'stylegenius_level_up', $this->user_id, $new_level );
    }

    /**
     * Gibt den Level-Badge-Pfad zurück
     *
     * @param int|null $level Level (optional).
     * @return string
     */
    public function get_level_badge( ?int $level = null ): string {
        $level = $level ?? $this->get_level();

        return STYLEGENIUS_PLUGIN_URL . 'assets/images/badges/level-' . $level . '.png';
    }

    /**
     * Gibt die Level-Icon-URL zurück
     *
     * @param int|null $level Level (optional).
     * @return string
     */
    public function get_level_icon_url( ?int $level = null ): string {
        $level = $level ?? $this->get_level();

        return STYLEGENIUS_PLUGIN_URL . 'assets/images/badges/level-' . $level . '-icon.svg';
    }

    /**
     * Gibt Informationen zum nächsten Level zurück
     *
     * @return array|null
     */
    public function get_next_level_info(): ?array {
        $current_level = $this->get_level();

        if ( $current_level >= self::MAX_LEVEL ) {
            return null;
        }

        $next_level = $current_level + 1;

        return array(
            'level'           => $next_level,
            'name'            => $this->get_level_name( $next_level ),
            'points_required' => $this->get_points_for_level( $next_level ),
            'perks'           => $this->get_level_perks( $next_level ),
            'badge_url'       => $this->get_level_badge( $next_level ),
        );
    }

    /**
     * Gibt alle Level zurück
     *
     * @return array
     */
    public function get_all_levels(): array {
        $levels = array();

        for ( $i = 1; $i <= self::MAX_LEVEL; $i++ ) {
            $levels[ $i ] = array(
                'level'           => $i,
                'name'            => $this->get_level_name( $i ),
                'points_required' => $this->get_points_for_level( $i ),
                'perks'           => $this->get_level_perks( $i ),
                'badge_url'       => $this->get_level_badge( $i ),
            );
        }

        return $levels;
    }

    /**
     * Gibt die Level-Historie zurück
     *
     * @return array
     */
    public function get_level_history(): array {
        return $this->meta->get( 'level_history', array() );
    }

    /**
     * Berechnet das Level aus Punkten
     *
     * @param int $points Punkte.
     * @return int Level.
     */
    public static function calculate_level_from_points( int $points ): int {
        $level_points = array(
            10 => 100000,
            9  => 50000,
            8  => 25000,
            7  => 10000,
            6  => 5000,
            5  => 2000,
            4  => 1000,
            3  => 500,
            2  => 200,
            1  => 0,
        );

        foreach ( $level_points as $level => $required ) {
            if ( $points >= $required ) {
                return $level;
            }
        }

        return 1;
    }

    /**
     * Gibt die Level-Verteilung aller Benutzer zurück
     *
     * @return array
     */
    public static function get_level_distribution(): array {
        global $wpdb;

        $results = $wpdb->get_results(
            "SELECT meta_value as level, COUNT(*) as count
            FROM {$wpdb->usermeta}
            WHERE meta_key = 'sg_level'
            GROUP BY meta_value
            ORDER BY CAST(meta_value AS UNSIGNED) ASC"
        );

        $distribution = array_fill( 1, self::MAX_LEVEL, 0 );

        foreach ( $results as $row ) {
            $level = (int) $row->level;
            if ( $level >= 1 && $level <= self::MAX_LEVEL ) {
                $distribution[ $level ] = (int) $row->count;
            }
        }

        return $distribution;
    }

    /**
     * Gibt den Level-Up-Bonus zurück
     *
     * @param int $level Neues Level.
     * @return int
     */
    private function get_level_up_bonus( int $level ): int {
        $bonuses = array(
            2  => 50,
            3  => 75,
            4  => 100,
            5  => 150,
            6  => 200,
            7  => 300,
            8  => 500,
            9  => 750,
            10 => 1000,
        );

        return $bonuses[ $level ] ?? 0;
    }

    /**
     * Loggt einen Level-Aufstieg
     *
     * @param int $new_level Neues Level.
     */
    private function log_level_up( int $new_level ): void {
        $history   = $this->get_level_history();
        $history[] = array(
            'level'     => $new_level,
            'timestamp' => current_time( 'mysql' ),
            'points'    => $this->points->get_points(),
        );

        $this->meta->set( 'level_history', $history );
    }

    /**
     * Gibt den Punkt-Multiplikator für das Level zurück
     *
     * @return float
     */
    public function get_level_multiplier(): float {
        $level = $this->get_level();

        $multipliers = array(
            1  => 1.0,
            2  => 1.1,
            3  => 1.15,
            4  => 1.2,
            5  => 1.25,
            6  => 1.3,
            7  => 1.4,
            8  => 1.5,
            9  => 1.6,
            10 => 1.75,
        );

        return $multipliers[ $level ] ?? 1.0;
    }
}
