<?php
/**
 * Personalization-Klasse
 *
 * Personalisierung für KI-Anfragen basierend auf Benutzerkontext.
 *
 * @package StyleGenius_Pro
 * @subpackage StyleGenius_Pro/includes/ai
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class StyleGenius_Personalization
 */
class StyleGenius_Personalization {

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
     * Database-Objekt
     *
     * @var StyleGenius_Database
     */
    private StyleGenius_Database $db;

    /**
     * Cache für Kontext
     *
     * @var array|null
     */
    private ?array $context_cache = null;

    /**
     * Konstruktor
     *
     * @param int $user_id Benutzer-ID.
     */
    public function __construct( int $user_id ) {
        $this->user_id = $user_id;
        $this->meta    = new StyleGenius_Meta( $user_id );
        $this->db      = new StyleGenius_Database();
    }

    /**
     * Gibt den kompletten Benutzerkontext zurück
     *
     * @return array
     */
    public function get_user_context(): array {
        if ( null !== $this->context_cache ) {
            return $this->context_cache;
        }

        $context = array(
            'style_type'        => $this->meta->get( 'style_type', '' ),
            'color_type'        => $this->get_color_type(),
            'profession'        => $this->meta->get( 'profession', '' ),
            'body_type'         => $this->meta->get( 'body_type', '' ),
            'budget'            => $this->get_budget_range(),
            'favorite_brands'   => $this->get_brand_preferences(),
            'typical_occasions' => $this->meta->get( 'typical_occasions', array() ),
            'disliked_styles'   => $this->meta->get( 'disliked_styles', array() ),
            'disliked_colors'   => $this->meta->get( 'disliked_colors', array() ),
            'wardrobe_summary'  => $this->get_wardrobe_summary(),
            'style_preferences' => $this->get_style_preferences(),
            'color_preferences' => $this->get_color_preferences(),
            'lifestyle'         => $this->get_lifestyle_context(),
        );

        $this->context_cache = $context;
        return $context;
    }

    /**
     * Baut einen Personalisierungs-Prompt
     *
     * @return string
     */
    public function build_personalization_prompt(): string {
        $context = $this->get_user_context();
        $parts   = array();

        if ( ! empty( $context['style_type'] ) ) {
            $parts[] = sprintf( 'Style-Typ: %s', $this->get_style_type_label( $context['style_type'] ) );
        }

        if ( ! empty( $context['color_type'] ) ) {
            $parts[] = sprintf( 'Farbtyp: %s', ucfirst( $context['color_type'] ) );
        }

        if ( ! empty( $context['profession'] ) ) {
            $parts[] = sprintf( 'Beruf: %s', $context['profession'] );
        }

        if ( ! empty( $context['body_type'] ) ) {
            $parts[] = sprintf( 'Körpertyp: %s', $context['body_type'] );
        }

        if ( ! empty( $context['budget'] ) ) {
            $parts[] = sprintf(
                'Budget: %d€ - %s',
                $context['budget']['min'],
                $context['budget']['max'] === -1 ? 'unbegrenzt' : $context['budget']['max'] . '€'
            );
        }

        if ( ! empty( $context['favorite_brands'] ) ) {
            $parts[] = sprintf( 'Lieblingsmarken: %s', implode( ', ', array_slice( $context['favorite_brands'], 0, 5 ) ) );
        }

        if ( ! empty( $context['typical_occasions'] ) ) {
            $parts[] = sprintf( 'Typische Anlässe: %s', implode( ', ', $context['typical_occasions'] ) );
        }

        if ( ! empty( $context['disliked_styles'] ) ) {
            $parts[] = sprintf( 'Mag nicht: %s', implode( ', ', $context['disliked_styles'] ) );
        }

        if ( ! empty( $context['wardrobe_summary'] ) ) {
            $ws = $context['wardrobe_summary'];
            $parts[] = sprintf(
                'Garderobe: %d Teile (%s)',
                $ws['total_items'],
                implode( ', ', array_map(
                    fn( $cat, $count ) => "{$count}x {$cat}",
                    array_keys( $ws['categories'] ),
                    array_values( $ws['categories'] )
                ) )
            );
        }

        if ( empty( $parts ) ) {
            return '';
        }

        return "\n\nBenutzer-Kontext:\n" . implode( "\n", $parts );
    }

