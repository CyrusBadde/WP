<?php
/**
 * StyleGenius Quiz Class
 *
 * Handles the Style Quiz functionality for determining user's style type.
 *
 * @package    StyleGenius_Pro
 * @subpackage StyleGenius_Pro/includes/features
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Style Quiz management class.
 */
class StyleGenius_Quiz {

    /**
     * Database instance.
     *
     * @var StyleGenius_Database
     */
    private $db;

    /**
     * Points instance.
     *
     * @var StyleGenius_Points
     */
    private $points;

    /**
     * Achievements instance.
     *
     * @var StyleGenius_Achievements
     */
    private $achievements;

    /**
     * Quiz questions.
     *
     * @var array
     */
    private $questions;

    /**
     * Style types definitions.
     *
     * @var array
     */
    private $style_types = array(
        'classic' => array(
            'name'        => 'Klassisch',
            'description' => 'Zeitloser Stil mit eleganten Basics und hochwertigen Materialien. Du setzt auf bewährte Schnitte und gediegene Farben.',
            'colors'      => array('#1a1a2e', '#16213e', '#0f3460', '#e94560', '#f5f5f5'),
            'keywords'    => array('zeitlos', 'elegant', 'hochwertig', 'traditionell', 'seriös'),
            'clothing'    => array('Blazer', 'Hemden', 'Stoffhosen', 'Pumps', 'Ledertaschen'),
            'brands'      => array('Max Mara', 'Boss', 'Ralph Lauren', 'Jil Sander'),
            'icon'        => 'crown',
        ),
        'creative' => array(
            'name'        => 'Kreativ',
            'description' => 'Experimentierfreudiger Stil mit ungewöhnlichen Kombinationen und Statement-Pieces. Du liebst es, aufzufallen und Trends zu setzen.',
            'colors'      => array('#ff6b6b', '#feca57', '#48dbfb', '#ff9ff3', '#54a0ff'),
            'keywords'    => array('mutig', 'experimentell', 'künstlerisch', 'individuell', 'ausdrucksstark'),
            'clothing'    => array('Statement-Jacken', 'Oversized', 'Prints', 'Layering', 'Accessoires'),
            'brands'      => array('Comme des Garçons', 'Maison Margiela', 'Acne Studios', 'Issey Miyake'),
            'icon'        => 'palette',
        ),
        'natural' => array(
            'name'        => 'Natürlich',
            'description' => 'Entspannter Stil mit Fokus auf Komfort und natürliche Materialien. Du bevorzugst erdige Töne und nachhaltige Mode.',
            'colors'      => array('#2d5016', '#6b8e23', '#daa520', '#8b4513', '#f5deb3'),
            'keywords'    => array('entspannt', 'nachhaltig', 'komfortabel', 'authentisch', 'erdverbunden'),
            'clothing'    => array('Strickwaren', 'Leinen', 'Baumwolle', 'Sneaker', 'Stoffbeutel'),
            'brands'      => array('Patagonia', 'Everlane', 'Arket', 'COS'),
            'icon'        => 'leaf',
        ),
        'romantic' => array(
            'name'        => 'Romantisch',
            'description' => 'Verspielter, femininer Stil mit floralen Prints, fließenden Stoffen und zarten Details. Du liebst das Weibliche und Verspielte.',
            'colors'      => array('#ffc0cb', '#ffb6c1', '#ff69b4', '#db7093', '#fff0f5'),
            'keywords'    => array('feminin', 'verspielt', 'romantisch', 'sanft', 'träumerisch'),
            'clothing'    => array('Kleider', 'Röcke', 'Rüschen', 'Spitze', 'Schmuck'),
            'brands'      => array('Zimmermann', 'Self-Portrait', 'Reformation', 'Sézane'),
            'icon'        => 'heart',
        ),
        'dramatic' => array(
            'name'        => 'Dramatisch',
            'description' => 'Kraftvoller Stil mit klaren Linien, starken Kontrasten und selbstbewusstem Auftreten. Du machst mit deiner Mode ein Statement.',
            'colors'      => array('#000000', '#ffffff', '#ff0000', '#c0c0c0', '#800000'),
            'keywords'    => array('kraftvoll', 'selbstbewusst', 'edgy', 'auffällig', 'markant'),
            'clothing'    => array('Leder', 'Schwarz', 'Strukturierte Schultern', 'Statement-Accessoires', 'Boots'),
            'brands'      => array('Alexander McQueen', 'Rick Owens', 'Balmain', 'Saint Laurent'),
            'icon'        => 'bolt',
        ),
        'sporty' => array(
            'name'        => 'Sportlich',
            'description' => 'Dynamischer Stil mit Fokus auf Funktionalität und moderne Casual-Wear. Du vereinst Komfort mit urbanem Chic.',
            'colors'      => array('#2c3e50', '#3498db', '#e74c3c', '#ecf0f1', '#1abc9c'),
            'keywords'    => array('aktiv', 'dynamisch', 'modern', 'praktisch', 'urban'),
            'clothing'    => array('Sneaker', 'Jogginghosen', 'Trainingsanzüge', 'Caps', 'Rucksäcke'),
            'brands'      => array('Nike', 'Adidas', 'Lululemon', 'Under Armour'),
            'icon'        => 'running',
        ),
    );

