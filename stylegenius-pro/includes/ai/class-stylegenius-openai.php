<?php
/**
 * OpenAI API-Klasse
 *
 * Kommunikation mit der OpenAI API.
 *
 * @package StyleGenius_Pro
 * @subpackage StyleGenius_Pro/includes/ai
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class StyleGenius_OpenAI
 */
class StyleGenius_OpenAI {

    /**
     * API-Endpoint
     *
     * @var string
     */
    const API_ENDPOINT = 'https://api.openai.com/v1/chat/completions';

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

        $this->api_key    = $api_key ?? ( $options['openai_api_key'] ?? '' );
        $this->model      = $options['openai_model'] ?? 'gpt-4o';
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
     * Sendet eine Nachricht an OpenAI
     *
     * @param array  $messages      Array von Nachrichten.
     * @param string $system_prompt System-Prompt.
     * @param array  $options       Zusätzliche Optionen.
     * @return array
     */
    public function send_message( array $messages, string $system_prompt = '', array $options = array() ): array {
        if ( ! $this->is_configured() ) {
            return $this->handle_error( new WP_Error( 'not_configured', __( 'OpenAI API ist nicht konfiguriert.', 'stylegenius-pro' ) ) );
        }

        $formatted_messages = $this->format_messages( $messages, $system_prompt );

        $body = array(
            'model'      => $this->model,
            'messages'   => $formatted_messages,
            'max_tokens' => $options['max_tokens'] ?? $this->max_tokens,
        );

        if ( isset( $options['temperature'] ) ) {
            $body['temperature'] = floatval( $options['temperature'] );
        }

        // JSON-Mode für strukturierte Antworten
        if ( ! empty( $options['json_mode'] ) ) {
            $body['response_format'] = array( 'type' => 'json_object' );
        }

        return $this->make_request( self::API_ENDPOINT, $body );
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
            return $this->handle_error( new WP_Error( 'not_configured', __( 'OpenAI API ist nicht konfiguriert.', 'stylegenius-pro' ) ) );
        }

        // Vision-fähiges Modell prüfen
        if ( ! $this->model_supports_vision() ) {
            $this->model = 'gpt-4o'; // Fallback
        }