    /**
     * Gibt Style-Präferenzen zurück
     *
     * @return array
     */
    public function get_style_preferences(): array {
        $style_type = $this->meta->get( 'style_type', '' );

        $preferences = array(
            'power_executive'       => array(
                'formal'       => 5,
                'classic'      => 5,
                'minimalist'   => 3,
                'trendy'       => 1,
                'casual'       => 2,
            ),
            'creative_professional' => array(
                'formal'       => 3,
                'classic'      => 2,
                'minimalist'   => 3,
                'trendy'       => 4,
                'casual'       => 4,
            ),
            'classic_traditionalist'=> array(
                'formal'       => 4,
                'classic'      => 5,
                'minimalist'   => 3,
                'trendy'       => 1,
                'casual'       => 2,
            ),
            'modern_minimalist'     => array(
                'formal'       => 3,
                'classic'      => 3,
                'minimalist'   => 5,
                'trendy'       => 3,
                'casual'       => 3,
            ),
            'smart_casual_expert'   => array(
                'formal'       => 2,
                'classic'      => 3,
                'minimalist'   => 3,
                'trendy'       => 3,
                'casual'       => 5,
            ),
            'trendsetter'           => array(
                'formal'       => 2,
                'classic'      => 1,
                'minimalist'   => 2,
                'trendy'       => 5,
                'casual'       => 4,
            ),
        );

        return $preferences[ $style_type ] ?? array(
            'formal'     => 3,
            'classic'    => 3,
            'minimalist' => 3,
            'trendy'     => 3,
            'casual'     => 3,
        );
    }

    /**
     * Gibt Farb-Präferenzen zurück
     *
     * @return array
     */
    public function get_color_preferences(): array {
        $color_type = $this->get_color_type();
        $disliked   = $this->meta->get( 'disliked_colors', array() );

        $palettes = array(
            'spring' => array(
                'preferred' => array( '#FF6B35', '#00B4D8', '#FFD93D', '#6BCB77', '#FF9A8B' ),
                'neutral'   => array( '#F5F5DC', '#FFFDD0', '#DEB887' ),
            ),
            'summer' => array(
                'preferred' => array( '#6C63FF', '#E8A0BF', '#B4E4FF', '#95D2B3', '#D0BFFF' ),
                'neutral'   => array( '#C0C0C0', '#F5F5F5', '#E6E6FA' ),
            ),
            'autumn' => array(
                'preferred' => array( '#B45309', '#78350F', '#365314', '#7C2D12', '#C2410C' ),
                'neutral'   => array( '#D4A574', '#8B7355', '#F5DEB3' ),
            ),
            'winter' => array(
                'preferred' => array( '#1E3A8A', '#831843', '#064E3B', '#000000', '#FFFFFF' ),
                'neutral'   => array( '#1F2937', '#374151', '#F9FAFB' ),
            ),
        );

        $palette = $palettes[ $color_type ] ?? $palettes['winter'];

        // Unbeliebte Farben entfernen
        if ( ! empty( $disliked ) ) {
            $palette['avoid'] = $disliked;
        }

        return $palette;
    }

    /**
     * Gibt Marken-Präferenzen zurück
     *
     * @return array
     */
    public function get_brand_preferences(): array {
        return $this->meta->get( 'favorite_brands', array() );
    }

    /**
     * Gibt das Budget zurück
     *
     * @return array
     */
    public function get_budget_range(): array {
        return array(
            'min' => (int) $this->meta->get( 'budget_min', 0 ),
            'max' => (int) $this->meta->get( 'budget_max', 0 ),
        );
    }