    /**
     * Constructor.
     */
    public function __construct() {
        $this->db = new StyleGenius_Database();
        $this->points = new StyleGenius_Points();
        $this->achievements = new StyleGenius_Achievements();
        $this->init_questions();
    }

    /**
     * Initialize quiz questions.
     *
     * @return void
     */
    private function init_questions(): void {
        $this->questions = array(
            array(
                'id'       => 1,
                'question' => 'Wie würdest du deinen idealen Arbeitstag-Look beschreiben?',
                'answers'  => array(
                    array('text' => 'Schlichter Blazer mit Bluse und Stoffhose', 'scores' => array('classic' => 3)),
                    array('text' => 'Mix aus verschiedenen Mustern und ungewöhnlichen Accessoires', 'scores' => array('creative' => 3)),
                    array('text' => 'Bequeme Hose aus Naturmaterial mit lockerem Oberteil', 'scores' => array('natural' => 3)),
                    array('text' => 'Feminines Kleid mit zarten Details', 'scores' => array('romantic' => 3)),
                    array('text' => 'All-Black-Outfit mit Statement-Schmuck', 'scores' => array('dramatic' => 3)),
                    array('text' => 'Elegante Jogginghose mit hochwertigem Hoodie', 'scores' => array('sporty' => 3)),
                ),
            ),
            array(
                'id'       => 2,
                'question' => 'Welches Accessoire darf in deinem Outfit nicht fehlen?',
                'answers'  => array(
                    array('text' => 'Eine klassische Ledertasche in neutraler Farbe', 'scores' => array('classic' => 3)),
                    array('text' => 'Ein ausgefallenes Statement-Piece', 'scores' => array('creative' => 3)),
                    array('text' => 'Ein handgemachter Schal oder Holzschmuck', 'scores' => array('natural' => 3)),
                    array('text' => 'Zarter Goldschmuck oder Perlen', 'scores' => array('romantic' => 3)),
                    array('text' => 'Auffällige Sonnenbrille oder markante Uhr', 'scores' => array('dramatic' => 3)),
                    array('text' => 'Stylische Sneaker oder Sportuhr', 'scores' => array('sporty' => 3)),
                ),
            ),
            array(
                'id'       => 3,
                'question' => 'Wie verhältst du dich bei Mode-Trends?',
                'answers'  => array(
                    array('text' => 'Ich bleibe bei dem, was zeitlos und bewährt ist', 'scores' => array('classic' => 3)),
                    array('text' => 'Ich experimentiere gerne und setze eigene Trends', 'scores' => array('creative' => 3)),
                    array('text' => 'Trends interessieren mich nur, wenn sie nachhaltig sind', 'scores' => array('natural' => 3)),
                    array('text' => 'Ich folge romantischen und femininen Trends', 'scores' => array('romantic' => 3)),
                    array('text' => 'Ich suche Trends, die mich hervorstechen lassen', 'scores' => array('dramatic' => 3)),
                    array('text' => 'Ich mag funktionale Trends aus dem Athleisure-Bereich', 'scores' => array('sporty' => 3)),
                ),
            ),
            array(
                'id'       => 4,
                'question' => 'Welche Farben dominieren deinen Kleiderschrank?',
                'answers'  => array(
                    array('text' => 'Navy, Grau, Beige und Weiß', 'scores' => array('classic' => 3)),
                    array('text' => 'Bunte Mischung mit vielen Mustern', 'scores' => array('creative' => 3)),
                    array('text' => 'Erdtöne, Olivgrün und Naturfarben', 'scores' => array('natural' => 3)),
                    array('text' => 'Pastelltöne, Rosa und zarte Farben', 'scores' => array('romantic' => 3)),
                    array('text' => 'Schwarz, Weiß und kräftige Akzentfarben', 'scores' => array('dramatic' => 3)),
                    array('text' => 'Sportliche Farben wie Dunkelblau, Rot, Türkis', 'scores' => array('sporty' => 3)),
                ),
            ),
            array(
                'id'       => 5,
                'question' => 'Was ist dir beim Kleidungskauf am wichtigsten?',
                'answers'  => array(
                    array('text' => 'Qualität und Langlebigkeit', 'scores' => array('classic' => 3)),
                    array('text' => 'Einzigartigkeit und Originalität', 'scores' => array('creative' => 3)),
                    array('text' => 'Nachhaltigkeit und natürliche Materialien', 'scores' => array('natural' => 3)),
                    array('text' => 'Schöne Details und feminine Schnitte', 'scores' => array('romantic' => 3)),
                    array('text' => 'Wirkung und Statement', 'scores' => array('dramatic' => 3)),
                    array('text' => 'Komfort und Bewegungsfreiheit', 'scores' => array('sporty' => 3)),
                ),
            ),
            array(
                'id'       => 6,
                'question' => 'Wie würdest du dich für eine wichtige Geschäftspräsentation kleiden?',
                'answers'  => array(
                    array('text' => 'Klassischer Anzug oder Kostüm', 'scores' => array('classic' => 3)),
                    array('text' => 'Etwas Unerwartetes, das im Gedächtnis bleibt', 'scores' => array('creative' => 3)),
                    array('text' => 'Gepflegt aber ungezwungen, ohne Krawatten-Zwang', 'scores' => array('natural' => 3)),
                    array('text' => 'Elegantes Kleid mit dezenten Details', 'scores' => array('romantic' => 3)),
                    array('text' => 'Selbstbewusster Look, der Autorität ausstrahlt', 'scores' => array('dramatic' => 3)),
                    array('text' => 'Smart Casual mit sportlichem Touch', 'scores' => array('sporty' => 3)),
                ),
            ),
            array(
                'id'       => 7,
                'question' => 'Welcher Schuhstil spricht dich am meisten an?',
                'answers'  => array(
                    array('text' => 'Klassische Pumps oder Lederschuhe', 'scores' => array('classic' => 3)),
                    array('text' => 'Ausgefallene Designer-Schuhe', 'scores' => array('creative' => 3)),
                    array('text' => 'Bequeme Naturleder-Boots oder Espadrilles', 'scores' => array('natural' => 3)),
                    array('text' => 'Elegante Ballerinas oder zierliche Heels', 'scores' => array('romantic' => 3)),
                    array('text' => 'Markante Stiefel oder Statement-Heels', 'scores' => array('dramatic' => 3)),
                    array('text' => 'Stylische Sneaker oder Laufschuhe', 'scores' => array('sporty' => 3)),
                ),
            ),
            array(
                'id'       => 8,
                'question' => 'Wie würdest du dein Styling für einen entspannten Sonntag beschreiben?',
                'answers'  => array(
                    array('text' => 'Chinos und polierter Casual-Look', 'scores' => array('classic' => 3)),
                    array('text' => 'Vintage-Mix mit interessanten Fundstücken', 'scores' => array('creative' => 3)),
                    array('text' => 'Weite Leinenhose und lockeres Shirt', 'scores' => array('natural' => 3)),
                    array('text' => 'Sommerliches Kleid mit Blumenmuster', 'scores' => array('romantic' => 3)),
                    array('text' => 'Cooler All-Black-Look auch am Wochenende', 'scores' => array('dramatic' => 3)),
                    array('text' => 'Jogginghose und Hoodie - aber stylisch!', 'scores' => array('sporty' => 3)),
                ),
            ),
            array(
                'id'       => 9,
                'question' => 'Welches Muster trägst du am liebsten?',
                'answers'  => array(
                    array('text' => 'Dezente Streifen oder unifarben', 'scores' => array('classic' => 3)),
                    array('text' => 'Abstrakte Prints und ungewöhnliche Muster', 'scores' => array('creative' => 3)),
                    array('text' => 'Organische Muster und Batik', 'scores' => array('natural' => 3)),
                    array('text' => 'Florale Prints und Polka Dots', 'scores' => array('romantic' => 3)),
                    array('text' => 'Animal-Prints oder geometrische Muster', 'scores' => array('dramatic' => 3)),
                    array('text' => 'Sporty Stripes und Logos', 'scores' => array('sporty' => 3)),
                ),
            ),
            array(
                'id'       => 10,
                'question' => 'Welche Modemarke spricht dich instinktiv am meisten an?',
                'answers'  => array(
                    array('text' => 'Max Mara oder Hugo Boss', 'scores' => array('classic' => 3)),
                    array('text' => 'Comme des Garçons oder Maison Margiela', 'scores' => array('creative' => 3)),
                    array('text' => 'Arket oder COS', 'scores' => array('natural' => 3)),
                    array('text' => 'Zimmermann oder Sézane', 'scores' => array('romantic' => 3)),
                    array('text' => 'Alexander McQueen oder Saint Laurent', 'scores' => array('dramatic' => 3)),
                    array('text' => 'Nike oder Lululemon', 'scores' => array('sporty' => 3)),
                ),
            ),
        );
    }

