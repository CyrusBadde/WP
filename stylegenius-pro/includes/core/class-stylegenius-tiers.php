<?php
/**
 * Tiers-Klasse
 *
 * Verwaltet die Mitgliedschaftsstufen und deren Features.
 *
 * @package StyleGenius_Pro
 * @subpackage StyleGenius_Pro/includes/core
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class StyleGenius_Tiers
 */
class StyleGenius_Tiers {

    /**
     * Konstanten für Tier-Namen
     */
    const TIER_FREE    = 'free';
    const TIER_PREMIUM = 'premium';
    const TIER_VIP     = 'vip';

    /**
     * Tier-Hierarchie
     *
     * @var array
     */
    private static array $tier_hierarchy = array(
        'free'    => 0,
        'premium' => 1,
        'vip'     => 2,
    );

    /**
     * Gibt alle Tiers zurück
     *
     * @return array
     */
    public static function get_all_tiers(): array {
        return array(
            self::TIER_FREE    => array(
                'name'        => __( 'Free', 'stylegenius-pro' ),
                'description' => __( 'Kostenloser Einstieg in die Welt des Stylings', 'stylegenius-pro' ),
                'price'       => 0,
                'level'       => 0,
            ),
            self::TIER_PREMIUM => array(
                'name'        => __( 'Premium', 'stylegenius-pro' ),
                'description' => __( 'Erweiterte Styling-Beratung für ambitionierte Professionals', 'stylegenius-pro' ),
                'price'       => 9.90,
                'level'       => 1,
            ),
            self::TIER_VIP     => array(
                'name'        => __( 'VIP', 'stylegenius-pro' ),
                'description' => __( 'Komplette Styling-Suite mit allen Premium-Features', 'stylegenius-pro' ),
                'price'       => 29.90,
                'level'       => 2,
            ),
        );
    }

    /**
     * Prüft ob ein Tier gültig ist
     *
     * @param string $tier Tier-Name.
     * @return bool
     */
    public static function is_valid_tier( string $tier ): bool {
        return isset( self::$tier_hierarchy[ $tier ] );
    }

    /**
     * Gibt das Level eines Tiers zurück
     *
     * @param string $tier Tier-Name.
     * @return int
     */
    public static function get_tier_level( string $tier ): int {
        return self::$tier_hierarchy[ $tier ] ?? 0;
    }

    /**
     * Vergleicht zwei Tiers
     *
     * @param string $tier1 Erstes Tier.
     * @param string $tier2 Zweites Tier.
     * @return int -1 wenn tier1 < tier2, 0 wenn gleich, 1 wenn tier1 > tier2.
     */
    public static function compare_tiers( string $tier1, string $tier2 ): int {
        $level1 = self::get_tier_level( $tier1 );
        $level2 = self::get_tier_level( $tier2 );

        return $level1 <=> $level2;
    }

    /**
     * Gibt die Limits für ein Tier zurück
     *
     * @param string $tier Tier-Name.
     * @return array
     */
    public static function get_tier_limits( string $tier ): array {
        $options = get_option( 'stylegenius_options', array() );

        $limits = array(
            self::TIER_FREE    => array(
                'ai_requests'      => $options['free_tier_limit'] ?? 10,
                'wardrobe_items'   => $options['wardrobe_limit_free'] ?? 20,
                'capsules'         => $options['capsule_limit_free'] ?? 1,
                'before_after'     => 3,
                'shopping_queries' => 0,
            ),
            self::TIER_PREMIUM => array(
                'ai_requests'      => $options['premium_tier_limit'] ?? 100,
                'wardrobe_items'   => $options['wardrobe_limit_premium'] ?? 100,
                'capsules'         => $options['capsule_limit_premium'] ?? 5,
                'before_after'     => 20,
                'shopping_queries' => 10,
            ),
            self::TIER_VIP     => array(
                'ai_requests'      => $options['vip_tier_limit'] ?? -1, // Unbegrenzt
                'wardrobe_items'   => $options['wardrobe_limit_vip'] ?? -1,
                'capsules'         => $options['capsule_limit_vip'] ?? -1,
                'before_after'     => -1,
                'shopping_queries' => -1,
            ),
        );

        return $limits[ $tier ] ?? $limits[ self::TIER_FREE ];
    }

