<?php
/**
 * StyleGenius Shopping Class
 *
 * Handles Shopping Assistant functionality with affiliate integration.
 *
 * @package    StyleGenius_Pro
 * @subpackage StyleGenius_Pro/includes/features
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shopping Assistant management class.
 */
class StyleGenius_Shopping {

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
     * Points instance.
     *
     * @var StyleGenius_Points
     */
    private $points;

    /**
     * Affiliate partners.
     *
     * @var array
     */
    private $partners = array(
        'zalando' => array(
            'name'          => 'Zalando',
            'base_url'      => 'https://www.zalando.de',
            'search_url'    => 'https://www.zalando.de/katalog/',
            'affiliate_tag' => 'sg_affiliate_zalando_id',
            'icon'          => 'zalando.svg',
        ),
        'aboutyou' => array(
            'name'          => 'About You',
            'base_url'      => 'https://www.aboutyou.de',
            'search_url'    => 'https://www.aboutyou.de/suche/',
            'affiliate_tag' => 'sg_affiliate_aboutyou_id',
            'icon'          => 'aboutyou.svg',
        ),
        'amazon' => array(
            'name'          => 'Amazon Fashion',
            'base_url'      => 'https://www.amazon.de/fashion',
            'search_url'    => 'https://www.amazon.de/s?k=',
            'affiliate_tag' => 'sg_affiliate_amazon_id',
            'icon'          => 'amazon.svg',
        ),
        'hm' => array(
            'name'          => 'H&M',
            'base_url'      => 'https://www2.hm.com/de_de',
            'search_url'    => 'https://www2.hm.com/de_de/search-results.html?q=',
            'affiliate_tag' => 'sg_affiliate_hm_id',
            'icon'          => 'hm.svg',
        ),
        'asos' => array(
            'name'          => 'ASOS',
            'base_url'      => 'https://www.asos.com/de/',
            'search_url'    => 'https://www.asos.com/de/suche/?q=',
            'affiliate_tag' => 'sg_affiliate_asos_id',
            'icon'          => 'asos.svg',
        ),
    );

    /**
     * Constructor.
     */
    public function __construct() {
        $this->db = new StyleGenius_Database();
        $this->ai = new StyleGenius_AI_Manager();
        $this->points = new StyleGenius_Points();
    }

    /**
     * Get available partners.
     *
     * @return array
     */
    public function get_partners(): array {
        $active_partners = array();

        foreach ($this->partners as $key => $partner) {
            $affiliate_id = get_option($partner['affiliate_tag'], '');
            if (!empty($affiliate_id)) {
                $active_partners[$key] = $partner;
                $active_partners[$key]['active'] = true;
            } else {
                $active_partners[$key] = $partner;
                $active_partners[$key]['active'] = false;
            }
        }

        return $active_partners;
    }

    /**
     * Get shopping recommendations based on wardrobe gaps.
     *
     * @param int   $user_id User ID.
     * @param array $options Options.
     * @return array
     */
    public function get_recommendations(int $user_id, array $options = array()): array {
        // Check VIP tier
        $tier = get_user_meta($user_id, 'sg_tier', true) ?: 'free';
        if ($tier !== 'vip') {
            return array(
                'success' => false,
                'error'   => 'Der Shopping-Assistent ist ein VIP-Feature.',
                'upgrade' => true,
            );
        }

        // Get user context
        $style_type = get_user_meta($user_id, 'sg_style_type', true);
        $color_type = get_user_meta($user_id, 'sg_color_type', true);

        // Get wardrobe gaps
        $wardrobe = new StyleGenius_Wardrobe();
        $gaps_analysis = $wardrobe->analyze_gaps($user_id);

        // Build AI prompt
        $context = array(
            'style_type' => $style_type,
            'color_type' => $color_type,
            'gaps'       => $gaps_analysis['gaps'],
            'budget'     => $options['budget'] ?? 'mittel',
            'occasion'   => $options['occasion'] ?? null,
        );

        $recommendations = $this->get_ai_recommendations($user_id, $context);

        if (!$recommendations['success']) {
            return $recommendations;
        }

        // Add affiliate links
        $recommendations['shopping_links'] = $this->generate_shopping_links($recommendations['items']);

        // Track usage
        $this->track_recommendation($user_id, $recommendations);

        return $recommendations;
    }