    /**
     * Get quiz questions.
     *
     * @return array
     */
    public function get_questions(): array {
        return $this->questions;
    }

    /**
     * Get style types.
     *
     * @return array
     */
    public function get_style_types(): array {
        return $this->style_types;
    }

    /**
     * Get a specific style type.
     *
     * @param string $type Style type key.
     * @return array|null
     */
    public function get_style_type(string $type): ?array {
        return $this->style_types[$type] ?? null;
    }

    /**
     * Submit quiz answers and calculate result.
     *
     * @param int   $user_id User ID.
     * @param array $answers Array of answers (question_id => answer_index).
     * @return array Result data.
     */
    public function submit_answers(int $user_id, array $answers): array {
        // Validate answers
        if (count($answers) < count($this->questions)) {
            return array(
                'success' => false,
                'error'   => 'Bitte beantworte alle Fragen.',
            );
        }

        // Calculate style type
        $style_type = $this->calculate_style_type($answers);
        $scores = $this->calculate_scores($answers);

        // Save result
        $this->save_result($user_id, $style_type, $answers, $scores);

        // Award points
        $this->points->award_points($user_id, 'quiz_completed', 50, 'Style-Quiz abgeschlossen');

        // Check achievements
        $this->check_quiz_achievements($user_id, $style_type);

        // Get style type details
        $style_details = $this->style_types[$style_type];

        return array(
            'success'       => true,
            'style_type'    => $style_type,
            'style_name'    => $style_details['name'],
            'description'   => $style_details['description'],
            'colors'        => $style_details['colors'],
            'keywords'      => $style_details['keywords'],
            'clothing'      => $style_details['clothing'],
            'brands'        => $style_details['brands'],
            'scores'        => $scores,
            'badge_svg'     => $this->get_badge_svg($style_type),
            'share_data'    => $this->get_shareable_result($user_id),
        );
    }

