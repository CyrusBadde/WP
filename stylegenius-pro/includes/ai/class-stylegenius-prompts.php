<?php
/**
 * Prompts-Klasse
 *
 * Enthält alle KI-Prompts für StyleGenius Pro (auf Deutsch).
 *
 * @package StyleGenius_Pro
 * @subpackage StyleGenius_Pro/includes/ai
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class StyleGenius_Prompts
 */
class StyleGenius_Prompts {

    /**
     * Benutzerdefinierte Prompts
     *
     * @var array
     */
    private array $custom_prompts = array();

    /**
     * Konstruktor
     */
    public function __construct() {
        $this->custom_prompts = get_option( 'stylegenius_custom_prompts', array() );
    }

    /**
     * Gibt den System-Prompt für einen bestimmten Typ zurück
     *
     * @param string $type Prompt-Typ.
     * @return string
     */
    public function get_system_prompt( string $type ): string {
        // Prüfe auf benutzerdefinierten Prompt
        if ( ! empty( $this->custom_prompts[ $type ] ) ) {
            return $this->custom_prompts[ $type ];
        }

        $method = 'get_' . $type . '_system_prompt';
        if ( method_exists( $this, $method ) ) {
            return $this->$method();
        }

        return $this->get_chat_system_prompt();
    }

    /**
     * Chat-System-Prompt
     *
     * @return string
     */
    public function get_chat_system_prompt(): string {
        return <<<'PROMPT'
Du bist StyleGenius, ein professioneller KI-Styling-Berater für Business-Professionals im deutschsprachigen Raum. Du hilfst Menschen dabei, ihren persönlichen Stil zu entwickeln und professionell aufzutreten.

Deine Persönlichkeit:
- Freundlich, professionell und kompetent
- Du sprichst den Nutzer mit "Du" an
- Du verwendest eine klare, verständliche Sprache ohne übermäßigen Fachjargon
- Du bist ermutigend und positiv, aber ehrlich
- Du berücksichtigst immer den deutschen/europäischen Business-Kontext

Deine Expertise umfasst:
- Business-Casual und Corporate Dresscodes
- Farbberatung und Farbkombinationen
- Körpertypen und vorteilhafte Schnitte
- Aktuelle Mode-Trends im Business-Bereich
- Capsule Wardrobes und Outfit-Kombinationen
- Accessoire-Beratung
- Anlass-gerechte Kleidung

Wichtige Regeln:
1. Beziehe dich immer auf den Kontext des Nutzers (Style-Typ, Farbtyp, Beruf, etc.)
2. Gib konkrete, umsetzbare Tipps
3. Schlage wenn passend spezifische Kleidungsstücke oder Kombinationen vor
4. Frage nach, wenn dir wichtige Informationen fehlen
5. Berücksichtige das Budget des Nutzers
6. Empfehle nachhaltige und zeitlose Optionen
7. Vermeide stereotype Aussagen über Geschlecht oder Körperform
8. Formatiere deine Antworten übersichtlich mit Aufzählungen wenn sinnvoll

Antwortformat:
- Halte Antworten fokussiert und auf den Punkt
- Nutze Emojis sparsam und nur wenn passend
- Strukturiere längere Antworten mit Absätzen oder Listen
PROMPT;
    }