    /**
     * Get AI-powered shopping recommendations.
     *
     * @param int   $user_id User ID.
     * @param array $context User context.
     * @return array
     */
    private function get_ai_recommendations(int $user_id, array $context): array {
        $prompt = "Als Shopping-Berater, empfehle konkrete Kleidungsstücke basierend auf:\n\n";

        if ($context['style_type']) {
            $prompt .= "Style-Typ: " . $context['style_type'] . "\n";
        }

        if ($context['color_type']) {
            $prompt .= "Farbtyp: " . $context['color_type'] . "\n";
        }

        if (!empty($context['gaps'])) {
            $prompt .= "\nFehlende Basics:\n";
            foreach ($context['gaps'] as $gap) {
                $prompt .= "- {$gap['name']}: {$gap['missing']} Stück benötigt\n";
            }
        }

        if ($context['occasion']) {
            $prompt .= "\nAnlass: " . $context['occasion'] . "\n";
        }

        $budget_map = array(
            'günstig' => 'unter 50€ pro Teil',
            'mittel'  => '50-150€ pro Teil',
            'premium' => '150-400€ pro Teil',
            'luxus'   => 'über 400€ pro Teil',
        );

        $prompt .= "\nBudget: " . ($budget_map[$context['budget']] ?? 'mittel') . "\n";

        $prompt .= "\nGib mir 5 konkrete Produktempfehlungen mit:
1. Produktname/Typ
2. Empfohlene Farbe
3. Ungefährer Preis
4. Warum es passt
5. Suchbegriff für Online-Shops";

        $response = $this->ai->chat($user_id, $prompt, array('type' => 'shopping'));

        if (!$response['success']) {
            return $response;
        }

        // Parse recommendations from AI response
        $items = $this->parse_recommendations($response['response']);

        return array(
            'success'      => true,
            'items'        => $items,
            'raw_response' => $response['response'],
        );
    }

    /**
     * Parse AI recommendations into structured data.
     *
     * @param string $response AI response text.
     * @return array
     */
    private function parse_recommendations(string $response): array {
        $items = array();

        // Simple parsing - AI returns numbered list
        $lines = explode("\n", $response);
        $current_item = null;

        foreach ($lines as $line) {
            $line = trim($line);

            // Check for numbered item start
            if (preg_match('/^(\d+)\.\s*(.+)/', $line, $matches)) {
                if ($current_item) {
                    $items[] = $current_item;
                }
                $current_item = array(
                    'name'         => $matches[2],
                    'color'        => '',
                    'price_range'  => '',
                    'reason'       => '',
                    'search_term'  => $matches[2],
                );
            } elseif ($current_item) {
                // Parse sub-items
                if (stripos($line, 'farbe') !== false) {
                    $current_item['color'] = $this->extract_value($line);
                } elseif (stripos($line, 'preis') !== false || stripos($line, '€') !== false) {
                    $current_item['price_range'] = $this->extract_value($line);
                } elseif (stripos($line, 'warum') !== false || stripos($line, 'passt') !== false) {
                    $current_item['reason'] = $this->extract_value($line);
                } elseif (stripos($line, 'such') !== false) {
                    $current_item['search_term'] = $this->extract_value($line);
                }
            }
        }

        if ($current_item) {
            $items[] = $current_item;
        }

        return $items;
    }

    /**
     * Extract value from line.
     *
     * @param string $line Text line.
     * @return string
     */
    private function extract_value(string $line): string {
        // Remove label prefixes
        $patterns = array(
            '/^[\-\*]\s*/',
            '/^farbe\s*:\s*/i',
            '/^preis\s*:\s*/i',
            '/^warum\s*:\s*/i',
            '/^suchbegriff\s*:\s*/i',
            '/^empfohlen\s*:\s*/i',
        );

        foreach ($patterns as $pattern) {
            $line = preg_replace($pattern, '', $line);
        }

        return trim($line);
    }

    /**
     * Generate affiliate shopping links.
     *
     * @param array $items Recommended items.
     * @return array
     */
    private function generate_shopping_links(array $items): array {
        $links = array();
        $active_partners = array_filter($this->get_partners(), fn($p) => $p['active']);

        foreach ($items as $item) {
            $item_links = array();
            $search_term = urlencode($item['search_term'] ?? $item['name']);

            foreach ($active_partners as $key => $partner) {
                $affiliate_id = get_option($partner['affiliate_tag'], '');
                $url = $this->build_affiliate_url($partner, $search_term, $affiliate_id);

                $item_links[$key] = array(
                    'name'    => $partner['name'],
                    'url'     => $url,
                    'icon'    => $partner['icon'],
                );
            }

            $links[] = array(
                'item'  => $item['name'],
                'links' => $item_links,
            );
        }

        return $links;
    }