    /**
     * Calculate scores from answers.
     *
     * @param array $answers User answers.
     * @return array Scores per style type.
     */
    private function calculate_scores(array $answers): array {
        $scores = array(
            'classic'  => 0,
            'creative' => 0,
            'natural'  => 0,
            'romantic' => 0,
            'dramatic' => 0,
            'sporty'   => 0,
        );

        foreach ($answers as $question_id => $answer_index) {
            $question = $this->get_question_by_id($question_id);
            if ($question && isset($question['answers'][$answer_index])) {
                $answer = $question['answers'][$answer_index];
                foreach ($answer['scores'] as $type => $score) {
                    $scores[$type] += $score;
                }
            }
        }

        return $scores;
    }

    /**
     * Get question by ID.
     *
     * @param int $question_id Question ID.
     * @return array|null
     */
    private function get_question_by_id(int $question_id): ?array {
        foreach ($this->questions as $question) {
            if ($question['id'] === $question_id) {
                return $question;
            }
        }
        return null;
    }

    /**
     * Calculate style type from answers.
     *
     * @param array $answers User answers.
     * @return string Dominant style type.
     */
    public function calculate_style_type(array $answers): string {
        $scores = $this->calculate_scores($answers);

        // Find highest score
        arsort($scores);
        $top_types = array_keys($scores);

        return $top_types[0];
    }