    /**
     * Erstellt den Kontext-Prompt für einen Benutzer
     *
     * @param array $user_data Benutzerdaten.
     * @return string
     */
    public function get_chat_context_prompt( array $user_data ): string {
        $context_parts = array();

        if ( ! empty( $user_data['style_type'] ) ) {
            $style_desc = $this->get_style_type_description( $user_data['style_type'] );
            $context_parts[] = "Style-Typ: {$user_data['style_type']} - {$style_desc}";
        }

        if ( ! empty( $user_data['color_type'] ) ) {
            $context_parts[] = "Farbtyp: {$user_data['color_type']}";
        }

        if ( ! empty( $user_data['profession'] ) ) {
            $context_parts[] = "Beruf: {$user_data['profession']}";
        }

        if ( ! empty( $user_data['body_type'] ) ) {
            $context_parts[] = "Körpertyp: {$user_data['body_type']}";
        }

        if ( ! empty( $user_data['budget_min'] ) || ! empty( $user_data['budget_max'] ) ) {
            $budget_min = $user_data['budget_min'] ?? 0;
            $budget_max = $user_data['budget_max'] ?? 'unbegrenzt';
            $context_parts[] = "Budget: {$budget_min}€ - {$budget_max}€";
        }

        if ( ! empty( $user_data['favorite_brands'] ) ) {
            $brands = implode( ', ', $user_data['favorite_brands'] );
            $context_parts[] = "Lieblingsmarken: {$brands}";
        }

        if ( ! empty( $user_data['typical_occasions'] ) ) {
            $occasions = implode( ', ', $user_data['typical_occasions'] );
            $context_parts[] = "Typische Anlässe: {$occasions}";
        }

        if ( ! empty( $user_data['disliked_styles'] ) ) {
            $dislikes = implode( ', ', $user_data['disliked_styles'] );
            $context_parts[] = "Mag nicht: {$dislikes}";
        }

        if ( ! empty( $user_data['wardrobe_summary'] ) ) {
            $context_parts[] = "Garderobe-Zusammenfassung: {$user_data['wardrobe_summary']}";
        }

        if ( empty( $context_parts ) ) {
            return '';
        }

        return "\n\nKontext des Nutzers:\n" . implode( "\n", $context_parts );
    }

    /**
     * Quiz-Analyse-Prompt
     *
     * @param array $answers Quiz-Antworten.
     * @return string
     */
    public function get_quiz_analysis_prompt( array $answers ): string {
        $answers_text = wp_json_encode( $answers, JSON_UNESCAPED_UNICODE );

        return <<<PROMPT
Analysiere die folgenden Quiz-Antworten und bestimme den Style-Typ des Nutzers.

Quiz-Antworten:
{$answers_text}

Die möglichen Style-Typen sind:
1. Power Executive - Autoritär, klassisch, hochwertig. Dunkle Anzüge, klassische Schnitte, dezente Accessoires.
2. Creative Professional - Kreativ, individuell, trendbewusst. Ungewöhnliche Kombinationen, Statement-Pieces, Farbtupfer.
3. Classic Traditionalist - Zeitlos, konservativ, elegant. Klassische Schnitte, neutrale Farben, traditionelle Muster.
4. Modern Minimalist - Reduziert, clean, modern. Klare Linien, monochromatische Looks, hochwertige Basics.
5. Smart Casual Expert - Entspannt professionell, vielseitig. Mix aus casual und business, hochwertige Alltagskleidung.
6. Trendsetter - Modebewusst, experimentierfreudig, auffällig. Aktuelle Trends, mutige Kombinationen, Fashion-Forward.

Aufgabe:
1. Analysiere die Antworten und ordne sie einem Style-Typ zu
2. Erkläre kurz, warum dieser Typ passt (2-3 Sätze)
3. Gib 3 personalisierte Style-Tipps basierend auf dem Typ

Antworte im JSON-Format:
{
    "style_type": "power_executive",
    "confidence": 85,
    "reasoning": "Kurze Erklärung...",
    "tips": ["Tipp 1", "Tipp 2", "Tipp 3"],
    "keywords": ["elegant", "klassisch", "hochwertig"]
}
PROMPT;
    }

    /**
     * Kleidungsstück-Analyse-Prompt
     *
     * @return string
     */
    public function get_clothing_analysis_prompt(): string {
        return <<<'PROMPT'
Analysiere dieses Kleidungsstück im Detail.

Bitte identifiziere und beschreibe:
1. Kategorie (Oberteil, Hose, Kleid, Jacke, Schuhe, Accessoire, etc.)
2. Subkategorie (z.B. Blazer, Chino, Midi-Kleid, Sneaker, etc.)
3. Hauptfarbe und eventuelle Sekundärfarben (als HEX-Werte wenn möglich)
4. Muster (uni, gestreift, kariert, geblümt, etc.)
5. Material (geschätzt: Baumwolle, Wolle, Seide, Synthetik, Leder, etc.)
6. Stil (casual, business, elegant, sportlich, etc.)
7. Passende Anlässe
8. Passende Jahreszeiten
9. Qualitätseindruck (1-5)
10. Empfohlene Kombinationen (3-5 Vorschläge)

Antworte im JSON-Format:
{
    "category": "tops",
    "subcategory": "blazer",
    "color": "#1A365D",
    "colors_secondary": ["#FFFFFF"],
    "pattern": "uni",
    "material": "wolle",
    "style": "business",
    "occasions": ["büro", "meeting", "geschäftsessen"],
    "seasons": ["herbst", "winter", "frühling"],
    "quality_score": 4,
    "combination_suggestions": [
        "Weiße Bluse + dunkle Jeans",
        "Hellblaues Hemd + graue Stoffhose",
        "Rollkragenpullover + Stoffhose"
    ],
    "description": "Kurze Beschreibung des Stücks"
}
PROMPT;
    }