    /**
     * Build affiliate URL.
     *
     * @param array  $partner      Partner config.
     * @param string $search_term  Search term.
     * @param string $affiliate_id Affiliate ID.
     * @return string
     */
    private function build_affiliate_url(array $partner, string $search_term, string $affiliate_id): string {
        $url = $partner['search_url'] . $search_term;

        // Add affiliate parameters based on partner
        switch (true) {
            case strpos($partner['base_url'], 'amazon') !== false:
                $url .= '&tag=' . $affiliate_id;
                break;

            case strpos($partner['base_url'], 'zalando') !== false:
                $url .= '?wmc=' . $affiliate_id;
                break;

            case strpos($partner['base_url'], 'aboutyou') !== false:
                $url = add_query_arg('affiliate_id', $affiliate_id, $url);
                break;

            default:
                $url = add_query_arg('ref', $affiliate_id, $url);
        }

        return $url;
    }

    /**
     * Search products (placeholder for API integration).
     *
     * @param string $query   Search query.
     * @param array  $options Options.
     * @return array
     */
    public function search_products(string $query, array $options = array()): array {
        // This would integrate with actual product APIs
        // For now, return affiliate links

        $links = array();
        $active_partners = array_filter($this->get_partners(), fn($p) => $p['active']);
        $search_term = urlencode($query);

        foreach ($active_partners as $key => $partner) {
            $affiliate_id = get_option($partner['affiliate_tag'], '');
            $links[$key] = array(
                'name' => $partner['name'],
                'url'  => $this->build_affiliate_url($partner, $search_term, $affiliate_id),
                'icon' => $partner['icon'],
            );
        }

        return array(
            'success' => true,
            'query'   => $query,
            'links'   => $links,
        );
    }

    /**
     * Track shopping recommendation.
     *
     * @param int   $user_id         User ID.
     * @param array $recommendations Recommendations data.
     * @return void
     */
    private function track_recommendation(int $user_id, array $recommendations): void {
        // Could log for analytics
        do_action('stylegenius_shopping_recommendation', $user_id, $recommendations);
    }

    /**
     * Track affiliate click.
     *
     * @param int    $user_id User ID (0 if guest).
     * @param string $partner Partner key.
     * @param string $product Product name/search term.
     * @return void
     */
    public function track_click(int $user_id, string $partner, string $product): void {
        global $wpdb;

        $table = $wpdb->prefix . 'sg_affiliate_clicks';

        $wpdb->insert(
            $table,
            array(
                'user_id'    => $user_id,
                'partner'    => sanitize_text_field($partner),
                'product'    => sanitize_text_field($product),
                'clicked_at' => current_time('mysql'),
                'ip_hash'    => md5($_SERVER['REMOTE_ADDR'] ?? ''),
            )
        );
    }

