<?php
/**
 * StyleGenius Challenges Class
 *
 * Handles Weekly Style Challenges with community voting.
 *
 * @package    StyleGenius_Pro
 * @subpackage StyleGenius_Pro/includes/features
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Challenges management class.
 */
class StyleGenius_Challenges {

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
     * Challenge types.
     *
     * @var array
     */
    private $challenge_types = array(
        'outfit'     => 'Outfit-Challenge',
        'color'      => 'Farb-Challenge',
        'capsule'    => 'Capsule-Challenge',
        'accessory'  => 'Accessoire-Challenge',
        'seasonal'   => 'Saisonale Challenge',
        'theme'      => 'Themen-Challenge',
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
     * Get challenge types.
     *
     * @return array
     */
    public function get_types(): array {
        return $this->challenge_types;
    }

    /**
     * Create a new challenge (Admin function).
     *
     * @param array $data Challenge data.
     * @return array Result.
     */
    public function create_challenge(array $data): array {
        global $wpdb;

        // Validate required fields
        $required = array('title', 'description', 'type', 'start_date', 'end_date');
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return array(
                    'success' => false,
                    'error'   => sprintf('Feld "%s" ist erforderlich.', $field),
                );
            }
        }

        // Validate dates
        $start = strtotime($data['start_date']);
        $end = strtotime($data['end_date']);

        if ($end <= $start) {
            return array(
                'success' => false,
                'error'   => 'Enddatum muss nach dem Startdatum liegen.',
            );
        }

        $table = $wpdb->prefix . 'sg_challenges';

        $result = $wpdb->insert(
            $table,
            array(
                'title'       => sanitize_text_field($data['title']),
                'description' => sanitize_textarea_field($data['description']),
                'type'        => sanitize_text_field($data['type']),
                'rules'       => sanitize_textarea_field($data['rules'] ?? ''),
                'image_id'    => intval($data['image_id'] ?? 0),
                'start_date'  => date('Y-m-d H:i:s', $start),
                'end_date'    => date('Y-m-d H:i:s', $end),
                'prize_info'  => sanitize_textarea_field($data['prize_info'] ?? ''),
                'points'      => intval($data['points'] ?? 50),
                'winner_points' => intval($data['winner_points'] ?? 200),
                'status'      => 'scheduled',
                'created_at'  => current_time('mysql'),
            )
        );

        if (false === $result) {
            return array(
                'success' => false,
                'error'   => 'Fehler beim Erstellen der Challenge.',
            );
        }