    /**
     * Outfit-Analyse-Prompt
     *
     * @param string|null $occasion Anlass.
     * @return string
     */
    public function get_outfit_analysis_prompt( ?string $occasion = null ): string {
        $occasion_text = $occasion ? "für den Anlass: {$occasion}" : '';

        return <<<PROMPT
Analysiere dieses Outfit-Foto {$occasion_text}.

Bewerte das Outfit nach folgenden Kriterien (jeweils 1-100 Punkte):

1. **Passform** (25 Punkte max): Sitzt die Kleidung gut? Richtige Größe? Vorteilhafte Proportionen?
2. **Farbkombination** (25 Punkte max): Harmonieren die Farben? Passend zum vermuteten Farbtyp?
3. **Stil-Kohärenz** (25 Punkte max): Passt alles zusammen? Konsistenter Style?
4. **Accessoires & Details** (15 Punkte max): Sinnvolle Ergänzungen? Nicht überladen?
5. **Anlass-Angemessenheit** (10 Punkte max): Passend für den genannten/vermuteten Anlass?

Gib dann konkrete Verbesserungsvorschläge.

Antworte im JSON-Format:
{
    "overall_score": 75,
    "scores": {
        "fit": 18,
        "color": 20,
        "style": 22,
        "accessories": 10,
        "occasion": 5
    },
    "analysis": {
        "positives": ["Was gut funktioniert...", "..."],
        "negatives": ["Was verbessert werden könnte...", "..."]
    },
    "improvements": [
        {
            "priority": 1,
            "area": "Passform",
            "issue": "Das Sakko ist etwas zu weit",
            "suggestion": "Ein taillierter Schnitt würde die Silhouette verbessern"
        }
    ],
    "quick_wins": ["Sofort umsetzbare Verbesserungen..."],
    "overall_feedback": "Zusammenfassende Einschätzung in 2-3 Sätzen"
}
PROMPT;
    }

    /**
     * Farbanalyse-Prompt
     *
     * @return string
     */
    public function get_color_analysis_prompt(): string {
        return <<<'PROMPT'
Analysiere dieses Selfie für eine Farbtyp-Bestimmung.

Untersuche folgende Merkmale:
1. Hautunterton (warm/kühl/neutral)
2. Kontraststärke zwischen Haut, Haaren und Augen (niedrig/mittel/hoch)
3. Haarfarbe und -intensität
4. Augenfarbe

Bestimme daraus den Farbtyp:
- Frühling (warm, hell): Warme, klare, helle Farben
- Sommer (kühl, gedämpft): Kühle, gedämpfte, sanfte Farben
- Herbst (warm, gedämpft): Warme, erdige, satte Farben
- Winter (kühl, klar): Kühle, klare, intensive Farben

Und den Subtyp (wenn erkennbar):
- Light (hell)
- True/Pure (rein)
- Deep (tief/dunkel)
- Soft (gedämpft)
- Clear/Bright (klar)
- Warm (warm)
- Cool (kühl)

Antworte im JSON-Format:
{
    "undertone": "warm",
    "contrast_level": "mittel",
    "color_type": "herbst",
    "color_subtype": "soft",
    "confidence": 80,
    "analysis": {
        "skin": "Beschreibung Hautton...",
        "hair": "Beschreibung Haarfarbe...",
        "eyes": "Beschreibung Augenfarbe...",
        "overall": "Zusammenfassung..."
    },
    "best_colors": [
        {"name": "Olivgrün", "hex": "#808000"},
        {"name": "Terrakotta", "hex": "#E2725B"},
        {"name": "Senfgelb", "hex": "#FFDB58"}
    ],
    "neutral_colors": [
        {"name": "Creme", "hex": "#FFFDD0"},
        {"name": "Kamel", "hex": "#C19A6B"}
    ],
    "avoid_colors": [
        {"name": "Neonpink", "hex": "#FF6EC7"},
        {"name": "Eisblau", "hex": "#99FFFF"}
    ],
    "metal_recommendation": "gold",
    "tips": ["Persönliche Styling-Tipps basierend auf dem Farbtyp..."]
}
PROMPT;
    }

