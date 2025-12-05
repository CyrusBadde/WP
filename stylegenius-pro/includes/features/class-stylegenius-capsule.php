<?php
/**
 * StyleGenius Capsule Class
 *
 * Handles Capsule Wardrobe generation and management.
 *
 * @package    StyleGenius_Pro
 * @subpackage StyleGenius_Pro/includes/features
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Capsule Wardrobe management class.
 */
class StyleGenius_Capsule {

    /**
     * Database instance.
     *
     * @var StyleGenius_Database
     */
    private $db;

    /**
     * AI Manager instance.
     *
     * @var StyleGenius_AI_Manager
     */
    private $ai;

    /**
     * Wardrobe instance.
     *
     * @var StyleGenius_Wardrobe
     */
    private $wardrobe;

    /**
     * Points instance.
     *
     * @var StyleGenius_Points
     */
    private $points;

    /**
     * Capsule presets.
     *
     * @var array
     */
    private $presets = array(
        'minimal' => array(
            'name'        => 'Minimalistisch',
            'description' => '33 Teile für maximale Vielseitigkeit',
            'items'       => 33,
            'composition' => array(
                'tops'      => 9,
                'bottoms'   => 5,
                'dresses'   => 3,
                'outerwear' => 4,
                'shoes'     => 6,
                'bags'      => 3,
                'accessories' => 3,
            ),
        ),
        'work' => array(
            'name'        => 'Business Capsule',
            'description' => 'Professionelle Looks für die Arbeitswoche',
            'items'       => 25,
            'composition' => array(
                'tops'      => 7,
                'bottoms'   => 4,
                'dresses'   => 2,
                'outerwear' => 3,
                'shoes'     => 4,
                'bags'      => 3,
                'accessories' => 2,
            ),
        ),
        'travel' => array(
            'name'        => 'Reise Capsule',
            'description' => 'Kompakte Garderobe für unterwegs',
            'items'       => 15,
            'composition' => array(
                'tops'      => 5,
                'bottoms'   => 3,
                'dresses'   => 1,
                'outerwear' => 1,
                'shoes'     => 2,
                'bags'      => 2,
                'accessories' => 1,
            ),
        ),
        'seasonal' => array(
            'name'        => 'Saisonale Capsule',
            'description' => 'Optimiert für eine Jahreszeit',
            'items'       => 40,
            'composition' => array(
                'tops'      => 10,
                'bottoms'   => 6,
                'dresses'   => 4,
                'outerwear' => 5,
                'shoes'     => 7,
                'bags'      => 4,
                'accessories' => 4,
            ),
        ),
    );

    /**
     * Constructor.
     */
    public function __construct() {
        $this->db = new StyleGenius_Database();
        $this->ai = new StyleGenius_AI_Manager();
        $this->wardrobe = new StyleGenius_Wardrobe();
        $this->points = new StyleGenius_Points();
    }

    /**
     * Get available presets.
     *
     * @return array
     */
    public function get_presets(): array {
        return $this->presets;
    }

