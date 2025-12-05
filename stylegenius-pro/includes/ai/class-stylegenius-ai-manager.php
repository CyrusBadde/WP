<?php
/**
 * AI-Manager-Klasse
 *
 * Zentrale Schnittstelle für alle KI-Operationen.
 *
 * @package StyleGenius_Pro
 * @subpackage StyleGenius_Pro/includes/ai
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class StyleGenius_AI_Manager
 */
class StyleGenius_AI_Manager {

    /**
     * Aktiver Provider
     *
     * @var string
     */
    private string $provider;

    /**
     * Claude-Instanz
     *
     * @var StyleGenius_Claude|null
     */
    private ?StyleGenius_Claude $claude = null;

    /**
     * OpenAI-Instanz
     *
     * @var StyleGenius_OpenAI|null
     */
    private ?StyleGenius_OpenAI $openai = null;

    /**
     * Prompts-Instanz
     *
     * @var StyleGenius_Prompts
     */
    private StyleGenius_Prompts $prompts;

    /**
     * Personalization-Instanz
     *
     * @var StyleGenius_Personalization|null
     */
    private ?StyleGenius_Personalization $personalization = null;

    /**
     * Konstruktor
     */
    public function __construct() {
        $options        = get_option( 'stylegenius_options', array() );
        $this->provider = $options['ai_provider'] ?? 'claude';
        $this->prompts  = new StyleGenius_Prompts();
    }

    /**
     * Gibt den aktiven Provider zurück
     *
     * @return string
     */
    public function get_active_provider(): string {
        return $this->provider;
    }

    /**
     * Setzt den Provider
     *
     * @param string $provider Provider-Name.
     * @return bool
     */
    public function set_provider( string $provider ): bool {
        if ( ! in_array( $provider, array( 'claude', 'openai' ), true ) ) {
            return false;
        }

        $this->provider = $provider;
        return true;
    }

    /**
     * Gibt die aktive Provider-Instanz zurück
     *
     * @return StyleGenius_Claude|StyleGenius_OpenAI
     */
    public function get_provider_instance(): StyleGenius_Claude|StyleGenius_OpenAI {
        if ( 'claude' === $this->provider ) {
            if ( null === $this->claude ) {
                $this->claude = new StyleGenius_Claude();
            }
            return $this->claude;
        }

        if ( null === $this->openai ) {
            $this->openai = new StyleGenius_OpenAI();
        }
        return $this->openai;
    }

    /**
     * Prüft ob der Provider konfiguriert ist
     *
     * @return bool
     */
    public function is_configured(): bool {
        return $this->get_provider_instance()->is_configured();
    }

    /**
     * Testet die API-Verbindung
     *
     * @return bool|WP_Error
     */
    public function test_connection(): bool|WP_Error {
        return $this->get_provider_instance()->test_connection();
    }

    /**
     * Sendet eine Chat-Nachricht
     *
     * @param int    $user_id Benutzer-ID.
     * @param string $message Nachricht.
     * @param array  $context Zusätzlicher Kontext.
     * @return array
     */
    public function send_chat_message( int $user_id, string $message, array $context = array() ): array {
        // Personalisierung initialisieren
        if ( null === $this->personalization ) {
            $this->personalization = new StyleGenius_Personalization( $user_id );
        }

        // Benutzer und Kontext laden
        $user    = new StyleGenius_User( $user_id );
        $db      = new StyleGenius_Database();

        // Nutzungslimit prüfen
        if ( ! $user->can_use_feature( 'ai_chat' ) ) {
            return array(
                'success' => false,
                'error'   => __( 'Du hast dein monatliches Limit erreicht. Upgrade auf Premium für mehr Anfragen.', 'stylegenius-pro' ),
                'code'    => 'usage_limit_reached',
            );
        }

        // System-Prompt mit Kontext
        $system_prompt = $this->prompts->get_chat_system_prompt();
        $user_context  = $this->personalization->get_user_context();
        $system_prompt .= $this->prompts->get_chat_context_prompt( $user_context );

        // Chat-Historie laden
        $history  = $db->get_user_chat_history( $user_id, 10 );
        $messages = array();

        foreach ( $history as $msg ) {
            $messages[] = array(
                'role'    => $msg->role,
                'content' => $msg->content,
            );
        }

        // Neue Nachricht hinzufügen
        $messages[] = array(
            'role'    => 'user',
            'content' => $message,
        );

        // An Provider senden
        $result = $this->get_provider_instance()->send_message( $messages, $system_prompt );

        if ( $result['success'] ) {
            // Nachrichten speichern
            $db->add_chat_message( $user_id, 'user', $message, 0 );
            $db->add_chat_message( $user_id, 'assistant', $result['content'], $result['total_tokens'] ?? 0 );

            // Nutzung erhöhen
            $user->increment_usage();

            // Punkte vergeben
            $points = new StyleGenius_Points( $user_id );
            $points->award_points( 'consultation' );

            // Streak aktualisieren
            $streaks = new StyleGenius_Streaks( $user_id );
            $streaks->update_streak();

            // Request loggen
            $this->log_request( $user_id, 'chat', $result['total_tokens'] ?? 0, true );
        } else {
            $this->log_request( $user_id, 'chat', 0, false );
        }

        return $result;
    }

