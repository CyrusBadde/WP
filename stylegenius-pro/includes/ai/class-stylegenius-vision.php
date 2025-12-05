<?php
/**
 * Vision-Klasse
 *
 * Bildanalyse-Funktionen für Kleidung und Outfits.
 *
 * @package StyleGenius_Pro
 * @subpackage StyleGenius_Pro/includes/ai
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class StyleGenius_Vision
 */
class StyleGenius_Vision {

    /**
     * AI-Manager-Instanz
     *
     * @var StyleGenius_AI_Manager
     */
    private StyleGenius_AI_Manager $ai_manager;

    /**
     * Prompts-Instanz
     *
     * @var StyleGenius_Prompts
     */
    private StyleGenius_Prompts $prompts;

    /**
     * Maximale Bildgröße in Bytes
     *
     * @var int
     */
    const MAX_FILE_SIZE = 20971520; // 20MB

    /**
     * Unterstützte Formate
     *
     * @var array
     */
    const SUPPORTED_FORMATS = array( 'image/jpeg', 'image/png', 'image/webp', 'image/gif' );

    /**
     * Konstruktor
     *
     * @param StyleGenius_AI_Manager $ai_manager AI-Manager-Instanz.
     */
    public function __construct( StyleGenius_AI_Manager $ai_manager ) {
        $this->ai_manager = $ai_manager;
        $this->prompts    = new StyleGenius_Prompts();
    }

    /**
     * Analysiert ein Kleidungsstück
     *
     * @param string $image_path Bildpfad.
     * @return array
     */
    public function analyze_clothing_item( string $image_path ): array {
        $validation = $this->validate_image( $image_path );
        if ( is_wp_error( $validation ) ) {
            return array(
                'success' => false,
                'error'   => $validation->get_error_message(),
            );
        }

        $prepared = $this->prepare_image( $image_path );
        if ( is_wp_error( $prepared ) ) {
            return array(
                'success' => false,
                'error'   => $prepared->get_error_message(),
            );
        }

        $prompt   = $this->prompts->get_clothing_analysis_prompt();
        $provider = $this->ai_manager->get_provider_instance();

        $result = $provider->analyze_image(
            $prepared['base64'],
            $prepared['mime_type'],
            $prompt
        );

        if ( $result['success'] && ! empty( $result['json'] ) ) {
            // Validiere und bereinige die Ergebnisse
            $result['analysis'] = $this->normalize_clothing_analysis( $result['json'] );
        }

        return $result;
    }

    /**
     * Analysiert ein Outfit
     *
     * @param string      $image_path Bildpfad.
     * @param string|null $occasion   Anlass.
     * @return array
     */
    public function analyze_outfit( string $image_path, ?string $occasion = null ): array {
        $validation = $this->validate_image( $image_path );
        if ( is_wp_error( $validation ) ) {
            return array(
                'success' => false,
                'error'   => $validation->get_error_message(),
            );
        }

        $prepared = $this->prepare_image( $image_path );
        if ( is_wp_error( $prepared ) ) {
            return array(
                'success' => false,
                'error'   => $prepared->get_error_message(),
            );
        }

        $prompt   = $this->prompts->get_outfit_analysis_prompt( $occasion );
        $provider = $this->ai_manager->get_provider_instance();

        $result = $provider->analyze_image(
            $prepared['base64'],
            $prepared['mime_type'],
            $prompt
        );

        if ( $result['success'] && ! empty( $result['json'] ) ) {
            $result['analysis'] = $this->normalize_outfit_analysis( $result['json'] );
        }

        return $result;
    }

