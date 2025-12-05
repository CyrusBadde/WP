<?php
/**
 * StyleGenius Referral Class
 *
 * Handles referral/invitation system with tiered rewards.
 *
 * @package    StyleGenius_Pro
 * @subpackage StyleGenius_Pro/includes/social
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Referral system management class.
 */
class StyleGenius_Referral {

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
     * Referral code prefix.
     *
     * @var string
     */
    private $code_prefix = 'SG';

    /**
     * Reward tiers.
     *
     * @var array
     */
    private $reward_tiers = array(
        1  => array('referrer' => 100, 'referred' => 50),   // First referral
        5  => array('referrer' => 150, 'referred' => 75),   // 5 referrals
        10 => array('referrer' => 200, 'referred' => 100),  // 10 referrals
        25 => array('referrer' => 300, 'referred' => 150),  // 25 referrals
        50 => array('referrer' => 500, 'referred' => 200),  // 50 referrals
    );

    /**
     * Referral statuses.
     *
     * @var array
     */
    private $statuses = array(
        'pending'   => 'Ausstehend',
        'completed' => 'Abgeschlossen',
        'expired'   => 'Abgelaufen',
        'cancelled' => 'Storniert',
    );

    /**
     * Constructor.
     */
    public function __construct() {
        $this->db = new StyleGenius_Database();
        $this->points = new StyleGenius_Points();
        $this->achievements = new StyleGenius_Achievements();
    }

    /**
     * Get reward tiers.
     *
     * @return array
     */
    public function get_reward_tiers(): array {
        return $this->reward_tiers;
    }