    /**
     * Analysiert ein Bild
     *
     * @param int    $user_id       Benutzer-ID.
     * @param string $image_path    Bildpfad.
     * @param string $analysis_type Analyse-Typ.
     * @return array
     */
    public function analyze_image( int $user_id, string $image_path, string $analysis_type ): array {
        $user = new StyleGenius_User( $user_id );

        if ( ! $user->can_use_feature( 'ai_analysis' ) ) {
            return array(
                'success' => false,
                'error'   => __( 'Du hast dein monatliches Limit erreicht.', 'stylegenius-pro' ),
                'code'    => 'usage_limit_reached',
            );
        }

        // Bild laden und konvertieren
        if ( ! file_exists( $image_path ) ) {
            return array(
                'success' => false,
                'error'   => __( 'Bild nicht gefunden.', 'stylegenius-pro' ),
            );
        }

        $image_data = file_get_contents( $image_path );
        $base64     = base64_encode( $image_data );
        $mime_type  = mime_content_type( $image_path );

        // Passenden Prompt wählen
        switch ( $analysis_type ) {
            case 'clothing':
                $prompt = $this->prompts->get_clothing_analysis_prompt();
                break;
            case 'outfit':
                $prompt = $this->prompts->get_outfit_analysis_prompt();
                break;
            case 'color':
                $prompt = $this->prompts->get_color_analysis_prompt();
                break;
            default:
                $prompt = $this->prompts->get_clothing_analysis_prompt();
        }

        $result = $this->get_provider_instance()->analyze_image( $base64, $mime_type, $prompt );

        if ( $result['success'] ) {
            $user->increment_usage();

            $points = new StyleGenius_Points( $user_id );
            $points->award_points( 'upload' );

            $this->log_request( $user_id, 'image_analysis', $result['total_tokens'] ?? 0, true );
        } else {
            $this->log_request( $user_id, 'image_analysis', 0, false );
        }

        return $result;
    }

    /**
     * Generiert einen Outfit-Vorschlag
     *
     * @param int    $user_id        Benutzer-ID.
     * @param string $occasion       Anlass.
     * @param array  $wardrobe_items Garderobe-Items (optional).
     * @return array
     */
    public function generate_outfit_suggestion( int $user_id, string $occasion, array $wardrobe_items = array() ): array {
        $user = new StyleGenius_User( $user_id );

        if ( ! $user->can_use_feature( 'ai_chat' ) ) {
            return array(
                'success' => false,
                'error'   => __( 'Du hast dein monatliches Limit erreicht.', 'stylegenius-pro' ),
            );
        }

        // Garderobe laden falls nicht übergeben
        if ( empty( $wardrobe_items ) ) {
            $wardrobe       = new StyleGenius_Wardrobe( $user_id );
            $wardrobe_items = $wardrobe->get_all_items();
        }

        // Garderobe-Zusammenfassung erstellen
        $summary = $this->create_wardrobe_summary( $wardrobe_items );

        $prompt        = $this->prompts->get_outfit_suggestion_prompt( $occasion, $summary );
        $system_prompt = $this->prompts->get_chat_system_prompt();

        $result = $this->get_provider_instance()->send_single_message( $prompt, $system_prompt );

        if ( $result['success'] ) {
            $user->increment_usage();
            $this->log_request( $user_id, 'outfit_suggestion', $result['total_tokens'] ?? 0, true );
        }

        return $result;
    }

    /**
     * Generiert eine Capsule Wardrobe
     *
     * @param int    $user_id     Benutzer-ID.
     * @param string $season      Saison.
     * @param array  $preferences Präferenzen.
     * @return array
     */
    public function generate_capsule( int $user_id, string $season, array $preferences = array() ): array {
        $user = new StyleGenius_User( $user_id );

        if ( ! $user->can_use_feature( 'capsule_ai' ) ) {
            return array(
                'success' => false,
                'error'   => __( 'KI-Capsules sind nur für Premium-Mitglieder verfügbar.', 'stylegenius-pro' ),
            );
        }

        // Personalization für Kontext
        if ( null === $this->personalization ) {
            $this->personalization = new StyleGenius_Personalization( $user_id );
        }

        $user_context = $this->personalization->get_user_context();
        $preferences  = array_merge( $user_context, $preferences );

        $prompt        = $this->prompts->get_capsule_generation_prompt( $season, $preferences );
        $system_prompt = $this->prompts->get_chat_system_prompt();

        $result = $this->get_provider_instance()->send_single_message( $prompt, $system_prompt );

        if ( $result['success'] ) {
            $user->increment_usage();
            $this->log_request( $user_id, 'capsule_generation', $result['total_tokens'] ?? 0, true );
        }

        return $result;
    }