    /**
     * Save quiz result for user.
     *
     * @param int    $user_id    User ID.
     * @param string $style_type Calculated style type.
     * @param array  $answers    User answers.
     * @param array  $scores     Calculated scores.
     * @return void
     */
    public function save_result(int $user_id, string $style_type, array $answers, array $scores = array()): void {
        if (empty($scores)) {
            $scores = $this->calculate_scores($answers);
        }

        $result = array(
            'style_type'   => $style_type,
            'answers'      => $answers,
            'scores'       => $scores,
            'completed_at' => current_time('mysql'),
        );

        update_user_meta($user_id, 'sg_quiz_result', $result);
        update_user_meta($user_id, 'sg_style_type', $style_type);

        // Log the quiz completion
        do_action('stylegenius_quiz_completed', $user_id, $style_type, $result);
    }

    /**
     * Get user's quiz result.
     *
     * @param int $user_id User ID.
     * @return array|null
     */
    public function get_result(int $user_id): ?array {
        $result = get_user_meta($user_id, 'sg_quiz_result', true);

        if (empty($result)) {
            return null;
        }

        // Enhance with style type details
        $style_type = $result['style_type'];
        if (isset($this->style_types[$style_type])) {
            $result['style_details'] = $this->style_types[$style_type];
        }

        return $result;
    }

    /**
     * Check if user has completed quiz.
     *
     * @param int $user_id User ID.
     * @return bool
     */
    public function has_completed_quiz(int $user_id): bool {
        $result = get_user_meta($user_id, 'sg_quiz_result', true);
        return !empty($result);
    }

    /**
     * Get badge SVG for style type.
     *
     * @param string $style_type Style type.
     * @return string SVG markup.
     */
    public function get_badge_svg(string $style_type): string {
        $style = $this->style_types[$style_type] ?? null;
        if (!$style) {
            return '';
        }

        $primary_color = $style['colors'][0];
        $secondary_color = $style['colors'][1];
        $accent_color = $style['colors'][3] ?? $style['colors'][2];
        $name = $style['name'];
        $icon = $this->get_icon_path($style['icon']);

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200" width="200" height="200">
            <defs>
                <linearGradient id="grad_' . $style_type . '" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" style="stop-color:' . $primary_color . ';stop-opacity:1" />
                    <stop offset="100%" style="stop-color:' . $secondary_color . ';stop-opacity:1" />
                </linearGradient>
            </defs>
            <circle cx="100" cy="100" r="95" fill="url(#grad_' . $style_type . ')" stroke="' . $accent_color . '" stroke-width="3"/>
            <circle cx="100" cy="100" r="80" fill="none" stroke="rgba(255,255,255,0.3)" stroke-width="1"/>
            <g transform="translate(100, 70)" fill="#ffffff">
                ' . $icon . '
            </g>
            <text x="100" y="145" text-anchor="middle" fill="#ffffff" font-family="Arial, sans-serif" font-size="16" font-weight="bold">' . strtoupper($name) . '</text>
            <text x="100" y="165" text-anchor="middle" fill="rgba(255,255,255,0.8)" font-family="Arial, sans-serif" font-size="10">Style-Typ</text>
        </svg>';

        return $svg;
    }