    /**
     * Generate unique referral code for user.
     *
     * @param int $user_id User ID.
     * @return string
     */
    public function generate_code(int $user_id): string {
        $existing = get_user_meta($user_id, 'sg_referral_code', true);

        if (!empty($existing)) {
            return $existing;
        }

        // Generate unique code
        $user = get_userdata($user_id);
        $username_part = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $user->user_login), 0, 4));

        if (strlen($username_part) < 4) {
            $username_part = str_pad($username_part, 4, 'X');
        }

        $random_part = strtoupper(substr(md5(uniqid($user_id, true)), 0, 4));
        $code = $this->code_prefix . $username_part . $random_part;

        // Ensure unique
        while ($this->code_exists($code)) {
            $random_part = strtoupper(substr(md5(uniqid($user_id . time(), true)), 0, 4));
            $code = $this->code_prefix . $username_part . $random_part;
        }

        update_user_meta($user_id, 'sg_referral_code', $code);

        return $code;
    }

    /**
     * Check if code exists.
     *
     * @param string $code Referral code.
     * @return bool
     */
    private function code_exists(string $code): bool {
        global $wpdb;

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->usermeta}
                WHERE meta_key = 'sg_referral_code' AND meta_value = %s",
                $code
            )
        );

        return intval($count) > 0;
    }

    /**
     * Get user by referral code.
     *
     * @param string $code Referral code.
     * @return int|null User ID or null.
     */
    public function get_user_by_code(string $code): ?int {
        global $wpdb;

        $code = strtoupper(sanitize_text_field($code));

        $user_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT user_id FROM {$wpdb->usermeta}
                WHERE meta_key = 'sg_referral_code' AND meta_value = %s",
                $code
            )
        );

        return $user_id ? intval($user_id) : null;
    }

    /**
     * Get referral URL for user.
     *
     * @param int $user_id User ID.
     * @return string
     */
    public function get_referral_url(int $user_id): string {
        $code = $this->generate_code($user_id);

        return add_query_arg(
            array('ref' => $code),
            home_url('/registrierung/')
        );
    }

    /**
     * Process referral registration.
     *
     * @param int    $referred_user_id New user ID.
     * @param string $referral_code    Referral code used.
     * @return array Result.
     */
    public function process_referral(int $referred_user_id, string $referral_code): array {
        global $wpdb;

        // Get referrer
        $referrer_id = $this->get_user_by_code($referral_code);

        if (!$referrer_id) {
            return array(
                'success' => false,
                'error'   => 'Ungültiger Empfehlungscode.',
            );
        }

        // Prevent self-referral
        if ($referrer_id === $referred_user_id) {
            return array(
                'success' => false,
                'error'   => 'Selbst-Empfehlung ist nicht möglich.',
            );
        }

        // Check if already referred
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}sg_referrals
                WHERE referred_id = %d",
                $referred_user_id
            )
        );

        if ($existing) {
            return array(
                'success' => false,
                'error'   => 'Diese Person wurde bereits empfohlen.',
            );
        }

        $table = $wpdb->prefix . 'sg_referrals';

        // Create referral record
        $result = $wpdb->insert(
            $table,
            array(
                'referrer_id'  => $referrer_id,
                'referred_id'  => $referred_user_id,
                'code_used'    => $referral_code,
                'status'       => 'pending',
                'created_at'   => current_time('mysql'),
            )
        );

        if (!$result) {
            return array(
                'success' => false,
                'error'   => 'Fehler beim Speichern der Empfehlung.',
            );
        }

        $referral_id = $wpdb->insert_id;

        // Mark referred user
        update_user_meta($referred_user_id, 'sg_referred_by', $referrer_id);
        update_user_meta($referred_user_id, 'sg_referral_id', $referral_id);

        return array(
            'success'     => true,
            'referral_id' => $referral_id,
            'referrer_id' => $referrer_id,
        );
    }

    /**
     * Complete referral and award rewards.
     *
     * @param int $referred_user_id Referred user ID.
     * @return array Result.
     */
    public function complete_referral(int $referred_user_id): array {
        global $wpdb;

        $table = $wpdb->prefix . 'sg_referrals';

        // Get referral record
        $referral = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                WHERE referred_id = %d AND status = 'pending'",
                $referred_user_id
            )
        );

        if (!$referral) {
            return array(
                'success' => false,
                'error'   => 'Keine ausstehende Empfehlung gefunden.',
            );
        }

        $referrer_id = intval($referral->referrer_id);

        // Get referrer's total completed referrals for tier calculation
        $completed_count = intval($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table}
                WHERE referrer_id = %d AND status = 'completed'",
                $referrer_id
            )
        )) + 1;

        // Get reward tier
        $rewards = $this->get_reward_for_count($completed_count);

        // Update status
        $wpdb->update(
            $table,
            array(
                'status'       => 'completed',
                'completed_at' => current_time('mysql'),
                'rewards'      => wp_json_encode($rewards),
            ),
            array('id' => $referral->id)
        );

        // Award points to referrer
        $this->points->award_points(
            $referrer_id,
            'referral',
            $rewards['referrer'],
            sprintf('Empfehlung #%d abgeschlossen', $completed_count)
        );

        // Award points to referred user
        $this->points->award_points(
            $referred_user_id,
            'referral_bonus',
            $rewards['referred'],
            'Willkommensbonus für Empfehlung'
        );

        // Check achievements
        $this->check_referral_achievements($referrer_id, $completed_count);

        do_action('stylegenius_referral_completed', $referrer_id, $referred_user_id, $rewards);

        return array(
            'success'         => true,
            'rewards'         => $rewards,
            'completed_count' => $completed_count,
        );
    }

    /**
     * Get reward for referral count.
     *
     * @param int $count Current referral count (including this one).
     * @return array Rewards for referrer and referred.
     */
    private function get_reward_for_count(int $count): array {
        $tier_rewards = array('referrer' => 100, 'referred' => 50);

        foreach ($this->reward_tiers as $threshold => $rewards) {
            if ($count >= $threshold) {
                $tier_rewards = $rewards;
            }
        }

        return $tier_rewards;
    }

    /**
     * Get user's referral statistics.
     *
     * @param int $user_id User ID.
     * @return array
     */
    public function get_user_stats(int $user_id): array {
        global $wpdb;

        $table = $wpdb->prefix . 'sg_referrals';

        // Get counts
        $stats = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
                FROM {$table}
                WHERE referrer_id = %d",
                $user_id
            ),
            ARRAY_A
        );

        // Calculate total earned
        $total_earned = intval($wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(points) FROM {$wpdb->prefix}sg_points_log
                WHERE user_id = %d AND action = 'referral'",
                $user_id
            )
        ));

        // Get current tier and next tier
        $completed = intval($stats['completed'] ?? 0);
        $current_tier = null;
        $next_tier = null;

        foreach ($this->reward_tiers as $threshold => $rewards) {
            if ($completed >= $threshold) {
                $current_tier = array(
                    'threshold' => $threshold,
                    'rewards'   => $rewards,
                );
            } else if ($next_tier === null) {
                $next_tier = array(
                    'threshold' => $threshold,
                    'rewards'   => $rewards,
                    'remaining' => $threshold - $completed,
                );
            }
        }

        return array(
            'code'         => $this->generate_code($user_id),
            'url'          => $this->get_referral_url($user_id),
            'total'        => intval($stats['total'] ?? 0),
            'pending'      => intval($stats['pending'] ?? 0),
            'completed'    => $completed,
            'total_earned' => $total_earned,
            'current_tier' => $current_tier,
            'next_tier'    => $next_tier,
        );
    }

    /**
     * Get user's referral history.
     *
     * @param int $user_id User ID.
     * @param int $limit   Number of entries.
     * @return array
     */
    public function get_user_referrals(int $user_id, int $limit = 20): array {
        global $wpdb;

        $table = $wpdb->prefix . 'sg_referrals';

        $referrals = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                WHERE referrer_id = %d
                ORDER BY created_at DESC
                LIMIT %d",
                $user_id,
                $limit
            ),
            ARRAY_A
        );

        return array_map(function ($referral) {
            $referred_user = get_userdata($referral['referred_id']);
            $rewards = $referral['rewards'] ? json_decode($referral['rewards'], true) : null;

            return array(
                'id'           => intval($referral['id']),
                'referred_id'  => intval($referral['referred_id']),
                'referred_name' => $referred_user ? $referred_user->display_name : 'Unbekannt',
                'referred_avatar' => get_avatar_url($referral['referred_id'], array('size' => 48)),
                'status'       => $referral['status'],
                'status_label' => $this->statuses[$referral['status']] ?? $referral['status'],
                'rewards'      => $rewards,
                'created_at'   => $referral['created_at'],
                'completed_at' => $referral['completed_at'],
            );
        }, $referrals);
    }

    /**
     * Check and award referral achievements.
     *
     * @param int $user_id         Referrer user ID.
     * @param int $completed_count Number of completed referrals.
     * @return void
     */
    private function check_referral_achievements(int $user_id, int $completed_count): void {
        // First referral
        if ($completed_count >= 1) {
            $this->achievements->award_achievement($user_id, 'first_referral');
        }

        // 5 referrals
        if ($completed_count >= 5) {
            $this->achievements->award_achievement($user_id, 'referral_5');
        }

        // 10 referrals
        if ($completed_count >= 10) {
            $this->achievements->award_achievement($user_id, 'referral_10');
        }

        // 25 referrals
        if ($completed_count >= 25) {
            $this->achievements->award_achievement($user_id, 'referral_25');
        }

        // 50 referrals
        if ($completed_count >= 50) {
            $this->achievements->award_achievement($user_id, 'referral_master');
        }
    }

    /**
     * Get leaderboard of top referrers.
     *
     * @param int    $limit  Number of entries.
     * @param string $period Time period.
     * @return array
     */
    public function get_leaderboard(int $limit = 10, string $period = 'all'): array {
        global $wpdb;

        $table = $wpdb->prefix . 'sg_referrals';

        $date_condition = '';
        switch ($period) {
            case 'month':
                $date_condition = "AND completed_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
                break;
            case 'week':
                $date_condition = "AND completed_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
                break;
        }

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT referrer_id, COUNT(*) as referral_count
                FROM {$table}
                WHERE status = 'completed' {$date_condition}
                GROUP BY referrer_id
                ORDER BY referral_count DESC
                LIMIT %d",
                $limit
            ),
            ARRAY_A
        );

        return array_map(function ($row, $index) {
            $user = get_userdata($row['referrer_id']);
            return array(
                'rank'     => $index + 1,
                'user_id'  => intval($row['referrer_id']),
                'name'     => $user ? $user->display_name : 'Unbekannt',
                'avatar'   => get_avatar_url($row['referrer_id'], array('size' => 64)),
                'count'    => intval($row['referral_count']),
            );
        }, $results, array_keys($results));
    }

    /**
     * Expire old pending referrals.
     *
     * @param int $days Days after which to expire.
     * @return int Number expired.
     */
    public function expire_old_referrals(int $days = 30): int {
        global $wpdb;

        $table = $wpdb->prefix . 'sg_referrals';

        return $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table}
                SET status = 'expired'
                WHERE status = 'pending'
                AND created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            )
        );
    }

    /**
     * Get global referral statistics.
     *
     * @return array
     */
    public function get_statistics(): array {
        global $wpdb;

        $table = $wpdb->prefix . 'sg_referrals';

        return array(
            'total'        => intval($wpdb->get_var("SELECT COUNT(*) FROM {$table}")),
            'completed'    => intval($wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'completed'")),
            'pending'      => intval($wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'pending'")),
            'this_week'    => intval($wpdb->get_var(
                "SELECT COUNT(*) FROM {$table}
                WHERE status = 'completed'
                AND completed_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)"
            )),
            'this_month'   => intval($wpdb->get_var(
                "SELECT COUNT(*) FROM {$table}
                WHERE status = 'completed'
                AND completed_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)"
            )),
            'unique_referrers' => intval($wpdb->get_var(
                "SELECT COUNT(DISTINCT referrer_id) FROM {$table} WHERE status = 'completed'"
            )),
        );
    }

    /**
     * Render referral widget HTML.
     *
     * @param int   $user_id User ID.
     * @param array $options Display options.
     * @return string HTML.
     */
    public function render_widget(int $user_id, array $options = array()): string {
        $stats = $this->get_user_stats($user_id);

        $html = '<div class="sg-referral-widget">';

        // Referral code and URL
        $html .= '<div class="sg-referral-code-section">';
        $html .= '<h4>Dein Empfehlungscode</h4>';
        $html .= '<div class="sg-referral-code">' . esc_html($stats['code']) . '</div>';
        $html .= '<input type="text" class="sg-referral-url" value="' . esc_attr($stats['url']) . '" readonly>';
        $html .= '<button class="sg-copy-url" data-url="' . esc_attr($stats['url']) . '">Link kopieren</button>';
        $html .= '</div>';

        // Statistics
        $html .= '<div class="sg-referral-stats">';
        $html .= sprintf(
            '<div class="sg-stat"><span class="sg-stat-value">%d</span><span class="sg-stat-label">Empfehlungen</span></div>',
            $stats['completed']
        );
        $html .= sprintf(
            '<div class="sg-stat"><span class="sg-stat-value">%d</span><span class="sg-stat-label">Punkte verdient</span></div>',
            $stats['total_earned']
        );
        $html .= '</div>';

        // Next tier progress
        if ($stats['next_tier']) {
            $progress = (($stats['next_tier']['threshold'] - $stats['next_tier']['remaining']) / $stats['next_tier']['threshold']) * 100;
            $html .= '<div class="sg-referral-progress">';
            $html .= sprintf(
                '<p>Noch %d Empfehlungen bis zum nächsten Bonus (%d Punkte pro Empfehlung)</p>',
                $stats['next_tier']['remaining'],
                $stats['next_tier']['rewards']['referrer']
            );
            $html .= sprintf(
                '<div class="sg-progress-bar"><div class="sg-progress-fill" style="width: %d%%"></div></div>',
                intval($progress)
            );
            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Export user referral data for GDPR.
     *
     * @param int $user_id User ID.
     * @return array
     */
    public function export_data(int $user_id): array {
        return array(
            'code'      => get_user_meta($user_id, 'sg_referral_code', true),
            'referrals' => $this->get_user_referrals($user_id, 1000),
            'stats'     => $this->get_user_stats($user_id),
        );
    }

    /**
     * Delete user referral data (GDPR).
     * Note: Only anonymizes, doesn't delete to maintain referral integrity.
     *
     * @param int $user_id User ID.
     * @return bool
     */
    public function delete_data(int $user_id): bool {
        global $wpdb;

        // Remove referral code
        delete_user_meta($user_id, 'sg_referral_code');
        delete_user_meta($user_id, 'sg_referred_by');
        delete_user_meta($user_id, 'sg_referral_id');

        // Anonymize referral records (keep for statistics)
        $table = $wpdb->prefix . 'sg_referrals';

        $wpdb->update(
            $table,
            array('referrer_id' => 0),
            array('referrer_id' => $user_id)
        );

        $wpdb->update(
            $table,
            array('referred_id' => 0),
            array('referred_id' => $user_id)
        );

        return true;
    }
}