    /**
     * Generate capsule wardrobe.
     *
     * @param int   $user_id User ID.
     * @param array $options Generation options.
     * @return array
     */
    public function generate(int $user_id, array $options = array()): array {
        // Check tier
        $tier = get_user_meta($user_id, 'sg_tier', true) ?: 'free';
        if ($tier === 'free') {
            return array(
                'success' => false,
                'error'   => 'Capsule Wardrobe ist ein Premium-Feature.',
                'upgrade' => true,
            );
        }

        // Get user's wardrobe
        $wardrobe_data = $this->wardrobe->get_items($user_id, array('limit' => 500));

        if ($wardrobe_data['total'] < 10) {
            return array(
                'success' => false,
                'error'   => 'Du brauchst mindestens 10 Kleidungsstücke für eine Capsule Wardrobe.',
            );
        }

        // Determine preset
        $preset_key = $options['preset'] ?? 'minimal';
        $preset = $this->presets[$preset_key] ?? $this->presets['minimal'];

        // Season filter
        $season = $options['season'] ?? $this->get_current_season();

        // Filter items for season
        $available_items = array();
        foreach ($wardrobe_data['items'] as $item) {
            if (in_array($season, $item['seasons']) || in_array('all', $item['seasons'])) {
                $cat = $item['category'];
                if (!isset($available_items[$cat])) {
                    $available_items[$cat] = array();
                }
                $available_items[$cat][] = $item;
            }
        }

        // Build capsule
        $capsule_items = array();

        foreach ($preset['composition'] as $category => $count) {
            if (!isset($available_items[$category])) {
                continue;
            }

            $category_items = $available_items[$category];

            // Score and sort items
            usort($category_items, function ($a, $b) {
                // Prioritize favorites
                if ($a['favorite'] !== $b['favorite']) {
                    return $b['favorite'] - $a['favorite'];
                }
                // Then by wear count
                return $b['wear_count'] - $a['wear_count'];
            });

            // Select top items
            $selected = array_slice($category_items, 0, $count);
            $capsule_items = array_merge($capsule_items, $selected);
        }

        if (empty($capsule_items)) {
            return array(
                'success' => false,
                'error'   => 'Nicht genug passende Kleidungsstücke für diese Capsule.',
            );
        }

        // Get AI recommendations for combinations
        $ai_analysis = null;
        if ($tier === 'vip') {
            $ai_analysis = $this->get_ai_analysis($user_id, $capsule_items);
        }

        // Save capsule
        $capsule_id = $this->save_capsule($user_id, array(
            'preset'  => $preset_key,
            'season'  => $season,
            'items'   => array_column($capsule_items, 'id'),
            'analysis' => $ai_analysis,
        ));

        // Award points
        $this->points->award_points($user_id, 'capsule_created', 25, 'Capsule Wardrobe erstellt');

        // Generate outfit plan
        $outfit_plan = $this->generate_outfit_plan($capsule_items, 30);

        return array(
            'success'     => true,
            'capsule_id'  => $capsule_id,
            'preset'      => $preset,
            'season'      => $season,
            'items'       => $capsule_items,
            'item_count'  => count($capsule_items),
            'outfit_plan' => $outfit_plan,
            'ai_analysis' => $ai_analysis,
            'grid'        => $this->generate_grid($capsule_items),
        );
    }

    /**
     * Get AI analysis for capsule.
     *
     * @param int   $user_id User ID.
     * @param array $items   Capsule items.
     * @return array|null
     */
    private function get_ai_analysis(int $user_id, array $items): ?array {
        $item_descriptions = array();
        foreach ($items as $item) {
            $colors = implode(', ', $item['colors']);
            $item_descriptions[] = "{$item['name']} ({$item['category']}, {$colors})";
        }

        $prompt = "Analysiere diese Capsule Wardrobe:\n" . implode("\n", $item_descriptions);
        $prompt .= "\n\nGib mir:\n1. Eine Bewertung der Farbharmonie\n2. 5 kreative Outfit-Kombinationen\n3. Tipps, was noch fehlen könnte";

        $response = $this->ai->chat($user_id, $prompt, array('type' => 'capsule'));

        if (!$response['success']) {
            return null;
        }

        return array(
            'recommendations' => $response['response'],
            'generated_at'    => current_time('mysql'),
        );
    }

    /**
     * Save capsule to database.
     *
     * @param int   $user_id User ID.
     * @param array $data    Capsule data.
     * @return int|false
     */
    private function save_capsule(int $user_id, array $data) {
        global $wpdb;

        $table = $wpdb->prefix . 'sg_capsules';

        $result = $wpdb->insert(
            $table,
            array(
                'user_id'     => $user_id,
                'name'        => $this->presets[$data['preset']]['name'] . ' - ' . ucfirst($data['season']),
                'season'      => $data['season'],
                'items'       => wp_json_encode($data['items']),
                'settings'    => wp_json_encode(array('preset' => $data['preset'])),
                'ai_analysis' => $data['analysis'] ? wp_json_encode($data['analysis']) : null,
                'created_at'  => current_time('mysql'),
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Get user's capsules.
     *
     * @param int $user_id User ID.
     * @return array
     */
    public function get_capsules(int $user_id): array {
        global $wpdb;

        $table = $wpdb->prefix . 'sg_capsules';

        $capsules = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                WHERE user_id = %d
                ORDER BY created_at DESC",
                $user_id
            ),
            ARRAY_A
        );

        $formatted = array();
        foreach ($capsules as $capsule) {
            $formatted[] = $this->format_capsule($capsule);
        }

        return $formatted;
    }

    /**
     * Get single capsule.
     *
     * @param int $capsule_id Capsule ID.
     * @param int $user_id    User ID.
     * @return array|null
     */
    public function get_capsule(int $capsule_id, int $user_id): ?array {
        global $wpdb;

        $table = $wpdb->prefix . 'sg_capsules';

        $capsule = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                WHERE id = %d AND user_id = %d",
                $capsule_id,
                $user_id
            ),
            ARRAY_A
        );

        if (!$capsule) {
            return null;
        }