    /**
     * Gibt den Lifestyle-Kontext zurück
     *
     * @return array
     */
    public function get_lifestyle_context(): array {
        return array(
            'profession'        => $this->meta->get( 'profession', '' ),
            'typical_occasions' => $this->meta->get( 'typical_occasions', array() ),
            'work_environment'  => $this->detect_work_environment(),
        );
    }

    /**
     * Gibt den Körper-Kontext zurück
     *
     * @return array
     */
    public function get_body_context(): array {
        return array(
            'body_type' => $this->meta->get( 'body_type', '' ),
        );
    }

    /**
     * Zeichnet Feedback auf
     *
     * @param string $item_type Item-Typ.
     * @param int    $item_id   Item-ID.
     * @param bool   $positive  Positiv?
     * @param string $context   Kontext.
     */
    public function record_feedback( string $item_type, int $item_id, bool $positive, string $context = '' ): void {
        $feedback = $this->meta->get( 'ai_feedback', array() );

        $feedback[] = array(
            'type'      => $item_type,
            'item_id'   => $item_id,
            'positive'  => $positive,
            'context'   => $context,
            'timestamp' => current_time( 'mysql' ),
        );

        // Nur die letzten 100 Feedbacks behalten
        $feedback = array_slice( $feedback, -100 );

        $this->meta->set( 'ai_feedback', $feedback );
    }

    /**
     * Gibt Feedback-Historie zurück
     *
     * @param string|null $item_type Item-Typ-Filter.
     * @param int         $limit     Limit.
     * @return array
     */
    public function get_feedback_history( ?string $item_type = null, int $limit = 100 ): array {
        $feedback = $this->meta->get( 'ai_feedback', array() );

        if ( null !== $item_type ) {
            $feedback = array_filter( $feedback, fn( $f ) => $f['type'] === $item_type );
        }

        return array_slice( $feedback, -$limit );
    }

    /**
     * Berechnet Präferenz-Scores basierend auf Feedback
     *
     * @return array
     */
    public function calculate_preference_scores(): array {
        $feedback = $this->get_feedback_history();

        $scores = array(
            'categories' => array(),
            'colors'     => array(),
            'styles'     => array(),
        );

        foreach ( $feedback as $f ) {
            $delta = $f['positive'] ? 1 : -1;

            // Hier würden wir basierend auf dem Item-Typ Scores anpassen
            // Vereinfachte Implementierung
        }

        return $scores;
    }

    /**
     * Gibt Empfehlungs-Historie zurück
     *
     * @param int $limit Limit.
     * @return array
     */
    public function get_recommendation_history( int $limit = 50 ): array {
        $history = $this->meta->get( 'recommendation_history', array() );
        return array_slice( $history, -$limit );
    }

    /**
     * Aktualisiert Präferenzen basierend auf Feedback
     */
    public function update_preferences_from_feedback(): void {
        $scores = $this->calculate_preference_scores();

        // Präferenzen basierend auf Scores anpassen
        // Dies ist ein Platzhalter für komplexere ML-Logik
    }

    /**
     * Gibt negative Präferenzen zurück
     *
     * @return array
     */
    public function get_negative_preferences(): array {
        return array(
            'styles' => $this->meta->get( 'disliked_styles', array() ),
            'colors' => $this->meta->get( 'disliked_colors', array() ),
        );
    }

    /**
     * Merged explizite und implizite Präferenzen
     *
     * @return array
     */
    public function merge_explicit_implicit_preferences(): array {
        $explicit = array(
            'brands'    => $this->get_brand_preferences(),
            'styles'    => $this->get_style_preferences(),
            'colors'    => $this->get_color_preferences(),
            'dislikes'  => $this->get_negative_preferences(),
        );

        $implicit = $this->calculate_preference_scores();

        // Einfaches Merging - explizite Präferenzen haben Vorrang
        return array_merge_recursive( $implicit, $explicit );
    }