    /**
     * Capsule-Generierungs-Prompt
     *
     * @param string $season      Saison.
     * @param array  $preferences Präferenzen.
     * @return string
     */
    public function get_capsule_generation_prompt( string $season, array $preferences ): string {
        $prefs_text = ! empty( $preferences ) ? wp_json_encode( $preferences, JSON_UNESCAPED_UNICODE ) : 'keine besonderen';

        return <<<PROMPT
Erstelle eine Capsule Wardrobe für die Saison: {$season}

Präferenzen des Nutzers: {$prefs_text}

Eine ideale Business Capsule Wardrobe enthält:
- 3-4 Oberteile (Blusen/Hemden)
- 2-3 Strickteile
- 2-3 Hosen/Röcke
- 1-2 Blazer/Jacken
- 1-2 Kleider (optional)
- 2-3 Schuhe
- Passende Accessoires

Kriterien:
1. Alle Teile müssen untereinander kombinierbar sein
2. Neutrale Basisfarben mit 1-2 Akzentfarben
3. Hochwertige, zeitlose Stücke
4. Passend zur Saison und Business-Kontext
5. Mindestens 15-20 verschiedene Outfits möglich

Antworte im JSON-Format:
{
    "name": "Frühling Business Capsule",
    "season": "frühling",
    "color_palette": {
        "base_colors": ["#000000", "#FFFFFF", "#1A365D"],
        "accent_colors": ["#E53E3E"]
    },
    "items": [
        {
            "category": "tops",
            "name": "Weiße Bluse",
            "description": "Klassische Hemdbluse aus Baumwolle",
            "color": "#FFFFFF",
            "versatility": 5
        }
    ],
    "outfit_combinations": [
        {
            "name": "Meeting Monday",
            "items": ["Weiße Bluse", "Navy Blazer", "Graue Stoffhose"],
            "occasion": "business_meeting"
        }
    ],
    "total_outfits": 20,
    "missing_pieces": ["Empfohlene Ergänzungen falls Lücken erkannt"],
    "budget_estimate": {
        "low": 500,
        "mid": 1000,
        "high": 2000
    }
}
PROMPT;
    }

    /**
     * Shopping-Empfehlungs-Prompt
     *
     * @param array $user_profile Benutzerprofil.
     * @param array $filters      Filter.
     * @return string
     */
    public function get_shopping_prompt( array $user_profile, array $filters ): string {
        $profile_text = wp_json_encode( $user_profile, JSON_UNESCAPED_UNICODE );
        $filters_text = wp_json_encode( $filters, JSON_UNESCAPED_UNICODE );

        return <<<PROMPT
Basierend auf dem Nutzerprofil und den Filtern, empfehle passende Kleidungsstücke.

Nutzerprofil: {$profile_text}
Filter: {$filters_text}

Berücksichtige:
1. Style-Typ und Farbtyp des Nutzers
2. Vorhandene Garderobe (keine Duplikate)
3. Erkannte Lücken in der Garderobe
4. Budget-Rahmen
5. Bevorzugte Marken
6. Unbeliebte Stile/Farben meiden

Erstelle eine Liste von Empfehlungen mit:
- Spezifischer Produktbeschreibung
- Begründung warum es passt
- Wie es kombiniert werden kann
- Preisrahmen

Antworte im JSON-Format:
{
    "recommendations": [
        {
            "type": "hose",
            "description": "Marineblaue Stoffhose mit geradem Schnitt",
            "reason": "Ergänzt perfekt deine vorhandenen Blazer",
            "combinations": ["Mit weißer Bluse", "Mit hellblauem Hemd"],
            "priority": "hoch",
            "price_range": {
                "min": 60,
                "max": 150
            },
            "search_keywords": ["marineblaue stoffhose damen", "navy chino business"]
        }
    ],
    "total_budget_needed": 350,
    "priority_purchase": "marineblaue Stoffhose",
    "shopping_tips": ["Achte auf..."]
}
PROMPT;
    }

