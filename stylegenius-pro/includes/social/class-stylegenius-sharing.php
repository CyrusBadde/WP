<?php
/**
 * StyleGenius Sharing Class
 *
 * Handles social sharing functionality for all content types.
 *
 * @package    StyleGenius_Pro
 * @subpackage StyleGenius_Pro/includes/social
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Social Sharing management class.
 */
class StyleGenius_Sharing {

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
     * Supported platforms.
     *
     * @var array
     */
    private $platforms = array(
        'facebook' => array(
            'name'       => 'Facebook',
            'icon'       => 'facebook',
            'color'      => '#1877F2',
            'share_url'  => 'https://www.facebook.com/sharer/sharer.php?u=%s&quote=%s',
        ),
        'twitter' => array(
            'name'       => 'Twitter/X',
            'icon'       => 'twitter',
            'color'      => '#1DA1F2',
            'share_url'  => 'https://twitter.com/intent/tweet?url=%s&text=%s&hashtags=%s',
        ),
        'pinterest' => array(
            'name'       => 'Pinterest',
            'icon'       => 'pinterest',
            'color'      => '#E60023',
            'share_url'  => 'https://pinterest.com/pin/create/button/?url=%s&media=%s&description=%s',
        ),
        'linkedin' => array(
            'name'       => 'LinkedIn',
            'icon'       => 'linkedin',
            'color'      => '#0A66C2',
            'share_url'  => 'https://www.linkedin.com/sharing/share-offsite/?url=%s',
        ),
        'whatsapp' => array(
            'name'       => 'WhatsApp',
            'icon'       => 'whatsapp',
            'color'      => '#25D366',
            'share_url'  => 'https://api.whatsapp.com/send?text=%s',
        ),
        'telegram' => array(
            'name'       => 'Telegram',
            'icon'       => 'telegram',
            'color'      => '#0088CC',
            'share_url'  => 'https://t.me/share/url?url=%s&text=%s',
        ),
        'email' => array(
            'name'       => 'E-Mail',
            'icon'       => 'email',
            'color'      => '#666666',
            'share_url'  => 'mailto:?subject=%s&body=%s',
        ),
        'copy' => array(
            'name'       => 'Link kopieren',
            'icon'       => 'link',
            'color'      => '#333333',
            'share_url'  => '',
        ),
    );

    /**
     * Shareable content types.
     *
     * @var array
     */
    private $content_types = array(
        'quiz_result'     => 'Style-Quiz Ergebnis',
        'color_profile'   => 'Farbprofil',
        'outfit'          => 'Outfit',
        'capsule'         => 'Capsule Wardrobe',
        'before_after'    => 'Vorher-Nachher',
        'challenge_entry' => 'Challenge-Beitrag',
        'badge'           => 'Badge',
        'level_up'        => 'Level-Up',
        'streak'          => 'Streak',
    );

    /**
     * Constructor.
     */
    public function __construct() {
        $this->db = new StyleGenius_Database();
        $this->points = new StyleGenius_Points();
    }

    /**
     * Get available platforms.
     *
     * @return array
     */
    public function get_platforms(): array {
        return $this->platforms;
    }

    /**
     * Get content types.
     *
     * @return array
     */
    public function get_content_types(): array {
        return $this->content_types;
    }

    /**
     * Generate share links for content.
     *
     * @param string $content_type Content type.
     * @param int    $content_id   Content ID or user ID.
     * @param array  $data         Additional data.
     * @return array Share links for each platform.
     */
    public function generate_share_links(string $content_type, int $content_id, array $data = array()): array {
        $share_data = $this->get_share_data($content_type, $content_id, $data);

        if (!$share_data) {
            return array();
        }

        $links = array();

        foreach ($this->platforms as $key => $platform) {
            if ($key === 'copy') {
                $links[$key] = array(
                    'name'  => $platform['name'],
                    'icon'  => $platform['icon'],
                    'color' => $platform['color'],
                    'url'   => $share_data['url'],
                    'type'  => 'copy',
                );
                continue;
            }

            $url = $this->build_share_url($key, $share_data);

            $links[$key] = array(
                'name'     => $platform['name'],
                'icon'     => $platform['icon'],
                'color'    => $platform['color'],
                'url'      => $url,
                'type'     => 'popup',
            );
        }

        return $links;
    }