    /**
     * Erkennt Farben in einem Bild
     *
     * @param string $image_path Bildpfad.
     * @return array
     */
    public function detect_colors( string $image_path ): array {
        $validation = $this->validate_image( $image_path );
        if ( is_wp_error( $validation ) ) {
            return array(
                'success' => false,
                'error'   => $validation->get_error_message(),
            );
        }

        $prepared = $this->prepare_image( $image_path );
        if ( is_wp_error( $prepared ) ) {
            return array(
                'success' => false,
                'error'   => $prepared->get_error_message(),
            );
        }

        $prompt = <<<'PROMPT'
Analysiere die dominanten Farben in diesem Bild.

Identifiziere:
1. Die Hauptfarbe (60% Regel)
2. Sekundärfarbe(n) (30% Regel)
3. Akzentfarben (10% Regel)

Für jede Farbe gib an:
- Namen (auf Deutsch)
- HEX-Wert
- Geschätzter Prozentanteil

Antworte im JSON-Format:
{
    "primary_color": {"name": "Navy", "hex": "#1A365D", "percentage": 60},
    "secondary_colors": [{"name": "Weiß", "hex": "#FFFFFF", "percentage": 30}],
    "accent_colors": [{"name": "Gold", "hex": "#D69E2E", "percentage": 10}],
    "color_harmony": "komplementär",
    "mood": "professionell und elegant"
}
PROMPT;

        $provider = $this->ai_manager->get_provider_instance();
        return $provider->analyze_image( $prepared['base64'], $prepared['mime_type'], $prompt );
    }

    /**
     * Analysiert die Farbgebung einer Person
     *
     * @param string $image_path Bildpfad.
     * @return array
     */
    public function analyze_person_coloring( string $image_path ): array {
        $validation = $this->validate_image( $image_path );
        if ( is_wp_error( $validation ) ) {
            return array(
                'success' => false,
                'error'   => $validation->get_error_message(),
            );
        }

        $prepared = $this->prepare_image( $image_path );
        if ( is_wp_error( $prepared ) ) {
            return array(
                'success' => false,
                'error'   => $prepared->get_error_message(),
            );
        }

        $prompt   = $this->prompts->get_color_analysis_prompt();
        $provider = $this->ai_manager->get_provider_instance();

        return $provider->analyze_image( $prepared['base64'], $prepared['mime_type'], $prompt );
    }

    /**
     * Schlägt Kombinationen für ein Kleidungsstück vor
     *
     * @param string $item_image_path Bildpfad des Kleidungsstücks.
     * @param array  $wardrobe_items  Andere Garderobe-Items.
     * @return array
     */
    public function suggest_combinations( string $item_image_path, array $wardrobe_items ): array {
        $validation = $this->validate_image( $item_image_path );
        if ( is_wp_error( $validation ) ) {
            return array(
                'success' => false,
                'error'   => $validation->get_error_message(),
            );
        }

        $prepared = $this->prepare_image( $item_image_path );
        if ( is_wp_error( $prepared ) ) {
            return array(
                'success' => false,
                'error'   => $prepared->get_error_message(),
            );
        }

        // Erstelle Zusammenfassung der Garderobe
        $wardrobe_summary = array();
        foreach ( $wardrobe_items as $item ) {
            $wardrobe_summary[] = array(
                'id'       => $item->id,
                'category' => $item->category,
                'name'     => $item->name ?? 'Unbenannt',
                'color'    => $item->color ?? 'Unbekannt',
                'style'    => $item->style ?? 'casual',
            );
        }

        $prompt = sprintf(
            'Analysiere dieses Kleidungsstück und schlage Kombinationen mit folgender Garderobe vor:

Verfügbare Teile:
%s

Erstelle 5 Outfit-Vorschläge, die das gezeigte Teil enthalten.

Antworte im JSON-Format:
{
    "item_analysis": {
        "category": "...",
        "color": "...",
        "style": "..."
    },
    "combinations": [
        {
            "name": "Look Name",
            "items": [{"id": 123}, {"id": 456}],
            "occasion": "büro",
            "why_it_works": "Erklärung..."
        }
    ]
}',
            wp_json_encode( $wardrobe_summary, JSON_UNESCAPED_UNICODE )
        );

        $provider = $this->ai_manager->get_provider_instance();
        return $provider->analyze_image( $prepared['base64'], $prepared['mime_type'], $prompt );
    }