    /**
     * Gibt die Features für ein Tier zurück
     *
     * @param string $tier Tier-Name.
     * @return array
     */
    public static function get_tier_features( string $tier ): array {
        $features = array(
            self::TIER_FREE    => array(
                'quiz'              => true,
                'ai_chat'           => true,
                'ai_analysis'       => true,
                'wardrobe'          => true,
                'color_analysis'    => true,
                'capsule_basic'     => true,
                'badges'            => true,
                'leaderboard'       => true,
                'referral'          => true,
                'sharing'           => true,
                'challenges_view'   => true,
                'challenges_vote'   => false,
                'challenges_enter'  => false,
                'before_after'      => true,
                'shopping'          => false,
                'weekly_reports'    => false,
                'priority_support'  => false,
            ),
            self::TIER_PREMIUM => array(
                'quiz'              => true,
                'ai_chat'           => true,
                'ai_analysis'       => true,
                'wardrobe'          => true,
                'color_analysis'    => true,
                'capsule_basic'     => true,
                'capsule_ai'        => true,
                'badges'            => true,
                'leaderboard'       => true,
                'referral'          => true,
                'sharing'           => true,
                'challenges_view'   => true,
                'challenges_vote'   => true,
                'challenges_enter'  => true,
                'before_after'      => true,
                'shopping'          => false,
                'weekly_reports'    => true,
                'priority_support'  => false,
            ),
            self::TIER_VIP     => array(
                'quiz'              => true,
                'ai_chat'           => true,
                'ai_analysis'       => true,
                'ai_recommendations'=> true,
                'wardrobe'          => true,
                'color_analysis'    => true,
                'capsule_basic'     => true,
                'capsule_ai'        => true,
                'badges'            => true,
                'leaderboard'       => true,
                'referral'          => true,
                'sharing'           => true,
                'challenges_view'   => true,
                'challenges_vote'   => true,
                'challenges_enter'  => true,
                'before_after'      => true,
                'shopping'          => true,
                'weekly_reports'    => true,
                'monthly_reports'   => true,
                'priority_support'  => true,
                'personal_stylist'  => true,
            ),
        );

        return $features[ $tier ] ?? $features[ self::TIER_FREE ];
    }

    /**
     * Prüft ob ein Tier ein Feature hat
     *
     * @param string $tier    Tier-Name.
     * @param string $feature Feature-Name.
     * @return bool
     */
    public static function has_feature( string $tier, string $feature ): bool {
        $features = self::get_tier_features( $tier );
        return $features[ $feature ] ?? false;
    }

    /**
     * Gibt den Namen eines Tiers zurück
     *
     * @param string $tier Tier-Name.
     * @return string
     */
    public static function get_tier_name( string $tier ): string {
        $tiers = self::get_all_tiers();
        return $tiers[ $tier ]['name'] ?? $tier;
    }

    /**
     * Gibt die Beschreibung eines Tiers zurück
     *
     * @param string $tier Tier-Name.
     * @return string
     */
    public static function get_tier_description( string $tier ): string {
        $tiers = self::get_all_tiers();
        return $tiers[ $tier ]['description'] ?? '';
    }

    /**
     * Gibt den Preis eines Tiers zurück
     *
     * @param string $tier   Tier-Name.
     * @param string $period Abrechnungsperiode (monthly/yearly).
     * @return float
     */
    public static function get_tier_price( string $tier, string $period = 'monthly' ): float {
        $options = get_option( 'stylegenius_options', array() );

        $prices = array(
            self::TIER_FREE    => array(
                'monthly' => 0,
                'yearly'  => 0,
            ),
            self::TIER_PREMIUM => array(
                'monthly' => (float) ( $options['premium_price_monthly'] ?? 9.90 ),
                'yearly'  => (float) ( $options['premium_price_yearly'] ?? 99.00 ),
            ),
            self::TIER_VIP     => array(
                'monthly' => (float) ( $options['vip_price_monthly'] ?? 29.90 ),
                'yearly'  => (float) ( $options['vip_price_yearly'] ?? 299.00 ),
            ),
        );

        return $prices[ $tier ][ $period ] ?? 0;
    }

    /**
     * Gibt die WooCommerce Produkt-ID für ein Tier zurück
     *
     * @param string $tier   Tier-Name.
     * @param string $period Abrechnungsperiode.
     * @return int|null
     */
    public static function get_tier_product_id( string $tier, string $period = 'monthly' ): ?int {
        if ( self::TIER_FREE === $tier ) {
            return null;
        }

        $sku = 'stylegenius_' . $tier . '_' . $period;
        $product_id = wc_get_product_id_by_sku( $sku );

        return $product_id ?: null;
    }

