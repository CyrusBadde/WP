<?php
/**
 * StyleGenius Leaderboard Class
 *
 * Handles all leaderboard functionality including rankings, periods, and caching.
 *
 * @package    StyleGenius_Pro
 * @subpackage StyleGenius_Pro/includes/gamification
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Leaderboard management class.
 */
class StyleGenius_Leaderboard {

    /**
     * Database instance.
     *
     * @var StyleGenius_Database
     */
    private $db;

    /**
     * Cache duration in seconds.
     *
     * @var int
     */
    private $cache_duration = 3600;

    /**
     * Leaderboard types.
     *
     * @var array
     */
    private $types = array(
        'points'      => 'Punkte',
        'level'       => 'Level',
        'badges'      => 'Badges',
        'streak'      => 'Streak',
        'challenges'  => 'Challenges',
        'referrals'   => 'Empfehlungen',
        'wardrobe'    => 'Garderobe',
        'consultations' => 'Beratungen',
    );

    /**
     * Period definitions.
     *
     * @var array
     */
    private $periods = array(
        'daily'   => 'Heute',
        'weekly'  => 'Diese Woche',
        'monthly' => 'Diesen Monat',
        'all'     => 'Gesamt',
    );

    /**
     * Constructor.
     */
    public function __construct() {
        $this->db = new StyleGenius_Database();
    }

    /**
     * Get available leaderboard types.
     *
     * @return array
     */
    public function get_types(): array {
        return $this->types;
    }

    /**
     * Get available periods.
     *
     * @return array
     */
    public function get_periods(): array {
        return $this->periods;
    }

    /**
     * Get leaderboard data.
     *
     * @param string $type   Leaderboard type.
     * @param string $period Time period.
     * @param int    $limit  Number of entries.
     * @return array
     */
    public function get_leaderboard(string $type, string $period = 'all', int $limit = 10): array {
        $cache_key = "sg_leaderboard_{$type}_{$period}_{$limit}";
        $cached = get_transient($cache_key);

        if (false !== $cached) {
            return $cached;
        }

        $leaderboard = array();

        switch ($type) {
            case 'points':
                $leaderboard = $this->get_points_leaderboard($period, $limit);
                break;
            case 'level':
                $leaderboard = $this->get_level_leaderboard($limit);
                break;
            case 'badges':
                $leaderboard = $this->get_badges_leaderboard($limit);
                break;
            case 'streak':
                $leaderboard = $this->get_streak_leaderboard($limit);
                break;
            case 'challenges':
                $leaderboard = $this->get_challenges_leaderboard($period, $limit);
                break;
            case 'referrals':
                $leaderboard = $this->get_referrals_leaderboard($period, $limit);
                break;
            case 'wardrobe':
                $leaderboard = $this->get_wardrobe_leaderboard($limit);
                break;
            case 'consultations':
                $leaderboard = $this->get_consultations_leaderboard($period, $limit);
                break;
            default:
                $leaderboard = $this->get_points_leaderboard($period, $limit);
        }

        // Add rank numbers
        foreach ($leaderboard as $index => $entry) {
            $leaderboard[$index]['rank'] = $index + 1;
        }

        set_transient($cache_key, $leaderboard, $this->cache_duration);

        return $leaderboard;
    }

