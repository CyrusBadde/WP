<?php
/**
 * Achievements-Klasse
 *
 * Verwaltet das Achievement/Badge-System.
 *
 * @package StyleGenius_Pro
 * @subpackage StyleGenius_Pro/includes/gamification
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class StyleGenius_Achievements
 */
class StyleGenius_Achievements {

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
     * Gibt alle verfügbaren Achievements zurück
     *
     * @return array
     */
    public function get_all_achievements(): array {
        return array(
            // Onboarding (5)
            'first_login'       => array(
                'name'        => __( 'Willkommen!', 'stylegenius-pro' ),
                'description' => __( 'Dein erstes Login bei StyleGenius', 'stylegenius-pro' ),
                'category'    => 'onboarding',
                'points'      => 10,
                'icon'        => 'welcome',
                'secret'      => false,
            ),
            'profile_complete'  => array(
                'name'        => __( 'Profil-Profi', 'stylegenius-pro' ),
                'description' => __( 'Vervollständige dein Profil zu 100%', 'stylegenius-pro' ),
                'category'    => 'onboarding',
                'points'      => 50,
                'icon'        => 'profile',
                'secret'      => false,
            ),
            'quiz_complete'     => array(
                'name'        => __( 'Style-Entdecker', 'stylegenius-pro' ),
                'description' => __( 'Schließe das Style-Quiz ab', 'stylegenius-pro' ),
                'category'    => 'onboarding',
                'points'      => 100,
                'icon'        => 'quiz',
                'secret'      => false,
            ),
            'first_upload'      => array(
                'name'        => __( 'Erster Schnappschuss', 'stylegenius-pro' ),
                'description' => __( 'Lade dein erstes Foto hoch', 'stylegenius-pro' ),
                'category'    => 'onboarding',
                'points'      => 25,
                'icon'        => 'camera',
                'secret'      => false,
            ),
            'first_consultation'=> array(
                'name'        => __( 'Beratungs-Neuling', 'stylegenius-pro' ),
                'description' => __( 'Führe deine erste KI-Beratung durch', 'stylegenius-pro' ),
                'category'    => 'onboarding',
                'points'      => 50,
                'icon'        => 'chat',
                'secret'      => false,
            ),

            // Style-Typ (6)
            'style_power'       => array(
                'name'        => __( 'Power Executive', 'stylegenius-pro' ),
                'description' => __( 'Dein Style-Typ: Power Executive', 'stylegenius-pro' ),
                'category'    => 'style_type',
                'points'      => 0,
                'icon'        => 'power',
                'secret'      => false,
            ),
            'style_creative'    => array(
                'name'        => __( 'Creative Professional', 'stylegenius-pro' ),
                'description' => __( 'Dein Style-Typ: Creative Professional', 'stylegenius-pro' ),
                'category'    => 'style_type',
                'points'      => 0,
                'icon'        => 'creative',
                'secret'      => false,
            ),
            'style_classic'     => array(
                'name'        => __( 'Classic Traditionalist', 'stylegenius-pro' ),
                'description' => __( 'Dein Style-Typ: Classic Traditionalist', 'stylegenius-pro' ),
                'category'    => 'style_type',
                'points'      => 0,
                'icon'        => 'classic',
                'secret'      => false,
            ),
            'style_minimal'     => array(
                'name'        => __( 'Modern Minimalist', 'stylegenius-pro' ),
                'description' => __( 'Dein Style-Typ: Modern Minimalist', 'stylegenius-pro' ),
                'category'    => 'style_type',
                'points'      => 0,
                'icon'        => 'minimal',
                'secret'      => false,
            ),
            'style_smart'       => array(
                'name'        => __( 'Smart Casual Expert', 'stylegenius-pro' ),
                'description' => __( 'Dein Style-Typ: Smart Casual Expert', 'stylegenius-pro' ),
                'category'    => 'style_type',
                'points'      => 0,
                'icon'        => 'smart',
                'secret'      => false,
            ),
            'style_trend'       => array(
                'name'        => __( 'Trendsetter', 'stylegenius-pro' ),
                'description' => __( 'Dein Style-Typ: Trendsetter', 'stylegenius-pro' ),
                'category'    => 'style_type',
                'points'      => 0,
                'icon'        => 'trend',
                'secret'      => false,
            ),

            // Farbtyp (4)
            'color_spring'      => array(
                'name'        => __( 'Frühlingstyp', 'stylegenius-pro' ),
                'description' => __( 'Dein Farbtyp: Frühling', 'stylegenius-pro' ),
                'category'    => 'color_type',
                'points'      => 0,
                'icon'        => 'spring',
                'secret'      => false,
            ),
            'color_summer'      => array(
                'name'        => __( 'Sommertyp', 'stylegenius-pro' ),
                'description' => __( 'Dein Farbtyp: Sommer', 'stylegenius-pro' ),
                'category'    => 'color_type',
                'points'      => 0,
                'icon'        => 'summer',
                'secret'      => false,
            ),
            'color_autumn'      => array(
                'name'        => __( 'Herbsttyp', 'stylegenius-pro' ),
                'description' => __( 'Dein Farbtyp: Herbst', 'stylegenius-pro' ),
                'category'    => 'color_type',
                'points'      => 0,
                'icon'        => 'autumn',
                'secret'      => false,
            ),
            'color_winter'      => array(
                'name'        => __( 'Wintertyp', 'stylegenius-pro' ),
                'description' => __( 'Dein Farbtyp: Winter', 'stylegenius-pro' ),
                'category'    => 'color_type',
                'points'      => 0,
                'icon'        => 'winter',
                'secret'      => false,
            ),

            // Garderobe (5)
            'wardrobe_10'       => array(
                'name'        => __( 'Garderobe-Starter', 'stylegenius-pro' ),
                'description' => __( 'Füge 10 Teile zu deiner Garderobe hinzu', 'stylegenius-pro' ),
                'category'    => 'wardrobe',
                'points'      => 50,
                'icon'        => 'wardrobe',
                'secret'      => false,
                'requirement' => array( 'type' => 'wardrobe_count', 'value' => 10 ),
            ),
            'wardrobe_25'       => array(
                'name'        => __( 'Garderobe-Builder', 'stylegenius-pro' ),
                'description' => __( 'Füge 25 Teile zu deiner Garderobe hinzu', 'stylegenius-pro' ),
                'category'    => 'wardrobe',
                'points'      => 100,
                'icon'        => 'wardrobe',
                'secret'      => false,
                'requirement' => array( 'type' => 'wardrobe_count', 'value' => 25 ),
            ),
            'wardrobe_50'       => array(
                'name'        => __( 'Garderobe-Master', 'stylegenius-pro' ),
                'description' => __( 'Füge 50 Teile zu deiner Garderobe hinzu', 'stylegenius-pro' ),
                'category'    => 'wardrobe',
                'points'      => 200,
                'icon'        => 'wardrobe',
                'secret'      => false,
                'requirement' => array( 'type' => 'wardrobe_count', 'value' => 50 ),
            ),
            'wardrobe_100'      => array(
                'name'        => __( 'Fashion-Sammler', 'stylegenius-pro' ),
                'description' => __( 'Füge 100 Teile zu deiner Garderobe hinzu', 'stylegenius-pro' ),
                'category'    => 'wardrobe',
                'points'      => 500,
                'icon'        => 'wardrobe-gold',
                'secret'      => false,
                'requirement' => array( 'type' => 'wardrobe_count', 'value' => 100 ),
            ),
            'capsule_creator'   => array(
                'name'        => __( 'Capsule-Creator', 'stylegenius-pro' ),
                'description' => __( 'Erstelle deine erste Capsule Wardrobe', 'stylegenius-pro' ),
                'category'    => 'wardrobe',
                'points'      => 75,
                'icon'        => 'capsule',
                'secret'      => false,
            ),

            // Beratung (5)
            'consultations_10'  => array(
                'name'        => __( 'Beratungs-Fan', 'stylegenius-pro' ),
                'description' => __( 'Führe 10 KI-Beratungen durch', 'stylegenius-pro' ),
                'category'    => 'consultation',
                'points'      => 100,
                'icon'        => 'chat',
                'secret'      => false,
                'requirement' => array( 'type' => 'consultation_count', 'value' => 10 ),
            ),
            'consultations_50'  => array(
                'name'        => __( 'Beratungs-Profi', 'stylegenius-pro' ),
                'description' => __( 'Führe 50 KI-Beratungen durch', 'stylegenius-pro' ),
                'category'    => 'consultation',
                'points'      => 250,
                'icon'        => 'chat-gold',
                'secret'      => false,
                'requirement' => array( 'type' => 'consultation_count', 'value' => 50 ),
            ),
            'outfit_score_90'   => array(
                'name'        => __( 'Outfit-Perfektionist', 'stylegenius-pro' ),
                'description' => __( 'Erreiche einen Outfit-Score von 90+', 'stylegenius-pro' ),
                'category'    => 'consultation',
                'points'      => 150,
                'icon'        => 'star',
                'secret'      => false,
            ),
            'color_analyzed'    => array(
                'name'        => __( 'Farb-Experte', 'stylegenius-pro' ),
                'description' => __( 'Lasse deinen Farbtyp analysieren', 'stylegenius-pro' ),
                'category'    => 'consultation',
                'points'      => 75,
                'icon'        => 'palette',
                'secret'      => false,
            ),
            'before_after'      => array(
                'name'        => __( 'Transformation', 'stylegenius-pro' ),
                'description' => __( 'Erstelle deine erste Before/After Analyse', 'stylegenius-pro' ),
                'category'    => 'consultation',
                'points'      => 100,
                'icon'        => 'transform',
                'secret'      => false,
            ),

            // Challenges (5)
            'challenge_first'   => array(
                'name'        => __( 'Challenge-Starter', 'stylegenius-pro' ),
                'description' => __( 'Nimm an deiner ersten Challenge teil', 'stylegenius-pro' ),
                'category'    => 'challenges',
                'points'      => 50,
                'icon'        => 'trophy',
                'secret'      => false,
            ),
            'challenge_5'       => array(
                'name'        => __( 'Challenge-Enthusiast', 'stylegenius-pro' ),
                'description' => __( 'Nimm an 5 Challenges teil', 'stylegenius-pro' ),
                'category'    => 'challenges',
                'points'      => 150,
                'icon'        => 'trophy',
                'secret'      => false,
                'requirement' => array( 'type' => 'challenge_count', 'value' => 5 ),
            ),
            'challenge_win'     => array(
                'name'        => __( 'Champion', 'stylegenius-pro' ),
                'description' => __( 'Gewinne eine Challenge', 'stylegenius-pro' ),
                'category'    => 'challenges',
                'points'      => 300,
                'icon'        => 'trophy-gold',
                'secret'      => false,
            ),
            'challenge_win_3'   => array(
                'name'        => __( 'Triple-Champion', 'stylegenius-pro' ),
                'description' => __( 'Gewinne 3 Challenges', 'stylegenius-pro' ),
                'category'    => 'challenges',
                'points'      => 500,
                'icon'        => 'crown',
                'secret'      => false,
                'requirement' => array( 'type' => 'challenge_wins', 'value' => 3 ),
            ),
            'voter_50'          => array(
                'name'        => __( 'Community-Supporter', 'stylegenius-pro' ),
                'description' => __( 'Stimme für 50 Challenge-Einträge ab', 'stylegenius-pro' ),
                'category'    => 'challenges',
                'points'      => 100,
                'icon'        => 'vote',
                'secret'      => false,
                'requirement' => array( 'type' => 'votes_cast', 'value' => 50 ),
            ),

            // Social (5)
            'first_share'       => array(
                'name'        => __( 'Social Butterfly', 'stylegenius-pro' ),
                'description' => __( 'Teile zum ersten Mal etwas', 'stylegenius-pro' ),
                'category'    => 'social',
                'points'      => 25,
                'icon'        => 'share',
                'secret'      => false,
            ),
            'shares_10'         => array(
                'name'        => __( 'Influencer', 'stylegenius-pro' ),
                'description' => __( 'Teile 10 Mal etwas', 'stylegenius-pro' ),
                'category'    => 'social',
                'points'      => 100,
                'icon'        => 'share-gold',
                'secret'      => false,
                'requirement' => array( 'type' => 'share_count', 'value' => 10 ),
            ),
            'referral_first'    => array(
                'name'        => __( 'Empfehlungs-Star', 'stylegenius-pro' ),
                'description' => __( 'Empfehle deinen ersten Freund', 'stylegenius-pro' ),
                'category'    => 'social',
                'points'      => 100,
                'icon'        => 'gift',
                'secret'      => false,
            ),
            'referral_5'        => array(
                'name'        => __( 'Networking-Pro', 'stylegenius-pro' ),
                'description' => __( 'Empfehle 5 Freunde', 'stylegenius-pro' ),
                'category'    => 'social',
                'points'      => 300,
                'icon'        => 'network',
                'secret'      => false,
                'requirement' => array( 'type' => 'referral_count', 'value' => 5 ),
            ),
            'referral_10'       => array(
                'name'        => __( 'Style-Ambassador', 'stylegenius-pro' ),
                'description' => __( 'Empfehle 10 Freunde', 'stylegenius-pro' ),
                'category'    => 'social',
                'points'      => 500,
                'icon'        => 'ambassador',
                'secret'      => false,
                'requirement' => array( 'type' => 'referral_count', 'value' => 10 ),
            ),

            // Streaks (4)
            'streak_7'          => array(
                'name'        => __( 'Wochenkrieger', 'stylegenius-pro' ),
                'description' => __( '7 Tage Streak', 'stylegenius-pro' ),
                'category'    => 'streaks',
                'points'      => 100,
                'icon'        => 'flame',
                'secret'      => false,
            ),
            'streak_30'         => array(
                'name'        => __( 'Monatsmeister', 'stylegenius-pro' ),
                'description' => __( '30 Tage Streak', 'stylegenius-pro' ),
                'category'    => 'streaks',
                'points'      => 300,
                'icon'        => 'flame-gold',
                'secret'      => false,
            ),
            'streak_100'        => array(
                'name'        => __( 'Unaufhaltsam', 'stylegenius-pro' ),
                'description' => __( '100 Tage Streak', 'stylegenius-pro' ),
                'category'    => 'streaks',
                'points'      => 750,
                'icon'        => 'flame-platinum',
                'secret'      => false,
            ),
            'streak_365'        => array(
                'name'        => __( 'Legendäre Hingabe', 'stylegenius-pro' ),
                'description' => __( '365 Tage Streak - Ein ganzes Jahr!', 'stylegenius-pro' ),
                'category'    => 'streaks',
                'points'      => 2000,
                'icon'        => 'diamond',
                'secret'      => false,
            ),

            // Premium (3)
            'premium_member'    => array(
                'name'        => __( 'Premium-Mitglied', 'stylegenius-pro' ),
                'description' => __( 'Werde Premium-Mitglied', 'stylegenius-pro' ),
                'category'    => 'premium',
                'points'      => 100,
                'icon'        => 'premium',
                'secret'      => false,
            ),
            'vip_member'        => array(
                'name'        => __( 'VIP-Status', 'stylegenius-pro' ),
                'description' => __( 'Werde VIP-Mitglied', 'stylegenius-pro' ),
                'category'    => 'premium',
                'points'      => 250,
                'icon'        => 'vip',
                'secret'      => false,
            ),
            'loyal_member'      => array(
                'name'        => __( 'Treues Mitglied', 'stylegenius-pro' ),
                'description' => __( 'Sei 1 Jahr lang Mitglied', 'stylegenius-pro' ),
                'category'    => 'premium',
                'points'      => 500,
                'icon'        => 'heart',
                'secret'      => false,
            ),

            // Spezial (5)
            'early_adopter'     => array(
                'name'        => __( 'Early Adopter', 'stylegenius-pro' ),
                'description' => __( 'Einer der ersten 100 Nutzer', 'stylegenius-pro' ),
                'category'    => 'special',
                'points'      => 500,
                'icon'        => 'rocket',
                'secret'      => true,
            ),
            'points_1000'       => array(
                'name'        => __( 'Punkte-Sammler', 'stylegenius-pro' ),
                'description' => __( 'Erreiche 1.000 Punkte', 'stylegenius-pro' ),
                'category'    => 'special',
                'points'      => 100,
                'icon'        => 'points',
                'secret'      => false,
                'requirement' => array( 'type' => 'points', 'value' => 1000 ),
            ),
            'points_10000'      => array(
                'name'        => __( 'Punkte-König', 'stylegenius-pro' ),
                'description' => __( 'Erreiche 10.000 Punkte', 'stylegenius-pro' ),
                'category'    => 'special',
                'points'      => 500,
                'icon'        => 'points-gold',
                'secret'      => false,
                'requirement' => array( 'type' => 'points', 'value' => 10000 ),
            ),
            'night_owl'         => array(
                'name'        => __( 'Nachteuele', 'stylegenius-pro' ),
                'description' => __( 'Nutze StyleGenius nach Mitternacht', 'stylegenius-pro' ),
                'category'    => 'special',
                'points'      => 25,
                'icon'        => 'owl',
                'secret'      => true,
            ),
            'completionist'     => array(
                'name'        => __( 'Alles erledigt!', 'stylegenius-pro' ),
                'description' => __( 'Verdiene alle nicht-geheimen Badges', 'stylegenius-pro' ),
                'category'    => 'special',
                'points'      => 1000,
                'icon'        => 'crown-diamond',
                'secret'      => true,
            ),
        );
    }