    /**
     * Before/After-Analyse-Prompt
     *
     * @param string $occasion Anlass.
     * @return string
     */
    public function get_before_after_prompt( string $occasion ): string {
        return <<<PROMPT
Analysiere dieses Outfit für den Anlass: {$occasion}

Erstelle eine detaillierte Vorher/Nachher-Analyse mit:

1. VORHER-BEWERTUNG (aktueller Zustand):
   - Gesamteindruck (1-100)
   - Was funktioniert gut
   - Was könnte verbessert werden

2. NACHHER-EMPFEHLUNG:
   - Konkrete Verbesserungsvorschläge
   - Alternative Kombinationen mit ähnlichen Teilen
   - Priorisierte Änderungen (was bringt am meisten)

3. VISUALISIERUNG:
   - Beschreibe wie das optimierte Outfit aussehen würde

Antworte im JSON-Format:
{
    "before": {
        "score": 65,
        "strengths": ["Gute Farbwahl", "Passende Schuhe"],
        "weaknesses": ["Sakko zu groß", "Krawatte unpassend"]
    },
    "after": {
        "projected_score": 88,
        "changes": [
            {
                "priority": 1,
                "what": "Sakko tauschen oder ändern lassen",
                "why": "Verbessert die Silhouette erheblich",
                "impact": "+15 Punkte"
            }
        ],
        "description": "Mit den Änderungen würde das Outfit..."
    },
    "quick_fixes": [
        "Hemdärmel unter dem Sakko zeigen lassen",
        "Gürtel farblich auf Schuhe abstimmen"
    ],
    "investment_fixes": [
        "Maßanfertigung Sakko: ca. 300-500€"
    ],
    "occasion_specific": "Für {$occasion} besonders wichtig..."
}
PROMPT;
    }

    /**
     * Outfit-Vorschlags-Prompt
     *
     * @param string $occasion          Anlass.
     * @param array  $wardrobe_summary Garderobe-Zusammenfassung.
     * @return string
     */
    public function get_outfit_suggestion_prompt( string $occasion, array $wardrobe_summary ): string {
        $wardrobe_text = wp_json_encode( $wardrobe_summary, JSON_UNESCAPED_UNICODE );

        return <<<PROMPT
Erstelle einen Outfit-Vorschlag für den Anlass: {$occasion}

Verfügbare Garderobe:
{$wardrobe_text}

Erstelle 3 verschiedene Outfit-Vorschläge, sortiert nach Passung für den Anlass.

Für jeden Vorschlag:
1. Wähle passende Teile aus der Garderobe
2. Erkläre warum die Kombination funktioniert
3. Gib Styling-Tipps
4. Bewerte die Anlass-Angemessenheit

Antworte im JSON-Format:
{
    "occasion": "{$occasion}",
    "suggestions": [
        {
            "rank": 1,
            "name": "Professional Power Look",
            "items": [
                {"id": 123, "name": "Navy Blazer"},
                {"id": 456, "name": "Weiße Bluse"},
                {"id": 789, "name": "Graue Stoffhose"}
            ],
            "why_it_works": "Klassische Business-Kombination...",
            "styling_tips": ["Ärmel umkrempeln für lässigeren Look"],
            "occasion_score": 95,
            "accessories_suggestion": "Dezente Goldkette, Lederuhr"
        }
    ],
    "missing_for_occasion": "Ein beigefarbener Trenchcoat würde diese Garderobe perfekt ergänzen",
    "weather_note": "Bei kühlerem Wetter empfehle ich..."
}
PROMPT;
    }