    /**
     * Holt Shopping-Empfehlungen
     *
     * @param int    $user_id Benutzer-ID.
     * @param string $query   Suchanfrage.
     * @param array  $filters Filter.
     * @return array
     */
    public function get_shopping_recommendations( int $user_id, string $query, array $filters = array() ): array {
        $user = new StyleGenius_User( $user_id );

        if ( ! $user->can_use_feature( 'shopping' ) ) {
            return array(
                'success' => false,
                'error'   => __( 'Der Shopping-Assistent ist nur für VIP-Mitglieder verfügbar.', 'stylegenius-pro' ),
            );
        }

        if ( null === $this->personalization ) {
            $this->personalization = new StyleGenius_Personalization( $user_id );
        }

        $user_profile = $this->personalization->get_user_context();
        $prompt       = $this->prompts->get_shopping_prompt( $user_profile, array_merge( $filters, array( 'query' => $query ) ) );

        $result = $this->get_provider_instance()->send_single_message( $prompt, $this->prompts->get_chat_system_prompt() );

        if ( $result['success'] ) {
            $user->increment_usage();
            $this->log_request( $user_id, 'shopping', $result['total_tokens'] ?? 0, true );
        }

        return $result;
    }

    /**
     * Analysiert den Farbtyp
     *
     * @param int    $user_id    Benutzer-ID.
     * @param string $image_path Bildpfad.
     * @return array
     */
    public function analyze_color_type( int $user_id, string $image_path ): array {
        $user = new StyleGenius_User( $user_id );

        if ( ! $user->can_use_feature( 'color_analysis' ) ) {
            return array(
                'success' => false,
                'error'   => __( 'Farbanalyse ist nicht verfügbar.', 'stylegenius-pro' ),
            );
        }

        if ( ! file_exists( $image_path ) ) {
            return array(
                'success' => false,
                'error'   => __( 'Bild nicht gefunden.', 'stylegenius-pro' ),
            );
        }

        $image_data = file_get_contents( $image_path );
        $base64     = base64_encode( $image_data );
        $mime_type  = mime_content_type( $image_path );

        $prompt = $this->prompts->get_color_analysis_prompt();
        $result = $this->get_provider_instance()->analyze_image( $base64, $mime_type, $prompt );

        if ( $result['success'] ) {
            $user->increment_usage();

            $points = new StyleGenius_Points( $user_id );
            $points->award_points( 'upload' );

            $this->log_request( $user_id, 'color_analysis', $result['total_tokens'] ?? 0, true );
        }

        return $result;
    }

    /**
     * Generiert eine Vorher/Nachher-Analyse
     *
     * @param int    $user_id    Benutzer-ID.
     * @param string $image_path Bildpfad.
     * @param string $occasion   Anlass.
     * @return array
     */
    public function generate_before_after_analysis( int $user_id, string $image_path, string $occasion ): array {
        $user = new StyleGenius_User( $user_id );

        if ( ! $user->can_use_feature( 'before_after' ) ) {
            return array(
                'success' => false,
                'error'   => __( 'Du hast dein Limit für Outfit-Analysen erreicht.', 'stylegenius-pro' ),
            );
        }

        if ( ! file_exists( $image_path ) ) {
            return array(
                'success' => false,
                'error'   => __( 'Bild nicht gefunden.', 'stylegenius-pro' ),
            );
        }

        $image_data = file_get_contents( $image_path );
        $base64     = base64_encode( $image_data );
        $mime_type  = mime_content_type( $image_path );

        $prompt = $this->prompts->get_before_after_prompt( $occasion );
        $result = $this->get_provider_instance()->analyze_image( $base64, $mime_type, $prompt );

        if ( $result['success'] ) {
            $user->increment_usage();
            $this->log_request( $user_id, 'before_after', $result['total_tokens'] ?? 0, true );
        }

        return $result;
    }

    /**
     * Analysiert Quiz-Antworten
     *
     * @param array $answers Quiz-Antworten.
     * @return array
     */
    public function get_quiz_result_analysis( array $answers ): array {
        $prompt        = $this->prompts->get_quiz_analysis_prompt( $answers );
        $system_prompt = $this->prompts->get_chat_system_prompt();

        return $this->get_provider_instance()->send_single_message( $prompt, $system_prompt );
    }