    /**
     * Gibt Achievements nach Kategorie zurück
     *
     * @param string $category Kategorie.
     * @return array
     */
    public function get_achievements_by_category( string $category ): array {
        $all = $this->get_all_achievements();

        return array_filter( $all, fn( $a ) => $a['category'] === $category );
    }

    /**
     * Gibt die vom Benutzer verdienten Achievements zurück
     *
     * @return array
     */
    public function get_user_achievements(): array {
        global $wpdb;

        $table = $this->db->get_table_name( 'achievements' );

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT achievement_id, earned_at FROM {$table} WHERE user_id = %d ORDER BY earned_at DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $this->user_id
            )
        );

        $all     = $this->get_all_achievements();
        $earned  = array();

        foreach ( $results as $row ) {
            if ( isset( $all[ $row->achievement_id ] ) ) {
                $earned[ $row->achievement_id ] = array_merge(
                    $all[ $row->achievement_id ],
                    array(
                        'id'        => $row->achievement_id,
                        'earned_at' => $row->earned_at,
                    )
                );
            }
        }

        return $earned;
    }

    /**
     * Prüft ob ein Achievement verdient wurde
     *
     * @param string $achievement_id Achievement-ID.
     * @return bool
     */
    public function has_achievement( string $achievement_id ): bool {
        $table = $this->db->get_table_name( 'achievements' );

        return $this->db->get_count( 'achievements', array(
            'user_id'        => $this->user_id,
            'achievement_id' => $achievement_id,
        ) ) > 0;
    }

    /**
     * Gibt ein einzelnes Achievement zurück
     *
     * @param string $achievement_id Achievement-ID.
     * @return array|null
     */
    public function get_achievement( string $achievement_id ): ?array {
        $all = $this->get_all_achievements();
        return $all[ $achievement_id ] ?? null;
    }

    /**
     * Prüft ob ein Achievement verdient werden kann
     *
     * @param string $achievement_id Achievement-ID.
     * @return bool
     */
    public function check_achievement( string $achievement_id ): bool {
        if ( $this->has_achievement( $achievement_id ) ) {
            return false;
        }

        $achievement = $this->get_achievement( $achievement_id );
        if ( ! $achievement ) {
            return false;
        }

        // Wenn keine Requirement, muss manuell vergeben werden
        if ( empty( $achievement['requirement'] ) ) {
            return false;
        }

        $result = $this->evaluate_requirement( $achievement['requirement'] );
        return $result['met'] ?? false;
    }

    /**
     * Vergibt ein Achievement
     *
     * @param string $achievement_id Achievement-ID.
     * @return bool
     */
    public function award_achievement( string $achievement_id ): bool {
        if ( $this->has_achievement( $achievement_id ) ) {
            return false;
        }

        $achievement = $this->get_achievement( $achievement_id );
        if ( ! $achievement ) {
            return false;
        }

        // In DB speichern
        $result = $this->db->insert(
            'achievements',
            array(
                'user_id'        => $this->user_id,
                'achievement_id' => $achievement_id,
                'earned_at'      => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s' )
        );

        if ( false === $result ) {
            return false;
        }

        // Punkte vergeben
        if ( ! empty( $achievement['points'] ) ) {
            $points = new StyleGenius_Points( $this->user_id );
            $points->award_points( 'badge_earned', $achievement['points'] );
        }

        /**
         * Fires when an achievement is earned.
         *
         * @param int    $user_id        User ID.
         * @param string $achievement_id Achievement ID.
         * @param array  $achievement    Achievement data.
         */
        do_action( 'stylegenius_achievement_earned', $this->user_id, $achievement_id, $achievement );

        return true;
    }

    /**
     * Gibt den Fortschritt für ein Achievement zurück
     *
     * @param string $achievement_id Achievement-ID.
     * @return array
     */
    public function get_achievement_progress( string $achievement_id ): array {
        $achievement = $this->get_achievement( $achievement_id );

        if ( ! $achievement || empty( $achievement['requirement'] ) ) {
            return array(
                'current'    => 0,
                'target'     => 0,
                'percentage' => 0,
            );
        }

        return $this->evaluate_requirement( $achievement['requirement'] );
    }

    /**
     * Gibt die nächsten erreichbaren Achievements zurück
     *
     * @param int $limit Limit.
     * @return array
     */
    public function get_next_achievements( int $limit = 5 ): array {
        $all    = $this->get_all_achievements();
        $earned = array_keys( $this->get_user_achievements() );
        $next   = array();

        foreach ( $all as $id => $achievement ) {
            // Bereits verdient überspringen
            if ( in_array( $id, $earned, true ) ) {
                continue;
            }

            // Geheime überspringen
            if ( ! empty( $achievement['secret'] ) ) {
                continue;
            }

            $progress = $this->get_achievement_progress( $id );

            $next[] = array_merge(
                array( 'id' => $id ),
                $achievement,
                array( 'progress' => $progress )
            );
        }

        // Nach Fortschritt sortieren
        usort( $next, fn( $a, $b ) => $b['progress']['percentage'] <=> $a['progress']['percentage'] );

        return array_slice( $next, 0, $limit );
    }

    /**
     * Gibt die Anforderungen für ein Achievement zurück
     *
     * @param string $achievement_id Achievement-ID.
     * @return array
     */
    public function get_achievement_requirements( string $achievement_id ): array {
        $achievement = $this->get_achievement( $achievement_id );
        return $achievement['requirement'] ?? array();
    }

    /**
     * Prüft ob ein Achievement geheim ist
     *
     * @param string $achievement_id Achievement-ID.
     * @return bool
     */
    public function is_achievement_secret( string $achievement_id ): bool {
        $achievement = $this->get_achievement( $achievement_id );
        return ! empty( $achievement['secret'] );
    }

    /**
     * Gibt die Seltenheit eines Achievements zurück
     *
     * @param string $achievement_id Achievement-ID.
     * @return float Prozent der Nutzer, die es haben.
     */
    public function get_achievement_rarity( string $achievement_id ): float {
        global $wpdb;

        $table       = $this->db->get_table_name( 'achievements' );
        $total_users = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users}" );

        if ( $total_users === 0 ) {
            return 100;
        }

        $has_achievement = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT user_id) FROM {$table} WHERE achievement_id = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $achievement_id
            )
        );

        return round( ( $has_achievement / $total_users ) * 100, 1 );
    }

    /**
     * Gibt die Punkte für ein Achievement zurück
     *
     * @param string $achievement_id Achievement-ID.
     * @return int
     */
    public function get_achievement_points( string $achievement_id ): int {
        $achievement = $this->get_achievement( $achievement_id );
        return $achievement['points'] ?? 0;
    }

    /**
     * Löst Achievement-Prüfung für eine Aktion aus
     *
     * @param string $action Aktion.
     * @return array Verdiente Achievements.
     */
    public function trigger_check( string $action ): array {
        $earned = array();
        $all    = $this->get_all_achievements();

        foreach ( $all as $id => $achievement ) {
            if ( $this->has_achievement( $id ) ) {
                continue;
            }

            if ( empty( $achievement['requirement'] ) ) {
                continue;
            }

            if ( $this->check_achievement( $id ) ) {
                if ( $this->award_achievement( $id ) ) {
                    $earned[] = $id;
                }
            }
        }

        return $earned;
    }

    /**
     * Gibt den Vervollständigungsgrad zurück
     *
     * @return float
     */
    public function get_completion_percentage(): float {
        $all    = $this->get_all_achievements();
        $earned = $this->get_user_achievements();

        // Geheime nicht mitzählen
        $countable = array_filter( $all, fn( $a ) => empty( $a['secret'] ) );

        if ( empty( $countable ) ) {
            return 100;
        }

        $earned_countable = array_filter( $earned, fn( $a ) => empty( $a['secret'] ) );

        return round( ( count( $earned_countable ) / count( $countable ) ) * 100, 1 );
    }

    /**
     * Gibt Achievements nach Seltenheit zurück
     *
     * @param string $rarity Seltenheit (common, uncommon, rare, epic, legendary).
     * @return array
     */
    public function get_achievements_by_rarity( string $rarity ): array {
        $all      = $this->get_all_achievements();
        $filtered = array();

        foreach ( $all as $id => $achievement ) {
            $rarity_percent = $this->get_achievement_rarity( $id );

            $achievement_rarity = 'common';
            if ( $rarity_percent < 50 ) {
                $achievement_rarity = 'uncommon';
            }
            if ( $rarity_percent < 25 ) {
                $achievement_rarity = 'rare';
            }
            if ( $rarity_percent < 10 ) {
                $achievement_rarity = 'epic';
            }
            if ( $rarity_percent < 1 ) {
                $achievement_rarity = 'legendary';
            }

            if ( $achievement_rarity === $rarity ) {
                $filtered[ $id ] = $achievement;
            }
        }

        return $filtered;
    }

    /**
     * Wertet eine Anforderung aus
     *
     * @param array $requirement Anforderung.
     * @return array
     */
    private function evaluate_requirement( array $requirement ): array {
        $type   = $requirement['type'] ?? '';
        $target = $requirement['value'] ?? 0;

        $current = 0;

        switch ( $type ) {
            case 'wardrobe_count':
                $current = $this->db->get_count( 'wardrobe', array( 'user_id' => $this->user_id ) );
                break;

            case 'consultation_count':
                $current = $this->db->get_count( 'chat_history', array(
                    'user_id' => $this->user_id,
                    'role'    => 'user',
                ) );
                break;

            case 'challenge_count':
                $current = $this->db->get_count( 'challenge_entries', array( 'user_id' => $this->user_id ) );
                break;

            case 'challenge_wins':
                $current = $this->db->get_count( 'challenge_entries', array(
                    'user_id'   => $this->user_id,
                    'is_winner' => 1,
                ) );
                break;

            case 'share_count':
                $current = $this->db->get_count( 'shares', array( 'user_id' => $this->user_id ) );
                break;

            case 'referral_count':
                $current = (int) ( new StyleGenius_Meta( $this->user_id ) )->get( 'referral_count', 0 );
                break;

            case 'points':
                $current = ( new StyleGenius_Points( $this->user_id ) )->get_points();
                break;

            case 'votes_cast':
                $current = $this->db->get_count( 'challenge_votes', array( 'user_id' => $this->user_id ) );
                break;

            default:
                $current = 0;
        }

        $percentage = $target > 0 ? min( 100, ( $current / $target ) * 100 ) : 100;

        return array(
            'current'    => $current,
            'target'     => $target,
            'percentage' => round( $percentage, 1 ),
            'met'        => $current >= $target,
        );
    }
}