    /**
     * Get icon path for badge.
     *
     * @param string $icon Icon name.
     * @return string SVG path.
     */
    private function get_icon_path(string $icon): string {
        $icons = array(
            'crown'   => '<path transform="translate(-20, -15) scale(1.5)" d="M2.5 6.5L8 12L13.5 6.5L12 2H4L2.5 6.5Z M4 14H12V16H4V14Z"/>',
            'palette' => '<path transform="translate(-15, -15) scale(1.2)" d="M12 2C6.49 2 2 6.49 2 12s4.49 10 10 10c1.38 0 2.5-1.12 2.5-2.5 0-.61-.23-1.2-.64-1.67-.08-.1-.13-.21-.13-.33 0-.28.22-.5.5-.5H16c3.31 0 6-2.69 6-6 0-4.96-4.49-9-10-9zm-5.5 9c-.83 0-1.5-.67-1.5-1.5S5.67 8 6.5 8 8 8.67 8 9.5 7.33 11 6.5 11zm3-4C8.67 7 8 6.33 8 5.5S8.67 4 9.5 4s1.5.67 1.5 1.5S10.33 7 9.5 7zm5 0c-.83 0-1.5-.67-1.5-1.5S13.67 4 14.5 4s1.5.67 1.5 1.5S15.33 7 14.5 7zm3 4c-.83 0-1.5-.67-1.5-1.5S16.67 8 17.5 8s1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/>',
            'leaf'    => '<path transform="translate(-15, -15) scale(1.2)" d="M17 8C8 10 5.9 16.17 3.82 21.34l1.89.66.95-2.3c.48.17.98.3 1.34.3C19 20 22 3 22 3c-1 2-8 2.25-13 3.25S2 11.5 2 13.5s1.75 3.75 1.75 3.75C7 8 17 8 17 8z"/>',
            'heart'   => '<path transform="translate(-15, -15) scale(1.2)" d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>',
            'bolt'    => '<path transform="translate(-12, -15) scale(1.5)" d="M7 2v11h3v9l7-12h-4l4-8z"/>',
            'running' => '<path transform="translate(-15, -15) scale(1.2)" d="M13.49 5.48c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm-3.6 13.9l1-4.4 2.1 2v6h2v-7.5l-2.1-2 .6-3c1.3 1.5 3.3 2.5 5.5 2.5v-2c-1.9 0-3.5-1-4.3-2.4l-1-1.6c-.4-.6-1-1-1.7-1-.3 0-.5.1-.8.1l-5.2 2.2v4.7h2v-3.4l1.8-.7-1.6 8.1-4.9-1-.4 2 7 1.4z"/>',
        );

        return $icons[$icon] ?? $icons['crown'];
    }

    /**
     * Get shareable result data.
     *
     * @param int $user_id User ID.
     * @return array
     */
    public function get_shareable_result(int $user_id): array {
        $result = $this->get_result($user_id);

        if (!$result) {
            return array();
        }

        $style_type = $result['style_type'];
        $style = $this->style_types[$style_type];
        $user = get_userdata($user_id);

        return array(
            'style_type'  => $style_type,
            'style_name'  => $style['name'],
            'description' => $style['description'],
            'user_name'   => $user ? $user->display_name : '',
            'badge_url'   => $this->get_badge_image_url($user_id),
            'share_text'  => sprintf(
                'Ich bin der %s Style-Typ! Finde deinen Style-Typ auf StyleGenius Pro. #StyleGenius #Fashion',
                $style['name']
            ),
            'share_url'   => add_query_arg(
                array(
                    'ref'   => 'quiz',
                    'style' => $style_type,
                ),
                home_url('/style-quiz/')
            ),
        );
    }

    /**
     * Get or generate badge image URL.
     *
     * @param int $user_id User ID.
     * @return string
     */
    public function get_badge_image_url(int $user_id): string {
        $result = $this->get_result($user_id);
        if (!$result) {
            return '';
        }

        $style_type = $result['style_type'];
        $upload_dir = wp_upload_dir();
        $badge_dir = $upload_dir['basedir'] . '/stylegenius/badges/';
        $badge_file = $badge_dir . 'quiz-badge-' . $user_id . '.png';
        $badge_url = $upload_dir['baseurl'] . '/stylegenius/badges/quiz-badge-' . $user_id . '.png';

        // Check if badge needs regeneration
        $result_time = strtotime($result['completed_at'] ?? '');
        if (file_exists($badge_file) && filemtime($badge_file) >= $result_time) {
            return $badge_url;
        }

        // Generate badge image (requires Imagick or GD)
        $generated = $this->generate_badge_image($user_id, $style_type, $badge_file);

        if ($generated) {
            return $badge_url;
        }

        // Fallback to SVG data URL
        $svg = $this->get_badge_svg($style_type);
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    /**
     * Generate PNG badge image.
     *
     * @param int    $user_id    User ID.
     * @param string $style_type Style type.
     * @param string $file_path  Output file path.
     * @return bool
     */
    private function generate_badge_image(int $user_id, string $style_type, string $file_path): bool {
        // Ensure directory exists
        $dir = dirname($file_path);
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }

        // Try Imagick first
        if (extension_loaded('imagick')) {
            try {
                $svg = $this->get_badge_svg($style_type);
                $imagick = new Imagick();
                $imagick->readImageBlob($svg);
                $imagick->setImageFormat('png');
                $imagick->writeImage($file_path);
                $imagick->clear();
                $imagick->destroy();
                return true;
            } catch (Exception $e) {
                // Fall through to GD
            }
        }

        // GD fallback - create simple badge
        if (extension_loaded('gd')) {
            $style = $this->style_types[$style_type];
            $image = imagecreatetruecolor(200, 200);

            // Enable alpha
            imagesavealpha($image, true);

            // Parse colors
            $primary = $this->hex_to_rgb($style['colors'][0]);
            $color = imagecolorallocate($image, $primary['r'], $primary['g'], $primary['b']);
            $white = imagecolorallocate($image, 255, 255, 255);

            // Draw circle
            imagefilledellipse($image, 100, 100, 190, 190, $color);
            imageellipse($image, 100, 100, 160, 160, $white);

            // Add text
            $font = 5; // Built-in font
            $text = strtoupper($style['name']);
            $text_width = imagefontwidth($font) * strlen($text);
            imagestring($image, $font, (200 - $text_width) / 2, 140, $text, $white);

            imagepng($image, $file_path);
            imagedestroy($image);
            return true;
        }

        return false;
    }

