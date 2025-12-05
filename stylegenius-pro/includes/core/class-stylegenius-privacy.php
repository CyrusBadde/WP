<?php
/**
 * Privacy-Klasse
 *
 * DSGVO-konforme Datenschutz-Funktionen.
 *
 * @package StyleGenius_Pro
 * @subpackage StyleGenius_Pro/includes/core
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class StyleGenius_Privacy
 */
class StyleGenius_Privacy {

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
        $this->meta    = new StyleGenius_Meta( $user_id );
    }

    /**
     * Gibt alle Datenschutz-Einstellungen zurück
     *
     * @return array
     */
    public function get_settings(): array {
        $defaults = array(
            'profile_public'     => false,
            'show_in_leaderboard'=> true,
            'show_badges'        => true,
            'show_level'         => true,
            'share_style_type'   => false,
            'share_wardrobe'     => false,
            'share_color_palette'=> false,
            'email_weekly_report'=> true,
            'email_challenges'   => true,
            'email_achievements' => true,
            'email_marketing'    => false,
        );

        $settings = $this->meta->get( 'privacy_settings', array() );

        return wp_parse_args( $settings, $defaults );
    }

    /**
     * Gibt eine einzelne Datenschutz-Einstellung zurück
     *
     * @param string $field Feldname.
     * @return bool
     */
    public function get_setting( string $field ): bool {
        $settings = $this->get_settings();
        return (bool) ( $settings[ $field ] ?? false );
    }

    /**
     * Aktualisiert eine Datenschutz-Einstellung
     *
     * @param string $field     Feldname.
     * @param bool   $is_public Öffentlich/aktiviert?
     * @return bool
     */
    public function update_setting( string $field, bool $is_public ): bool {
        $settings           = $this->get_settings();
        $settings[ $field ] = $is_public;

        return $this->meta->set( 'privacy_settings', $settings );
    }

    /**
     * Aktualisiert mehrere Datenschutz-Einstellungen
     *
     * @param array $settings Array von Einstellungen.
     * @return bool
     */
    public function update_settings( array $settings ): bool {
        $current  = $this->get_settings();
        $updated  = array();

        foreach ( $current as $key => $value ) {
            $updated[ $key ] = isset( $settings[ $key ] ) ? (bool) $settings[ $key ] : $value;
        }

        return $this->meta->set( 'privacy_settings', $updated );
    }

    /**
     * Prüft ob ein Feld öffentlich ist
     *
     * @param string $field Feldname.
     * @return bool
     */
    public function is_field_public( string $field ): bool {
        return $this->get_setting( $field );
    }

    /**
     * Gibt öffentliche Profildaten zurück
     *
     * @return array
     */
    public function get_public_profile_data(): array {
        $settings = $this->get_settings();
        $user     = new StyleGenius_User( $this->user_id );
        $data     = array();

        // Immer öffentlich (wenn profile_public)
        if ( $settings['profile_public'] ) {
            $data['display_name'] = $user->get_display_name();
            $data['avatar']       = $user->get_avatar_url();
        }

        if ( $settings['show_level'] ) {
            $data['level'] = $this->meta->get( 'level', 1 );
            $levels        = new StyleGenius_Levels( $this->user_id );
            $data['level_name'] = $levels->get_level_name();
        }

        if ( $settings['show_badges'] ) {
            $badges        = new StyleGenius_Badges( $this->user_id );
            $data['badges'] = $badges->get_visible_badges();
        }

        if ( $settings['share_style_type'] ) {
            $data['style_type'] = $user->get_style_type();
        }

        return $data;
    }

    /**
     * Prüft ob ein Viewer ein Feld sehen darf
     *
     * @param int    $viewer_id Viewer-ID.
     * @param string $field     Feldname.
     * @return bool
     */
    public function can_view_field( int $viewer_id, string $field ): bool {
        // Eigenes Profil
        if ( $viewer_id === $this->user_id ) {
            return true;
        }

        // Admins
        if ( user_can( $viewer_id, 'manage_stylegenius' ) ) {
            return true;
        }

        return $this->is_field_public( $field );
    }

    /**
     * Exportiert alle Benutzerdaten (DSGVO)
     *
     * @return array
     */
    public function export_user_data(): array {
        $export = array();
        $db     = new StyleGenius_Database();

        // WordPress-Benutzerdaten
        $user = get_user_by( 'id', $this->user_id );
        if ( $user ) {
            $export['user'] = array(
                'username'    => $user->user_login,
                'email'       => $user->user_email,
                'display_name'=> $user->display_name,
                'registered'  => $user->user_registered,
            );
        }

        // User-Meta
        $export['profile'] = $this->meta->export_for_gdpr();

        // Chat-Verlauf
        $export['chat_history'] = $db->get_user_chat_history( $this->user_id, 1000 );

        // Garderobe
        $export['wardrobe'] = $db->get_user_wardrobe( $this->user_id );

        // Punkte-Verlauf
        $export['points_history'] = $db->get_user_points_history( $this->user_id, 1000 );

        // Achievements
        $achievements_table = $db->get_table_name( 'achievements' );
        global $wpdb;
        $export['achievements'] = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT achievement_id, earned_at FROM {$achievements_table} WHERE user_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $this->user_id
            )
        );

        // Streak-Daten
        $streak = $db->get_row( 'streaks', array( 'user_id' => $this->user_id ) );
        if ( $streak ) {
            $export['streak'] = $streak;
        }

        // Referrals
        $referrals_table     = $db->get_table_name( 'referrals' );
        $export['referrals'] = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$referrals_table} WHERE referrer_id = %d OR referred_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $this->user_id,
                $this->user_id
            )
        );

        // Capsules
        $capsules_table     = $db->get_table_name( 'capsules' );
        $export['capsules'] = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$capsules_table} WHERE user_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $this->user_id
            )
        );

        // Farbprofil
        $color_profile = $db->get_row( 'color_profiles', array( 'user_id' => $this->user_id ) );
        if ( $color_profile ) {
            $export['color_profile'] = $color_profile;
        }

        // Before/After Analysen
        $ba_table              = $db->get_table_name( 'before_after' );
        $export['outfit_analyses'] = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$ba_table} WHERE user_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $this->user_id
            )
        );

        // Challenge-Einträge
        $entries_table           = $db->get_table_name( 'challenge_entries' );
        $export['challenge_entries'] = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$entries_table} WHERE user_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $this->user_id
            )
        );

        // Challenge-Votes
        $votes_table           = $db->get_table_name( 'challenge_votes' );
        $export['challenge_votes'] = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$votes_table} WHERE user_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $this->user_id
            )
        );

        // Shares
        $shares_table     = $db->get_table_name( 'shares' );
        $export['shares'] = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$shares_table} WHERE user_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $this->user_id
            )
        );

        // Affiliate-Klicks
        $clicks_table            = $db->get_table_name( 'affiliate_clicks' );
        $export['affiliate_clicks'] = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$clicks_table} WHERE user_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $this->user_id
            )
        );

        // Fortschritt
        $progress = $db->get_row( 'user_progress', array( 'user_id' => $this->user_id ) );
        if ( $progress ) {
            $export['progress'] = $progress;
        }

        return $export;
    }

    /**
     * Gibt den Export als downloadbare Datei zurück
     *
     * @return string JSON-String.
     */
    public function get_export_file(): string {
        $data = $this->export_user_data();

        return wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
    }

    /**
     * Löscht alle Benutzerdaten (DSGVO - Recht auf Löschung)
     *
     * @return bool
     */
    public function delete_all_data(): bool {
        global $wpdb;
        $db = new StyleGenius_Database();

        $db->start_transaction();

        try {
            // Chat-Verlauf löschen
            $db->delete( 'chat_history', array( 'user_id' => $this->user_id ) );

            // Garderobe löschen (inkl. Bilder)
            $wardrobe_items = $db->get_user_wardrobe( $this->user_id );
            foreach ( $wardrobe_items as $item ) {
                if ( $item->attachment_id ) {
                    wp_delete_attachment( $item->attachment_id, true );
                }
            }
            $db->delete( 'wardrobe', array( 'user_id' => $this->user_id ) );

            // Punkte-Verlauf löschen
            $db->delete( 'points_log', array( 'user_id' => $this->user_id ) );

            // Achievements löschen
            $db->delete( 'achievements', array( 'user_id' => $this->user_id ) );

            // Streak löschen
            $db->delete( 'streaks', array( 'user_id' => $this->user_id ) );

            // Referrals anonymisieren (nicht löschen wegen Integrität)
            $referrals_table = $db->get_table_name( 'referrals' );
            $wpdb->update(
                $referrals_table,
                array( 'referrer_id' => 0 ),
                array( 'referrer_id' => $this->user_id ),
                array( '%d' ),
                array( '%d' )
            );
            $wpdb->update(
                $referrals_table,
                array( 'referred_id' => 0 ),
                array( 'referred_id' => $this->user_id ),
                array( '%d' ),
                array( '%d' )
            );

            // Capsules löschen
            $db->delete( 'capsules', array( 'user_id' => $this->user_id ) );

            // Farbprofil löschen
            $db->delete( 'color_profiles', array( 'user_id' => $this->user_id ) );

            // Before/After löschen (inkl. Bilder)
            $ba_table   = $db->get_table_name( 'before_after' );
            $ba_entries = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT image_id FROM {$ba_table} WHERE user_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $this->user_id
                )
            );
            foreach ( $ba_entries as $entry ) {
                if ( $entry->image_id ) {
                    wp_delete_attachment( $entry->image_id, true );
                }
            }
            $db->delete( 'before_after', array( 'user_id' => $this->user_id ) );

            // Challenge-Einträge löschen (Votes mit)
            $entries_table = $db->get_table_name( 'challenge_entries' );
            $entry_ids     = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT id FROM {$entries_table} WHERE user_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $this->user_id
                )
            );
            foreach ( $entry_ids as $entry_id ) {
                $db->delete( 'challenge_votes', array( 'entry_id' => $entry_id ) );
            }
            $db->delete( 'challenge_entries', array( 'user_id' => $this->user_id ) );

            // Eigene Votes löschen
            $db->delete( 'challenge_votes', array( 'user_id' => $this->user_id ) );

            // Shares löschen
            $db->delete( 'shares', array( 'user_id' => $this->user_id ) );

            // Affiliate-Klicks anonymisieren
            $clicks_table = $db->get_table_name( 'affiliate_clicks' );
            $wpdb->update(
                $clicks_table,
                array( 'user_id' => 0 ),
                array( 'user_id' => $this->user_id ),
                array( '%d' ),
                array( '%d' )
            );

            // Fortschritt löschen
            $db->delete( 'user_progress', array( 'user_id' => $this->user_id ) );

            // User-Meta löschen
            $this->meta->delete_all();

            // Upload-Ordner des Users löschen
            $upload_dir = wp_upload_dir();
            $user_dir   = $upload_dir['basedir'] . '/stylegenius/users/' . $this->user_id;
            if ( is_dir( $user_dir ) ) {
                $this->delete_directory( $user_dir );
            }

            $db->commit();

            /**
             * Fires after all user data is deleted.
             *
             * @param int $user_id User ID.
             */
            do_action( 'stylegenius_user_data_deleted', $this->user_id );

            return true;

        } catch ( Exception $e ) {
            $db->rollback();
            return false;
        }
    }

    /**
     * Anonymisiert Benutzerdaten (Alternative zur Löschung)
     *
     * @return bool
     */
    public function anonymize_data(): bool {
        // Personenbezogene Daten entfernen, aber Statistiken behalten
        $this->meta->set( 'favorite_brands', array() );
        $this->meta->set( 'budget_min', 0 );
        $this->meta->set( 'budget_max', 0 );
        $this->meta->set( 'body_type', '' );
        $this->meta->set( 'profession', '' );
        $this->meta->set( 'typical_occasions', array() );
        $this->meta->set( 'disliked_styles', array() );
        $this->meta->set( 'disliked_colors', array() );
        $this->meta->set( 'quiz_answers', array() );

        // Farbprofil löschen
        $db = new StyleGenius_Database();
        $db->delete( 'color_profiles', array( 'user_id' => $this->user_id ) );

        // Bilder in Garderobe anonymisieren
        global $wpdb;
        $wardrobe_table = $db->get_table_name( 'wardrobe' );
        $wpdb->update(
            $wardrobe_table,
            array(
                'name'  => __( 'Anonymisiert', 'stylegenius-pro' ),
                'brand' => '',
                'notes' => '',
            ),
            array( 'user_id' => $this->user_id ),
            array( '%s', '%s', '%s' ),
            array( '%d' )
        );

        return true;
    }

    /**
     * Registriert die Datenexporter für WordPress
     *
     * @return array
     */
    public static function register_exporters(): array {
        return array(
            array(
                'exporter_friendly_name' => __( 'StyleGenius Pro', 'stylegenius-pro' ),
                'callback'               => array( __CLASS__, 'exporter_callback' ),
            ),
        );
    }

    /**
     * Registriert die Datenlöscher für WordPress
     *
     * @return array
     */
    public static function register_erasers(): array {
        return array(
            array(
                'eraser_friendly_name' => __( 'StyleGenius Pro', 'stylegenius-pro' ),
                'callback'             => array( __CLASS__, 'eraser_callback' ),
            ),
        );
    }

    /**
     * Callback für den WordPress Datenexporter
     *
     * @param string $email E-Mail des Benutzers.
     * @param int    $page  Seite (Pagination).
     * @return array
     */
    public static function exporter_callback( string $email, int $page ): array {
        $user = get_user_by( 'email', $email );

        if ( ! $user ) {
            return array(
                'data' => array(),
                'done' => true,
            );
        }

        $privacy = new self( $user->ID );
        $data    = $privacy->export_user_data();

        $export_items = array();

        // Profil-Daten
        if ( ! empty( $data['profile'] ) ) {
            $export_items[] = array(
                'group_id'    => 'stylegenius-profile',
                'group_label' => __( 'StyleGenius Profil', 'stylegenius-pro' ),
                'item_id'     => 'profile-' . $user->ID,
                'data'        => $data['profile'],
            );
        }

        // Chat-Verlauf
        if ( ! empty( $data['chat_history'] ) ) {
            foreach ( $data['chat_history'] as $message ) {
                $export_items[] = array(
                    'group_id'    => 'stylegenius-chat',
                    'group_label' => __( 'StyleGenius Chat-Verlauf', 'stylegenius-pro' ),
                    'item_id'     => 'chat-' . $message->id,
                    'data'        => array(
                        array(
                            'name'  => __( 'Rolle', 'stylegenius-pro' ),
                            'value' => $message->role,
                        ),
                        array(
                            'name'  => __( 'Nachricht', 'stylegenius-pro' ),
                            'value' => $message->content,
                        ),
                        array(
                            'name'  => __( 'Datum', 'stylegenius-pro' ),
                            'value' => $message->created_at,
                        ),
                    ),
                );
            }
        }

        return array(
            'data' => $export_items,
            'done' => true,
        );
    }

    /**
     * Callback für den WordPress Datenlöscher
     *
     * @param string $email E-Mail des Benutzers.
     * @param int    $page  Seite (Pagination).
     * @return array
     */
    public static function eraser_callback( string $email, int $page ): array {
        $user = get_user_by( 'email', $email );

        if ( ! $user ) {
            return array(
                'items_removed'  => false,
                'items_retained' => false,
                'messages'       => array(),
                'done'           => true,
            );
        }

        $privacy = new self( $user->ID );
        $result  = $privacy->delete_all_data();

        return array(
            'items_removed'  => $result,
            'items_retained' => ! $result,
            'messages'       => array(),
            'done'           => true,
        );
    }

    /**
     * Fügt Datenschutz-Policy-Inhalt hinzu
     */
    public static function add_privacy_policy_content(): void {
        if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
            return;
        }

        $content = sprintf(
            '<h2>%s</h2>
            <p>%s</p>
            <h3>%s</h3>
            <p>%s</p>
            <ul>
                <li>%s</li>
                <li>%s</li>
                <li>%s</li>
                <li>%s</li>
                <li>%s</li>
            </ul>
            <h3>%s</h3>
            <p>%s</p>
            <h3>%s</h3>
            <p>%s</p>',
            __( 'StyleGenius Pro', 'stylegenius-pro' ),
            __( 'Wenn du StyleGenius Pro nutzt, speichern wir bestimmte Daten, um dir ein personalisiertes Styling-Erlebnis zu bieten.', 'stylegenius-pro' ),
            __( 'Welche Daten wir sammeln', 'stylegenius-pro' ),
            __( 'Wir sammeln und speichern folgende Daten:', 'stylegenius-pro' ),
            __( 'Dein Style-Profil (Quiz-Antworten, Präferenzen)', 'stylegenius-pro' ),
            __( 'Fotos, die du hochlädst (Kleidungsstücke, Selfies für Farbanalyse)', 'stylegenius-pro' ),
            __( 'Deinen Chat-Verlauf mit dem KI-Berater', 'stylegenius-pro' ),
            __( 'Gamification-Daten (Punkte, Badges, Level)', 'stylegenius-pro' ),
            __( 'Nutzungsstatistiken und Aktivitätsdaten', 'stylegenius-pro' ),
            __( 'KI-Datenverarbeitung', 'stylegenius-pro' ),
            __( 'Für die KI-Beratung werden deine Anfragen und hochgeladene Bilder an unsere KI-Provider (Claude/OpenAI) übermittelt. Diese Daten werden nur zur Verarbeitung deiner Anfrage verwendet und nicht dauerhaft gespeichert.', 'stylegenius-pro' ),
            __( 'Deine Rechte', 'stylegenius-pro' ),
            __( 'Du kannst jederzeit alle deine Daten einsehen, exportieren und löschen. Besuche dafür dein StyleGenius Dashboard und wähle "Datenschutz-Einstellungen".', 'stylegenius-pro' )
        );

        wp_add_privacy_policy_content( 'StyleGenius Pro', $content );
    }

    /**
     * Loggt eine Einwilligung
     *
     * @param string $type  Art der Einwilligung.
     * @param bool   $given Einwilligung gegeben?
     */
    public function log_consent( string $type, bool $given ): void {
        $consents = $this->meta->get( 'consents', array() );

        $consents[ $type ] = array(
            'given' => $given,
            'date'  => current_time( 'mysql' ),
            'ip'    => sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) ),
        );

        $this->meta->set( 'consents', $consents );
    }

    /**
     * Prüft ob eine Einwilligung vorhanden ist
     *
     * @param string $type Art der Einwilligung.
     * @return bool
     */
    public function has_consent( string $type ): bool {
        $consents = $this->meta->get( 'consents', array() );
        return ! empty( $consents[ $type ]['given'] );
    }

    /**
     * Widerruft eine Einwilligung
     *
     * @param string $type Art der Einwilligung.
     */
    public function revoke_consent( string $type ): void {
        $this->log_consent( $type, false );
    }

    /**
     * Löscht ein Verzeichnis rekursiv
     *
     * @param string $dir Verzeichnispfad.
     */
    private function delete_directory( string $dir ): void {
        if ( ! is_dir( $dir ) ) {
            return;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS ),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ( $files as $file ) {
            if ( $file->isDir() ) {
                rmdir( $file->getRealPath() );
            } else {
                unlink( $file->getRealPath() );
            }
        }

        rmdir( $dir );
    }
}

// WordPress Privacy-Hooks registrieren
add_filter( 'wp_privacy_personal_data_exporters', function( $exporters ) {
    $exporters['stylegenius-pro'] = array(
        'exporter_friendly_name' => __( 'StyleGenius Pro', 'stylegenius-pro' ),
        'callback'               => array( 'StyleGenius_Privacy', 'exporter_callback' ),
    );
    return $exporters;
} );

add_filter( 'wp_privacy_personal_data_erasers', function( $erasers ) {
    $erasers['stylegenius-pro'] = array(
        'eraser_friendly_name' => __( 'StyleGenius Pro', 'stylegenius-pro' ),
        'callback'             => array( 'StyleGenius_Privacy', 'eraser_callback' ),
    );
    return $erasers;
} );

add_action( 'admin_init', array( 'StyleGenius_Privacy', 'add_privacy_policy_content' ) );