    /**
     * Gibt eine Garderobe-Zusammenfassung zurück
     *
     * @return array
     */
    public function get_wardrobe_summary(): array {
        $items = $this->db->get_user_wardrobe( $this->user_id );

        $summary = array(
            'total_items' => count( $items ),
            'categories'  => array(),
            'colors'      => array(),
            'brands'      => array(),
            'seasons'     => array(),
        );

        foreach ( $items as $item ) {
            // Kategorien
            $cat = $item->category ?? 'andere';
            $summary['categories'][ $cat ] = ( $summary['categories'][ $cat ] ?? 0 ) + 1;

            // Farben
            if ( ! empty( $item->color ) ) {
                $summary['colors'][ $item->color ] = ( $summary['colors'][ $item->color ] ?? 0 ) + 1;
            }

            // Marken
            if ( ! empty( $item->brand ) ) {
                $summary['brands'][ $item->brand ] = ( $summary['brands'][ $item->brand ] ?? 0 ) + 1;
            }

            // Saisons
            if ( ! empty( $item->season ) ) {
                $summary['seasons'][ $item->season ] = ( $summary['seasons'][ $item->season ] ?? 0 ) + 1;
            }
        }

        // Top-Werte sortieren
        arsort( $summary['colors'] );
        arsort( $summary['brands'] );

        $summary['dominant_colors'] = array_slice( array_keys( $summary['colors'] ), 0, 5 );
        $summary['frequent_brands'] = array_slice( array_keys( $summary['brands'] ), 0, 5 );

        return $summary;
    }

    /**
     * Gibt Interaktionsmuster zurück
     *
     * @return array
     */
    public function get_interaction_patterns(): array {
        // Analysiere vergangene Interaktionen
        $chat_history = $this->db->get_user_chat_history( $this->user_id, 50 );

        $patterns = array(
            'total_messages'   => count( $chat_history ),
            'avg_message_length' => 0,
            'common_topics'    => array(),
            'preferred_times'  => array(),
        );

        if ( ! empty( $chat_history ) ) {
            $total_length = 0;
            foreach ( $chat_history as $msg ) {
                if ( 'user' === $msg->role ) {
                    $total_length += strlen( $msg->content );
                }
            }
            $user_messages = array_filter( $chat_history, fn( $m ) => 'user' === $m->role );
            if ( count( $user_messages ) > 0 ) {
                $patterns['avg_message_length'] = $total_length / count( $user_messages );
            }
        }

        return $patterns;
    }

    /**
     * Prüft ob ein Thema vorgeschlagen werden sollte
     *
     * @param string $topic Thema.
     * @return bool
     */
    public function should_suggest_topic( string $topic ): bool {
        $context = $this->get_user_context();

        // Logik basierend auf Kontext
        switch ( $topic ) {
            case 'color_analysis':
                return empty( $context['color_type'] );

            case 'wardrobe_gaps':
                $ws = $context['wardrobe_summary'];
                return ( $ws['total_items'] ?? 0 ) >= 10;

            case 'capsule':
                return ! empty( $context['style_type'] );

            default:
                return true;
        }
    }

    /**
     * Gibt eine personalisierte Begrüßung zurück
     *
     * @return string
     */
    public function get_personalized_greeting(): string {
        $user    = new StyleGenius_User( $this->user_id );
        $name    = $user->get_display_name();
        $context = $this->get_user_context();

        $hour = (int) current_time( 'G' );

        if ( $hour < 12 ) {
            $greeting = __( 'Guten Morgen', 'stylegenius-pro' );
        } elseif ( $hour < 18 ) {
            $greeting = __( 'Guten Tag', 'stylegenius-pro' );
        } else {
            $greeting = __( 'Guten Abend', 'stylegenius-pro' );
        }

        $message = sprintf( '%s, %s!', $greeting, $name );

        // Kontextbezogene Ergänzung
        if ( empty( $context['style_type'] ) ) {
            $message .= ' ' . __( 'Hast du schon unser Style-Quiz gemacht?', 'stylegenius-pro' );
        } elseif ( empty( $context['color_type'] ) ) {
            $message .= ' ' . __( 'Wie wäre es mit einer Farbtyp-Analyse?', 'stylegenius-pro' );
        } else {
            $message .= ' ' . __( 'Wie kann ich dir heute bei deinem Style helfen?', 'stylegenius-pro' );
        }

        return $message;
    }