    /**
     * Kombinations-Prompt
     *
     * @param array $item_details   Details des aktuellen Teils.
     * @param array $wardrobe_items Andere Garderobe-Teile.
     * @return string
     */
    public function get_combination_prompt( array $item_details, array $wardrobe_items ): string {
        $item_text     = wp_json_encode( $item_details, JSON_UNESCAPED_UNICODE );
        $wardrobe_text = wp_json_encode( $wardrobe_items, JSON_UNESCAPED_UNICODE );

        return <<<PROMPT
Finde die besten Kombinationen für dieses Kleidungsstück:

Ausgewähltes Teil:
{$item_text}

Verfügbare Garderobe:
{$wardrobe_text}

Erstelle 5 verschiedene Outfit-Kombinationen, von klassisch bis kreativ.

Antworte im JSON-Format:
{
    "selected_item": "Name des Teils",
    "combinations": [
        {
            "name": "Business Classic",
            "items": [
                {"id": 123, "role": "hauptteil"},
                {"id": 456, "role": "oberteil"},
                {"id": 789, "role": "unterteil"}
            ],
            "style": "business",
            "occasions": ["büro", "meeting"],
            "why_it_works": "Erklärung...",
            "difficulty": "einfach"
        }
    ],
    "styling_rules": [
        "Dieses Teil funktioniert am besten mit...",
        "Vermeide Kombinationen mit..."
    ],
    "color_matches": ["#1A365D", "#FFFFFF"],
    "color_clashes": ["#FF0000"]
}
PROMPT;
    }

    /**
     * Garderobe-Lücken-Analyse-Prompt
     *
     * @param array  $wardrobe_summary Garderobe-Zusammenfassung.
     * @param string $style_type       Style-Typ.
     * @return string
     */
    public function get_wardrobe_gap_analysis_prompt( array $wardrobe_summary, string $style_type ): string {
        $wardrobe_text = wp_json_encode( $wardrobe_summary, JSON_UNESCAPED_UNICODE );
        $style_desc    = $this->get_style_type_description( $style_type );

        return <<<PROMPT
Analysiere die Garderobe auf Lücken und Verbesserungspotential.

Garderobe-Zusammenfassung:
{$wardrobe_text}

Style-Typ: {$style_type}
{$style_desc}

Analysiere:
1. Welche Basis-Teile fehlen für eine vollständige Business-Garderobe?
2. Welche Teile würden die Kombinationsmöglichkeiten erhöhen?
3. Welche Farbenlücken gibt es?
4. Was passt zum Style-Typ, fehlt aber?

Antworte im JSON-Format:
{
    "overall_assessment": {
        "completeness": 70,
        "versatility": 60,
        "style_consistency": 80
    },
    "critical_gaps": [
        {
            "category": "blazer",
            "description": "Ein gut sitzender Blazer in Dunkelblau fehlt",
            "priority": "hoch",
            "reason": "Basis für jeden Business-Look",
            "budget_estimate": "150-400€"
        }
    ],
    "nice_to_have": [
        {
            "category": "accessoires",
            "description": "Statement-Kette für mehr Varianz",
            "priority": "niedrig"
        }
    ],
    "color_gaps": ["Neutrale Erdtöne fehlen"],
    "style_type_recommendations": [
        "Für deinen {$style_type} Style empfehle ich zusätzlich..."
    ],
    "duplicate_warning": ["Du hast 5 weiße Blusen - vielleicht reichen 3?"],
    "action_plan": [
        "1. Zuerst: Navy Blazer kaufen",
        "2. Dann: Stoffhose in Grau ergänzen"
    ]
}
PROMPT;
    }

    /**
     * Setzt einen benutzerdefinierten Prompt
     *
     * @param string $type   Prompt-Typ.
     * @param string $prompt Neuer Prompt.
     * @return bool
     */
    public function set_custom_prompt( string $type, string $prompt ): bool {
        $validation = $this->validate_prompt( $prompt );
        if ( is_wp_error( $validation ) ) {
            return false;
        }

        $this->custom_prompts[ $type ] = $prompt;
        return update_option( 'stylegenius_custom_prompts', $this->custom_prompts );
    }

    /**
     * Gibt einen benutzerdefinierten Prompt zurück
     *
     * @param string $type Prompt-Typ.
     * @return string
     */
    public function get_custom_prompt( string $type ): string {
        return $this->custom_prompts[ $type ] ?? '';
    }

    /**
     * Setzt einen Prompt auf Standard zurück
     *
     * @param string $type Prompt-Typ.
     * @return bool
     */
    public function reset_to_default( string $type ): bool {
        unset( $this->custom_prompts[ $type ] );
        return update_option( 'stylegenius_custom_prompts', $this->custom_prompts );
    }