    /**
     * Convert hex color to RGB.
     *
     * @param string $hex Hex color code.
     * @return array RGB values.
     */
    private function hex_to_rgb(string $hex): array {
        $hex = ltrim($hex, '#');
        return array(
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2)),
        );
    }

    /**
     * Check and award quiz-related achievements.
     *
     * @param int    $user_id    User ID.
     * @param string $style_type Calculated style type.
     * @return void
     */
    private function check_quiz_achievements(int $user_id, string $style_type): void {
        // Award style type specific achievement
        $achievement_map = array(
            'classic'  => 'style_classic',
            'creative' => 'style_creative',
            'natural'  => 'style_natural',
            'romantic' => 'style_romantic',
            'dramatic' => 'style_dramatic',
            'sporty'   => 'style_sporty',
        );

        if (isset($achievement_map[$style_type])) {
            $this->achievements->award_achievement($user_id, $achievement_map[$style_type]);
        }
    }

    /**
     * Retake quiz - clear previous result.
     *
     * @param int $user_id User ID.
     * @return bool
     */
    public function retake_quiz(int $user_id): bool {
        delete_user_meta($user_id, 'sg_quiz_result');
        // Keep style_type for history
        return true;
    }

    /**
     * Get quiz completion statistics.
     *
     * @return array
     */
    public function get_statistics(): array {
        global $wpdb;

        $cache_key = 'sg_quiz_statistics';
        $cached = get_transient($cache_key);

        if (false !== $cached) {
            return $cached;
        }

        // Count completions per style type
        $results = $wpdb->get_results(
            "SELECT meta_value
            FROM {$wpdb->usermeta}
            WHERE meta_key = 'sg_style_type'",
            ARRAY_A
        );

        $type_counts = array_fill_keys(array_keys($this->style_types), 0);
        foreach ($results as $row) {
            $type = $row['meta_value'];
            if (isset($type_counts[$type])) {
                $type_counts[$type]++;
            }
        }

        $total = array_sum($type_counts);

        $stats = array(
            'total_completions' => $total,
            'type_counts'       => $type_counts,
            'type_percentages'  => array(),
        );

        foreach ($type_counts as $type => $count) {
            $stats['type_percentages'][$type] = $total > 0
                ? round(($count / $total) * 100, 1)
                : 0;
        }

        set_transient($cache_key, $stats, HOUR_IN_SECONDS);

        return $stats;
    }

    /**
     * AJAX: Submit quiz answers.
     */
    public function ajax_submit_quiz(): void {
        check_ajax_referer('stylegenius_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Bitte melde dich an.', 'stylegenius-pro')));
        }

        $answers = isset($_POST['answers']) ? (array) $_POST['answers'] : array();
        if (empty($answers)) {
            wp_send_json_error(array('message' => __('Keine Antworten erhalten.', 'stylegenius-pro')));
        }

        $result = $this->submit_answers(get_current_user_id(), $answers);
        wp_send_json_success($result);
    }

    /**
     * AJAX: Get quiz questions.
     */
    public function ajax_get_questions(): void {
        wp_send_json_success(array('questions' => $this->get_questions()));
    }
}
