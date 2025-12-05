<?php
/**
 * Claude API-Klasse
 *
 * Kommunikation mit der Anthropic Claude API.
 *
 * @package StyleGenius_Pro
 * @subpackage StyleGenius_Pro/includes/ai
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class StyleGenius_Claude
 */
class StyleGenius_Claude {

    /**
     * API-Endpoint
     *
     * @var string
     */
    const API_ENDPOINT = 'https://api.anthropic.com/v1/messages';

    /**
     * API-Version
     *
     * @var string
     */
    const API_VERSION = '2023-06-01';

    /**
     * API-Key
     *
     * @var string
     */
    private string $api_key;

    /**
     * Modell
     *
     * @var string
     */
    private string $model;

    /**
     * Max Tokens
     *
     * @var int
     */
    private int $max_tokens;

    /**
     * Timeout in Sekunden
     *
     * @var int
     */
    private int $timeout;

    /**
     * Konstruktor
     *
     * @param string|null $api_key API-Key.
     */
    public function __construct( ?string $api_key = null ) {
        $options = get_option( 'stylegenius_options', array() );

        $this->api_key    = $api_key ?? ( $options['claude_api_key'] ?? '' );
        $this->model      = $options['claude_model'] ?? 'claude-sonnet-4-20250514';
        $this->max_tokens = 4096;
        $this->timeout    = 60;
    }

    /**
     * Prüft ob der Provider konfiguriert ist
     *
     * @return bool
     */
    public function is_configured(): bool {
        return ! empty( $this->api_key );
    }

    /**
     * Setzt das Modell
     *
     * @param string $model Modell-ID.
     */
    public function set_model( string $model ): void {
        $this->model = $model;
    }

    /**
     * Setzt Max Tokens
     *
     * @param int $tokens Max Tokens.
     */
    public function set_max_tokens( int $tokens ): void {
        $this->max_tokens = $tokens;
    }

    /**
     * Sendet eine Nachricht an Claude
     *
     * @param array  $messages      Array von Nachrichten.
     * @param string $system_prompt System-Prompt.
     * @param array  $options       Zusätzliche Optionen.
     * @return array
     */
    public function send_message( array $messages, string $system_prompt = '', array $options = array() ): array {
        if ( ! $this->is_configured() ) {
            return $this->handle_error( new WP_Error( 'not_configured', __( 'Claude API ist nicht konfiguriert.', 'stylegenius-pro' ) ) );
        }

        $body = array(
            'model'      => $this->model,
            'max_tokens' => $options['max_tokens'] ?? $this->max_tokens,
            'messages'   => $this->format_messages( $messages ),
        );

        if ( ! empty( $system_prompt ) ) {
            $body['system'] = $system_prompt;
        }

        if ( isset( $options['temperature'] ) ) {
            $body['temperature'] = floatval( $options['temperature'] );
        }

        return $this->make_request( $body );
    }

    /**
     * Sendet eine einzelne Nachricht
     *
     * @param string $message       Nachricht.
     * @param string $system_prompt System-Prompt.
     * @return array
     */
    public function send_single_message( string $message, string $system_prompt = '' ): array {
        return $this->send_message(
            array(
                array(
                    'role'    => 'user',
                    'content' => $message,
                ),
            ),
            $system_prompt
        );
    }

    /**
     * Analysiert ein Bild (Base64)
     *
     * @param string $image_base64 Base64-kodiertes Bild.
     * @param string $media_type   MIME-Typ des Bildes.
     * @param string $prompt       Analyse-Prompt.
     * @return array
     */
    public function analyze_image( string $image_base64, string $media_type, string $prompt ): array {
        if ( ! $this->is_configured() ) {
            return $this->handle_error( new WP_Error( 'not_configured', __( 'Claude API ist nicht konfiguriert.', 'stylegenius-pro' ) ) );
        }

        $messages = array(
            array(
                'role'    => 'user',
                'content' => array(
                    array(
                        'type'   => 'image',
                        'source' => array(
                            'type'         => 'base64',
                            'media_type'   => $media_type,
                            'data'         => $image_base64,
                        ),
                    ),
                    array(
                        'type' => 'text',
                        'text' => $prompt,
                    ),
                ),
            ),
        );

        $body = array(
            'model'      => $this->model,
            'max_tokens' => $this->max_tokens,
            'messages'   => $messages,
        );

        return $this->make_request( $body );
    }

