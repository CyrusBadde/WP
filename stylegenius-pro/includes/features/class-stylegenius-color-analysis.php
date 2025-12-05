<?php
/**
 * StyleGenius Color Analysis Class
 *
 * Handles color type analysis based on selfies and generates personal color palettes.
 *
 * @package    StyleGenius_Pro
 * @subpackage StyleGenius_Pro/includes/features
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Color Analysis management class.
 */
class StyleGenius_Color_Analysis {

    /**
     * Database instance.
     *
     * @var StyleGenius_Database
     */
    private $db;

    /**
     * Vision instance.
     *
     * @var StyleGenius_Vision
     */
    private $vision;

    /**
     * AI Manager instance.
     *
     * @var StyleGenius_AI_Manager
     */
    private $ai;

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
     * Color type definitions.
     *
     * @var array
     */
    private $color_types = array(
        'spring' => array(
            'name'        => 'Frühlingstyp',
            'description' => 'Warme, helle und klare Farben stehen dir am besten. Deine Haut hat einen warmen, goldenen Unterton.',
            'undertone'   => 'warm',
            'intensity'   => 'light',
            'colors'      => array(
                'primär'     => array('#FF6B35', '#F7C59F', '#FFE66D', '#7EB77F', '#4ECDC4'),
                'neutral'    => array('#E8DCD5', '#D4A574', '#A67B5B', '#8B7355', '#F5E6D3'),
                'akzent'     => array('#FF4757', '#FF7F50', '#FFBE76', '#70A1FF', '#C56CF0'),
                'vermeiden'  => array('#000000', '#1A1A2E', '#2C3E50', '#8B0000', '#800080'),
            ),
            'metals'      => 'gold',
            'celebrities' => array('Blake Lively', 'Cameron Diaz', 'Nicole Kidman'),
            'keywords'    => array('frisch', 'lebendig', 'strahlend', 'warm', 'klar'),
        ),
        'summer' => array(
            'name'        => 'Sommertyp',
            'description' => 'Kühle, gedämpfte und sanfte Farben harmonieren perfekt mit deinem Teint. Dein Unterton ist rosig-kühl.',
            'undertone'   => 'cool',
            'intensity'   => 'muted',
            'colors'      => array(
                'primär'     => array('#B8C9E1', '#E8B4BC', '#C9A7EB', '#A8D8EA', '#D4A5A5'),
                'neutral'    => array('#8E8E93', '#A7A7AD', '#C7C7CD', '#E5E5EA', '#F2F2F7'),
                'akzent'     => array('#9B59B6', '#3498DB', '#1ABC9C', '#E91E63', '#607D8B'),
                'vermeiden'  => array('#FF4500', '#FFD700', '#FF8C00', '#228B22', '#8B4513'),
            ),
            'metals'      => 'silver',
            'celebrities' => array('Reese Witherspoon', 'Jennifer Aniston', 'Gwyneth Paltrow'),
            'keywords'    => array('sanft', 'elegant', 'gedämpft', 'kühl', 'harmonisch'),
        ),
        'autumn' => array(
            'name'        => 'Herbsttyp',
            'description' => 'Warme, erdige und gedämpfte Farben bringen deine natürliche Schönheit zum Vorschein. Dein Teint hat goldene Untertöne.',
            'undertone'   => 'warm',
            'intensity'   => 'muted',
            'colors'      => array(
                'primär'     => array('#D35400', '#A0522D', '#6B8E23', '#8B4513', '#CD853F'),
                'neutral'    => array('#8B7355', '#A67B5B', '#D4A574', '#E8DCD5', '#5D4037'),
                'akzent'     => array('#B8860B', '#556B2F', '#8B0000', '#2F4F4F', '#DAA520'),
                'vermeiden'  => array('#FF69B4', '#00FFFF', '#FF1493', '#4169E1', '#9370DB'),
            ),
            'metals'      => 'gold',
            'celebrities' => array('Julia Roberts', 'Jessica Alba', 'Eva Mendes'),
            'keywords'    => array('warm', 'erdig', 'natürlich', 'reich', 'gemütlich'),
        ),
        'winter' => array(
            'name'        => 'Wintertyp',
            'description' => 'Kühle, klare und intensive Farben sowie starke Kontraste stehen dir hervorragend. Dein Teint ist kühl mit bläulichen Untertönen.',
            'undertone'   => 'cool',
            'intensity'   => 'clear',
            'colors'      => array(
                'primär'     => array('#1A1A2E', '#E94560', '#0F3460', '#16213E', '#FFFFFF'),
                'neutral'    => array('#2C3E50', '#34495E', '#95A5A6', '#BDC3C7', '#ECF0F1'),
                'akzent'     => array('#C0392B', '#8E44AD', '#2980B9', '#27AE60', '#F39C12'),
                'vermeiden'  => array('#FFDAB9', '#F5DEB3', '#D2B48C', '#FFE4C4', '#FFF8DC'),
            ),
            'metals'      => 'silver',
            'celebrities' => array('Katy Perry', 'Megan Fox', 'Courteney Cox'),
            'keywords'    => array('klar', 'kontrastreich', 'intensiv', 'kühl', 'dramatisch'),
        ),
    );