        $messages = array(
            array(
                'role'    => 'user',
                'content' => array(
                    array(
                        'type'      => 'image_url',
                        'image_url' => array(
                            'url'    => "data:{$media_type};base64,{$image_base64}",
                            'detail' => 'high',
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
            'messages'   => $messages,
            'max_tokens' => $this->max_tokens,
        );

        return $this->make_request( self::API_ENDPOINT, $body );
    }

    /**
     * Analysiert ein Bild von URL
     *
     * @param string $image_url Bild-URL.
     * @param string $prompt    Analyse-Prompt.
     * @return array
     */
    public function analyze_image_from_url( string $image_url, string $prompt ): array {
        if ( ! $this->is_configured() ) {
            return $this->handle_error( new WP_Error( 'not_configured', __( 'OpenAI API ist nicht konfiguriert.', 'stylegenius-pro' ) ) );
        }

        if ( ! $this->model_supports_vision() ) {
            $this->model = 'gpt-4o';
        }

        $messages = array(
            array(
                'role'    => 'user',
                'content' => array(
                    array(
                        'type'      => 'image_url',
                        'image_url' => array(
                            'url'    => $image_url,
                            'detail' => 'high',
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
            'messages'   => $messages,
            'max_tokens' => $this->max_tokens,
        );

        return $this->make_request( self::API_ENDPOINT, $body );
    }

    /**
     * Streamt eine Nachricht (Platzhalter)
     *
     * @param array    $messages      Nachrichten.
     * @param string   $system_prompt System-Prompt.
     * @param callable $callback      Callback für Chunks.
     * @return array
     */
    public function stream_message( array $messages, string $system_prompt, callable $callback ): array {
        // Streaming ist für WordPress-Kontext komplizierter
        return $this->send_message( $messages, $system_prompt );
    }

    /**
     * Führt den API-Request aus
     *
     * @param string $endpoint API-Endpoint.
     * @param array  $body     Request-Body.
     * @return array
     */
    private function make_request( string $endpoint, array $body ): array {
        $start_time = microtime( true );

        $response = wp_remote_post(
            $endpoint,
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
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $this->api_key,
        );
    }

    /**
     * Formatiert Nachrichten für die API
     *
     * @param array  $messages      Nachrichten.
     * @param string $system_prompt System-Prompt.
     * @return array
     */
    private function format_messages( array $messages, string $system_prompt ): array {
        $formatted = array();

        // System-Prompt als erste Nachricht
        if ( ! empty( $system_prompt ) ) {
            $formatted[] = array(
                'role'    => 'system',
                'content' => $system_prompt,
            );
        }

        foreach ( $messages as $message ) {
            // Bereits strukturierte Nachrichten direkt übernehmen
            if ( isset( $message['content'] ) && is_array( $message['content'] ) ) {
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
        $content = $response['choices'][0]['message']['content'] ?? '';

        // Versuche JSON zu parsen wenn vorhanden
        $json_content = null;
        if ( preg_match( '/```json\s*([\s\S]*?)\s*```/', $content, $matches ) ) {
            $json_content = json_decode( $matches[1], true );
        } elseif ( preg_match( '/\{[\s\S]*\}/', $content, $matches ) ) {
            $json_content = json_decode( $matches[0], true );
        }

        $usage = $response['usage'] ?? array();

        return array(
            'success'       => true,
            'content'       => $content,
            'json'          => $json_content,
            'model'         => $response['model'] ?? $this->model,
            'input_tokens'  => $usage['prompt_tokens'] ?? 0,
            'output_tokens' => $usage['completion_tokens'] ?? 0,
            'total_tokens'  => $usage['total_tokens'] ?? 0,
            'finish_reason' => $response['choices'][0]['finish_reason'] ?? null,
            'request_time'  => round( $request_time, 3 ),
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
            error_log( 'StyleGenius OpenAI Error: ' . $message );
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
            'gpt-4o'           => array(
                'name'        => 'GPT-4o',
                'description' => 'Bestes multimodales Modell',
                'max_tokens'  => 128000,
                'vision'      => true,
            ),
            'gpt-4o-mini'      => array(
                'name'        => 'GPT-4o Mini',
                'description' => 'Schnell und kosteneffizient',
                'max_tokens'  => 128000,
                'vision'      => true,
            ),
            'gpt-4-turbo'      => array(
                'name'        => 'GPT-4 Turbo',
                'description' => 'Leistungsstark mit Vision',
                'max_tokens'  => 128000,
                'vision'      => true,
            ),
            'gpt-3.5-turbo'    => array(
                'name'        => 'GPT-3.5 Turbo',
                'description' => 'Schnell und günstig',
                'max_tokens'  => 16385,
                'vision'      => false,
            ),
        );
    }

    /**
     * Prüft ob das aktuelle Modell Vision unterstützt
     *
     * @return bool
     */
    private function model_supports_vision(): bool {
        $models = $this->get_available_models();
        return $models[ $this->model ]['vision'] ?? false;
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
            'gpt-4o'        => array(
                'input'  => 2.50,
                'output' => 10.00,
            ),
            'gpt-4o-mini'   => array(
                'input'  => 0.15,
                'output' => 0.60,
            ),
            'gpt-4-turbo'   => array(
                'input'  => 10.00,
                'output' => 30.00,
            ),
            'gpt-3.5-turbo' => array(
                'input'  => 0.50,
                'output' => 1.50,
            ),
        );

        $model_pricing = $pricing[ $this->model ] ?? $pricing['gpt-4o'];

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

        return substr( $this->api_key, 0, 7 ) . '...' . substr( $this->api_key, -4 );
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