        return $this->format_capsule($capsule, true);
    }

    /**
     * Format capsule data.
     *
     * @param array $capsule       Raw capsule data.
     * @param bool  $include_items Include full item data.
     * @return array
     */
    private function format_capsule(array $capsule, bool $include_items = false): array {
        $item_ids = json_decode($capsule['items'], true) ?: array();
        $settings = json_decode($capsule['settings'], true) ?: array();

        $formatted = array(
            'id'         => intval($capsule['id']),
            'name'       => $capsule['name'],
            'season'     => $capsule['season'],
            'item_count' => count($item_ids),
            'settings'   => $settings,
            'created_at' => $capsule['created_at'],
        );

        if ($include_items) {
            $items = array();
            foreach ($item_ids as $item_id) {
                $item = $this->wardrobe->get_item($item_id, intval($capsule['user_id']));
                if ($item) {
                    $items[] = $item;
                }
            }
            $formatted['items'] = $items;
            $formatted['grid'] = $this->generate_grid($items);

            if ($capsule['ai_analysis']) {
                $formatted['ai_analysis'] = json_decode($capsule['ai_analysis'], true);
            }
        }

        return $formatted;
    }

    /**
     * Delete capsule.
     *
     * @param int $capsule_id Capsule ID.
     * @param int $user_id    User ID.
     * @return bool
     */
    public function delete_capsule(int $capsule_id, int $user_id): bool {
        global $wpdb;

        $table = $wpdb->prefix . 'sg_capsules';

        $result = $wpdb->delete(
            $table,
            array(
                'id'      => $capsule_id,
                'user_id' => $user_id,
            ),
            array('%d', '%d')
        );

        return false !== $result;
    }

    /**
     * Generate outfit plan for days.
     *
     * @param array $items Capsule items.
     * @param int   $days  Number of days.
     * @return array
     */
    public function generate_outfit_plan(array $items, int $days = 30): array {
        // Group items by category
        $by_category = array();
        foreach ($items as $item) {
            $cat = $item['category'];
            if (!isset($by_category[$cat])) {
                $by_category[$cat] = array();
            }
            $by_category[$cat][] = $item;
        }

        $plan = array();
        $used_combinations = array();

        for ($day = 1; $day <= $days; $day++) {
            $outfit = array();
            $combo_key = '';

            // Decide between dress or top+bottom
            $use_dress = !empty($by_category['dresses']) && ($day % 3 === 0);

            if ($use_dress) {
                $outfit['dress'] = $this->select_least_used($by_category['dresses'], $used_combinations, 'dress');
                $combo_key = 'dress_' . $outfit['dress']['id'];
            } else {
                if (!empty($by_category['tops'])) {
                    $outfit['top'] = $this->select_least_used($by_category['tops'], $used_combinations, 'top');
                    $combo_key .= 'top_' . $outfit['top']['id'];
                }

                if (!empty($by_category['bottoms'])) {
                    $outfit['bottom'] = $this->select_least_used($by_category['bottoms'], $used_combinations, 'bottom');
                    $combo_key .= '_bottom_' . $outfit['bottom']['id'];
                }
            }

            // Add outerwear
            if (!empty($by_category['outerwear']) && ($day % 2 === 0 || $day <= 7)) {
                $outfit['outerwear'] = $this->select_least_used($by_category['outerwear'], $used_combinations, 'outerwear');
            }

            // Add shoes
            if (!empty($by_category['shoes'])) {
                $outfit['shoes'] = $this->select_least_used($by_category['shoes'], $used_combinations, 'shoes');
            }

            // Add occasional accessory
            if (!empty($by_category['accessories']) && $day % 2 === 0) {
                $outfit['accessory'] = $this->select_least_used($by_category['accessories'], $used_combinations, 'accessory');
            }

            // Track combination
            if (!isset($used_combinations[$combo_key])) {
                $used_combinations[$combo_key] = 0;
            }
            $used_combinations[$combo_key]++;

            $plan[] = array(
                'day'    => $day,
                'date'   => date('Y-m-d', strtotime("+{$day} days")),
                'outfit' => $outfit,
            );
        }

        return $plan;
    }

    /**
     * Select least used item from category.
     *
     * @param array  $items       Items in category.
     * @param array  $used        Used items tracker.
     * @param string $category    Category name.
     * @return array
     */
    private function select_least_used(array $items, array $used, string $category): array {
        // Count usage
        $usage_counts = array();
        foreach ($items as $item) {
            $key = $category . '_' . $item['id'];
            $usage_counts[$item['id']] = 0;

            foreach ($used as $combo => $count) {
                if (strpos($combo, $key) !== false) {
                    $usage_counts[$item['id']] += $count;
                }
            }
        }

        // Sort by usage
        asort($usage_counts);
        $least_used_id = array_key_first($usage_counts);

        // Find and return item
        foreach ($items as $item) {
            if ($item['id'] == $least_used_id) {
                return $item;
            }
        }

        // Fallback to random
        return $items[array_rand($items)];
    }

    /**
     * Generate mix & match grid.
     *
     * @param array $items Capsule items.
     * @return array
     */
    public function generate_grid(array $items): array {
        $grid = array(
            'tops'        => array(),
            'bottoms'     => array(),
            'dresses'     => array(),
            'outerwear'   => array(),
            'shoes'       => array(),
            'bags'        => array(),
            'accessories' => array(),
        );

        foreach ($items as $item) {
            $cat = $item['category'];
            if (isset($grid[$cat])) {
                $grid[$cat][] = array(
                    'id'        => $item['id'],
                    'thumbnail' => $item['thumbnail'],
                    'name'      => $item['name'],
                    'colors'    => $item['colors'],
                );
            }
        }

        // Remove empty categories
        return array_filter($grid);
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
     * Get outfit of the day.
     *
     * @param int $user_id User ID.
     * @param int $capsule_id Capsule ID (optional).
     * @return array
     */
    public function get_outfit_of_the_day(int $user_id, ?int $capsule_id = null): array {
        // Check if already generated today
        $cache_key = "sg_ootd_{$user_id}_{$capsule_id}";
        $cached = get_transient($cache_key);

        if (false !== $cached) {
            return $cached;
        }

        // Get items to choose from
        if ($capsule_id) {
            $capsule = $this->get_capsule($capsule_id, $user_id);
            if (!$capsule) {
                return array(
                    'success' => false,
                    'error'   => 'Capsule nicht gefunden.',
                );
            }
            $items = $capsule['items'];
        } else {
            // Use full wardrobe with season filter
            $wardrobe = $this->wardrobe->get_items($user_id, array(
                'season' => $this->get_current_season(),
                'limit'  => 200,
            ));
            $items = $wardrobe['items'];
        }

        if (count($items) < 3) {
            return array(
                'success' => false,
                'error'   => 'Nicht genug Kleidungsstücke.',
            );
        }

        // Use day of year as seed for consistent daily outfit
        $seed = intval(date('z')) + $user_id;
        mt_srand($seed);

        // Group by category
        $by_category = array();
        foreach ($items as $item) {
            $cat = $item['category'];
            if (!isset($by_category[$cat])) {
                $by_category[$cat] = array();
            }
            $by_category[$cat][] = $item;
        }

        $outfit = array();

        // Pick items
        if (!empty($by_category['dresses']) && mt_rand(0, 2) === 0) {
            $outfit['dress'] = $by_category['dresses'][mt_rand(0, count($by_category['dresses']) - 1)];
        } else {
            if (!empty($by_category['tops'])) {
                $outfit['top'] = $by_category['tops'][mt_rand(0, count($by_category['tops']) - 1)];
            }
            if (!empty($by_category['bottoms'])) {
                $outfit['bottom'] = $by_category['bottoms'][mt_rand(0, count($by_category['bottoms']) - 1)];
            }
        }

        if (!empty($by_category['outerwear'])) {
            $outfit['outerwear'] = $by_category['outerwear'][mt_rand(0, count($by_category['outerwear']) - 1)];
        }

        if (!empty($by_category['shoes'])) {
            $outfit['shoes'] = $by_category['shoes'][mt_rand(0, count($by_category['shoes']) - 1)];
        }

        if (!empty($by_category['accessories']) && mt_rand(0, 1)) {
            $outfit['accessory'] = $by_category['accessories'][mt_rand(0, count($by_category['accessories']) - 1)];
        }

        // Reset random seed
        mt_srand();

        $result = array(
            'success' => true,
            'date'    => date('Y-m-d'),
            'outfit'  => $outfit,
        );

        // Cache until end of day
        $seconds_until_midnight = strtotime('tomorrow') - time();
        set_transient($cache_key, $result, $seconds_until_midnight);

        return $result;
    }

    /**
     * Export capsule data for GDPR.
     *
     * @param int $user_id User ID.
     * @return array
     */
    public function export_data(int $user_id): array {
        return $this->get_capsules($user_id);
    }

    /**
     * Delete all capsules for user (GDPR).
     *
     * @param int $user_id User ID.
     * @return bool
     */
    public function delete_all(int $user_id): bool {
        global $wpdb;

        $table = $wpdb->prefix . 'sg_capsules';

        $result = $wpdb->delete(
            $table,
            array('user_id' => $user_id),
            array('%d')
        );

        return false !== $result;
    }
}