    /**
     * Constructor.
     */
    public function __construct() {
        $this->db = new StyleGenius_Database();
        $this->vision = new StyleGenius_Vision();
        $this->ai = new StyleGenius_AI_Manager();
        $this->points = new StyleGenius_Points();
        $this->achievements = new StyleGenius_Achievements();
    }

    /**
     * Get color types.
     *
     * @return array
     */
    public function get_color_types(): array {
        return $this->color_types;
    }

    /**
     * Analyze color type from selfie.
     *
     * @param int $user_id  User ID.
     * @param int $image_id Attachment ID of selfie.
     * @return array
     */
    public function analyze(int $user_id, int $image_id): array {
        // Check tier
        $tier = get_user_meta($user_id, 'sg_tier', true) ?: 'free';
        if ($tier === 'free') {
            return array(
                'success' => false,
                'error'   => 'Farbanalyse ist ein Premium-Feature.',
                'upgrade' => true,
            );
        }

        // Get image URL
        $image_url = wp_get_attachment_url($image_id);
        if (!$image_url) {
            return array(
                'success' => false,
                'error'   => 'Bild nicht gefunden.',
            );
        }

        // Validate image
        $upload = new StyleGenius_Upload();
        $validation = $upload->validate_for_purpose($image_id, 'selfie');

        if (!$validation['valid']) {
            return array(
                'success' => false,
                'error'   => $validation['error'],
            );
        }

        // Analyze with AI Vision
        $analysis = $this->vision->analyze_color_type($image_url);

        if (!$analysis['success']) {
            return array(
                'success' => false,
                'error'   => $analysis['error'] ?? 'Fehler bei der Analyse.',
            );
        }

        // Determine color type
        $color_type = $this->determine_color_type($analysis);

        // Get detailed type info
        $type_info = $this->color_types[$color_type];

        // Save result
        $this->save_result($user_id, $color_type, $image_id, $analysis);

        // Award points
        $this->points->award_points($user_id, 'color_analysis', 30, 'Farbanalyse durchgeführt');

        // Check achievements
        $this->check_color_achievements($user_id, $color_type);

        return array(
            'success'         => true,
            'color_type'      => $color_type,
            'name'            => $type_info['name'],
            'description'     => $type_info['description'],
            'undertone'       => $type_info['undertone'],
            'intensity'       => $type_info['intensity'],
            'color_palette'   => $type_info['colors'],
            'metals'          => $type_info['metals'],
            'celebrities'     => $type_info['celebrities'],
            'keywords'        => $type_info['keywords'],
            'analysis_data'   => $analysis,
            'recommendations' => $this->get_recommendations($color_type),
        );
    }