    /**
     * Get share data for content type.
     *
     * @param string $content_type Content type.
     * @param int    $content_id   Content ID.
     * @param array  $data         Additional data.
     * @return array|null
     */
    private function get_share_data(string $content_type, int $content_id, array $data = array()): ?array {
        $site_name = get_bloginfo('name');
        $default_hashtags = 'StyleGenius,Fashion,Style';

        switch ($content_type) {
            case 'quiz_result':
                $quiz = new StyleGenius_Quiz();
                $result = $quiz->get_result($content_id);
                if (!$result) {
                    return null;
                }
                return array(
                    'title'       => sprintf('Mein Style-Typ: %s', $result['style_details']['name']),
                    'description' => $result['style_details']['description'],
                    'text'        => sprintf('Ich bin der %s Style-Typ! Finde deinen Style-Typ auf %s', $result['style_details']['name'], $site_name),
                    'url'         => add_query_arg(array('ref' => 'share', 'style' => $result['style_type']), home_url('/style-quiz/')),
                    'image'       => $quiz->get_badge_image_url($content_id),
                    'hashtags'    => 'StyleGenius,StyleQuiz,Fashion',
                );

            case 'color_profile':
                $color = new StyleGenius_Color_Analysis();
                $profile = $color->get_profile($content_id);
                if (!$profile) {
                    return null;
                }
                return array(
                    'title'       => sprintf('Mein Farbtyp: %s', $profile['name']),
                    'description' => $profile['description'],
                    'text'        => sprintf('Ich bin der %s! Finde deinen Farbtyp auf %s', $profile['name'], $site_name),
                    'url'         => add_query_arg(array('ref' => 'share'), home_url('/farbanalyse/')),
                    'image'       => $data['card_image'] ?? '',
                    'hashtags'    => 'StyleGenius,Farbberatung,Farbtyp',
                );

            case 'outfit':
                return array(
                    'title'       => $data['title'] ?? 'Mein Outfit',
                    'description' => $data['description'] ?? 'Schau dir mein Outfit an!',
                    'text'        => sprintf('%s - erstellt mit %s', $data['title'] ?? 'Mein Outfit', $site_name),
                    'url'         => $data['url'] ?? home_url('/garderobe/'),
                    'image'       => $data['image'] ?? '',
                    'hashtags'    => 'StyleGenius,OOTD,OutfitOfTheDay',
                );

            case 'capsule':
                $capsule = new StyleGenius_Capsule();
                $capsule_data = $capsule->get_capsule($content_id, $data['user_id'] ?? 0);
                if (!$capsule_data) {
                    return null;
                }
                return array(
                    'title'       => sprintf('Meine Capsule Wardrobe: %s', $capsule_data['name']),
                    'description' => sprintf('%d Teile für die perfekte Garderobe', $capsule_data['item_count']),
                    'text'        => sprintf('Meine Capsule Wardrobe mit %d Teilen - erstellt mit %s', $capsule_data['item_count'], $site_name),
                    'url'         => add_query_arg(array('ref' => 'share'), home_url('/capsule-wardrobe/')),
                    'image'       => $data['grid_image'] ?? '',
                    'hashtags'    => 'StyleGenius,CapsuleWardrobe,MinimalistFashion',
                );

            case 'before_after':
                $ba = new StyleGenius_Before_After();
                $transformation = $ba->get_transformation($content_id, $data['user_id'] ?? null);
                if (!$transformation) {
                    return null;
                }
                return array(
                    'title'       => $transformation['title'] ?: 'Meine Style-Transformation',
                    'description' => 'Schau dir meine Styling-Transformation an!',
                    'text'        => sprintf('%s - meine Transformation mit %s', $transformation['title'] ?: 'Style-Transformation', $site_name),
                    'url'         => $transformation['share_url'],
                    'image'       => $transformation['after_image_url'],
                    'hashtags'    => 'StyleGenius,Transformation,BeforeAfter',
                );

            case 'challenge_entry':
                return array(
                    'title'       => $data['challenge_title'] ?? 'Mein Challenge-Beitrag',
                    'description' => 'Stimme für meinen Beitrag!',
                    'text'        => sprintf('Stimme für meinen Beitrag bei der %s! %s', $data['challenge_title'] ?? 'Style Challenge', $site_name),
                    'url'         => $data['entry_url'] ?? home_url('/challenges/'),
                    'image'       => $data['image'] ?? '',
                    'hashtags'    => 'StyleGenius,StyleChallenge,Vote',
                );

            case 'badge':
                return array(
                    'title'       => sprintf('Badge freigeschaltet: %s', $data['badge_name'] ?? 'Achievement'),
                    'description' => $data['badge_description'] ?? 'Ich habe ein neues Badge erhalten!',
                    'text'        => sprintf('Ich habe das Badge "%s" auf %s freigeschaltet!', $data['badge_name'] ?? 'Achievement', $site_name),
                    'url'         => add_query_arg(array('ref' => 'share'), home_url('/profil/')),
                    'image'       => $data['badge_image'] ?? '',
                    'hashtags'    => 'StyleGenius,Achievement,Badge',
                );

            case 'level_up':
                return array(
                    'title'       => sprintf('Level Up! Level %d erreicht', $data['level'] ?? 1),
                    'description' => 'Ich bin auf ein neues Level aufgestiegen!',
                    'text'        => sprintf('Ich habe Level %d auf %s erreicht!', $data['level'] ?? 1, $site_name),
                    'url'         => add_query_arg(array('ref' => 'share'), home_url('/profil/')),
                    'image'       => '',
                    'hashtags'    => 'StyleGenius,LevelUp,Achievement',
                );

            case 'streak':
                return array(
                    'title'       => sprintf('%d Tage Streak!', $data['days'] ?? 1),
                    'description' => 'Meine Style-Streak läuft!',
                    'text'        => sprintf('Ich habe eine %d-Tage-Streak auf %s!', $data['days'] ?? 1, $site_name),
                    'url'         => add_query_arg(array('ref' => 'share'), home_url('/profil/')),
                    'image'       => '',
                    'hashtags'    => 'StyleGenius,Streak,Motivation',
                );

            default:
                return null;
        }
    }