    /**
     * Analysiert ein Bild von URL
     *
     * @param string $image_url Bild-URL.
     * @param string $prompt    Analyse-Prompt.
     * @return array
     */
    public function analyze_image_from_url( string $image_url, string $prompt ): array {
        // Bild herunterladen und konvertieren
        $response = wp_remote_get( $image_url, array( 'timeout' => 30 ) );

        if ( is_wp_error( $response ) ) {
            return $this->handle_error( $response );
        }

        $body      = wp_remote_retrieve_body( $response );
        $mime_type = wp_remote_retrieve_header( $response, 'content-type' );

        if ( empty( $body ) ) {
            return $this->handle_error( new WP_Error( 'image_download_failed', __( 'Bild konnte nicht heruntergeladen werden.', 'stylegenius-pro' ) ) );
        }

        $base64 = base64_encode( $body );

        return $this->analyze_image( $base64, $mime_type, $prompt );
    }

    /**
     * Streamt eine Nachricht (für zukünftige Implementierung)
     *
     * @param array    $messages      Nachrichten.
     * @param string   $system_prompt System-Prompt.
     * @param callable $callback      Callback für Chunks.
     * @return array
     */
    public function stream_message( array $messages, string $system_prompt, callable $callback ): array {
        // Streaming ist für WordPress-Kontext komplizierter
        // Fallback auf normale Request
        return $this->send_message( $messages, $system_prompt );
    }

    /**
     * Führt den API-Request aus
     *
     * @param array $body Request-Body.
     * @return array
     */
    private function make_request( array $body ): array {
        $start_time = microtime( true );

        $response = wp_remote_post(
            self::API_ENDPOINT,
            array(
                'headers' => $this->build_headers(),
                'body'    => wp_json_encode( $body ),
                'timeout' => $this->timeout,
            )
        );

        $request_time = microtime( true ) - $start_time;

        if ( is_wp_error( $response ) ) {
            return $this->handle_error( $response );
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $response_code !== 200 ) {
            $error_message = $response_body['error']['message'] ?? __( 'Unbekannter API-Fehler', 'stylegenius-pro' );
            return $this->handle_error(
                new WP_Error(
                    'api_error',
                    $error_message,
                    array( 'status' => $response_code )
                )
            );
        }

        return $this->handle_response( $response_body, $request_time );
    }

    /**
     * Erstellt die Request-Header
     *
     * @return array
     */
    private function build_headers(): array {
        return array(
            'Content-Type'      => 'application/json',
            'x-api-key'         => $this->api_key,
            'anthropic-version' => self::API_VERSION,
        );
    }

    /**
     * Formatiert Nachrichten für die API
     *
     * @param array $messages Nachrichten.
     * @return array
     */
    private function format_messages( array $messages ): array {
        $formatted = array();

        foreach ( $messages as $message ) {
            // Wenn content bereits strukturiert ist, direkt übernehmen
            if ( is_array( $message['content'] ) ) {
                $formatted[] = $message;
                continue;
            }

            $formatted[] = array(
                'role'    => $message['role'] ?? 'user',
                'content' => $message['content'] ?? '',
            );
        }

        return $formatted;
    }

    /**
     * Verarbeitet die API-Antwort
     *
     * @param array $response     API-Antwort.
     * @param float $request_time Request-Zeit.
     * @return array
     */
    private function handle_response( array $response, float $request_time = 0 ): array {
        $content = '';

        if ( ! empty( $response['content'] ) ) {
            foreach ( $response['content'] as $block ) {
                if ( 'text' === $block['type'] ) {
                    $content .= $block['text'];
                }
            }
        }

        // Versuche JSON zu parsen wenn vorhanden
        $json_content = null;
        if ( preg_match( '/```json\s*([\s\S]*?)\s*```/', $content, $matches ) ) {
            $json_content = json_decode( $matches[1], true );
        } elseif ( preg_match( '/\{[\s\S]*\}/', $content, $matches ) ) {
            $json_content = json_decode( $matches[0], true );
        }

        return array(
            'success'      => true,
            'content'      => $content,
            'json'         => $json_content,
            'model'        => $response['model'] ?? $this->model,
            'input_tokens' => $response['usage']['input_tokens'] ?? 0,
            'output_tokens'=> $response['usage']['output_tokens'] ?? 0,
            'total_tokens' => ( $response['usage']['input_tokens'] ?? 0 ) + ( $response['usage']['output_tokens'] ?? 0 ),
            'stop_reason'  => $response['stop_reason'] ?? null,
            'request_time' => round( $request_time, 3 ),
        );
    }