    /**
     * Gibt die Style-Typ-Beschreibung zurück
     *
     * @param string $type Style-Typ.
     * @return string
     */
    private function get_style_type_description( string $type ): string {
        $descriptions = array(
            'power_executive'      => 'Bevorzugt klassische, hochwertige Business-Kleidung. Setzt auf Autorität und Professionalität. Dunkle Farben, klassische Schnitte, dezente Accessoires.',
            'creative_professional'=> 'Verbindet Professionalität mit Individualität. Mag ungewöhnliche Kombinationen, Statement-Pieces und Farbtupfer im Business-Look.',
            'classic_traditionalist'=> 'Zeitloser, konservativer Stil. Setzt auf bewährte Klassiker, neutrale Farben und traditionelle Muster wie Streifen oder Karos.',
            'modern_minimalist'    => 'Liebt klare Linien und reduzierte Looks. Bevorzugt monochromatische Outfits und hochwertige Basics. Weniger ist mehr.',
            'smart_casual_expert'  => 'Meistert den Mix aus casual und business perfekt. Vielseitige Garderobe für verschiedene Anlässe. Entspannte Eleganz.',
            'trendsetter'          => 'Immer am Puls der Zeit. Experimentiert gerne mit aktuellen Trends und mutigen Kombinationen. Fashion-Forward im Business-Kontext.',
        );

        return $descriptions[ $type ] ?? '';
    }

    /**
     * Gibt die Farbpalette-Beschreibung für einen Farbtyp zurück
     *
     * @param string $color_type Farbtyp.
     * @return string
     */
    private function get_color_palette_prompt_part( string $color_type ): string {
        $palettes = array(
            'spring' => 'Warme, klare, helle Farben wie Koralle, Türkis, Warmweiß, Goldgelb.',
            'summer' => 'Kühle, gedämpfte Farben wie Lavendel, Rosenholz, Graublau, Mintgrün.',
            'autumn' => 'Warme, erdige Farben wie Terrakotta, Olivgrün, Senfgelb, Rostbraun.',
            'winter' => 'Kühle, klare, intensive Farben wie Royalblau, Magenta, Reinweiß, Schwarz.',
        );

        return $palettes[ $color_type ] ?? '';
    }

    /**
     * Gibt alle verfügbaren Prompt-Typen zurück
     *
     * @return array
     */
    public function get_all_prompt_types(): array {
        return array(
            'chat'             => __( 'Chat-System-Prompt', 'stylegenius-pro' ),
            'quiz_analysis'    => __( 'Quiz-Analyse', 'stylegenius-pro' ),
            'clothing_analysis'=> __( 'Kleidungsstück-Analyse', 'stylegenius-pro' ),
            'outfit_analysis'  => __( 'Outfit-Analyse', 'stylegenius-pro' ),
            'color_analysis'   => __( 'Farbtyp-Analyse', 'stylegenius-pro' ),
            'capsule'          => __( 'Capsule-Generierung', 'stylegenius-pro' ),
            'shopping'         => __( 'Shopping-Empfehlungen', 'stylegenius-pro' ),
            'before_after'     => __( 'Vorher/Nachher-Analyse', 'stylegenius-pro' ),
            'outfit_suggestion'=> __( 'Outfit-Vorschlag', 'stylegenius-pro' ),
            'combination'      => __( 'Kombinationsvorschläge', 'stylegenius-pro' ),
            'gap_analysis'     => __( 'Lücken-Analyse', 'stylegenius-pro' ),
        );
    }

    /**
     * Validiert einen Prompt
     *
     * @param string $prompt Zu validierender Prompt.
     * @return bool|WP_Error
     */
    public function validate_prompt( string $prompt ): bool|WP_Error {
        if ( empty( trim( $prompt ) ) ) {
            return new WP_Error( 'empty_prompt', __( 'Der Prompt darf nicht leer sein.', 'stylegenius-pro' ) );
        }

        if ( strlen( $prompt ) < 50 ) {
            return new WP_Error( 'prompt_too_short', __( 'Der Prompt ist zu kurz (min. 50 Zeichen).', 'stylegenius-pro' ) );
        }

        if ( strlen( $prompt ) > 10000 ) {
            return new WP_Error( 'prompt_too_long', __( 'Der Prompt ist zu lang (max. 10.000 Zeichen).', 'stylegenius-pro' ) );
        }

        return true;
    }
}