        return array(
            'success'      => true,
            'challenge_id' => $wpdb->insert_id,
        );
    }

    /**
     * Get active challenges.
     *
     * @return array
     */
    public function get_active_challenges(): array {
        global $wpdb;

        $table = $wpdb->prefix . 'sg_challenges';
        $now = current_time('mysql');

        $challenges = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                WHERE start_date <= %s AND end_date >= %s
                AND status = 'active'
                ORDER BY end_date ASC",
                $now,
                $now
            ),
            ARRAY_A
        );

        return array_map(array($this, 'format_challenge'), $challenges);
    }

    /**
     * Get upcoming challenges.
     *
     * @param int $limit Number of challenges.
     * @return array
     */
    public function get_upcoming_challenges(int $limit = 5): array {
        global $wpdb;

        $table = $wpdb->prefix . 'sg_challenges';
        $now = current_time('mysql');

        $challenges = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                WHERE start_date > %s
                AND status = 'scheduled'
                ORDER BY start_date ASC
                LIMIT %d",
                $now,
                $limit
            ),
            ARRAY_A
        );

        return array_map(array($this, 'format_challenge'), $challenges);
    }

    /**
     * Get past challenges.
     *
     * @param int $limit Number of challenges.
     * @return array
     */
    public function get_past_challenges(int $limit = 10): array {
        global $wpdb;

        $table = $wpdb->prefix . 'sg_challenges';
        $now = current_time('mysql');

        $challenges = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                WHERE end_date < %s
                AND status = 'completed'
                ORDER BY end_date DESC
                LIMIT %d",
                $now,
                $limit
            ),
            ARRAY_A
        );

        return array_map(array($this, 'format_challenge'), $challenges);
    }

    /**
     * Get single challenge.
     *
     * @param int $challenge_id Challenge ID.
     * @return array|null
     */
    public function get_challenge(int $challenge_id): ?array {
        global $wpdb;

        $table = $wpdb->prefix . 'sg_challenges';

        $challenge = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d",
                $challenge_id
            ),
            ARRAY_A
        );

        if (!$challenge) {
            return null;
        }

        return $this->format_challenge($challenge, true);
    }

    /**
     * Format challenge data.
     *
     * @param array $challenge   Raw challenge data.
     * @param bool  $with_entries Include entries.
     * @return array
     */
    private function format_challenge(array $challenge, bool $with_entries = false): array {
        $formatted = array(
            'id'            => intval($challenge['id']),
            'title'         => $challenge['title'],
            'description'   => $challenge['description'],
            'type'          => $challenge['type'],
            'type_label'    => $this->challenge_types[$challenge['type']] ?? $challenge['type'],
            'rules'         => $challenge['rules'],
            'image_id'      => intval($challenge['image_id']),
            'image_url'     => $challenge['image_id'] ? wp_get_attachment_url($challenge['image_id']) : null,
            'start_date'    => $challenge['start_date'],
            'end_date'      => $challenge['end_date'],
            'prize_info'    => $challenge['prize_info'],
            'points'        => intval($challenge['points']),
            'winner_points' => intval($challenge['winner_points']),
            'status'        => $challenge['status'],
            'time_remaining' => $this->get_time_remaining($challenge['end_date']),
            'entry_count'   => $this->get_entry_count($challenge['id']),
        );

        if ($with_entries) {
            $formatted['entries'] = $this->get_entries($challenge['id']);
            $formatted['winner'] = $challenge['winner_id'] ? $this->get_winner_info($challenge['winner_id']) : null;
        }

        return $formatted;
    }

    /**
     * Get time remaining for challenge.
     *
     * @param string $end_date End date.
     * @return array
     */
    private function get_time_remaining(string $end_date): array {
        $end = strtotime($end_date);
        $now = current_time('timestamp');
        $diff = $end - $now;

        if ($diff <= 0) {
            return array(
                'ended'   => true,
                'text'    => 'Beendet',
            );
        }

        $days = floor($diff / DAY_IN_SECONDS);
        $hours = floor(($diff % DAY_IN_SECONDS) / HOUR_IN_SECONDS);
        $minutes = floor(($diff % HOUR_IN_SECONDS) / MINUTE_IN_SECONDS);

        $text = '';
        if ($days > 0) {
            $text = sprintf('%d Tag%s', $days, $days > 1 ? 'e' : '');
        } elseif ($hours > 0) {
            $text = sprintf('%d Stunde%s', $hours, $hours > 1 ? 'n' : '');
        } else {
            $text = sprintf('%d Minute%s', $minutes, $minutes > 1 ? 'n' : '');
        }

        return array(
            'ended'   => false,
            'days'    => $days,
            'hours'   => $hours,
            'minutes' => $minutes,
            'text'    => $text . ' verbleibend',
        );
    }

    /**
     * Submit entry to challenge.
     *
     * @param int   $user_id      User ID.
     * @param int   $challenge_id Challenge ID.
     * @param array $data         Entry data.
     * @return array Result.
     */
    public function submit_entry(int $user_id, int $challenge_id, array $data): array {
        global $wpdb;

        // Check if challenge is active
        $challenge = $this->get_challenge($challenge_id);

        if (!$challenge) {
            return array(
                'success' => false,
                'error'   => 'Challenge nicht gefunden.',
            );
        }

        if ($challenge['status'] !== 'active') {
            return array(
                'success' => false,
                'error'   => 'Diese Challenge ist nicht mehr aktiv.',
            );
        }

        if ($challenge['time_remaining']['ended']) {
            return array(
                'success' => false,
                'error'   => 'Die Einreichungsfrist ist abgelaufen.',
            );
        }

        // Check if already submitted
        if ($this->has_submitted($user_id, $challenge_id)) {
            return array(
                'success' => false,
                'error'   => 'Du hast bereits an dieser Challenge teilgenommen.',
            );
        }

        // Validate image
        if (empty($data['image_id'])) {
            return array(
                'success' => false,
                'error'   => 'Ein Bild ist erforderlich.',
            );
        }

        $table = $wpdb->prefix . 'sg_challenge_entries';

        $result = $wpdb->insert(
            $table,
            array(
                'challenge_id' => $challenge_id,
                'user_id'      => $user_id,
                'image_id'     => intval($data['image_id']),
                'caption'      => sanitize_textarea_field($data['caption'] ?? ''),
                'items_used'   => isset($data['items_used']) ? wp_json_encode($data['items_used']) : null,
                'status'       => 'approved', // Auto-approve, could add moderation
                'submitted_at' => current_time('mysql'),
            )
        );

        if (false === $result) {
            return array(
                'success' => false,
                'error'   => 'Fehler beim Einreichen.',
            );
        }

        $entry_id = $wpdb->insert_id;

        // Award participation points
        $this->points->award_points(
            $user_id,
            'challenge_entry',
            $challenge['points'],
            sprintf('Challenge-Teilnahme: %s', $challenge['title'])
        );

        // Check achievements
        $this->check_challenge_achievements($user_id);

        return array(
            'success'  => true,
            'entry_id' => $entry_id,
            'message'  => 'Dein Beitrag wurde erfolgreich eingereicht!',
        );
    }

    /**
     * Check if user has submitted to challenge.
     *
     * @param int $user_id      User ID.
     * @param int $challenge_id Challenge ID.
     * @return bool
     */
    public function has_submitted(int $user_id, int $challenge_id): bool {
        global $wpdb;

        $table = $wpdb->prefix . 'sg_challenge_entries';

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table}
                WHERE user_id = %d AND challenge_id = %d",
                $user_id,
                $challenge_id
            )
        );

        return intval($count) > 0;
    }

    /**
     * Get entries for challenge.
     *
     * @param int    $challenge_id Challenge ID.
     * @param string $sort         Sort order.
     * @param int    $limit        Number of entries.
     * @return array
     */
    public function get_entries(int $challenge_id, string $sort = 'votes', int $limit = 50): array {
        global $wpdb;

        $entries_table = $wpdb->prefix . 'sg_challenge_entries';
        $votes_table = $wpdb->prefix . 'sg_challenge_votes';

        $order = $sort === 'votes' ? 'vote_count DESC' : 'e.submitted_at DESC';

        $entries = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT e.*, COUNT(v.id) as vote_count
                FROM {$entries_table} e
                LEFT JOIN {$votes_table} v ON e.id = v.entry_id
                WHERE e.challenge_id = %d AND e.status = 'approved'
                GROUP BY e.id
                ORDER BY {$order}
                LIMIT %d",
                $challenge_id,
                $limit
            ),
            ARRAY_A
        );

        return array_map(array($this, 'format_entry'), $entries);
    }

    /**
     * Get user's entries.
     *
     * @param int $user_id User ID.
     * @param int $limit   Number of entries.
     * @return array
     */
    public function get_user_entries(int $user_id, int $limit = 20): array {
        global $wpdb;

        $entries_table = $wpdb->prefix . 'sg_challenge_entries';
        $votes_table = $wpdb->prefix . 'sg_challenge_votes';
        $challenges_table = $wpdb->prefix . 'sg_challenges';

        $entries = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT e.*, c.title as challenge_title, COUNT(v.id) as vote_count
                FROM {$entries_table} e
                INNER JOIN {$challenges_table} c ON e.challenge_id = c.id
                LEFT JOIN {$votes_table} v ON e.id = v.entry_id
                WHERE e.user_id = %d
                GROUP BY e.id
                ORDER BY e.submitted_at DESC
                LIMIT %d",
                $user_id,
                $limit
            ),
            ARRAY_A
        );

        return array_map(array($this, 'format_entry'), $entries);
    }

    /**
     * Format entry data.
     *
     * @param array $entry Raw entry data.
     * @return array
     */
    private function format_entry(array $entry): array {
        $user = get_userdata($entry['user_id']);

        return array(
            'id'              => intval($entry['id']),
            'challenge_id'    => intval($entry['challenge_id']),
            'challenge_title' => $entry['challenge_title'] ?? null,
            'user_id'         => intval($entry['user_id']),
            'user_name'       => $user ? $user->display_name : 'Unbekannt',
            'user_avatar'     => get_avatar_url($entry['user_id'], array('size' => 64)),
            'image_id'        => intval($entry['image_id']),
            'image_url'       => wp_get_attachment_url($entry['image_id']),
            'thumbnail_url'   => wp_get_attachment_image_url($entry['image_id'], 'medium'),
            'caption'         => $entry['caption'],
            'items_used'      => $entry['items_used'] ? json_decode($entry['items_used'], true) : array(),
            'vote_count'      => intval($entry['vote_count'] ?? 0),
            'status'          => $entry['status'],
            'submitted_at'    => $entry['submitted_at'],
        );
    }

    /**
     * Vote for an entry.
     *
     * @param int $user_id  Voting user ID.
     * @param int $entry_id Entry ID.
     * @return array Result.
     */
    public function vote(int $user_id, int $entry_id): array {
        global $wpdb;

        $entries_table = $wpdb->prefix . 'sg_challenge_entries';
        $votes_table = $wpdb->prefix . 'sg_challenge_votes';

        // Get entry
        $entry = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$entries_table} WHERE id = %d",
                $entry_id
            )
        );

        if (!$entry) {
            return array(
                'success' => false,
                'error'   => 'Beitrag nicht gefunden.',
            );
        }

        // Can't vote for own entry
        if (intval($entry->user_id) === $user_id) {
            return array(
                'success' => false,
                'error'   => 'Du kannst nicht für deinen eigenen Beitrag stimmen.',
            );
        }

        // Check if already voted
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$votes_table}
                WHERE entry_id = %d AND voter_id = %d",
                $entry_id,
                $user_id
            )
        );

        if ($existing) {
            // Remove vote (toggle)
            $wpdb->delete(
                $votes_table,
                array('id' => $existing),
                array('%d')
            );

            return array(
                'success' => true,
                'action'  => 'removed',
                'votes'   => $this->get_vote_count($entry_id),
            );
        }

        // Check voting limit (max 3 per challenge per day)
        $challenge_id = $entry->challenge_id;
        $today_votes = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$votes_table} v
                INNER JOIN {$entries_table} e ON v.entry_id = e.id
                WHERE e.challenge_id = %d
                AND v.voter_id = %d
                AND DATE(v.voted_at) = CURDATE()",
                $challenge_id,
                $user_id
            )
        );

        if (intval($today_votes) >= 3) {
            return array(
                'success' => false,
                'error'   => 'Du hast heute bereits 3 Mal für diese Challenge gestimmt.',
            );
        }

        // Add vote
        $wpdb->insert(
            $votes_table,
            array(
                'entry_id'  => $entry_id,
                'voter_id'  => $user_id,
                'voted_at'  => current_time('mysql'),
            )
        );

        // Award points to voter
        $this->points->award_points($user_id, 'challenge_vote', 2, 'Challenge-Abstimmung');

        return array(
            'success' => true,
            'action'  => 'added',
            'votes'   => $this->get_vote_count($entry_id),
        );
    }

    /**
     * Get vote count for entry.
     *
     * @param int $entry_id Entry ID.
     * @return int
     */
    private function get_vote_count(int $entry_id): int {
        global $wpdb;

        $table = $wpdb->prefix . 'sg_challenge_votes';

        return intval($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE entry_id = %d",
                $entry_id
            )
        ));
    }

    /**
     * Get entry count for challenge.
     *
     * @param int $challenge_id Challenge ID.
     * @return int
     */
    private function get_entry_count(int $challenge_id): int {
        global $wpdb;

        $table = $wpdb->prefix . 'sg_challenge_entries';

        return intval($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table}
                WHERE challenge_id = %d AND status = 'approved'",
                $challenge_id
            )
        ));
    }

    /**
     * Complete challenge and determine winner.
     *
     * @param int $challenge_id Challenge ID.
     * @return array Result.
     */
    public function complete_challenge(int $challenge_id): array {
        global $wpdb;

        $challenges_table = $wpdb->prefix . 'sg_challenges';
        $entries_table = $wpdb->prefix . 'sg_challenge_entries';
        $votes_table = $wpdb->prefix . 'sg_challenge_votes';

        // Get challenge
        $challenge = $this->get_challenge($challenge_id);

        if (!$challenge) {
            return array(
                'success' => false,
                'error'   => 'Challenge nicht gefunden.',
            );
        }

        // Get winner (most votes)
        $winner = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT e.user_id, COUNT(v.id) as vote_count
                FROM {$entries_table} e
                LEFT JOIN {$votes_table} v ON e.id = v.entry_id
                WHERE e.challenge_id = %d AND e.status = 'approved'
                GROUP BY e.user_id
                ORDER BY vote_count DESC
                LIMIT 1",
                $challenge_id
            )
        );

        $winner_id = $winner ? intval($winner->user_id) : null;

        // Update challenge status
        $wpdb->update(
            $challenges_table,
            array(
                'status'    => 'completed',
                'winner_id' => $winner_id,
            ),
            array('id' => $challenge_id)
        );

        // Award winner points
        if ($winner_id) {
            $this->points->award_points(
                $winner_id,
                'challenge_winner',
                $challenge['winner_points'],
                sprintf('Challenge-Gewinner: %s', $challenge['title'])
            );

            // Award achievement
            $this->achievements->award_achievement($winner_id, 'challenge_winner');
        }

        return array(
            'success'   => true,
            'winner_id' => $winner_id,
            'votes'     => $winner ? intval($winner->vote_count) : 0,
        );
    }

    /**
     * Get winner info.
     *
     * @param int $user_id Winner user ID.
     * @return array|null
     */
    private function get_winner_info(int $user_id): ?array {
        $user = get_userdata($user_id);

        if (!$user) {
            return null;
        }

        return array(
            'user_id' => $user_id,
            'name'    => $user->display_name,
            'avatar'  => get_avatar_url($user_id, array('size' => 128)),
        );
    }

    /**
     * Check and update challenge statuses.
     * Should be run via cron.
     *
     * @return void
     */
    public function update_challenge_statuses(): void {
        global $wpdb;

        $table = $wpdb->prefix . 'sg_challenges';
        $now = current_time('mysql');

        // Activate scheduled challenges that have started
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table}
                SET status = 'active'
                WHERE status = 'scheduled'
                AND start_date <= %s",
                $now
            )
        );

        // Complete active challenges that have ended
        $ended = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT id FROM {$table}
                WHERE status = 'active'
                AND end_date < %s",
                $now
            )
        );

        foreach ($ended as $challenge_id) {
            $this->complete_challenge(intval($challenge_id));
        }
    }

    /**
     * Check and award challenge achievements.
     *
     * @param int $user_id User ID.
     * @return void
     */
    private function check_challenge_achievements(int $user_id): void {
        global $wpdb;

        $table = $wpdb->prefix . 'sg_challenge_entries';

        $count = intval($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE user_id = %d",
                $user_id
            )
        ));

        // First challenge
        if ($count >= 1) {
            $this->achievements->award_achievement($user_id, 'first_challenge');
        }

        // 5 challenges
        if ($count >= 5) {
            $this->achievements->award_achievement($user_id, 'challenge_5');
        }

        // 10 challenges
        if ($count >= 10) {
            $this->achievements->award_achievement($user_id, 'challenge_10');
        }
    }

    /**
     * Get challenge statistics.
     *
     * @return array
     */
    public function get_statistics(): array {
        global $wpdb;

        $challenges_table = $wpdb->prefix . 'sg_challenges';
        $entries_table = $wpdb->prefix . 'sg_challenge_entries';
        $votes_table = $wpdb->prefix . 'sg_challenge_votes';

        return array(
            'total_challenges'  => intval($wpdb->get_var("SELECT COUNT(*) FROM {$challenges_table}")),
            'active_challenges' => intval($wpdb->get_var("SELECT COUNT(*) FROM {$challenges_table} WHERE status = 'active'")),
            'total_entries'     => intval($wpdb->get_var("SELECT COUNT(*) FROM {$entries_table}")),
            'total_votes'       => intval($wpdb->get_var("SELECT COUNT(*) FROM {$votes_table}")),
            'unique_participants' => intval($wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM {$entries_table}")),
        );
    }

    /**
     * AJAX: Submit challenge entry.
     *
     * @return void
     */
    public function ajax_submit_entry(): void {
        check_ajax_referer('stylegenius_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Bitte melde dich an.', 'stylegenius-pro')));
        }

        $user_id = get_current_user_id();
        $challenge_id = isset($_POST['challenge_id']) ? absint($_POST['challenge_id']) : 0;

        if (!$challenge_id) {
            wp_send_json_error(array('message' => __('Keine Challenge-ID angegeben.', 'stylegenius-pro')));
        }

        $data = array(
            'image_id'   => isset($_POST['image_id']) ? absint($_POST['image_id']) : 0,
            'caption'    => isset($_POST['caption']) ? sanitize_textarea_field(wp_unslash($_POST['caption'])) : '',
            'items_used' => isset($_POST['items_used']) ? array_map('absint', (array) $_POST['items_used']) : array(),
        );

        $result = $this->submit_entry($user_id, $challenge_id, $data);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error(array('message' => $result['error']));
        }
    }

    /**
     * AJAX: Vote for challenge entry.
     *
     * @return void
     */
    public function ajax_vote_entry(): void {
        check_ajax_referer('stylegenius_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Bitte melde dich an.', 'stylegenius-pro')));
        }

        $user_id = get_current_user_id();
        $entry_id = isset($_POST['entry_id']) ? absint($_POST['entry_id']) : 0;

        if (!$entry_id) {
            wp_send_json_error(array('message' => __('Keine Beitrags-ID angegeben.', 'stylegenius-pro')));
        }

        $result = $this->vote($user_id, $entry_id);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error(array('message' => $result['error']));
        }
    }

    /**
     * Export user challenge data for GDPR.
     *
     * @param int $user_id User ID.
     * @return array
     */
    public function export_data(int $user_id): array {
        return array(
            'entries' => $this->get_user_entries($user_id, 1000),
        );
    }

    /**
     * Delete user challenge data (GDPR).
     *
     * @param int $user_id User ID.
     * @return bool
     */
    public function delete_data(int $user_id): bool {
        global $wpdb;

        $entries_table = $wpdb->prefix . 'sg_challenge_entries';
        $votes_table = $wpdb->prefix . 'sg_challenge_votes';

        // Delete votes by user
        $wpdb->delete(
            $votes_table,
            array('voter_id' => $user_id),
            array('%d')
        );

        // Get entry IDs and images
        $entries = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, image_id FROM {$entries_table} WHERE user_id = %d",
                $user_id
            )
        );

        foreach ($entries as $entry) {
            // Delete votes for this entry
            $wpdb->delete(
                $votes_table,
                array('entry_id' => $entry->id),
                array('%d')
            );

            // Delete image
            if ($entry->image_id) {
                wp_delete_attachment($entry->image_id, true);
            }
        }

        // Delete entries
        $wpdb->delete(
            $entries_table,
            array('user_id' => $user_id),
            array('%d')
        );

        return true;
    }
}