    /**
     * Erstellt eine Garderobe-Zusammenfassung
     *
     * @param array $items Garderobe-Items.
     * @return array
     */
    private function create_wardrobe_summary( array $items ): array {
        $summary = array(
            'total_items' => count( $items ),
            'categories'  => array(),
            'colors'      => array(),
            'seasons'     => array(),
        );

        foreach ( $items as $item ) {
            // Kategorien zählen
            $cat = $item->category ?? 'andere';
            if ( ! isset( $summary['categories'][ $cat ] ) ) {
                $summary['categories'][ $cat ] = 0;
            }
            $summary['categories'][ $cat ]++;

            // Farben sammeln
            if ( ! empty( $item->color ) ) {
                if ( ! isset( $summary['colors'][ $item->color ] ) ) {
                    $summary['colors'][ $item->color ] = 0;
                }
                $summary['colors'][ $item->color ]++;
            }

            // Saisons
            if ( ! empty( $item->season ) ) {
                if ( ! isset( $summary['seasons'][ $item->season ] ) ) {
                    $summary['seasons'][ $item->season ] = 0;
                }
                $summary['seasons'][ $item->season ]++;
            }
        }

        return $summary;
    }

    /**
     * Baut den Kontext für eine Anfrage
     *
     * @param int    $user_id      Benutzer-ID.
     * @param string $request_type Anfrage-Typ.
     * @return array
     */
    private function build_context( int $user_id, string $request_type ): array {
        if ( null === $this->personalization ) {
            $this->personalization = new StyleGenius_Personalization( $user_id );
        }

        return $this->personalization->get_user_context();
    }

    /**
     * Loggt eine KI-Anfrage
     *
     * @param int    $user_id Benutzer-ID.
     * @param string $type    Anfrage-Typ.
     * @param int    $tokens  Verwendete Tokens.
     * @param bool   $success Erfolgreich?
     */
    private function log_request( int $user_id, string $type, int $tokens, bool $success ): void {
        // Debug-Logging
        $options = get_option( 'stylegenius_options', array() );
        if ( ! empty( $options['debug_mode'] ) ) {
            error_log( sprintf(
                'StyleGenius AI Request: user=%d, type=%s, tokens=%d, success=%s, provider=%s',
                $user_id,
                $type,
                $tokens,
                $success ? 'true' : 'false',
                $this->provider
            ) );
        }

        /**
         * Fires after an AI request is made.
         *
         * @param int    $user_id  User ID.
         * @param string $type     Request type.
         * @param int    $tokens   Tokens used.
         * @param bool   $success  Whether request succeeded.
         * @param string $provider Provider used.
         */
        do_action( 'stylegenius_ai_request', $user_id, $type, $tokens, $success, $this->provider );
    }

    /**
     * Behandelt Fehler
     *
     * @param string $provider Provider.
     * @param mixed  $error    Fehler.
     * @return WP_Error
     */
    private function handle_error( string $provider, mixed $error ): WP_Error {
        $message = is_string( $error ) ? $error : ( $error['message'] ?? __( 'Unbekannter Fehler', 'stylegenius-pro' ) );

        return new WP_Error(
            'ai_error',
            sprintf( '%s: %s', strtoupper( $provider ), $message )
        );
    }

    /**
     * Gibt Nutzungsstatistiken zurück
     *
     * @param string $period Zeitraum.
     * @return array
     */
    public function get_usage_stats( string $period = 'month' ): array {
        global $wpdb;
        $db = new StyleGenius_Database();

        // Placeholder für Statistik-Implementierung
        return array(
            'total_requests' => 0,
            'total_tokens'   => 0,
            'by_type'        => array(),
            'by_provider'    => array(),
        );
    }

    /**
     * Schätzt die Token-Anzahl für einen Text
     *
     * @param string $text Text.
     * @return int Geschätzte Token-Anzahl.
     */
    public function estimate_tokens( string $text ): int {
        // Grobe Schätzung: ~4 Zeichen pro Token
        return (int) ceil( mb_strlen( $text ) / 4 );
    }

    /**
     * Gibt den Rate-Limit-Status zurück
     *
     * @return array
     */
    public function get_rate_limit_status(): array {
        // Placeholder - könnte von der API abgefragt werden
        return array(
            'requests_remaining' => -1,
            'tokens_remaining'   => -1,
            'reset_at'           => null,
        );
    }

    /**
     * Gibt die Prompts-Instanz zurück
     *
     * @return StyleGenius_Prompts
     */
    public function get_prompts(): StyleGenius_Prompts {
        return $this->prompts;
    }
}