    /**
     * Get points leaderboard.
     *
     * @param string $period Time period.
     * @param int    $limit  Number of entries.
     * @return array
     */
    private function get_points_leaderboard(string $period, int $limit): array {
        global $wpdb;

        $table = $wpdb->prefix . 'sg_points_log';
        $date_condition = $this->get_date_condition($period);

        if ('all' === $period) {
            // Use total from user meta for all-time
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT um.user_id, um.meta_value as total_points
                    FROM {$wpdb->usermeta} um
                    INNER JOIN {$wpdb->users} u ON um.user_id = u.ID
                    WHERE um.meta_key = 'sg_total_points'
                    AND um.meta_value > 0
                    ORDER BY CAST(um.meta_value AS UNSIGNED) DESC
                    LIMIT %d",
                    $limit
                ),
                ARRAY_A
            );
        } else {
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT user_id, SUM(points) as total_points
                    FROM {$table}
                    WHERE {$date_condition}
                    AND points > 0
                    GROUP BY user_id
                    ORDER BY total_points DESC
                    LIMIT %d",
                    $limit
                ),
                ARRAY_A
            );
        }

        return $this->format_leaderboard_results($results, 'total_points', 'Punkte');
    }

    /**
     * Get level leaderboard.
     *
     * @param int $limit Number of entries.
     * @return array
     */
    private function get_level_leaderboard(int $limit): array {
        global $wpdb;

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT um1.user_id,
                        CAST(um1.meta_value AS UNSIGNED) as level,
                        CAST(COALESCE(um2.meta_value, 0) AS UNSIGNED) as total_points
                FROM {$wpdb->usermeta} um1
                INNER JOIN {$wpdb->users} u ON um1.user_id = u.ID
                LEFT JOIN {$wpdb->usermeta} um2 ON um1.user_id = um2.user_id
                    AND um2.meta_key = 'sg_total_points'
                WHERE um1.meta_key = 'sg_level'
                ORDER BY level DESC, total_points DESC
                LIMIT %d",
                $limit
            ),
            ARRAY_A
        );

        return $this->format_leaderboard_results($results, 'level', 'Level');
    }

    /**
     * Get badges leaderboard.
     *
     * @param int $limit Number of entries.
     * @return array
     */
    private function get_badges_leaderboard(int $limit): array {
        global $wpdb;

        $table = $wpdb->prefix . 'sg_achievements';

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT user_id, COUNT(*) as badge_count
                FROM {$table}
                GROUP BY user_id
                ORDER BY badge_count DESC
                LIMIT %d",
                $limit
            ),
            ARRAY_A
        );

        return $this->format_leaderboard_results($results, 'badge_count', 'Badges');
    }

    /**
     * Get streak leaderboard.
     *
     * @param int $limit Number of entries.
     * @return array
     */
    private function get_streak_leaderboard(int $limit): array {
        global $wpdb;

        $table = $wpdb->prefix . 'sg_streaks';

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT user_id, current_streak, longest_streak
                FROM {$table}
                WHERE current_streak > 0
                ORDER BY current_streak DESC
                LIMIT %d",
                $limit
            ),
            ARRAY_A
        );

        return $this->format_leaderboard_results($results, 'current_streak', 'Tage Streak');
    }

    /**
     * Get challenges leaderboard.
     *
     * @param string $period Time period.
     * @param int    $limit  Number of entries.
     * @return array
     */
    private function get_challenges_leaderboard(string $period, int $limit): array {
        global $wpdb;

        $table = $wpdb->prefix . 'sg_challenge_entries';
        $date_condition = $this->get_date_condition($period, 'submitted_at');

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT user_id, COUNT(*) as challenge_count
                FROM {$table}
                WHERE {$date_condition}
                GROUP BY user_id
                ORDER BY challenge_count DESC
                LIMIT %d",
                $limit
            ),
            ARRAY_A
        );

        return $this->format_leaderboard_results($results, 'challenge_count', 'Challenges');
    }

    /**
     * Get referrals leaderboard.
     *
     * @param string $period Time period.
     * @param int    $limit  Number of entries.
     * @return array
     */
    private function get_referrals_leaderboard(string $period, int $limit): array {
        global $wpdb;

        $table = $wpdb->prefix . 'sg_referrals';
        $date_condition = $this->get_date_condition($period);

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT referrer_id as user_id, COUNT(*) as referral_count
                FROM {$table}
                WHERE status = 'completed'
                AND {$date_condition}
                GROUP BY referrer_id
                ORDER BY referral_count DESC
                LIMIT %d",
                $limit
            ),
            ARRAY_A
        );

        return $this->format_leaderboard_results($results, 'referral_count', 'Empfehlungen');
    }

    /**
     * Get wardrobe leaderboard.
     *
     * @param int $limit Number of entries.
     * @return array
     */
    private function get_wardrobe_leaderboard(int $limit): array {
        global $wpdb;

        $table = $wpdb->prefix . 'sg_wardrobe';

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT user_id, COUNT(*) as item_count
                FROM {$table}
                GROUP BY user_id
                ORDER BY item_count DESC
                LIMIT %d",
                $limit
            ),
            ARRAY_A
        );

        return $this->format_leaderboard_results($results, 'item_count', 'Kleidungsstücke');
    }

    /**
     * Get consultations leaderboard.
     *
     * @param string $period Time period.
     * @param int    $limit  Number of entries.
     * @return array
     */
    private function get_consultations_leaderboard(string $period, int $limit): array {
        global $wpdb;

        $table = $wpdb->prefix . 'sg_chat_history';
        $date_condition = $this->get_date_condition($period);

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT user_id, COUNT(*) as consultation_count
                FROM {$table}
                WHERE {$date_condition}
                GROUP BY user_id
                ORDER BY consultation_count DESC
                LIMIT %d",
                $limit
            ),
            ARRAY_A
        );

        return $this->format_leaderboard_results($results, 'consultation_count', 'Beratungen');
    }

    /**
     * Format leaderboard results with user data.
     *
     * @param array  $results     Query results.
     * @param string $value_key   Key for the value column.
     * @param string $value_label Label for the value.
     * @return array
     */
    private function format_leaderboard_results(array $results, string $value_key, string $value_label): array {
        $formatted = array();

        foreach ($results as $row) {
            $user_id = intval($row['user_id']);
            $user = get_userdata($user_id);

            if (!$user) {
                continue;
            }

            // Check privacy settings
            $privacy = get_user_meta($user_id, 'sg_privacy_settings', true);
            $show_in_leaderboard = true;

            if (is_array($privacy) && isset($privacy['show_in_leaderboard'])) {
                $show_in_leaderboard = (bool) $privacy['show_in_leaderboard'];
            }

            if (!$show_in_leaderboard) {
                continue;
            }

            $display_name = $this->get_display_name($user_id, $user);

            $formatted[] = array(
                'user_id'      => $user_id,
                'display_name' => $display_name,
                'avatar'       => get_avatar_url($user_id, array('size' => 64)),
                'value'        => intval($row[$value_key]),
                'value_label'  => $value_label,
                'level'        => intval(get_user_meta($user_id, 'sg_level', true) ?: 1),
                'tier'         => get_user_meta($user_id, 'sg_tier', true) ?: 'free',
            );
        }

        return $formatted;
    }

    /**
     * Get display name respecting privacy settings.
     *
     * @param int     $user_id User ID.
     * @param WP_User $user    User object.
     * @return string
     */
    private function get_display_name(int $user_id, WP_User $user): string {
        $privacy = get_user_meta($user_id, 'sg_privacy_settings', true);
        $use_anonymous = false;

        if (is_array($privacy) && isset($privacy['anonymous_leaderboard'])) {
            $use_anonymous = (bool) $privacy['anonymous_leaderboard'];
        }

        if ($use_anonymous) {
            // Generate anonymous name
            $hash = substr(md5($user_id . 'sg_salt'), 0, 6);
            return 'Style-User-' . strtoupper($hash);
        }

        return $user->display_name;
    }

    /**
     * Get date condition for SQL query.
     *
     * @param string $period       Time period.
     * @param string $column       Date column name.
     * @return string
     */
    private function get_date_condition(string $period, string $column = 'created_at'): string {
        switch ($period) {
            case 'daily':
                return "{$column} >= DATE_SUB(NOW(), INTERVAL 1 DAY)";
            case 'weekly':
                return "{$column} >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
            case 'monthly':
                return "{$column} >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
            case 'all':
            default:
                return '1=1';
        }
    }

    /**
     * Get user's rank for a specific leaderboard.
     *
     * @param int    $user_id User ID.
     * @param string $type    Leaderboard type.
     * @param string $period  Time period.
     * @return int|null
     */
    public function get_user_rank(int $user_id, string $type, string $period = 'all'): ?int {
        $cache_key = "sg_user_rank_{$user_id}_{$type}_{$period}";
        $cached = get_transient($cache_key);

        if (false !== $cached) {
            return $cached === 'null' ? null : intval($cached);
        }

        $rank = null;

        switch ($type) {
            case 'points':
                $rank = $this->get_points_rank($user_id, $period);
                break;
            case 'level':
                $rank = $this->get_level_rank($user_id);
                break;
            case 'badges':
                $rank = $this->get_badges_rank($user_id);
                break;
            case 'streak':
                $rank = $this->get_streak_rank($user_id);
                break;
            default:
                $rank = $this->get_points_rank($user_id, $period);
        }

        set_transient($cache_key, $rank === null ? 'null' : $rank, $this->cache_duration);

        return $rank;
    }

    /**
     * Get user's points rank.
     *
     * @param int    $user_id User ID.
     * @param string $period  Time period.
     * @return int|null
     */
    private function get_points_rank(int $user_id, string $period): ?int {
        global $wpdb;

        if ('all' === $period) {
            $user_points = intval(get_user_meta($user_id, 'sg_total_points', true));

            $rank = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) + 1
                    FROM {$wpdb->usermeta}
                    WHERE meta_key = 'sg_total_points'
                    AND CAST(meta_value AS UNSIGNED) > %d",
                    $user_points
                )
            );
        } else {
            $table = $wpdb->prefix . 'sg_points_log';
            $date_condition = $this->get_date_condition($period);

            $user_points = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT SUM(points)
                    FROM {$table}
                    WHERE user_id = %d
                    AND {$date_condition}
                    AND points > 0",
                    $user_id
                )
            );

            if (!$user_points) {
                return null;
            }

            $rank = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) + 1
                    FROM (
                        SELECT user_id, SUM(points) as total
                        FROM {$table}
                        WHERE {$date_condition}
                        AND points > 0
                        GROUP BY user_id
                        HAVING total > %d
                    ) as ranked",
                    $user_points
                )
            );
        }

        return $rank ? intval($rank) : null;
    }

    /**
     * Get user's level rank.
     *
     * @param int $user_id User ID.
     * @return int|null
     */
    private function get_level_rank(int $user_id): ?int {
        global $wpdb;

        $user_level = intval(get_user_meta($user_id, 'sg_level', true) ?: 1);
        $user_points = intval(get_user_meta($user_id, 'sg_total_points', true));

        $rank = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) + 1
                FROM {$wpdb->usermeta} um1
                LEFT JOIN {$wpdb->usermeta} um2 ON um1.user_id = um2.user_id
                    AND um2.meta_key = 'sg_total_points'
                WHERE um1.meta_key = 'sg_level'
                AND (
                    CAST(um1.meta_value AS UNSIGNED) > %d
                    OR (CAST(um1.meta_value AS UNSIGNED) = %d
                        AND CAST(COALESCE(um2.meta_value, 0) AS UNSIGNED) > %d)
                )",
                $user_level,
                $user_level,
                $user_points
            )
        );

        return $rank ? intval($rank) : null;
    }

    /**
     * Get user's badges rank.
     *
     * @param int $user_id User ID.
     * @return int|null
     */
    private function get_badges_rank(int $user_id): ?int {
        global $wpdb;

        $table = $wpdb->prefix . 'sg_achievements';

        $user_badges = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*)
                FROM {$table}
                WHERE user_id = %d",
                $user_id
            )
        );

        if (!$user_badges) {
            return null;
        }

        $rank = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) + 1
                FROM (
                    SELECT user_id, COUNT(*) as badge_count
                    FROM {$table}
                    GROUP BY user_id
                    HAVING badge_count > %d
                ) as ranked",
                $user_badges
            )
        );

        return $rank ? intval($rank) : null;
    }

    /**
     * Get user's streak rank.
     *
     * @param int $user_id User ID.
     * @return int|null
     */
    private function get_streak_rank(int $user_id): ?int {
        global $wpdb;

        $table = $wpdb->prefix . 'sg_streaks';

        $user_streak = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT current_streak
                FROM {$table}
                WHERE user_id = %d",
                $user_id
            )
        );

        if (!$user_streak) {
            return null;
        }

        $rank = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) + 1
                FROM {$table}
                WHERE current_streak > %d",
                $user_streak
            )
        );

        return $rank ? intval($rank) : null;
    }

    /**
     * Get all ranks for a user.
     *
     * @param int $user_id User ID.
     * @return array
     */
    public function get_user_ranks(int $user_id): array {
        $ranks = array();

        foreach (array_keys($this->types) as $type) {
            $ranks[$type] = $this->get_user_rank($user_id, $type);
        }

        return $ranks;
    }

    /**
     * Get users around a specific user's position.
     *
     * @param int    $user_id User ID.
     * @param string $type    Leaderboard type.
     * @param int    $range   Number of users above and below.
     * @return array
     */
    public function get_users_around(int $user_id, string $type, int $range = 5): array {
        $user_rank = $this->get_user_rank($user_id, $type);

        if (!$user_rank) {
            return array();
        }

        // Get a larger leaderboard and extract the range
        $start_rank = max(1, $user_rank - $range);
        $needed = $range * 2 + 1;

        $full_leaderboard = $this->get_leaderboard($type, 'all', $start_rank + $needed);

        $result = array();
        foreach ($full_leaderboard as $entry) {
            if ($entry['rank'] >= $start_rank && $entry['rank'] <= $user_rank + $range) {
                $entry['is_current_user'] = ($entry['user_id'] === $user_id);
                $result[] = $entry;
            }
        }

        return $result;
    }

    /**
     * Get leaderboard statistics.
     *
     * @param string $type Leaderboard type.
     * @return array
     */
    public function get_statistics(string $type): array {
        global $wpdb;

        $cache_key = "sg_leaderboard_stats_{$type}";
        $cached = get_transient($cache_key);

        if (false !== $cached) {
            return $cached;
        }

        $stats = array(
            'total_participants' => 0,
            'average_value'      => 0,
            'highest_value'      => 0,
            'median_value'       => 0,
        );

        switch ($type) {
            case 'points':
                $stats = $this->get_points_statistics();
                break;
            case 'level':
                $stats = $this->get_level_statistics();
                break;
            case 'badges':
                $stats = $this->get_badges_statistics();
                break;
            case 'streak':
                $stats = $this->get_streak_statistics();
                break;
        }

        set_transient($cache_key, $stats, $this->cache_duration);

        return $stats;
    }

    /**
     * Get points statistics.
     *
     * @return array
     */
    private function get_points_statistics(): array {
        global $wpdb;

        $result = $wpdb->get_row(
            "SELECT
                COUNT(*) as total_participants,
                AVG(CAST(meta_value AS UNSIGNED)) as average_value,
                MAX(CAST(meta_value AS UNSIGNED)) as highest_value
            FROM {$wpdb->usermeta}
            WHERE meta_key = 'sg_total_points'
            AND CAST(meta_value AS UNSIGNED) > 0",
            ARRAY_A
        );

        return array(
            'total_participants' => intval($result['total_participants'] ?? 0),
            'average_value'      => round(floatval($result['average_value'] ?? 0)),
            'highest_value'      => intval($result['highest_value'] ?? 0),
            'median_value'       => $this->get_median_points(),
        );
    }

    /**
     * Get median points value.
     *
     * @return int
     */
    private function get_median_points(): int {
        global $wpdb;

        $count = $wpdb->get_var(
            "SELECT COUNT(*)
            FROM {$wpdb->usermeta}
            WHERE meta_key = 'sg_total_points'
            AND CAST(meta_value AS UNSIGNED) > 0"
        );

        if (!$count) {
            return 0;
        }

        $offset = intval($count / 2);

        $median = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT CAST(meta_value AS UNSIGNED) as points
                FROM {$wpdb->usermeta}
                WHERE meta_key = 'sg_total_points'
                AND CAST(meta_value AS UNSIGNED) > 0
                ORDER BY points
                LIMIT 1 OFFSET %d",
                $offset
            )
        );

        return intval($median);
    }

    /**
     * Get level statistics.
     *
     * @return array
     */
    private function get_level_statistics(): array {
        global $wpdb;

        $result = $wpdb->get_row(
            "SELECT
                COUNT(*) as total_participants,
                AVG(CAST(meta_value AS UNSIGNED)) as average_value,
                MAX(CAST(meta_value AS UNSIGNED)) as highest_value
            FROM {$wpdb->usermeta}
            WHERE meta_key = 'sg_level'",
            ARRAY_A
        );

        return array(
            'total_participants' => intval($result['total_participants'] ?? 0),
            'average_value'      => round(floatval($result['average_value'] ?? 0), 1),
            'highest_value'      => intval($result['highest_value'] ?? 0),
            'median_value'       => 0,
        );
    }

    /**
     * Get badges statistics.
     *
     * @return array
     */
    private function get_badges_statistics(): array {
        global $wpdb;

        $table = $wpdb->prefix . 'sg_achievements';

        $result = $wpdb->get_row(
            "SELECT
                COUNT(DISTINCT user_id) as total_participants,
                AVG(badge_count) as average_value,
                MAX(badge_count) as highest_value
            FROM (
                SELECT user_id, COUNT(*) as badge_count
                FROM {$table}
                GROUP BY user_id
            ) as badge_counts",
            ARRAY_A
        );

        return array(
            'total_participants' => intval($result['total_participants'] ?? 0),
            'average_value'      => round(floatval($result['average_value'] ?? 0), 1),
            'highest_value'      => intval($result['highest_value'] ?? 0),
            'median_value'       => 0,
        );
    }

    /**
     * Get streak statistics.
     *
     * @return array
     */
    private function get_streak_statistics(): array {
        global $wpdb;

        $table = $wpdb->prefix . 'sg_streaks';

        $result = $wpdb->get_row(
            "SELECT
                COUNT(*) as total_participants,
                AVG(current_streak) as average_value,
                MAX(current_streak) as highest_value,
                MAX(longest_streak) as all_time_highest
            FROM {$table}
            WHERE current_streak > 0",
            ARRAY_A
        );

        return array(
            'total_participants' => intval($result['total_participants'] ?? 0),
            'average_value'      => round(floatval($result['average_value'] ?? 0), 1),
            'highest_value'      => intval($result['highest_value'] ?? 0),
            'all_time_highest'   => intval($result['all_time_highest'] ?? 0),
        );
    }

    /**
     * Clear leaderboard cache.
     *
     * @param string|null $type Specific type to clear, or null for all.
     * @return void
     */
    public function clear_cache(?string $type = null): void {
        global $wpdb;

        if ($type) {
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options}
                    WHERE option_name LIKE %s",
                    '_transient_sg_leaderboard_' . $type . '%'
                )
            );
        } else {
            $wpdb->query(
                "DELETE FROM {$wpdb->options}
                WHERE option_name LIKE '_transient_sg_leaderboard_%'
                OR option_name LIKE '_transient_sg_user_rank_%'"
            );
        }
    }

    /**
     * Get seasonal leaderboard for a specific challenge.
     *
     * @param int $challenge_id Challenge ID.
     * @param int $limit        Number of entries.
     * @return array
     */
    public function get_challenge_leaderboard(int $challenge_id, int $limit = 10): array {
        global $wpdb;

        $votes_table = $wpdb->prefix . 'sg_challenge_votes';
        $entries_table = $wpdb->prefix . 'sg_challenge_entries';

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT e.user_id, COUNT(v.id) as vote_count
                FROM {$entries_table} e
                LEFT JOIN {$votes_table} v ON e.id = v.entry_id
                WHERE e.challenge_id = %d
                GROUP BY e.user_id
                ORDER BY vote_count DESC
                LIMIT %d",
                $challenge_id,
                $limit
            ),
            ARRAY_A
        );

        return $this->format_leaderboard_results($results, 'vote_count', 'Stimmen');
    }

    /**
     * Get weekly winners for a challenge type.
     *
     * @param string $challenge_type Challenge type.
     * @param int    $weeks          Number of weeks to look back.
     * @return array
     */
    public function get_weekly_winners(string $challenge_type, int $weeks = 4): array {
        global $wpdb;

        $challenges_table = $wpdb->prefix . 'sg_challenges';
        $entries_table = $wpdb->prefix . 'sg_challenge_entries';
        $votes_table = $wpdb->prefix . 'sg_challenge_votes';

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    c.id as challenge_id,
                    c.title as challenge_title,
                    c.start_date,
                    c.end_date,
                    e.user_id,
                    COUNT(v.id) as vote_count
                FROM {$challenges_table} c
                INNER JOIN {$entries_table} e ON c.id = e.challenge_id
                LEFT JOIN {$votes_table} v ON e.id = v.entry_id
                WHERE c.type = %s
                AND c.end_date < NOW()
                AND c.end_date >= DATE_SUB(NOW(), INTERVAL %d WEEK)
                GROUP BY c.id, e.user_id
                HAVING vote_count = (
                    SELECT MAX(vc.vote_count)
                    FROM (
                        SELECT e2.user_id, COUNT(v2.id) as vote_count
                        FROM {$entries_table} e2
                        LEFT JOIN {$votes_table} v2 ON e2.id = v2.entry_id
                        WHERE e2.challenge_id = c.id
                        GROUP BY e2.user_id
                    ) as vc
                )
                ORDER BY c.end_date DESC",
                $challenge_type,
                $weeks
            ),
            ARRAY_A
        );

        $winners = array();
        foreach ($results as $row) {
            $user = get_userdata($row['user_id']);
            if ($user) {
                $winners[] = array(
                    'challenge_id'    => intval($row['challenge_id']),
                    'challenge_title' => $row['challenge_title'],
                    'week_start'      => $row['start_date'],
                    'week_end'        => $row['end_date'],
                    'user_id'         => intval($row['user_id']),
                    'display_name'    => $this->get_display_name($row['user_id'], $user),
                    'avatar'          => get_avatar_url($row['user_id'], array('size' => 64)),
                    'votes'           => intval($row['vote_count']),
                );
            }
        }

        return $winners;
    }
}
