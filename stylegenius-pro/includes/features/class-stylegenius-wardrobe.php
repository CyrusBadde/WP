<?php
/**
 * StyleGenius Wardrobe Class
 *
 * Handles virtual wardrobe functionality - adding, managing, and analyzing clothing items.
 *
 * @package    StyleGenius_Pro
 * @subpackage StyleGenius_Pro/includes/features
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Virtual Wardrobe management class.
 */
class StyleGenius_Wardrobe {

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
     * Clothing categories.
     *
     * @var array
     */
    private $categories = array(
        'tops' => array(
            'name'     => 'Oberteile',
            'icon'     => 'shirt',
            'subcats'  => array('t-shirt', 'bluse', 'hemd', 'pullover', 'strickjacke', 'hoodie', 'top', 'tunika'),
        ),
        'bottoms' => array(
            'name'     => 'Unterteile',
            'icon'     => 'pants',
            'subcats'  => array('hose', 'jeans', 'rock', 'shorts', 'leggings', 'culottes'),
        ),
        'dresses' => array(
            'name'     => 'Kleider & Jumpsuits',
            'icon'     => 'dress',
            'subcats'  => array('kleid', 'maxikleid', 'cocktailkleid', 'jumpsuit', 'overall'),
        ),
        'outerwear' => array(
            'name'     => 'Jacken & Mäntel',
            'icon'     => 'jacket',
            'subcats'  => array('blazer', 'jacke', 'mantel', 'parka', 'lederjacke', 'weste', 'cape'),
        ),
        'shoes' => array(
            'name'     => 'Schuhe',
            'icon'     => 'shoe',
            'subcats'  => array('sneaker', 'pumps', 'stiefel', 'sandalen', 'loafer', 'boots', 'ballerinas'),
        ),
        'bags' => array(
            'name'     => 'Taschen',
            'icon'     => 'bag',
            'subcats'  => array('handtasche', 'rucksack', 'clutch', 'shopper', 'umhängetasche', 'aktentasche'),
        ),
        'accessories' => array(
            'name'     => 'Accessoires',
            'icon'     => 'accessories',
            'subcats'  => array('schal', 'gürtel', 'hut', 'sonnenbrille', 'uhr', 'schmuck'),
        ),
    );

    /**
     * Seasons.
     *
     * @var array
     */
    private $seasons = array(
        'spring' => 'Frühling',
        'summer' => 'Sommer',
        'fall'   => 'Herbst',
        'winter' => 'Winter',
        'all'    => 'Ganzjährig',
    );

    /**
     * Occasions.
     *
     * @var array
     */
    private $occasions = array(
        'business'  => 'Business',
        'casual'    => 'Casual',
        'formal'    => 'Formal/Abend',
        'sport'     => 'Sport',
        'weekend'   => 'Wochenende',
        'date'      => 'Date Night',
    );

    /**
     * Constructor.
     */
    public function __construct() {
        $this->db = new StyleGenius_Database();
        $this->vision = new StyleGenius_Vision();
        $this->points = new StyleGenius_Points();
        $this->achievements = new StyleGenius_Achievements();
    }

    /**
     * Get categories.
     *
     * @return array
     */
    public function get_categories(): array {
        return $this->categories;
    }

    /**
     * Get seasons.
     *
     * @return array
     */
    public function get_seasons(): array {
        return $this->seasons;
    }

    /**
     * Get occasions.
     *
     * @return array
     */
    public function get_occasions(): array {
        return $this->occasions;
    }