    /**
     * Build share URL for platform.
     *
     * @param string $platform   Platform key.
     * @param array  $share_data Share data.
     * @return string
     */
    private function build_share_url(string $platform, array $share_data): string {
        $config = $this->platforms[$platform] ?? null;

        if (!$config || empty($config['share_url'])) {
            return '';
        }

        $url = urlencode($share_data['url'] ?? '');
        $text = urlencode($share_data['text'] ?? $share_data['title'] ?? '');
        $image = urlencode($share_data['image'] ?? '');
        $hashtags = $share_data['hashtags'] ?? '';

        switch ($platform) {
            case 'facebook':
                return sprintf($config['share_url'], $url, $text);

            case 'twitter':
                return sprintf($config['share_url'], $url, $text, $hashtags);

            case 'pinterest':
                return sprintf($config['share_url'], $url, $image, $text);

            case 'linkedin':
                return sprintf($config['share_url'], $url);

            case 'whatsapp':
                $message = $share_data['text'] . ' ' . $share_data['url'];
                return sprintf($config['share_url'], urlencode($message));

            case 'telegram':
                return sprintf($config['share_url'], $url, $text);

            case 'email':
                $subject = urlencode($share_data['title'] ?? '');
                $body = urlencode($share_data['text'] . "\n\n" . $share_data['url']);
                return sprintf($config['share_url'], $subject, $body);

            default:
                return '';
        }
    }

    /**
     * Track share action.
     *
     * @param int    $user_id      User ID.
     * @param string $platform     Platform key.
     * @param string $content_type Content type.
     * @param int    $content_id   Content ID.
     * @return bool
     */
    public function track_share(int $user_id, string $platform, string $content_type, int $content_id): bool {
        global $wpdb;

        $table = $wpdb->prefix . 'sg_shares';

        $result = $wpdb->insert(
            $table,
            array(
                'user_id'      => $user_id,
                'platform'     => sanitize_text_field($platform),
                'content_type' => sanitize_text_field($content_type),
                'content_id'   => $content_id,
                'shared_at'    => current_time('mysql'),
            )
        );

        if ($result) {
            // Award points for sharing
            $this->points->award_points($user_id, 'share', 5, sprintf('Geteilt auf %s', $this->platforms[$platform]['name'] ?? $platform));

            do_action('stylegenius_content_shared', $user_id, $platform, $content_type, $content_id);
        }

        return false !== $result;
    }