    /**
     * Behandelt Fehler
     *
     * @param WP_Error|array $error Fehler.
     * @return array
     */
    private function handle_error( WP_Error|array $error ): array {
        $message = is_wp_error( $error ) ? $error->get_error_message() : ( $error['message'] ?? __( 'Unbekannter Fehler', 'stylegenius-pro' ) );
        $code    = is_wp_error( $error ) ? $error->get_error_code() : 'error';

        // Logging im Debug-Modus
        $options = get_option( 'stylegenius_options', array() );
        if ( ! empty( $options['debug_mode'] ) ) {
            error_log( 'StyleGenius Claude Error: ' . $message );
        }

        return array(
            'success' => false,
            'error'   => $message,
            'code'    => $code,
            'content' => '',
        );
    }

    /**
     * Gibt verfügbare Modelle zurück
     *
     * @return array
     */
    public function get_available_models(): array {
        return array(
            'claude-sonnet-4-20250514'    => array(
                'name'        => 'Claude Sonnet 4',
                'description' => 'Bestes Preis-Leistungs-Verhältnis',
                'max_tokens'  => 64000,
                'vision'      => true,
            ),
            'claude-3-5-sonnet-20241022'    => array(
                'name'        => 'Claude 3.5 Sonnet',
                'description' => 'Schnell und intelligent',
                'max_tokens'  => 8192,
                'vision'      => true,
            ),
            'claude-3-5-haiku-20241022'  => array(
                'name'        => 'Claude 3.5 Haiku',
                'description' => 'Schnellstes Modell, günstig',
                'max_tokens'  => 8192,
                'vision'      => true,
            ),
            'claude-3-opus-20240229'    => array(
                'name'        => 'Claude 3 Opus',
                'description' => 'Höchste Qualität',
                'max_tokens'  => 4096,
                'vision'      => true,
            ),
        );
    }

    /**
     * Schätzt die Kosten
     *
     * @param int $input_tokens  Input-Tokens.
     * @param int $output_tokens Output-Tokens.
     * @return float Geschätzte Kosten in USD.
     */
    public function estimate_cost( int $input_tokens, int $output_tokens ): float {
        // Preise pro 1M Tokens (Stand: 2024)
        $pricing = array(
            'claude-sonnet-4-20250514' => array(
                'input'  => 3.00,
                'output' => 15.00,
            ),
            'claude-3-5-sonnet-20241022' => array(
                'input'  => 3.00,
                'output' => 15.00,
            ),
            'claude-3-5-haiku-20241022' => array(
                'input'  => 0.25,
                'output' => 1.25,
            ),
            'claude-3-opus-20240229' => array(
                'input'  => 15.00,
                'output' => 75.00,
            ),
        );

        $model_pricing = $pricing[ $this->model ] ?? $pricing['claude-3-5-sonnet-20241022'];

        $input_cost  = ( $input_tokens / 1000000 ) * $model_pricing['input'];
        $output_cost = ( $output_tokens / 1000000 ) * $model_pricing['output'];

        return round( $input_cost + $output_cost, 6 );
    }

    /**
     * Testet die API-Verbindung
     *
     * @return bool|WP_Error
     */
    public function test_connection(): bool|WP_Error {
        if ( ! $this->is_configured() ) {
            return new WP_Error( 'not_configured', __( 'API-Key nicht konfiguriert.', 'stylegenius-pro' ) );
        }

        $result = $this->send_single_message( 'Antworte nur mit "OK".' );

        if ( ! $result['success'] ) {
            return new WP_Error( 'connection_failed', $result['error'] );
        }

        return true;
    }

    /**
     * Gibt den aktuellen API-Key zurück (maskiert)
     *
     * @return string
     */
    public function get_masked_api_key(): string {
        if ( empty( $this->api_key ) ) {
            return '';
        }

        return substr( $this->api_key, 0, 10 ) . '...' . substr( $this->api_key, -4 );
    }

    /**
     * Setzt den API-Key
     *
     * @param string $api_key API-Key.
     */
    public function set_api_key( string $api_key ): void {
        $this->api_key = $api_key;
    }
}