    /**
     * Gibt nächste Empfehlungen zurück
     *
     * @return array
     */
    public function get_next_recommendations(): array {
        $context = $this->get_user_context();
        $recommendations = array();

        // Fehlende Profil-Elemente
        if ( empty( $context['style_type'] ) ) {
            $recommendations[] = array(
                'type'        => 'quiz',
                'priority'    => 1,
                'title'       => __( 'Style-Quiz abschließen', 'stylegenius-pro' ),
                'description' => __( 'Finde deinen persönlichen Style-Typ', 'stylegenius-pro' ),
            );
        }

        if ( empty( $context['color_type'] ) ) {
            $recommendations[] = array(
                'type'        => 'color_analysis',
                'priority'    => 2,
                'title'       => __( 'Farbtyp bestimmen', 'stylegenius-pro' ),
                'description' => __( 'Entdecke deine perfekte Farbpalette', 'stylegenius-pro' ),
            );
        }

        // Garderobe-Empfehlungen
        $ws = $context['wardrobe_summary'];
        if ( ( $ws['total_items'] ?? 0 ) < 10 ) {
            $recommendations[] = array(
                'type'        => 'wardrobe',
                'priority'    => 3,
                'title'       => __( 'Garderobe aufbauen', 'stylegenius-pro' ),
                'description' => __( 'Füge mehr Teile hinzu für bessere Empfehlungen', 'stylegenius-pro' ),
            );
        }

        // Nach Priorität sortieren
        usort( $recommendations, fn( $a, $b ) => $a['priority'] <=> $b['priority'] );

        return $recommendations;
    }

    /**
     * Gibt den Farbtyp zurück
     *
     * @return string
     */
    private function get_color_type(): string {
        global $wpdb;

        $table = $this->db->get_table_name( 'color_profiles' );
        $result = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT color_type FROM {$table} WHERE user_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $this->user_id
            )
        );

        return $result ?? '';
    }

    /**
     * Erkennt das Arbeitsumfeld basierend auf Beruf
     *
     * @return string
     */
    private function detect_work_environment(): string {
        $profession = strtolower( $this->meta->get( 'profession', '' ) );

        $corporate_keywords = array( 'manager', 'director', 'executive', 'consultant', 'banker', 'anwalt', 'rechtsanwalt' );
        $creative_keywords  = array( 'designer', 'künstler', 'kreativ', 'marketing', 'media', 'agentur' );
        $tech_keywords      = array( 'entwickler', 'engineer', 'tech', 'it', 'software', 'startup' );

        foreach ( $corporate_keywords as $keyword ) {
            if ( strpos( $profession, $keyword ) !== false ) {
                return 'corporate';
            }
        }

        foreach ( $creative_keywords as $keyword ) {
            if ( strpos( $profession, $keyword ) !== false ) {
                return 'creative';
            }
        }

        foreach ( $tech_keywords as $keyword ) {
            if ( strpos( $profession, $keyword ) !== false ) {
                return 'tech';
            }
        }

        return 'general';
    }

    /**
     * Gibt das Label für einen Style-Typ zurück
     *
     * @param string $type Style-Typ.
     * @return string
     */
    private function get_style_type_label( string $type ): string {
        $labels = array(
            'power_executive'       => __( 'Power Executive', 'stylegenius-pro' ),
            'creative_professional' => __( 'Creative Professional', 'stylegenius-pro' ),
            'classic_traditionalist'=> __( 'Classic Traditionalist', 'stylegenius-pro' ),
            'modern_minimalist'     => __( 'Modern Minimalist', 'stylegenius-pro' ),
            'smart_casual_expert'   => __( 'Smart Casual Expert', 'stylegenius-pro' ),
            'trendsetter'           => __( 'Trendsetter', 'stylegenius-pro' ),
        );

        return $labels[ $type ] ?? $type;
    }
}