    /**
     * Add item to wardrobe.
     *
     * @param int   $user_id User ID.
     * @param array $data    Item data.
     * @return array Result.
     */
    public function add_item(int $user_id, array $data): array {
        global $wpdb;

        // Validate required fields
        if (empty($data['image_id'])) {
            return array(
                'success' => false,
                'error'   => 'Ein Bild ist erforderlich.',
            );
        }

        // Get image URL
        $image_url = wp_get_attachment_url($data['image_id']);
        if (!$image_url) {
            return array(
                'success' => false,
                'error'   => 'Bild nicht gefunden.',
            );
        }

        // Auto-detect category and attributes with AI if not provided
        $analysis = array();
        if (empty($data['category']) || empty($data['colors'])) {
            $tier = get_user_meta($user_id, 'sg_tier', true) ?: 'free';
            if ($tier !== 'free') {
                $analysis = $this->vision->analyze_clothing($image_url);
            }
        }

        // Prepare item data
        $item_data = array(
            'user_id'     => $user_id,
            'image_id'    => intval($data['image_id']),
            'name'        => sanitize_text_field($data['name'] ?? ''),
            'category'    => sanitize_text_field($data['category'] ?? $analysis['category'] ?? 'tops'),
            'subcategory' => sanitize_text_field($data['subcategory'] ?? $analysis['subcategory'] ?? ''),
            'brand'       => sanitize_text_field($data['brand'] ?? ''),
            'colors'      => wp_json_encode($data['colors'] ?? $analysis['colors'] ?? array()),
            'seasons'     => wp_json_encode($data['seasons'] ?? array('all')),
            'occasions'   => wp_json_encode($data['occasions'] ?? array('casual')),
            'tags'        => wp_json_encode($data['tags'] ?? $analysis['tags'] ?? array()),
            'favorite'    => isset($data['favorite']) ? 1 : 0,
            'wear_count'  => 0,
            'ai_analysis' => !empty($analysis) ? wp_json_encode($analysis) : null,
            'created_at'  => current_time('mysql'),
            'updated_at'  => current_time('mysql'),
        );

        $table = $wpdb->prefix . 'sg_wardrobe';

        $result = $wpdb->insert($table, $item_data);

        if (false === $result) {
            return array(
                'success' => false,
                'error'   => 'Fehler beim Speichern des Kleidungsstücks.',
            );
        }

        $item_id = $wpdb->insert_id;

        // Award points
        $this->points->award_points($user_id, 'add_wardrobe_item', 10, 'Kleidungsstück hinzugefügt');

        // Check achievements
        $this->check_wardrobe_achievements($user_id);

        return array(
            'success'  => true,
            'item_id'  => $item_id,
            'item'     => $this->get_item($item_id, $user_id),
            'analysis' => $analysis,
        );
    }