    /**
     * Vergleicht zwei Outfits
     *
     * @param string $image1_path Pfad zum ersten Bild.
     * @param string $image2_path Pfad zum zweiten Bild.
     * @return array
     */
    public function compare_outfits( string $image1_path, string $image2_path ): array {
        // Beide Bilder validieren
        foreach ( array( $image1_path, $image2_path ) as $path ) {
            $validation = $this->validate_image( $path );
            if ( is_wp_error( $validation ) ) {
                return array(
                    'success' => false,
                    'error'   => $validation->get_error_message(),
                );
            }
        }

        // Für Vergleiche müssen wir sequentiell analysieren
        $analysis1 = $this->analyze_outfit( $image1_path );
        $analysis2 = $this->analyze_outfit( $image2_path );

        if ( ! $analysis1['success'] || ! $analysis2['success'] ) {
            return array(
                'success' => false,
                'error'   => __( 'Eines der Bilder konnte nicht analysiert werden.', 'stylegenius-pro' ),
            );
        }

        // Vergleich erstellen
        $comparison = array(
            'success'    => true,
            'outfit_1'   => $analysis1['analysis'] ?? $analysis1['json'],
            'outfit_2'   => $analysis2['analysis'] ?? $analysis2['json'],
            'comparison' => array(
                'score_difference' => abs(
                    ( $analysis1['analysis']['overall_score'] ?? 0 ) -
                    ( $analysis2['analysis']['overall_score'] ?? 0 )
                ),
                'better_outfit' => ( $analysis1['analysis']['overall_score'] ?? 0 ) >=
                                   ( $analysis2['analysis']['overall_score'] ?? 0 ) ? 1 : 2,
            ),
        );

        return $comparison;
    }

    /**
     * Erstellt ein Verbesserungs-Mockup
     *
     * @param array $analysis     Analyse-Ergebnisse.
     * @param array $user_profile Benutzerprofil.
     * @return array
     */
    public function get_improvement_mockup( array $analysis, array $user_profile ): array {
        // Dies würde eine textuelle Beschreibung des verbesserten Outfits generieren
        $improvements = $analysis['improvements'] ?? array();

        if ( empty( $improvements ) ) {
            return array(
                'success' => false,
                'error'   => __( 'Keine Verbesserungsvorschläge verfügbar.', 'stylegenius-pro' ),
            );
        }

        $description = __( 'So könnte dein verbessertes Outfit aussehen:', 'stylegenius-pro' ) . "\n\n";

        foreach ( $improvements as $improvement ) {
            $description .= sprintf(
                "• %s: %s\n",
                $improvement['area'] ?? '',
                $improvement['suggestion'] ?? ''
            );
        }

        return array(
            'success'     => true,
            'description' => $description,
            'changes'     => $improvements,
        );
    }

    /**
     * Bereitet ein Bild für die API vor
     *
     * @param string $image_path Bildpfad.
     * @return array|WP_Error
     */
    private function prepare_image( string $image_path ): array|WP_Error {
        if ( ! file_exists( $image_path ) ) {
            return new WP_Error( 'file_not_found', __( 'Bilddatei nicht gefunden.', 'stylegenius-pro' ) );
        }

        $image_data = file_get_contents( $image_path );
        if ( false === $image_data ) {
            return new WP_Error( 'read_error', __( 'Bild konnte nicht gelesen werden.', 'stylegenius-pro' ) );
        }

        $mime_type = mime_content_type( $image_path );

        // Bild ggf. komprimieren/skalieren
        $image_data = $this->optimize_for_api( $image_path, $image_data );

        return array(
            'base64'    => base64_encode( $image_data ),
            'mime_type' => $mime_type,
            'size'      => strlen( $image_data ),
        );
    }