    /**
     * Determine color type from analysis data.
     *
     * @param array $analysis Analysis data from AI.
     * @return string Color type key.
     */
    private function determine_color_type(array $analysis): string {
        // Extract features from analysis
        $undertone = $analysis['undertone'] ?? 'neutral';
        $skin_tone = $analysis['skin_tone'] ?? 'medium';
        $hair_color = $analysis['hair_color'] ?? 'brown';
        $eye_color = $analysis['eye_color'] ?? 'brown';
        $contrast = $analysis['contrast'] ?? 'medium';

        // Score each type
        $scores = array(
            'spring' => 0,
            'summer' => 0,
            'autumn' => 0,
            'winter' => 0,
        );

        // Undertone scoring
        if ($undertone === 'warm' || $undertone === 'golden') {
            $scores['spring'] += 3;
            $scores['autumn'] += 3;
        } elseif ($undertone === 'cool' || $undertone === 'pink' || $undertone === 'blue') {
            $scores['summer'] += 3;
            $scores['winter'] += 3;
        }

        // Skin tone scoring
        if ($skin_tone === 'light' || $skin_tone === 'fair') {
            $scores['spring'] += 2;
            $scores['summer'] += 2;
        } elseif ($skin_tone === 'medium') {
            $scores['autumn'] += 1;
            $scores['summer'] += 1;
        } elseif ($skin_tone === 'olive' || $skin_tone === 'deep') {
            $scores['autumn'] += 2;
            $scores['winter'] += 2;
        }

        // Hair color scoring
        $warm_hair = array('red', 'auburn', 'golden', 'strawberry', 'copper', 'honey');
        $cool_hair = array('ash', 'black', 'platinum', 'silver');

        if (in_array(strtolower($hair_color), $warm_hair, true)) {
            $scores['spring'] += 2;
            $scores['autumn'] += 2;
        } elseif (in_array(strtolower($hair_color), $cool_hair, true)) {
            $scores['summer'] += 2;
            $scores['winter'] += 2;
        }

        // Contrast scoring
        if ($contrast === 'high') {
            $scores['winter'] += 3;
            $scores['spring'] += 1;
        } elseif ($contrast === 'low') {
            $scores['summer'] += 3;
            $scores['autumn'] += 1;
        } else {
            $scores['spring'] += 1;
            $scores['autumn'] += 1;
        }

        // Eye color scoring
        $warm_eyes = array('amber', 'golden brown', 'hazel', 'warm brown', 'green-brown');
        $cool_eyes = array('blue', 'grey', 'cool brown', 'dark brown', 'black');

        $eye_lower = strtolower($eye_color);
        foreach ($warm_eyes as $warm) {
            if (strpos($eye_lower, $warm) !== false) {
                $scores['spring'] += 1;
                $scores['autumn'] += 1;
                break;
            }
        }
        foreach ($cool_eyes as $cool) {
            if (strpos($eye_lower, $cool) !== false) {
                $scores['summer'] += 1;
                $scores['winter'] += 1;
                break;
            }
        }

        // Find highest score
        arsort($scores);
        return array_key_first($scores);
    }

