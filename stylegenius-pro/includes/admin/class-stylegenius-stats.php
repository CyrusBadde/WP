<?php
/**
 * StyleGenius Pro Statistics Class
 *
 * Handles all plugin statistics and analytics
 *
 * @package    StyleGenius_Pro
 * @subpackage StyleGenius_Pro/includes/admin
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Statistics Class
 */
class StyleGenius_Stats {

    /**
     * Database instance
     *
     * @var StyleGenius_Database
     */
    private $db;

    /**
     * Constructor
     */
    public function __construct() {
        $this->db = new StyleGenius_Database();
    }

    /**
     * Get overview statistics
     *
     * @return array Overview data
     */
    public function get_overview() {
        global $wpdb;

        $tables = $this->db->get_table_names();

        // User statistics
        $total_users = $this->count_stylegenius_users();
        $active_users_today = $this->get_active_users('today');
        $active_users_week = $this->get_active_users('week');
        $active_users_month = $this->get_active_users('month');

        // Tier distribution
        $tier_distribution = $this->get_tier_distribution();

        // Feature usage
        $quiz_completions = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$tables['quiz_results']} WHERE created_at >= %s",
                date('Y-m-d 00:00:00', strtotime('-30 days'))
            )
        );

        $chat_messages = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$tables['chat_history']} WHERE created_at >= %s",
                date('Y-m-d 00:00:00', strtotime('-30 days'))
            )
        );

        $wardrobe_items = $wpdb->get_var("SELECT COUNT(*) FROM {$tables['wardrobe']}");

        $photo_analyses = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$tables['style_photos']} WHERE created_at >= %s",
                date('Y-m-d 00:00:00', strtotime('-30 days'))
            )
        );

        // Gamification stats
        $total_points = $wpdb->get_var("SELECT SUM(total_points) FROM {$tables['user_points']}");
        $badges_earned = $wpdb->get_var("SELECT COUNT(*) FROM {$tables['achievements']}");

        // Challenge stats
        $active_challenges = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$tables['challenges']} WHERE status = %s",
                'active'
            )
        );

        $challenge_entries = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$tables['challenge_entries']} WHERE created_at >= %s",
                date('Y-m-d 00:00:00', strtotime('-30 days'))
            )
        );

        // Referral stats
        $total_referrals = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$tables['referrals']} WHERE status = %s",
                'completed'
            )
        );

        // AI usage
        $ai_requests_today = $this->get_ai_usage('today');
        $ai_requests_month = $this->get_ai_usage('month');

        return array(
            'users' => array(
                'total' => (int) $total_users,
                'active_today' => (int) $active_users_today,
                'active_week' => (int) $active_users_week,
                'active_month' => (int) $active_users_month,
                'tier_distribution' => $tier_distribution,
            ),
            'features' => array(
                'quiz_completions_30d' => (int) $quiz_completions,
                'chat_messages_30d' => (int) $chat_messages,
                'wardrobe_items_total' => (int) $wardrobe_items,
                'photo_analyses_30d' => (int) $photo_analyses,
            ),
            'gamification' => array(
                'total_points' => (int) $total_points,
                'badges_earned' => (int) $badges_earned,
            ),
            'challenges' => array(
                'active' => (int) $active_challenges,
                'entries_30d' => (int) $challenge_entries,
            ),
            'referrals' => array(
                'completed' => (int) $total_referrals,
            ),
            'ai' => array(
                'requests_today' => (int) $ai_requests_today,
                'requests_month' => (int) $ai_requests_month,
            ),
        );
    }

    /**
     * Count StyleGenius users
     *
     * @return int User count
     */
    private function count_stylegenius_users() {
        global $wpdb;

        return (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta}
             WHERE meta_key LIKE 'sg_%'"
        );
    }

    /**
     * Get active users count
     *
     * @param string $period Period: today, week, month
     * @return int Active user count
     */
    private function get_active_users($period) {
        global $wpdb;

        $tables = $this->db->get_table_names();

        switch ($period) {
            case 'today':
                $date = date('Y-m-d 00:00:00');
                break;
            case 'week':
                $date = date('Y-m-d 00:00:00', strtotime('-7 days'));
                break;
            case 'month':
            default:
                $date = date('Y-m-d 00:00:00', strtotime('-30 days'));
                break;
        }

        // Count users with any activity
        $active = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT user_id) FROM (
                    SELECT user_id FROM {$tables['chat_history']} WHERE created_at >= %s
                    UNION
                    SELECT user_id FROM {$tables['quiz_results']} WHERE created_at >= %s
                    UNION
                    SELECT user_id FROM {$tables['wardrobe']} WHERE created_at >= %s
                    UNION
                    SELECT user_id FROM {$tables['style_photos']} WHERE created_at >= %s
                ) AS active_users",
                $date, $date, $date, $date
            )
        );

        return (int) $active;
    }

    /**
     * Get tier distribution
     *
     * @return array Tier counts
     */
    private function get_tier_distribution() {
        global $wpdb;

        $distribution = array(
            'free' => 0,
            'premium' => 0,
            'vip' => 0,
        );

        $results = $wpdb->get_results(
            "SELECT meta_value, COUNT(*) as count
             FROM {$wpdb->usermeta}
             WHERE meta_key = 'sg_subscription_tier'
             GROUP BY meta_value"
        );

        foreach ($results as $row) {
            if (isset($distribution[$row->meta_value])) {
                $distribution[$row->meta_value] = (int) $row->count;
            }
        }

        // Count users without tier as free
        $users_with_tier = array_sum($distribution);
        $total_sg_users = $this->count_stylegenius_users();
        $distribution['free'] += max(0, $total_sg_users - $users_with_tier);

        return $distribution;
    }

    /**
     * Get AI usage statistics
     *
     * @param string $period Period
     * @return int Request count
     */
    private function get_ai_usage($period) {
        global $wpdb;

        $tables = $this->db->get_table_names();

        switch ($period) {
            case 'today':
                $date = date('Y-m-d 00:00:00');
                break;
            case 'week':
                $date = date('Y-m-d 00:00:00', strtotime('-7 days'));
                break;
            case 'month':
            default:
                $date = date('Y-m-d 00:00:00', strtotime('-30 days'));
                break;
        }

        // Count AI interactions (chat messages with role 'assistant')
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$tables['chat_history']}
                 WHERE role = 'assistant' AND created_at >= %s",
                $date
            )
        );

        return (int) $count;
    }

    /**
     * Get recent activities
     *
     * @param int $limit Number of activities
     * @return array Recent activities
     */
    public function get_recent_activities($limit = 20) {
        global $wpdb;

        $tables = $this->db->get_table_names();
        $activities = array();

        // Quiz completions
        $quizzes = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT qr.user_id, qr.style_type, qr.created_at, u.display_name
                 FROM {$tables['quiz_results']} qr
                 LEFT JOIN {$wpdb->users} u ON qr.user_id = u.ID
                 ORDER BY qr.created_at DESC
                 LIMIT %d",
                $limit
            )
        );

        foreach ($quizzes as $quiz) {
            $activities[] = array(
                'type' => 'quiz',
                'user_id' => $quiz->user_id,
                'user_name' => $quiz->display_name ?: __('Gast', 'stylegenius-pro'),
                'description' => sprintf(
                    __('Hat den Style-Quiz abgeschlossen: %s', 'stylegenius-pro'),
                    ucfirst($quiz->style_type)
                ),
                'icon' => 'clipboard-check',
                'color' => 'purple',
                'date' => $quiz->created_at,
            );
        }

        // New wardrobe items
        $wardrobe_items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT w.user_id, w.name, w.category, w.created_at, u.display_name
                 FROM {$tables['wardrobe']} w
                 LEFT JOIN {$wpdb->users} u ON w.user_id = u.ID
                 ORDER BY w.created_at DESC
                 LIMIT %d",
                $limit
            )
        );

        foreach ($wardrobe_items as $item) {
            $activities[] = array(
                'type' => 'wardrobe',
                'user_id' => $item->user_id,
                'user_name' => $item->display_name ?: __('Gast', 'stylegenius-pro'),
                'description' => sprintf(
                    __('Hat "%s" zur Garderobe hinzugefügt (%s)', 'stylegenius-pro'),
                    $item->name,
                    $item->category
                ),
                'icon' => 'shirt',
                'color' => 'blue',
                'date' => $item->created_at,
            );
        }

        // Badge achievements
        $badges = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT a.user_id, a.badge_key, a.earned_at, u.display_name
                 FROM {$tables['achievements']} a
                 LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
                 ORDER BY a.earned_at DESC
                 LIMIT %d",
                $limit
            )
        );

        foreach ($badges as $badge) {
            $badge_name = $this->get_badge_display_name($badge->badge_key);
            $activities[] = array(
                'type' => 'achievement',
                'user_id' => $badge->user_id,
                'user_name' => $badge->display_name ?: __('Gast', 'stylegenius-pro'),
                'description' => sprintf(
                    __('Hat das Abzeichen "%s" verdient', 'stylegenius-pro'),
                    $badge_name
                ),
                'icon' => 'award',
                'color' => 'yellow',
                'date' => $badge->earned_at,
            );
        }

        // Challenge entries
        $entries = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ce.user_id, ce.created_at, c.title, u.display_name
                 FROM {$tables['challenge_entries']} ce
                 LEFT JOIN {$tables['challenges']} c ON ce.challenge_id = c.id
                 LEFT JOIN {$wpdb->users} u ON ce.user_id = u.ID
                 ORDER BY ce.created_at DESC
                 LIMIT %d",
                $limit
            )
        );

        foreach ($entries as $entry) {
            $activities[] = array(
                'type' => 'challenge',
                'user_id' => $entry->user_id,
                'user_name' => $entry->display_name ?: __('Gast', 'stylegenius-pro'),
                'description' => sprintf(
                    __('Nimmt an Challenge teil: %s', 'stylegenius-pro'),
                    $entry->title
                ),
                'icon' => 'target',
                'color' => 'green',
                'date' => $entry->created_at,
            );
        }

        // Referrals
        $referrals = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT r.referrer_id, r.referred_id, r.completed_at,
                        u1.display_name as referrer_name, u2.display_name as referred_name
                 FROM {$tables['referrals']} r
                 LEFT JOIN {$wpdb->users} u1 ON r.referrer_id = u1.ID
                 LEFT JOIN {$wpdb->users} u2 ON r.referred_id = u2.ID
                 WHERE r.status = 'completed'
                 ORDER BY r.completed_at DESC
                 LIMIT %d",
                $limit
            )
        );

        foreach ($referrals as $ref) {
            $activities[] = array(
                'type' => 'referral',
                'user_id' => $ref->referrer_id,
                'user_name' => $ref->referrer_name ?: __('Gast', 'stylegenius-pro'),
                'description' => sprintf(
                    __('Hat %s erfolgreich geworben', 'stylegenius-pro'),
                    $ref->referred_name ?: __('einen Nutzer', 'stylegenius-pro')
                ),
                'icon' => 'user-plus',
                'color' => 'pink',
                'date' => $ref->completed_at,
            );
        }

        // Sort by date descending
        usort($activities, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });

        // Limit to requested number
        return array_slice($activities, 0, $limit);
    }

    /**
     * Get badge display name
     *
     * @param string $badge_key Badge key
     * @return string Display name
     */
    private function get_badge_display_name($badge_key) {
        $badges = array(
            'first_quiz' => __('Stilentdecker', 'stylegenius-pro'),
            'wardrobe_starter' => __('Garderobe gestartet', 'stylegenius-pro'),
            'first_chat' => __('Erste Beratung', 'stylegenius-pro'),
            'photo_analyst' => __('Foto-Analyst', 'stylegenius-pro'),
            'color_expert' => __('Farbexperte', 'stylegenius-pro'),
            'capsule_creator' => __('Capsule-Creator', 'stylegenius-pro'),
            'challenge_winner' => __('Challenge-Sieger', 'stylegenius-pro'),
            'streak_master' => __('Streak-Master', 'stylegenius-pro'),
            'referral_champion' => __('Empfehlungschampion', 'stylegenius-pro'),
            'style_icon' => __('Style-Ikone', 'stylegenius-pro'),
        );

        return $badges[$badge_key] ?? ucfirst(str_replace('_', ' ', $badge_key));
    }

    /**
     * Get chart data
     *
     * @param string $chart_type Chart type
     * @param int    $days       Number of days
     * @return array Chart data
     */
    public function get_chart_data($chart_type, $days = 30) {
        switch ($chart_type) {
            case 'user_registrations':
                return $this->get_user_registration_chart($days);
            case 'feature_usage':
                return $this->get_feature_usage_chart($days);
            case 'ai_usage':
                return $this->get_ai_usage_chart($days);
            case 'tier_revenue':
                return $this->get_tier_revenue_chart($days);
            case 'gamification':
                return $this->get_gamification_chart($days);
            default:
                return array();
        }
    }

    /**
     * Get user registration chart data
     *
     * @param int $days Number of days
     * @return array Chart data
     */
    private function get_user_registration_chart($days) {
        global $wpdb;

        $data = array(
            'labels' => array(),
            'datasets' => array(
                array(
                    'label' => __('Neue Nutzer', 'stylegenius-pro'),
                    'data' => array(),
                    'backgroundColor' => 'rgba(147, 51, 234, 0.5)',
                    'borderColor' => 'rgb(147, 51, 234)',
                ),
            ),
        );

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $data['labels'][] = date('d.m.', strtotime($date));

            $count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->users}
                     WHERE DATE(user_registered) = %s",
                    $date
                )
            );

            $data['datasets'][0]['data'][] = (int) $count;
        }

        return $data;
    }

    /**
     * Get feature usage chart data
     *
     * @param int $days Number of days
     * @return array Chart data
     */
    private function get_feature_usage_chart($days) {
        global $wpdb;

        $tables = $this->db->get_table_names();

        $data = array(
            'labels' => array(),
            'datasets' => array(
                array(
                    'label' => __('Quiz', 'stylegenius-pro'),
                    'data' => array(),
                    'borderColor' => 'rgb(147, 51, 234)',
                    'backgroundColor' => 'rgba(147, 51, 234, 0.1)',
                ),
                array(
                    'label' => __('Chat', 'stylegenius-pro'),
                    'data' => array(),
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                ),
                array(
                    'label' => __('Garderobe', 'stylegenius-pro'),
                    'data' => array(),
                    'borderColor' => 'rgb(16, 185, 129)',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                ),
                array(
                    'label' => __('Foto-Analyse', 'stylegenius-pro'),
                    'data' => array(),
                    'borderColor' => 'rgb(245, 158, 11)',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
                ),
            ),
        );

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $data['labels'][] = date('d.m.', strtotime($date));

            // Quiz
            $quiz_count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$tables['quiz_results']} WHERE DATE(created_at) = %s",
                    $date
                )
            );
            $data['datasets'][0]['data'][] = (int) $quiz_count;

            // Chat
            $chat_count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$tables['chat_history']}
                     WHERE role = 'user' AND DATE(created_at) = %s",
                    $date
                )
            );
            $data['datasets'][1]['data'][] = (int) $chat_count;

            // Wardrobe
            $wardrobe_count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$tables['wardrobe']} WHERE DATE(created_at) = %s",
                    $date
                )
            );
            $data['datasets'][2]['data'][] = (int) $wardrobe_count;

            // Photo analysis
            $photo_count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$tables['style_photos']} WHERE DATE(created_at) = %s",
                    $date
                )
            );
            $data['datasets'][3]['data'][] = (int) $photo_count;
        }

        return $data;
    }

    /**
     * Get AI usage chart data
     *
     * @param int $days Number of days
     * @return array Chart data
     */
    private function get_ai_usage_chart($days) {
        global $wpdb;

        $tables = $this->db->get_table_names();

        $data = array(
            'labels' => array(),
            'datasets' => array(
                array(
                    'label' => __('AI-Anfragen', 'stylegenius-pro'),
                    'data' => array(),
                    'backgroundColor' => 'rgba(236, 72, 153, 0.5)',
                    'borderColor' => 'rgb(236, 72, 153)',
                ),
            ),
        );

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $data['labels'][] = date('d.m.', strtotime($date));

            $count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$tables['chat_history']}
                     WHERE role = 'assistant' AND DATE(created_at) = %s",
                    $date
                )
            );

            $data['datasets'][0]['data'][] = (int) $count;
        }

        return $data;
    }

    /**
     * Get tier revenue chart data
     *
     * @param int $days Number of days
     * @return array Chart data
     */
    private function get_tier_revenue_chart($days) {
        // This would integrate with WooCommerce subscriptions
        // For now, return simulated data structure

        $data = array(
            'labels' => array(
                __('Kostenlos', 'stylegenius-pro'),
                __('Premium', 'stylegenius-pro'),
                __('VIP', 'stylegenius-pro'),
            ),
            'datasets' => array(
                array(
                    'label' => __('Nutzer pro Stufe', 'stylegenius-pro'),
                    'data' => array(),
                    'backgroundColor' => array(
                        'rgba(156, 163, 175, 0.8)',
                        'rgba(147, 51, 234, 0.8)',
                        'rgba(245, 158, 11, 0.8)',
                    ),
                ),
            ),
        );

        $distribution = $this->get_tier_distribution();
        $data['datasets'][0]['data'] = array(
            $distribution['free'],
            $distribution['premium'],
            $distribution['vip'],
        );

        return $data;
    }

    /**
     * Get gamification chart data
     *
     * @param int $days Number of days
     * @return array Chart data
     */
    private function get_gamification_chart($days) {
        global $wpdb;

        $tables = $this->db->get_table_names();

        $data = array(
            'labels' => array(),
            'datasets' => array(
                array(
                    'label' => __('Punkte verdient', 'stylegenius-pro'),
                    'data' => array(),
                    'borderColor' => 'rgb(147, 51, 234)',
                    'backgroundColor' => 'rgba(147, 51, 234, 0.1)',
                    'yAxisID' => 'y',
                ),
                array(
                    'label' => __('Abzeichen verdient', 'stylegenius-pro'),
                    'data' => array(),
                    'borderColor' => 'rgb(245, 158, 11)',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
                    'yAxisID' => 'y1',
                ),
            ),
        );

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $data['labels'][] = date('d.m.', strtotime($date));

            // Points earned (from points_history)
            $points = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COALESCE(SUM(points), 0) FROM {$tables['points_history']}
                     WHERE points > 0 AND DATE(created_at) = %s",
                    $date
                )
            );
            $data['datasets'][0]['data'][] = (int) $points;

            // Badges earned
            $badges = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$tables['achievements']} WHERE DATE(earned_at) = %s",
                    $date
                )
            );
            $data['datasets'][1]['data'][] = (int) $badges;
        }

        return $data;
    }

    /**
     * Render charts container
     */
    public function render_charts() {
        ?>
        <div class="stylegenius-charts-container">
            <div class="stylegenius-chart-row">
                <div class="stylegenius-chart-card">
                    <h3><?php _e('Feature-Nutzung (30 Tage)', 'stylegenius-pro'); ?></h3>
                    <canvas id="sg-feature-usage-chart"></canvas>
                </div>
                <div class="stylegenius-chart-card">
                    <h3><?php _e('Nutzerverteilung nach Stufe', 'stylegenius-pro'); ?></h3>
                    <canvas id="sg-tier-chart"></canvas>
                </div>
            </div>
            <div class="stylegenius-chart-row">
                <div class="stylegenius-chart-card">
                    <h3><?php _e('AI-Nutzung (30 Tage)', 'stylegenius-pro'); ?></h3>
                    <canvas id="sg-ai-usage-chart"></canvas>
                </div>
                <div class="stylegenius-chart-card">
                    <h3><?php _e('Gamification (30 Tage)', 'stylegenius-pro'); ?></h3>
                    <canvas id="sg-gamification-chart"></canvas>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Feature Usage Chart
            var featureUsageData = <?php echo wp_json_encode($this->get_chart_data('feature_usage', 30)); ?>;
            new Chart(document.getElementById('sg-feature-usage-chart'), {
                type: 'line',
                data: featureUsageData,
                options: {
                    responsive: true,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: {
                            position: 'bottom',
                        }
                    }
                }
            });

            // Tier Distribution Chart
            var tierData = <?php echo wp_json_encode($this->get_chart_data('tier_revenue', 30)); ?>;
            new Chart(document.getElementById('sg-tier-chart'), {
                type: 'doughnut',
                data: tierData,
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                        }
                    }
                }
            });

            // AI Usage Chart
            var aiUsageData = <?php echo wp_json_encode($this->get_chart_data('ai_usage', 30)); ?>;
            new Chart(document.getElementById('sg-ai-usage-chart'), {
                type: 'bar',
                data: aiUsageData,
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                        }
                    }
                }
            });

            // Gamification Chart
            var gamificationData = <?php echo wp_json_encode($this->get_chart_data('gamification', 30)); ?>;
            new Chart(document.getElementById('sg-gamification-chart'), {
                type: 'line',
                data: gamificationData,
                options: {
                    responsive: true,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: {
                            position: 'bottom',
                        }
                    },
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            grid: {
                                drawOnChartArea: false,
                            },
                        }
                    }
                }
            });
        });
        </script>

        <style>
        .stylegenius-charts-container {
            margin-top: 20px;
        }
        .stylegenius-chart-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        .stylegenius-chart-card {
            flex: 1;
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .stylegenius-chart-card h3 {
            margin-top: 0;
            margin-bottom: 15px;
            font-size: 14px;
            color: #374151;
        }
        .stylegenius-chart-card canvas {
            max-height: 300px;
        }
        @media (max-width: 1200px) {
            .stylegenius-chart-row {
                flex-direction: column;
            }
        }
        </style>
        <?php
    }

    /**
     * Get export data
     *
     * @param string $type   Export type
     * @param array  $params Parameters
     * @return array Export data
     */
    public function get_export_data($type, $params = array()) {
        switch ($type) {
            case 'users':
                return $this->export_users($params);
            case 'activities':
                return $this->export_activities($params);
            case 'statistics':
                return $this->export_statistics($params);
            default:
                return array();
        }
    }

    /**
     * Export users data
     *
     * @param array $params Parameters
     * @return array User data
     */
    private function export_users($params) {
        global $wpdb;

        $limit = isset($params['limit']) ? (int) $params['limit'] : 1000;
        $tier = isset($params['tier']) ? sanitize_text_field($params['tier']) : '';

        $query = "
            SELECT u.ID, u.user_email, u.display_name, u.user_registered,
                   (SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = u.ID AND meta_key = 'sg_subscription_tier') as tier,
                   (SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = u.ID AND meta_key = 'sg_style_type') as style_type,
                   (SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = u.ID AND meta_key = 'sg_color_profile') as color_profile
            FROM {$wpdb->users} u
            WHERE u.ID IN (
                SELECT DISTINCT user_id FROM {$wpdb->usermeta} WHERE meta_key LIKE 'sg_%'
            )
        ";

        if (!empty($tier)) {
            $query .= $wpdb->prepare(
                " AND u.ID IN (
                    SELECT user_id FROM {$wpdb->usermeta}
                    WHERE meta_key = 'sg_subscription_tier' AND meta_value = %s
                )",
                $tier
            );
        }

        $query .= $wpdb->prepare(" LIMIT %d", $limit);

        $users = $wpdb->get_results($query, ARRAY_A);

        return array(
            'type' => 'users',
            'count' => count($users),
            'data' => $users,
            'exported_at' => current_time('mysql'),
        );
    }

    /**
     * Export activities data
     *
     * @param array $params Parameters
     * @return array Activities data
     */
    private function export_activities($params) {
        $days = isset($params['days']) ? (int) $params['days'] : 30;
        $limit = isset($params['limit']) ? (int) $params['limit'] : 1000;

        $activities = $this->get_recent_activities($limit);

        return array(
            'type' => 'activities',
            'count' => count($activities),
            'data' => $activities,
            'exported_at' => current_time('mysql'),
        );
    }

    /**
     * Export statistics data
     *
     * @param array $params Parameters
     * @return array Statistics data
     */
    private function export_statistics($params) {
        $overview = $this->get_overview();
        $charts = array(
            'feature_usage' => $this->get_chart_data('feature_usage', 30),
            'ai_usage' => $this->get_chart_data('ai_usage', 30),
            'gamification' => $this->get_chart_data('gamification', 30),
        );

        return array(
            'type' => 'statistics',
            'overview' => $overview,
            'charts' => $charts,
            'exported_at' => current_time('mysql'),
        );
    }
}