    /**
     * Optimiert ein Bild für die API
     *
     * @param string $image_path Bildpfad.
     * @param string $image_data Bilddaten.
     * @return string Optimierte Bilddaten.
     */
    private function optimize_for_api( string $image_path, string $image_data ): string {
        // Wenn das Bild zu groß ist, verkleinern
        if ( strlen( $image_data ) > 5242880 ) { // 5MB
            $image = wp_get_image_editor( $image_path );

            if ( ! is_wp_error( $image ) ) {
                // Auf max 2048px skalieren
                $image->resize( 2048, 2048, false );
                $image->set_quality( 85 );

                $temp_file = wp_tempnam( 'sg_' );
                $saved     = $image->save( $temp_file, 'image/jpeg' );

                if ( ! is_wp_error( $saved ) ) {
                    $image_data = file_get_contents( $saved['path'] );
                    unlink( $saved['path'] );
                }
            }
        }

        return $image_data;
    }

    /**
     * Validiert ein Bild
     *
     * @param string $image_path Bildpfad.
     * @return bool|WP_Error
     */
    private function validate_image( string $image_path ): bool|WP_Error {
        if ( ! file_exists( $image_path ) ) {
            return new WP_Error( 'file_not_found', __( 'Bilddatei nicht gefunden.', 'stylegenius-pro' ) );
        }

        $file_size = filesize( $image_path );
        if ( $file_size > self::MAX_FILE_SIZE ) {
            return new WP_Error(
                'file_too_large',
                sprintf(
                    /* translators: %s: Maximale Dateigröße */
                    __( 'Das Bild ist zu groß. Maximale Größe: %s.', 'stylegenius-pro' ),
                    size_format( self::MAX_FILE_SIZE )
                )
            );
        }

        $mime_type = mime_content_type( $image_path );
        if ( ! in_array( $mime_type, self::SUPPORTED_FORMATS, true ) ) {
            return new WP_Error(
                'invalid_format',
                __( 'Ungültiges Bildformat. Erlaubt sind: JPEG, PNG, WebP, GIF.', 'stylegenius-pro' )
            );
        }

        // Prüfe ob es wirklich ein gültiges Bild ist
        $image_info = getimagesize( $image_path );
        if ( false === $image_info ) {
            return new WP_Error( 'invalid_image', __( 'Die Datei ist kein gültiges Bild.', 'stylegenius-pro' ) );
        }

        return true;
    }

    /**
     * Normalisiert die Kleidungsstück-Analyse
     *
     * @param array $analysis Rohe Analyse.
     * @return array Normalisierte Analyse.
     */
    private function normalize_clothing_analysis( array $analysis ): array {
        $defaults = array(
            'category'               => 'andere',
            'subcategory'            => '',
            'color'                  => '#000000',
            'colors_secondary'       => array(),
            'pattern'                => 'uni',
            'material'               => '',
            'style'                  => 'casual',
            'occasions'              => array(),
            'seasons'                => array(),
            'quality_score'          => 3,
            'combination_suggestions'=> array(),
            'description'            => '',
        );

        return wp_parse_args( $analysis, $defaults );
    }

    /**
     * Normalisiert die Outfit-Analyse
     *
     * @param array $analysis Rohe Analyse.
     * @return array Normalisierte Analyse.
     */
    private function normalize_outfit_analysis( array $analysis ): array {
        $defaults = array(
            'overall_score'   => 0,
            'scores'          => array(
                'fit'         => 0,
                'color'       => 0,
                'style'       => 0,
                'accessories' => 0,
                'occasion'    => 0,
            ),
            'analysis'        => array(
                'positives' => array(),
                'negatives' => array(),
            ),
            'improvements'    => array(),
            'quick_wins'      => array(),
            'overall_feedback'=> '',
        );

        return wp_parse_args( $analysis, $defaults );
    }

    /**
     * Gibt unterstützte Formate zurück
     *
     * @return array
     */
    public function get_supported_formats(): array {
        return self::SUPPORTED_FORMATS;
    }

    /**
     * Gibt die maximale Dateigröße zurück
     *
     * @return int
     */
    public function get_max_file_size(): int {
        return self::MAX_FILE_SIZE;
    }
}