    /**
     * Save color analysis result.
     *
     * @param int    $user_id    User ID.
     * @param string $color_type Determined color type.
     * @param int    $image_id   Selfie attachment ID.
     * @param array  $analysis   Raw analysis data.
     * @return int|false
     */
    private function save_result(int $user_id, string $color_type, int $image_id, array $analysis) {
        global $wpdb;

        $table = $wpdb->prefix . 'sg_color_profiles';

        // Check for existing result
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE user_id = %d",
                $user_id
            )
        );

        $data = array(
            'user_id'     => $user_id,
            'color_type'  => $color_type,
            'undertone'   => $analysis['undertone'] ?? null,
            'skin_tone'   => $analysis['skin_tone'] ?? null,
            'hair_color'  => $analysis['hair_color'] ?? null,
            'eye_color'   => $analysis['eye_color'] ?? null,
            'contrast'    => $analysis['contrast'] ?? null,
            'image_id'    => $image_id,
            'analysis'    => wp_json_encode($analysis),
            'created_at'  => current_time('mysql'),
        );

        if ($existing) {
            $wpdb->update($table, $data, array('id' => $existing));
            $id = $existing;
        } else {
            $wpdb->insert($table, $data);
            $id = $wpdb->insert_id;
        }

        // Update user meta
        update_user_meta($user_id, 'sg_color_type', $color_type);
        update_user_meta($user_id, 'sg_metal_recommendation', $this->color_types[$color_type]['metals']);

        return $id;
    }

    /**
     * Get user's color profile.
     *
     * @param int $user_id User ID.
     * @return array|null
     */
    public function get_profile(int $user_id): ?array {
        global $wpdb;

        $table = $wpdb->prefix . 'sg_color_profiles';

        $profile = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE user_id = %d",
                $user_id
            ),
            ARRAY_A
        );

        if (!$profile) {
            return null;
        }

        $color_type = $profile['color_type'];
        $type_info = $this->color_types[$color_type];

        return array(
            'color_type'    => $color_type,
            'name'          => $type_info['name'],
            'description'   => $type_info['description'],
            'undertone'     => $profile['undertone'],
            'skin_tone'     => $profile['skin_tone'],
            'hair_color'    => $profile['hair_color'],
            'eye_color'     => $profile['eye_color'],
            'contrast'      => $profile['contrast'],
            'color_palette' => $type_info['colors'],
            'metals'        => $type_info['metals'],
            'celebrities'   => $type_info['celebrities'],
            'keywords'      => $type_info['keywords'],
            'image_id'      => intval($profile['image_id']),
            'image_url'     => wp_get_attachment_url($profile['image_id']),
            'created_at'    => $profile['created_at'],
        );
    }

    /**
     * Get color recommendations.
     *
     * @param string $color_type Color type key.
     * @return array
     */
    private function get_recommendations(string $color_type): array {
        $type = $this->color_types[$color_type];

        return array(
            'clothing' => array(
                'titel'   => 'Kleidungsfarben',
                'text'    => sprintf(
                    'Setze auf %s Farben aus deiner Palette. Deine Basisfarben sind perfekt für Basics, während Akzentfarben tolle Statements setzen.',
                    $type['undertone'] === 'warm' ? 'warme' : 'kühle'
                ),
                'farben'  => $type['colors']['primär'],
            ),
            'makeup' => array(
                'titel'   => 'Make-up Tipps',
                'text'    => sprintf(
                    'Wähle Lippenstifte und Rouge in %s Tönen. Lidschatten aus deiner Palette schmeicheln deinem Teint besonders.',
                    $type['undertone'] === 'warm' ? 'pfirsich- und korallenen' : 'rosigen und beerenfarbenen'
                ),
            ),
            'jewelry' => array(
                'titel'   => 'Schmuck & Metalle',
                'text'    => sprintf(
                    '%s-farbener Schmuck harmoniert am besten mit deinem Hautton. Auch Roségold kann bei dir funktionieren.',
                    ucfirst($type['metals']) === 'Gold' ? 'Gold' : 'Silber'
                ),
                'empfehlung' => $type['metals'],
            ),
            'avoid' => array(
                'titel'   => 'Farben zum Vermeiden',
                'text'    => 'Diese Farben können deinen Teint fahl erscheinen lassen oder mit deinen natürlichen Farben konkurrieren.',
                'farben'  => $type['colors']['vermeiden'],
            ),
        );
    }

    /**
     * Generate shareable color profile card.
     *
     * @param int $user_id User ID.
     * @return string|null SVG or image URL.
     */
    public function generate_profile_card(int $user_id): ?string {
        $profile = $this->get_profile($user_id);

        if (!$profile) {
            return null;
        }

        $type_info = $this->color_types[$profile['color_type']];
        $primary_colors = $type_info['colors']['primär'];

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 250" width="400" height="250">
            <defs>
                <linearGradient id="bg_grad" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" style="stop-color:' . $primary_colors[0] . ';stop-opacity:0.9" />
                    <stop offset="100%" style="stop-color:' . $primary_colors[1] . ';stop-opacity:0.9" />
                </linearGradient>
            </defs>
            <rect width="400" height="250" rx="15" fill="url(#bg_grad)"/>
            <rect x="15" y="15" width="370" height="220" rx="10" fill="white" fill-opacity="0.95"/>
            <text x="200" y="50" text-anchor="middle" font-family="Arial, sans-serif" font-size="22" font-weight="bold" fill="' . $primary_colors[0] . '">' . $type_info['name'] . '</text>
            <line x1="50" y1="65" x2="350" y2="65" stroke="' . $primary_colors[0] . '" stroke-width="2" stroke-opacity="0.3"/>';

        // Color swatches
        $swatch_y = 85;
        $swatch_x = 50;
        $swatch_size = 50;
        $gap = 10;

        foreach ($primary_colors as $i => $color) {
            $x = $swatch_x + ($i * ($swatch_size + $gap));
            $svg .= '<rect x="' . $x . '" y="' . $swatch_y . '" width="' . $swatch_size . '" height="' . $swatch_size . '" rx="5" fill="' . $color . '"/>';
        }

        // Metal recommendation
        $metal_icon = $type_info['metals'] === 'gold' ? '#FFD700' : '#C0C0C0';
        $svg .= '<circle cx="350" y="110" r="20" fill="' . $metal_icon . '" stroke="#333" stroke-width="1"/>
            <text x="350" y="115" text-anchor="middle" font-size="10" fill="#333">' . strtoupper(substr($type_info['metals'], 0, 1)) . '</text>';

        // Description (truncated)
        $desc = mb_substr($type_info['description'], 0, 80) . '...';
        $svg .= '<text x="200" y="165" text-anchor="middle" font-family="Arial, sans-serif" font-size="11" fill="#333">' . $desc . '</text>';

        // Keywords
        $keywords_text = implode(' • ', array_slice($type_info['keywords'], 0, 3));
        $svg .= '<text x="200" y="190" text-anchor="middle" font-family="Arial, sans-serif" font-size="10" fill="' . $primary_colors[0] . '">' . $keywords_text . '</text>';

        // Footer
        $svg .= '<text x="200" y="225" text-anchor="middle" font-family="Arial, sans-serif" font-size="10" fill="#666">StyleGenius Pro • Deine persönliche Farbanalyse</text>
        </svg>';

        return $svg;
    }

    /**
     * Check and award color-related achievements.
     *
     * @param int    $user_id    User ID.
     * @param string $color_type Color type.
     * @return void
     */
    private function check_color_achievements(int $user_id, string $color_type): void {
        // Award color type specific achievement
        $achievement_map = array(
            'spring' => 'color_spring',
            'summer' => 'color_summer',
            'autumn' => 'color_autumn',
            'winter' => 'color_winter',
        );

        if (isset($achievement_map[$color_type])) {
            $this->achievements->award_achievement($user_id, $achievement_map[$color_type]);
        }
    }

    /**
     * Check if color matches user's palette.
     *
     * @param int    $user_id User ID.
     * @param string $color   Hex color code.
     * @return array Match result.
     */
    public function check_color_match(int $user_id, string $color): array {
        $profile = $this->get_profile($user_id);

        if (!$profile) {
            return array(
                'success' => false,
                'error'   => 'Keine Farbanalyse vorhanden.',
            );
        }

        $color = strtoupper(ltrim($color, '#'));

        // Check all palette colors
        $all_colors = array_merge(
            $profile['color_palette']['primär'],
            $profile['color_palette']['neutral'],
            $profile['color_palette']['akzent']
        );

        $avoid_colors = $profile['color_palette']['vermeiden'] ?? array();

        // Calculate color distance
        $closest_match = null;
        $min_distance = PHP_INT_MAX;

        foreach ($all_colors as $palette_color) {
            $distance = $this->color_distance($color, ltrim($palette_color, '#'));
            if ($distance < $min_distance) {
                $min_distance = $distance;
                $closest_match = $palette_color;
            }
        }

        // Check if in avoid list
        $should_avoid = false;
        foreach ($avoid_colors as $avoid_color) {
            if ($this->color_distance($color, ltrim($avoid_color, '#')) < 50) {
                $should_avoid = true;
                break;
            }
        }

        // Determine match level
        $match_level = 'gut';
        if ($min_distance > 100) {
            $match_level = $should_avoid ? 'vermeiden' : 'neutral';
        } elseif ($min_distance > 50) {
            $match_level = 'akzeptabel';
        }

        return array(
            'success'       => true,
            'match_level'   => $match_level,
            'closest_match' => $closest_match,
            'distance'      => $min_distance,
            'should_avoid'  => $should_avoid,
            'recommendation' => $this->get_color_recommendation($match_level, $closest_match),
        );
    }

    /**
     * Calculate Euclidean distance between two colors.
     *
     * @param string $color1 Hex color without #.
     * @param string $color2 Hex color without #.
     * @return float
     */
    private function color_distance(string $color1, string $color2): float {
        $r1 = hexdec(substr($color1, 0, 2));
        $g1 = hexdec(substr($color1, 2, 2));
        $b1 = hexdec(substr($color1, 4, 2));

        $r2 = hexdec(substr($color2, 0, 2));
        $g2 = hexdec(substr($color2, 2, 2));
        $b2 = hexdec(substr($color2, 4, 2));

        return sqrt(pow($r1 - $r2, 2) + pow($g1 - $g2, 2) + pow($b1 - $b2, 2));
    }

    /**
     * Get color recommendation text.
     *
     * @param string $match_level Match level.
     * @param string $closest     Closest palette color.
     * @return string
     */
    private function get_color_recommendation(string $match_level, string $closest): string {
        switch ($match_level) {
            case 'gut':
                return 'Diese Farbe passt hervorragend zu deinem Farbtyp!';
            case 'akzeptabel':
                return sprintf('Diese Farbe ist in Ordnung. Noch besser wäre %s.', $closest);
            case 'neutral':
                return 'Diese Farbe ist nicht ideal für deinen Farbtyp.';
            case 'vermeiden':
                return 'Diese Farbe könnte deinen Teint fahl wirken lassen. Probiere lieber Farben aus deiner Palette.';
            default:
                return '';
        }
    }

    /**
     * Export color profile data for GDPR.
     *
     * @param int $user_id User ID.
     * @return array|null
     */
    public function export_data(int $user_id): ?array {
        return $this->get_profile($user_id);
    }

    /**
     * Delete color profile for user (GDPR).
     *
     * @param int $user_id User ID.
     * @return bool
     */
    public function delete_data(int $user_id): bool {
        global $wpdb;

        $table = $wpdb->prefix . 'sg_color_profiles';

        // Get image ID first
        $image_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT image_id FROM {$table} WHERE user_id = %d",
                $user_id
            )
        );

        // Delete profile
        $wpdb->delete(
            $table,
            array('user_id' => $user_id),
            array('%d')
        );

        // Delete image
        if ($image_id) {
            wp_delete_attachment($image_id, true);
        }

        // Remove user meta
        delete_user_meta($user_id, 'sg_color_type');
        delete_user_meta($user_id, 'sg_metal_recommendation');

        return true;
    }
}