    /**
     * Get click statistics.
     *
     * @param string|null $period Time period (day, week, month, all).
     * @return array
     */
    public function get_click_statistics(?string $period = 'month'): array {
        global $wpdb;

        $table = $wpdb->prefix . 'sg_affiliate_clicks';

        $date_condition = '1=1';
        switch ($period) {
            case 'day':
                $date_condition = "DATE(clicked_at) = CURDATE()";
                break;
            case 'week':
                $date_condition = "clicked_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
                break;
            case 'month':
                $date_condition = "clicked_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
                break;
        }

        // Total clicks
        $total = intval($wpdb->get_var(
            "SELECT COUNT(*) FROM {$table} WHERE {$date_condition}"
        ));

        // By partner
        $by_partner = $wpdb->get_results(
            "SELECT partner, COUNT(*) as clicks
            FROM {$table}
            WHERE {$date_condition}
            GROUP BY partner
            ORDER BY clicks DESC",
            ARRAY_A
        );

        // Top products
        $top_products = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT product, partner, COUNT(*) as clicks
                FROM {$table}
                WHERE {$date_condition}
                GROUP BY product, partner
                ORDER BY clicks DESC
                LIMIT %d",
                10
            ),
            ARRAY_A
        );

        return array(
            'total'        => $total,
            'by_partner'   => $by_partner,
            'top_products' => $top_products,
            'period'       => $period,
        );
    }

    /**
     * Get outfit shopping links.
     *
     * @param array $outfit Outfit items from wardrobe.
     * @return array
     */
    public function get_outfit_shopping_links(array $outfit): array {
        $links = array();

        foreach ($outfit as $type => $item) {
            if (!is_array($item) || empty($item['name'])) {
                continue;
            }

            $search_term = $item['name'];
            if (!empty($item['colors'])) {
                $search_term .= ' ' . implode(' ', array_slice($item['colors'], 0, 2));
            }

            $links[$type] = $this->search_products($search_term);
        }

        return $links;
    }

    /**
     * Find similar products.
     *
     * @param int $wardrobe_item_id Wardrobe item ID.
     * @param int $user_id          User ID.
     * @return array
     */
    public function find_similar(int $wardrobe_item_id, int $user_id): array {
        // Check VIP tier
        $tier = get_user_meta($user_id, 'sg_tier', true) ?: 'free';
        if ($tier !== 'vip') {
            return array(
                'success' => false,
                'error'   => 'Diese Funktion ist nur für VIP-Mitglieder verfügbar.',
                'upgrade' => true,
            );
        }

        $wardrobe = new StyleGenius_Wardrobe();
        $item = $wardrobe->get_item($wardrobe_item_id, $user_id);

        if (!$item) {
            return array(
                'success' => false,
                'error'   => 'Kleidungsstück nicht gefunden.',
            );
        }

        // Build search term from item data
        $search_parts = array($item['subcategory'] ?: $item['category']);

        if (!empty($item['colors'])) {
            $search_parts[] = implode(' ', array_slice($item['colors'], 0, 2));
        }

        if (!empty($item['brand'])) {
            $search_parts[] = $item['brand'];
        }

        $search_term = implode(' ', $search_parts);

        return array(
            'success'     => true,
            'item'        => $item,
            'search_term' => $search_term,
            'links'       => $this->search_products($search_term)['links'],
        );
    }

    /**
     * Get budget suggestions.
     *
     * @param int   $user_id User ID.
     * @param float $budget  Available budget.
     * @return array
     */
    public function get_budget_suggestions(int $user_id, float $budget): array {
        // Check VIP tier
        $tier = get_user_meta($user_id, 'sg_tier', true) ?: 'free';
        if ($tier !== 'vip') {
            return array(
                'success' => false,
                'error'   => 'Diese Funktion ist nur für VIP-Mitglieder verfügbar.',
                'upgrade' => true,
            );
        }

        $wardrobe = new StyleGenius_Wardrobe();
        $gaps = $wardrobe->analyze_gaps($user_id);

        if (empty($gaps['gaps'])) {
            return array(
                'success'     => true,
                'message'     => 'Deine Garderobe ist gut aufgestellt!',
                'suggestions' => array(),
            );
        }

        // Prioritize gaps
        $priority_order = array('outerwear', 'shoes', 'tops', 'bottoms', 'bags', 'accessories');
        usort($gaps['gaps'], function ($a, $b) use ($priority_order) {
            $pos_a = array_search($a['category'], $priority_order);
            $pos_b = array_search($b['category'], $priority_order);
            return ($pos_a === false ? 99 : $pos_a) - ($pos_b === false ? 99 : $pos_b);
        });

        // Allocate budget
        $suggestions = array();
        $remaining = $budget;

        $average_prices = array(
            'outerwear'   => 150,
            'shoes'       => 80,
            'tops'        => 40,
            'bottoms'     => 60,
            'bags'        => 70,
            'accessories' => 30,
            'dresses'     => 80,
        );

        foreach ($gaps['gaps'] as $gap) {
            $category = $gap['category'];
            $avg_price = $average_prices[$category] ?? 50;
            $can_buy = min($gap['missing'], floor($remaining / $avg_price));

            if ($can_buy > 0) {
                $cost = $can_buy * $avg_price;
                $suggestions[] = array(
                    'category'       => $category,
                    'name'           => $gap['name'],
                    'quantity'       => $can_buy,
                    'estimated_cost' => $cost,
                    'priority'       => 'hoch',
                );
                $remaining -= $cost;
            }
        }

        return array(
            'success'       => true,
            'budget'        => $budget,
            'allocated'     => $budget - $remaining,
            'remaining'     => $remaining,
            'suggestions'   => $suggestions,
        );
    }
}