    /**
     * Gibt den Upgrade-Pfad für ein Tier zurück
     *
     * @param string $current_tier Aktuelles Tier.
     * @return array
     */
    public static function get_upgrade_path( string $current_tier ): array {
        $current_level = self::get_tier_level( $current_tier );
        $upgrades      = array();

        foreach ( self::$tier_hierarchy as $tier => $level ) {
            if ( $level > $current_level ) {
                $upgrades[] = array(
                    'tier'        => $tier,
                    'name'        => self::get_tier_name( $tier ),
                    'price'       => self::get_tier_price( $tier ),
                    'price_yearly'=> self::get_tier_price( $tier, 'yearly' ),
                    'features'    => self::get_tier_features( $tier ),
                    'limits'      => self::get_tier_limits( $tier ),
                );
            }
        }

        return $upgrades;
    }

    /**
     * Gibt die zusätzlichen Benefits bei einem Upgrade zurück
     *
     * @param string $from_tier Aktuelles Tier.
     * @param string $to_tier   Ziel-Tier.
     * @return array
     */
    public static function get_upgrade_benefits( string $from_tier, string $to_tier ): array {
        $from_features = self::get_tier_features( $from_tier );
        $to_features   = self::get_tier_features( $to_tier );
        $from_limits   = self::get_tier_limits( $from_tier );
        $to_limits     = self::get_tier_limits( $to_tier );

        $benefits = array();

        // Neue Features
        foreach ( $to_features as $feature => $has ) {
            if ( $has && ! ( $from_features[ $feature ] ?? false ) ) {
                $benefits['new_features'][] = self::get_feature_label( $feature );
            }
        }

        // Erhöhte Limits
        foreach ( $to_limits as $limit_key => $limit_value ) {
            $from_limit = $from_limits[ $limit_key ] ?? 0;

            if ( -1 === $limit_value ) {
                $benefits['increased_limits'][] = sprintf(
                    /* translators: %s: Limit name */
                    __( '%s: Unbegrenzt', 'stylegenius-pro' ),
                    self::get_limit_label( $limit_key )
                );
            } elseif ( $limit_value > $from_limit ) {
                $benefits['increased_limits'][] = sprintf(
                    /* translators: 1: Limit name, 2: Old limit, 3: New limit */
                    __( '%1$s: %2$d → %3$d', 'stylegenius-pro' ),
                    self::get_limit_label( $limit_key ),
                    $from_limit,
                    $limit_value
                );
            }
        }

        return $benefits;
    }

    /**
     * Gibt das benötigte Tier für ein Feature zurück
     *
     * @param string $feature Feature-Name.
     * @return string
     */
    public static function get_required_tier_for_feature( string $feature ): string {
        // Von niedrigsten zum höchsten prüfen
        foreach ( array( self::TIER_FREE, self::TIER_PREMIUM, self::TIER_VIP ) as $tier ) {
            if ( self::has_feature( $tier, $feature ) ) {
                return $tier;
            }
        }

        return self::TIER_VIP;
    }

    /**
     * Upgraded einen Benutzer
     *
     * @param int    $user_id  Benutzer-ID.
     * @param string $new_tier Neues Tier.
     * @return bool
     */
    public function upgrade_user( int $user_id, string $new_tier ): bool {
        $user = new StyleGenius_User( $user_id );
        $old_tier = $user->get_tier();

        if ( self::compare_tiers( $new_tier, $old_tier ) <= 0 ) {
            return false;
        }

        $result = $user->set_tier( $new_tier );

        if ( $result ) {
            /**
             * Fires when a user is upgraded.
             *
             * @param int    $user_id  User ID.
             * @param string $new_tier New tier.
             * @param string $old_tier Old tier.
             */
            do_action( 'stylegenius_user_upgraded', $user_id, $new_tier, $old_tier );

            // Premium-Badge vergeben
            $badges = new StyleGenius_Badges( $user_id );
            if ( self::TIER_PREMIUM === $new_tier ) {
                $badges->award_badge( 'premium_member' );
            } elseif ( self::TIER_VIP === $new_tier ) {
                $badges->award_badge( 'vip_member' );
            }
        }

        return $result;
    }