    /**
     * Get user's share history.
     *
     * @param int $user_id User ID.
     * @param int $limit   Number of entries.
     * @return array
     */
    public function get_user_shares(int $user_id, int $limit = 20): array {
        global $wpdb;

        $table = $wpdb->prefix . 'sg_shares';

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                WHERE user_id = %d
                ORDER BY shared_at DESC
                LIMIT %d",
                $user_id,
                $limit
            ),
            ARRAY_A
        );
    }

    /**
     * Get share statistics.
     *
     * @param string|null $period Time period.
     * @return array
     */
    public function get_statistics(?string $period = 'month'): array {
        global $wpdb;

        $table = $wpdb->prefix . 'sg_shares';

        $date_condition = '1=1';
        switch ($period) {
            case 'day':
                $date_condition = "DATE(shared_at) = CURDATE()";
                break;
            case 'week':
                $date_condition = "shared_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
                break;
            case 'month':
                $date_condition = "shared_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
                break;
        }

        // Total shares
        $total = intval($wpdb->get_var(
            "SELECT COUNT(*) FROM {$table} WHERE {$date_condition}"
        ));

        // By platform
        $by_platform = $wpdb->get_results(
            "SELECT platform, COUNT(*) as shares
            FROM {$table}
            WHERE {$date_condition}
            GROUP BY platform
            ORDER BY shares DESC",
            ARRAY_A
        );

        // By content type
        $by_type = $wpdb->get_results(
            "SELECT content_type, COUNT(*) as shares
            FROM {$table}
            WHERE {$date_condition}
            GROUP BY content_type
            ORDER BY shares DESC",
            ARRAY_A
        );

        // Unique sharers
        $unique_users = intval($wpdb->get_var(
            "SELECT COUNT(DISTINCT user_id) FROM {$table} WHERE {$date_condition}"
        ));

        return array(
            'total'        => $total,
            'by_platform'  => $by_platform,
            'by_type'      => $by_type,
            'unique_users' => $unique_users,
            'period'       => $period,
        );
    }

    /**
     * Generate share buttons HTML.
     *
     * @param string $content_type Content type.
     * @param int    $content_id   Content ID.
     * @param array  $data         Additional data.
     * @param array  $options      Display options.
     * @return string HTML.
     */
    public function render_buttons(string $content_type, int $content_id, array $data = array(), array $options = array()): string {
        $links = $this->generate_share_links($content_type, $content_id, $data);

        if (empty($links)) {
            return '';
        }

        $platforms_to_show = $options['platforms'] ?? array_keys($this->platforms);
        $style = $options['style'] ?? 'icons'; // icons, buttons, minimal
        $size = $options['size'] ?? 'medium'; // small, medium, large

        $classes = array(
            'sg-share-buttons',
            'sg-share-' . $style,
            'sg-share-' . $size,
        );

        $html = '<div class="' . esc_attr(implode(' ', $classes)) . '">';

        if (!empty($options['label'])) {
            $html .= '<span class="sg-share-label">' . esc_html($options['label']) . '</span>';
        }

        $html .= '<div class="sg-share-links">';

        foreach ($platforms_to_show as $platform) {
            if (!isset($links[$platform])) {
                continue;
            }

            $link = $links[$platform];
            $attrs = array(
                'class'          => 'sg-share-link sg-share-' . $platform,
                'data-platform'  => $platform,
                'data-type'      => $content_type,
                'data-id'        => $content_id,
                'style'          => 'background-color: ' . $link['color'],
                'title'          => $link['name'],
                'aria-label'     => 'Teilen auf ' . $link['name'],
            );

            if ($link['type'] === 'copy') {
                $attrs['data-url'] = $link['url'];
                $attrs['href'] = '#';
                $attrs['role'] = 'button';
            } else {
                $attrs['href'] = $link['url'];
                $attrs['target'] = '_blank';
                $attrs['rel'] = 'noopener noreferrer';
            }

            $attr_string = '';
            foreach ($attrs as $key => $value) {
                $attr_string .= sprintf(' %s="%s"', $key, esc_attr($value));
            }

            $html .= '<a' . $attr_string . '>';
            $html .= '<span class="sg-share-icon sg-icon-' . $link['icon'] . '"></span>';

            if ($style === 'buttons') {
                $html .= '<span class="sg-share-name">' . esc_html($link['name']) . '</span>';
            }

            $html .= '</a>';
        }

        $html .= '</div></div>';

        return $html;
    }

    /**
     * Export user share data for GDPR.
     *
     * @param int $user_id User ID.
     * @return array
     */
    public function export_data(int $user_id): array {
        return $this->get_user_shares($user_id, 1000);
    }

    /**
     * Delete user share data (GDPR).
     *
     * @param int $user_id User ID.
     * @return bool
     */
    public function delete_data(int $user_id): bool {
        global $wpdb;

        $table = $wpdb->prefix . 'sg_shares';

        $result = $wpdb->delete(
            $table,
            array('user_id' => $user_id),
            array('%d')
        );

        return false !== $result;
    }
}