    /**
     * Update wardrobe item.
     *
     * @param int   $item_id Item ID.
     * @param int   $user_id User ID.
     * @param array $data    Updated data.
     * @return array Result.
     */
    public function update_item(int $item_id, int $user_id, array $data): array {
        global $wpdb;

        $table = $wpdb->prefix . 'sg_wardrobe';

        // Verify ownership
        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d AND user_id = %d",
                $item_id,
                $user_id
            )
        );

        if (!$existing) {
            return array(
                'success' => false,
                'error'   => 'Kleidungsstück nicht gefunden.',
            );
        }

        // Prepare update data
        $update_data = array(
            'updated_at' => current_time('mysql'),
        );

        $allowed_fields = array('name', 'category', 'subcategory', 'brand', 'favorite');
        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                $update_data[$field] = $field === 'favorite'
                    ? (int) $data[$field]
                    : sanitize_text_field($data[$field]);
            }
        }

        // Handle array fields
        $array_fields = array('colors', 'seasons', 'occasions', 'tags');
        foreach ($array_fields as $field) {
            if (isset($data[$field])) {
                $update_data[$field] = wp_json_encode($data[$field]);
            }
        }

        $result = $wpdb->update(
            $table,
            $update_data,
            array('id' => $item_id)
        );

        if (false === $result) {
            return array(
                'success' => false,
                'error'   => 'Fehler beim Aktualisieren.',
            );
        }

        return array(
            'success' => true,
            'item'    => $this->get_item($item_id, $user_id),
        );
    }

    /**
     * Delete wardrobe item.
     *
     * @param int $item_id Item ID.
     * @param int $user_id User ID.
     * @return array Result.
     */
    public function delete_item(int $item_id, int $user_id): array {
        global $wpdb;

        $table = $wpdb->prefix . 'sg_wardrobe';

        // Verify ownership and get image ID
        $item = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d AND user_id = %d",
                $item_id,
                $user_id
            )
        );

        if (!$item) {
            return array(
                'success' => false,
                'error'   => 'Kleidungsstück nicht gefunden.',
            );
        }

        // Delete the item
        $result = $wpdb->delete(
            $table,
            array('id' => $item_id),
            array('%d')
        );

        if (false === $result) {
            return array(
                'success' => false,
                'error'   => 'Fehler beim Löschen.',
            );
        }

        // Optionally delete the image
        if ($item->image_id) {
            wp_delete_attachment($item->image_id, true);
        }

        return array(
            'success' => true,
        );
    }

    /**
     * Get single wardrobe item.
     *
     * @param int $item_id Item ID.
     * @param int $user_id User ID.
     * @return array|null
     */
    public function get_item(int $item_id, int $user_id): ?array {
        global $wpdb;

        $table = $wpdb->prefix . 'sg_wardrobe';

        $item = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d AND user_id = %d",
                $item_id,
                $user_id
            ),
            ARRAY_A
        );

        if (!$item) {
            return null;
        }

        return $this->format_item($item);
    }

    /**
     * Get user's wardrobe items.
     *
     * @param int   $user_id User ID.
     * @param array $filters Filter options.
     * @return array
     */
    public function get_items(int $user_id, array $filters = array()): array {
        global $wpdb;

        $table = $wpdb->prefix . 'sg_wardrobe';

        $where = array("user_id = %d");
        $params = array($user_id);

        // Category filter
        if (!empty($filters['category'])) {
            $where[] = "category = %s";
            $params[] = $filters['category'];
        }

        // Season filter
        if (!empty($filters['season'])) {
            $where[] = "(seasons LIKE %s OR seasons LIKE %s)";
            $params[] = '%"' . $filters['season'] . '"%';
            $params[] = '%"all"%';
        }

        // Occasion filter
        if (!empty($filters['occasion'])) {
            $where[] = "occasions LIKE %s";
            $params[] = '%"' . $filters['occasion'] . '"%';
        }

        // Color filter
        if (!empty($filters['color'])) {
            $where[] = "colors LIKE %s";
            $params[] = '%"' . $filters['color'] . '"%';
        }

        // Favorites only
        if (!empty($filters['favorites'])) {
            $where[] = "favorite = 1";
        }

        // Search
        if (!empty($filters['search'])) {
            $where[] = "(name LIKE %s OR brand LIKE %s OR tags LIKE %s)";
            $search = '%' . $wpdb->esc_like($filters['search']) . '%';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        $where_sql = implode(' AND ', $where);

        // Sorting
        $order_by = 'created_at';
        $order = 'DESC';

        if (!empty($filters['sort'])) {
            switch ($filters['sort']) {
                case 'name':
                    $order_by = 'name';
                    $order = 'ASC';
                    break;
                case 'wear_count':
                    $order_by = 'wear_count';
                    $order = 'DESC';
                    break;
                case 'oldest':
                    $order_by = 'created_at';
                    $order = 'ASC';
                    break;
            }
        }

        // Pagination
        $limit = intval($filters['limit'] ?? 50);
        $offset = intval($filters['offset'] ?? 0);

        $sql = $wpdb->prepare(
            "SELECT * FROM {$table}
            WHERE {$where_sql}
            ORDER BY {$order_by} {$order}
            LIMIT %d OFFSET %d",
            array_merge($params, array($limit, $offset))
        );

        $items = $wpdb->get_results($sql, ARRAY_A);

        $formatted = array();
        foreach ($items as $item) {
            $formatted[] = $this->format_item($item);
        }

        // Get total count
        $total = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}",
                $params
            )
        );

        return array(
            'items'  => $formatted,
            'total'  => intval($total),
            'limit'  => $limit,
            'offset' => $offset,
        );
    }

    /**
     * Format item for output.
     *
     * @param array $item Raw item data.
     * @return array
     */
    private function format_item(array $item): array {
        return array(
            'id'          => intval($item['id']),
            'image_id'    => intval($item['image_id']),
            'image_url'   => wp_get_attachment_url($item['image_id']),
            'thumbnail'   => wp_get_attachment_image_url($item['image_id'], 'medium'),
            'name'        => $item['name'],
            'category'    => $item['category'],
            'subcategory' => $item['subcategory'],
            'brand'       => $item['brand'],
            'colors'      => json_decode($item['colors'], true) ?: array(),
            'seasons'     => json_decode($item['seasons'], true) ?: array(),
            'occasions'   => json_decode($item['occasions'], true) ?: array(),
            'tags'        => json_decode($item['tags'], true) ?: array(),
            'favorite'    => (bool) $item['favorite'],
            'wear_count'  => intval($item['wear_count']),
            'ai_analysis' => $item['ai_analysis'] ? json_decode($item['ai_analysis'], true) : null,
            'created_at'  => $item['created_at'],
        );
    }

    /**
     * Record item wear.
     *
     * @param int $item_id Item ID.
     * @param int $user_id User ID.
     * @return bool
     */
    public function record_wear(int $item_id, int $user_id): bool {
        global $wpdb;

        $table = $wpdb->prefix . 'sg_wardrobe';

        $result = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table}
                SET wear_count = wear_count + 1, last_worn = %s
                WHERE id = %d AND user_id = %d",
                current_time('mysql'),
                $item_id,
                $user_id
            )
        );

        return false !== $result;
    }

    /**
     * Toggle favorite status.
     *
     * @param int $item_id Item ID.
     * @param int $user_id User ID.
     * @return array Result.
     */
    public function toggle_favorite(int $item_id, int $user_id): array {
        global $wpdb;

        $table = $wpdb->prefix . 'sg_wardrobe';

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table}
                SET favorite = NOT favorite
                WHERE id = %d AND user_id = %d",
                $item_id,
                $user_id
            )
        );

        $item = $this->get_item($item_id, $user_id);

        return array(
            'success'  => true,
            'favorite' => $item ? $item['favorite'] : false,
        );
    }

    /**
     * Get wardrobe statistics.
     *
     * @param int $user_id User ID.
     * @return array
     */
    public function get_statistics(int $user_id): array {
        global $wpdb;

        $table = $wpdb->prefix . 'sg_wardrobe';

        // Total items
        $total = intval($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE user_id = %d",
                $user_id
            )
        ));

        // By category
        $by_category = array();
        $category_results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT category, COUNT(*) as count
                FROM {$table}
                WHERE user_id = %d
                GROUP BY category",
                $user_id
            ),
            ARRAY_A
        );

        foreach ($category_results as $row) {
            $by_category[$row['category']] = intval($row['count']);
        }

        // Most worn
        $most_worn = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                WHERE user_id = %d AND wear_count > 0
                ORDER BY wear_count DESC
                LIMIT 5",
                $user_id
            ),
            ARRAY_A
        );

        // Least worn
        $least_worn = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                WHERE user_id = %d
                ORDER BY wear_count ASC, created_at ASC
                LIMIT 5",
                $user_id
            ),
            ARRAY_A
        );

        // Color distribution
        $color_distribution = $this->analyze_color_distribution($user_id);

        return array(
            'total'              => $total,
            'by_category'        => $by_category,
            'favorites'          => intval($wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND favorite = 1",
                    $user_id
                )
            )),
            'most_worn'          => array_map(array($this, 'format_item'), $most_worn),
            'least_worn'         => array_map(array($this, 'format_item'), $least_worn),
            'color_distribution' => $color_distribution,
        );
    }

    /**
     * Analyze color distribution in wardrobe.
     *
     * @param int $user_id User ID.
     * @return array
     */
    private function analyze_color_distribution(int $user_id): array {
        global $wpdb;

        $table = $wpdb->prefix . 'sg_wardrobe';

        $items = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT colors FROM {$table} WHERE user_id = %d",
                $user_id
            )
        );

        $color_counts = array();

        foreach ($items as $colors_json) {
            $colors = json_decode($colors_json, true) ?: array();
            foreach ($colors as $color) {
                $color = strtolower($color);
                if (!isset($color_counts[$color])) {
                    $color_counts[$color] = 0;
                }
                $color_counts[$color]++;
            }
        }

        arsort($color_counts);

        return $color_counts;
    }

    /**
     * Generate outfit suggestion.
     *
     * @param int   $user_id User ID.
     * @param array $options Options (occasion, season, etc.).
     * @return array
     */
    public function suggest_outfit(int $user_id, array $options = array()): array {
        // Get available items
        $filters = array();

        if (!empty($options['occasion'])) {
            $filters['occasion'] = $options['occasion'];
        }

        if (!empty($options['season'])) {
            $filters['season'] = $options['season'];
        } else {
            $filters['season'] = $this->get_current_season();
        }

        $wardrobe = $this->get_items($user_id, array_merge($filters, array('limit' => 200)));

        if ($wardrobe['total'] < 3) {
            return array(
                'success' => false,
                'error'   => 'Du brauchst mindestens 3 Kleidungsstücke für einen Outfit-Vorschlag.',
            );
        }

        // Group by category
        $by_category = array();
        foreach ($wardrobe['items'] as $item) {
            $cat = $item['category'];
            if (!isset($by_category[$cat])) {
                $by_category[$cat] = array();
            }
            $by_category[$cat][] = $item;
        }

        // Build outfit
        $outfit = array();

        // Select top
        if (!empty($by_category['tops'])) {
            $outfit['top'] = $this->random_item($by_category['tops']);
        }

        // Select bottom (or dress)
        if (!empty($by_category['dresses']) && rand(0, 1)) {
            $outfit['dress'] = $this->random_item($by_category['dresses']);
            unset($outfit['top']);
        } elseif (!empty($by_category['bottoms'])) {
            $outfit['bottom'] = $this->random_item($by_category['bottoms']);
        }

        // Select outerwear (seasonal)
        if (!empty($by_category['outerwear']) && in_array($filters['season'], array('fall', 'winter', 'spring'))) {
            $outfit['outerwear'] = $this->random_item($by_category['outerwear']);
        }

        // Select shoes
        if (!empty($by_category['shoes'])) {
            $outfit['shoes'] = $this->random_item($by_category['shoes']);
        }

        // Select accessory
        if (!empty($by_category['accessories']) && rand(0, 1)) {
            $outfit['accessory'] = $this->random_item($by_category['accessories']);
        }

        // Select bag
        if (!empty($by_category['bags']) && rand(0, 1)) {
            $outfit['bag'] = $this->random_item($by_category['bags']);
        }

        if (empty($outfit)) {
            return array(
                'success' => false,
                'error'   => 'Konnte kein passendes Outfit zusammenstellen.',
            );
        }

        return array(
            'success' => true,
            'outfit'  => $outfit,
            'season'  => $filters['season'],
            'occasion' => $options['occasion'] ?? 'casual',
        );
    }

    /**
     * Get current season.
     *
     * @return string
     */
    private function get_current_season(): string {
        $month = intval(date('n'));

        if ($month >= 3 && $month <= 5) {
            return 'spring';
        } elseif ($month >= 6 && $month <= 8) {
            return 'summer';
        } elseif ($month >= 9 && $month <= 11) {
            return 'fall';
        } else {
            return 'winter';
        }
    }

    /**
     * Select random item from array.
     *
     * @param array $items Items array.
     * @return array
     */
    private function random_item(array $items): array {
        return $items[array_rand($items)];
    }

    /**
     * Analyze wardrobe gaps.
     *
     * @param int $user_id User ID.
     * @return array
     */
    public function analyze_gaps(int $user_id): array {
        $stats = $this->get_statistics($user_id);
        $gaps = array();
        $suggestions = array();

        // Check essential categories
        $essentials = array(
            'tops'      => 5,
            'bottoms'   => 3,
            'outerwear' => 2,
            'shoes'     => 3,
        );

        foreach ($essentials as $category => $min) {
            $count = $stats['by_category'][$category] ?? 0;
            if ($count < $min) {
                $gaps[] = array(
                    'category' => $category,
                    'name'     => $this->categories[$category]['name'],
                    'have'     => $count,
                    'need'     => $min,
                    'missing'  => $min - $count,
                );
            }
        }

        // Check color diversity
        $colors = $stats['color_distribution'];
        $neutral_colors = array('schwarz', 'weiß', 'grau', 'beige', 'navy');
        $has_neutrals = false;
        $has_colors = false;

        foreach ($colors as $color => $count) {
            if (in_array(strtolower($color), $neutral_colors)) {
                $has_neutrals = true;
            } else {
                $has_colors = true;
            }
        }

        if (!$has_neutrals) {
            $suggestions[] = 'Füge mehr neutrale Basics hinzu (Schwarz, Weiß, Grau, Beige).';
        }

        if (!$has_colors && $stats['total'] > 10) {
            $suggestions[] = 'Deine Garderobe könnte mehr Farbakzente vertragen.';
        }

        // Check occasion coverage
        $style_type = get_user_meta($user_id, 'sg_style_type', true);
        if ($style_type === 'classic' && ($stats['by_category']['outerwear'] ?? 0) < 2) {
            $suggestions[] = 'Für deinen klassischen Stil fehlt noch ein guter Blazer.';
        }

        return array(
            'gaps'        => $gaps,
            'suggestions' => $suggestions,
            'score'       => $this->calculate_wardrobe_score($stats),
        );
    }

    /**
     * Calculate wardrobe completeness score.
     *
     * @param array $stats Wardrobe statistics.
     * @return int Score 0-100.
     */
    private function calculate_wardrobe_score(array $stats): int {
        $score = 0;
        $max_score = 100;

        // Points for total items (max 30)
        $score += min(30, $stats['total'] * 2);

        // Points for category diversity (max 35)
        $categories_with_items = count(array_filter($stats['by_category']));
        $score += min(35, $categories_with_items * 5);

        // Points for color diversity (max 20)
        $unique_colors = count($stats['color_distribution']);
        $score += min(20, $unique_colors * 4);

        // Points for having favorites marked (max 15)
        if ($stats['favorites'] > 0) {
            $score += min(15, $stats['favorites'] * 3);
        }

        return min($max_score, $score);
    }

    /**
     * Check and award wardrobe achievements.
     *
     * @param int $user_id User ID.
     * @return void
     */
    private function check_wardrobe_achievements(int $user_id): void {
        global $wpdb;

        $table = $wpdb->prefix . 'sg_wardrobe';

        $count = intval($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE user_id = %d",
                $user_id
            )
        ));

        // First item
        if ($count >= 1) {
            $this->achievements->award_achievement($user_id, 'first_item');
        }

        // 10 items
        if ($count >= 10) {
            $this->achievements->award_achievement($user_id, 'wardrobe_10');
        }

        // 50 items
        if ($count >= 50) {
            $this->achievements->award_achievement($user_id, 'wardrobe_50');
        }

        // 100 items
        if ($count >= 100) {
            $this->achievements->award_achievement($user_id, 'wardrobe_100');
        }
    }

    /**
     * Export wardrobe data for GDPR.
     *
     * @param int $user_id User ID.
     * @return array
     */
    public function export_data(int $user_id): array {
        $items = $this->get_items($user_id, array('limit' => 1000));
        return $items['items'];
    }

    /**
     * Delete all wardrobe items for user (GDPR).
     *
     * @param int $user_id User ID.
     * @return bool
     */
    public function delete_all(int $user_id): bool {
        global $wpdb;

        $table = $wpdb->prefix . 'sg_wardrobe';

        // Get all image IDs first
        $image_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT image_id FROM {$table} WHERE user_id = %d",
                $user_id
            )
        );

        // Delete items
        $wpdb->delete(
            $table,
            array('user_id' => $user_id),
            array('%d')
        );

        // Delete images
        foreach ($image_ids as $image_id) {
            wp_delete_attachment($image_id, true);
        }

        return true;
    }
}