    /**
     * Downgraded einen Benutzer
     *
     * @param int    $user_id  Benutzer-ID.
     * @param string $new_tier Neues Tier.
     * @return bool
     */
    public function downgrade_user( int $user_id, string $new_tier ): bool {
        $user = new StyleGenius_User( $user_id );
        $old_tier = $user->get_tier();

        if ( self::compare_tiers( $new_tier, $old_tier ) >= 0 ) {
            return false;
        }

        $result = $user->set_tier( $new_tier );

        if ( $result ) {
            /**
             * Fires when a user is downgraded.
             *
             * @param int    $user_id  User ID.
             * @param string $new_tier New tier.
             * @param string $old_tier Old tier.
             */
            do_action( 'stylegenius_user_downgraded', $user_id, $new_tier, $old_tier );
        }

        return $result;
    }

    /**
     * Synchronisiert den Tier mit einer Subscription
     *
     * @param int $user_id Benutzer-ID.
     */
    public function sync_with_subscription( int $user_id ): void {
        if ( ! class_exists( 'WC_Subscriptions' ) ) {
            return;
        }

        $wc = new StyleGenius_WooCommerce();
        $subscription = $wc->get_user_subscription( $user_id );

        if ( $subscription && $subscription->has_status( 'active' ) ) {
            $tier = $wc->get_subscription_tier( $subscription );
            if ( $tier ) {
                $user = new StyleGenius_User( $user_id );
                $user->set_tier( $tier );
            }
        } else {
            // Keine aktive Subscription = Free
            $user = new StyleGenius_User( $user_id );
            $user->set_tier( self::TIER_FREE );
        }
    }

    /**
     * Gibt das Label für ein Feature zurück
     *
     * @param string $feature Feature-Name.
     * @return string
     */
    private static function get_feature_label( string $feature ): string {
        $labels = array(
            'quiz'               => __( 'Style-Quiz', 'stylegenius-pro' ),
            'ai_chat'            => __( 'KI-Beratung', 'stylegenius-pro' ),
            'ai_analysis'        => __( 'Foto-Analyse', 'stylegenius-pro' ),
            'ai_recommendations' => __( 'KI-Empfehlungen', 'stylegenius-pro' ),
            'wardrobe'           => __( 'Virtuelle Garderobe', 'stylegenius-pro' ),
            'color_analysis'     => __( 'Farbtyp-Analyse', 'stylegenius-pro' ),
            'capsule_basic'      => __( 'Capsule Wardrobes', 'stylegenius-pro' ),
            'capsule_ai'         => __( 'KI-generierte Capsules', 'stylegenius-pro' ),
            'badges'             => __( 'Badges & Erfolge', 'stylegenius-pro' ),
            'leaderboard'        => __( 'Leaderboard', 'stylegenius-pro' ),
            'referral'           => __( 'Empfehlungsprogramm', 'stylegenius-pro' ),
            'sharing'            => __( 'Social Sharing', 'stylegenius-pro' ),
            'challenges_view'    => __( 'Challenges ansehen', 'stylegenius-pro' ),
            'challenges_vote'    => __( 'Bei Challenges abstimmen', 'stylegenius-pro' ),
            'challenges_enter'   => __( 'An Challenges teilnehmen', 'stylegenius-pro' ),
            'before_after'       => __( 'Outfit-Bewertung', 'stylegenius-pro' ),
            'shopping'           => __( 'Shopping-Assistent', 'stylegenius-pro' ),
            'weekly_reports'     => __( 'Wöchentliche Reports', 'stylegenius-pro' ),
            'monthly_reports'    => __( 'Monatliche Reports', 'stylegenius-pro' ),
            'priority_support'   => __( 'Prioritäts-Support', 'stylegenius-pro' ),
            'personal_stylist'   => __( 'Persönlicher Stylist', 'stylegenius-pro' ),
        );

        return $labels[ $feature ] ?? $feature;
    }

    /**
     * Gibt das Label für ein Limit zurück
     *
     * @param string $limit_key Limit-Schlüssel.
     * @return string
     */
    private static function get_limit_label( string $limit_key ): string {
        $labels = array(
            'ai_requests'      => __( 'KI-Anfragen pro Monat', 'stylegenius-pro' ),
            'wardrobe_items'   => __( 'Garderobe-Einträge', 'stylegenius-pro' ),
            'capsules'         => __( 'Capsule Wardrobes', 'stylegenius-pro' ),
            'before_after'     => __( 'Outfit-Analysen pro Monat', 'stylegenius-pro' ),
            'shopping_queries' => __( 'Shopping-Suchen pro Monat', 'stylegenius-pro' ),
        );

        return $labels[ $limit_key ] ?? $limit_key;
    }
}
