<?php
/**
 * StyleGenius Chat Class
 *
 * Handles the AI styling chat functionality.
 *
 * @package    StyleGenius_Pro
 * @subpackage StyleGenius_Pro/includes/features
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * AI Chat management class.
 */
class StyleGenius_Chat {

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
     * User instance.
     *
     * @var StyleGenius_User
     */
    private $user;

    /**
     * Maximum context messages.
     *
     * @var int
     */
    private $max_context_messages = 10;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->db = new StyleGenius_Database();
        $this->ai = new StyleGenius_AI_Manager();
        $this->points = new StyleGenius_Points();
        $this->user = new StyleGenius_User();
    }

    /**
     * Send a message and get AI response.
     *
     * @param int         $user_id User ID.
     * @param string      $message User message.
     * @param string|null $session Session ID.
     * @return array Response data.
     */
    public function send_message(int $user_id, string $message, ?string $session = null): array {
        // Check usage limits
        if (!$this->user->can_use_ai($user_id)) {
            return array(
                'success' => false,
                'error'   => 'Dein monatliches Kontingent für KI-Anfragen ist aufgebraucht. Upgrade auf Premium für mehr Anfragen!',
                'upgrade' => true,
            );
        }

        // Sanitize message
        $message = sanitize_textarea_field($message);
        if (empty($message)) {
            return array(
                'success' => false,
                'error'   => 'Bitte gib eine Nachricht ein.',
            );
        }

        // Get or create session
        if (empty($session)) {
            $session = $this->create_session($user_id);
        }

        // Save user message
        $this->save_message($user_id, $session, 'user', $message);

        // Get conversation context
        $context = $this->get_conversation_context($user_id, $session);

        // Send to AI
        $response = $this->ai->chat($user_id, $message, array(
            'context'  => $context,
            'session'  => $session,
        ));

        if (!$response['success']) {
            return array(
                'success' => false,
                'error'   => $response['error'] ?? 'Es gab ein Problem bei der Verbindung mit dem Styling-Berater.',
            );
        }

        // Save assistant message
        $this->save_message($user_id, $session, 'assistant', $response['response']);

        // Track usage
        $this->user->track_ai_usage($user_id);

        // Award points
        $this->points->award_points($user_id, 'chat_message', 5, 'Styling-Beratung');

        // Check for follow-up suggestions
        $suggestions = $this->get_follow_up_suggestions($response['response']);

        return array(
            'success'     => true,
            'response'    => $response['response'],
            'session'     => $session,
            'suggestions' => $suggestions,
            'usage'       => $this->get_user_usage($user_id),
        );
    }

    /**
     * Send a message with image analysis.
     *
     * @param int         $user_id   User ID.
     * @param string      $message   User message.
     * @param array       $image_ids Attachment IDs.
     * @param string|null $session   Session ID.
     * @return array Response data.
     */
    public function send_message_with_images(int $user_id, string $message, array $image_ids, ?string $session = null): array {
        // Check VIP tier for image analysis
        $tier = get_user_meta($user_id, 'sg_tier', true) ?: 'free';

        if ('free' === $tier) {
            return array(
                'success' => false,
                'error'   => 'Foto-Analyse ist nur für Premium- und VIP-Mitglieder verfügbar.',
                'upgrade' => true,
            );
        }

        // Check usage limits
        if (!$this->user->can_use_ai($user_id)) {
            return array(
                'success' => false,
                'error'   => 'Dein monatliches Kontingent ist aufgebraucht.',
                'upgrade' => true,
            );
        }

        // Validate images
        $image_urls = array();
        foreach ($image_ids as $id) {
            $url = wp_get_attachment_url($id);
            if ($url) {
                $image_urls[] = $url;
            }
        }

        if (empty($image_urls)) {
            return array(
                'success' => false,
                'error'   => 'Keine gültigen Bilder gefunden.',
            );
        }

        // Get or create session
        if (empty($session)) {
            $session = $this->create_session($user_id);
        }

        // Save user message with image references
        $message_with_images = $message . "\n[Bilder: " . count($image_urls) . "]";
        $this->save_message($user_id, $session, 'user', $message_with_images, array('images' => $image_ids));

        // Get conversation context
        $context = $this->get_conversation_context($user_id, $session);

        // Send to AI with images
        $response = $this->ai->chat_with_vision($user_id, $message, $image_urls, array(
            'context' => $context,
            'session' => $session,
        ));

        if (!$response['success']) {
            return array(
                'success' => false,
                'error'   => $response['error'] ?? 'Fehler bei der Bildanalyse.',
            );
        }

        // Save assistant message
        $this->save_message($user_id, $session, 'assistant', $response['response']);

        // Track usage (counts as 2 for image analysis)
        $this->user->track_ai_usage($user_id, 2);

        // Award points
        $this->points->award_points($user_id, 'image_consultation', 15, 'Foto-Styling-Beratung');

        return array(
            'success'     => true,
            'response'    => $response['response'],
            'session'     => $session,
            'suggestions' => $this->get_follow_up_suggestions($response['response']),
            'usage'       => $this->get_user_usage($user_id),
        );
    }

    /**
     * Create a new chat session.
     *
     * @param int $user_id User ID.
     * @return string Session ID.
     */
    private function create_session(int $user_id): string {
        return 'sg_' . $user_id . '_' . wp_generate_uuid4();
    }

    /**
     * Save a message to history.
     *
     * @param int    $user_id  User ID.
     * @param string $session  Session ID.
     * @param string $role     Message role (user/assistant).
     * @param string $content  Message content.
     * @param array  $metadata Additional metadata.
     * @return int|false Insert ID or false.
     */
    private function save_message(int $user_id, string $session, string $role, string $content, array $metadata = array()) {
        global $wpdb;

        $table = $wpdb->prefix . 'sg_chat_history';

        $result = $wpdb->insert(
            $table,
            array(
                'user_id'    => $user_id,
                'session_id' => $session,
                'role'       => $role,
                'content'    => $content,
                'metadata'   => !empty($metadata) ? wp_json_encode($metadata) : null,
                'created_at' => current_time('mysql'),
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s')
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Get conversation context for AI.
     *
     * @param int    $user_id User ID.
     * @param string $session Session ID.
     * @return array Message history.
     */
    private function get_conversation_context(int $user_id, string $session): array {
        global $wpdb;

        $table = $wpdb->prefix . 'sg_chat_history';

        $messages = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT role, content
                FROM {$table}
                WHERE user_id = %d AND session_id = %s
                ORDER BY created_at DESC
                LIMIT %d",
                $user_id,
                $session,
                $this->max_context_messages
            ),
            ARRAY_A
        );

        // Reverse to get chronological order
        return array_reverse($messages);
    }

    /**
     * Get chat history for a user.
     *
     * @param int         $user_id User ID.
     * @param string|null $session Specific session or null for all.
     * @param int         $limit   Number of messages.
     * @return array
     */
    public function get_history(int $user_id, ?string $session = null, int $limit = 50): array {
        global $wpdb;

        $table = $wpdb->prefix . 'sg_chat_history';

        if ($session) {
            $messages = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, role, content, metadata, created_at
                    FROM {$table}
                    WHERE user_id = %d AND session_id = %s
                    ORDER BY created_at ASC
                    LIMIT %d",
                    $user_id,
                    $session,
                    $limit
                ),
                ARRAY_A
            );
        } else {
            $messages = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, session_id, role, content, metadata, created_at
                    FROM {$table}
                    WHERE user_id = %d
                    ORDER BY created_at DESC
                    LIMIT %d",
                    $user_id,
                    $limit
                ),
                ARRAY_A
            );
        }

        // Parse metadata
        foreach ($messages as &$message) {
            if (!empty($message['metadata'])) {
                $message['metadata'] = json_decode($message['metadata'], true);
            }
        }

        return $messages;
    }

    /**
     * Get user's chat sessions.
     *
     * @param int $user_id User ID.
     * @return array
     */
    public function get_sessions(int $user_id): array {
        global $wpdb;

        $table = $wpdb->prefix . 'sg_chat_history';

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    session_id,
                    MIN(created_at) as started_at,
                    MAX(created_at) as last_message_at,
                    COUNT(*) as message_count,
                    (SELECT content FROM {$table} h2
                     WHERE h2.session_id = h1.session_id
                     AND h2.role = 'user'
                     ORDER BY created_at ASC LIMIT 1) as first_message
                FROM {$table} h1
                WHERE user_id = %d
                GROUP BY session_id
                ORDER BY last_message_at DESC",
                $user_id
            ),
            ARRAY_A
        );
    }

    /**
     * Delete a chat session.
     *
     * @param int    $user_id User ID.
     * @param string $session Session ID.
     * @return bool
     */
    public function delete_session(int $user_id, string $session): bool {
        global $wpdb;

        $table = $wpdb->prefix . 'sg_chat_history';

        $result = $wpdb->delete(
            $table,
            array(
                'user_id'    => $user_id,
                'session_id' => $session,
            ),
            array('%d', '%s')
        );

        return false !== $result;
    }

    /**
     * Get follow-up suggestions based on response.
     *
     * @param string $response AI response.
     * @return array
     */
    private function get_follow_up_suggestions(string $response): array {
        $suggestions = array();

        // Analyze response content for relevant follow-ups
        $keywords = array(
            'farbe'     => 'Welche Farben passen am besten zu meinem Typ?',
            'outfit'    => 'Kannst du mir Outfit-Kombinationen vorschlagen?',
            'anlass'    => 'Was würdest du für einen formellen Anlass empfehlen?',
            'capsule'   => 'Wie erstelle ich eine Capsule Wardrobe?',
            'accessoire' => 'Welche Accessoires würden gut dazu passen?',
            'schuh'     => 'Welche Schuhe empfiehlst du zu diesem Stil?',
            'business'  => 'Wie kleide ich mich professionell im Büro?',
            'trend'     => 'Welche aktuellen Trends passen zu mir?',
        );

        $response_lower = strtolower($response);
        $added = 0;

        foreach ($keywords as $keyword => $suggestion) {
            if (strpos($response_lower, $keyword) !== false && $added < 3) {
                // Don't suggest what was just discussed
                if (strpos($response_lower, strtolower($suggestion)) === false) {
                    $suggestions[] = $suggestion;
                    $added++;
                }
            }
        }

        // Add default suggestions if none found
        if (empty($suggestions)) {
            $suggestions = array(
                'Zeig mir passende Outfit-Ideen',
                'Welche Accessoires empfiehlst du?',
                'Gibt es weitere Tipps für meinen Stil?',
            );
        }

        return $suggestions;
    }

    /**
     * Get user's AI usage stats.
     *
     * @param int $user_id User ID.
     * @return array
     */
    private function get_user_usage(int $user_id): array {
        $tier = get_user_meta($user_id, 'sg_tier', true) ?: 'free';
        $used = intval(get_user_meta($user_id, 'sg_monthly_ai_usage', true));

        $limits = array(
            'free'    => 10,
            'premium' => 100,
            'vip'     => -1, // unlimited
        );

        $limit = $limits[$tier] ?? 10;

        return array(
            'used'      => $used,
            'limit'     => $limit,
            'remaining' => $limit > 0 ? max(0, $limit - $used) : -1,
            'unlimited' => $limit < 0,
            'tier'      => $tier,
        );
    }

    /**
     * Get quick action responses.
     *
     * @param int    $user_id User ID.
     * @param string $action  Quick action type.
     * @return array
     */
    public function quick_action(int $user_id, string $action): array {
        $actions = array(
            'outfit_today' => 'Was soll ich heute anziehen? Berücksichtige das aktuelle Wetter und meine Termine.',
            'color_advice' => 'Welche Farben stehen mir am besten und warum?',
            'wardrobe_gap' => 'Analysiere meine Garderobe - was fehlt mir noch für eine vielseitige Capsule Wardrobe?',
            'trend_check'  => 'Welche aktuellen Modetrends passen zu meinem Stil-Typ?',
            'event_outfit' => 'Ich brauche Outfit-Ideen für einen besonderen Anlass. Was empfiehlst du?',
            'style_tips'   => 'Gib mir drei konkrete Styling-Tipps, die ich sofort umsetzen kann.',
        );

        if (!isset($actions[$action])) {
            return array(
                'success' => false,
                'error'   => 'Unbekannte Aktion.',
            );
        }

        return $this->send_message($user_id, $actions[$action]);
    }

    /**
     * Export chat history for GDPR.
     *
     * @param int $user_id User ID.
     * @return array
     */
    public function export_history(int $user_id): array {
        global $wpdb;

        $table = $wpdb->prefix . 'sg_chat_history';

        $messages = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT session_id, role, content, created_at
                FROM {$table}
                WHERE user_id = %d
                ORDER BY created_at ASC",
                $user_id
            ),
            ARRAY_A
        );

        return $messages;
    }

    /**
     * Delete all chat history for user (GDPR).
     *
     * @param int $user_id User ID.
     * @return bool
     */
    public function delete_all_history(int $user_id): bool {
        global $wpdb;

        $table = $wpdb->prefix . 'sg_chat_history';

        $result = $wpdb->delete(
            $table,
            array('user_id' => $user_id),
            array('%d')
        );

        return false !== $result;
    }

    /**
     * Get chat statistics for admin.
     *
     * @return array
     */
    public function get_statistics(): array {
        global $wpdb;

        $table = $wpdb->prefix . 'sg_chat_history';

        $stats = array(
            'total_messages'   => 0,
            'total_sessions'   => 0,
            'messages_today'   => 0,
            'messages_week'    => 0,
            'avg_per_session'  => 0,
            'active_users'     => 0,
        );

        // Total messages
        $stats['total_messages'] = intval($wpdb->get_var(
            "SELECT COUNT(*) FROM {$table}"
        ));

        // Total sessions
        $stats['total_sessions'] = intval($wpdb->get_var(
            "SELECT COUNT(DISTINCT session_id) FROM {$table}"
        ));

        // Messages today
        $stats['messages_today'] = intval($wpdb->get_var(
            "SELECT COUNT(*) FROM {$table} WHERE DATE(created_at) = CURDATE()"
        ));

        // Messages this week
        $stats['messages_week'] = intval($wpdb->get_var(
            "SELECT COUNT(*) FROM {$table} WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)"
        ));

        // Average messages per session
        if ($stats['total_sessions'] > 0) {
            $stats['avg_per_session'] = round($stats['total_messages'] / $stats['total_sessions'], 1);
        }

        // Active users (users who chatted in last 30 days)
        $stats['active_users'] = intval($wpdb->get_var(
            "SELECT COUNT(DISTINCT user_id) FROM {$table}
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        ));

        return $stats;
    }

    /**
     * AJAX: Send message.
     */
    public function ajax_send_message(): void {
        check_ajax_referer('stylegenius_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Bitte melde dich an.', 'stylegenius-pro')));
        }

        $message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';
        $session = isset($_POST['session']) ? sanitize_text_field($_POST['session']) : null;

        if (empty($message)) {
            wp_send_json_error(array('message' => __('Nachricht darf nicht leer sein.', 'stylegenius-pro')));
        }

        $result = $this->send_message(get_current_user_id(), $message, $session);

        if (isset($result['error'])) {
            wp_send_json_error($result);
        }

        wp_send_json_success($result);
    }

    /**
     * AJAX: Get chat history.
     */
    public function ajax_get_history(): void {
        check_ajax_referer('stylegenius_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Bitte melde dich an.', 'stylegenius-pro')));
        }

        $session = isset($_POST['session']) ? sanitize_text_field($_POST['session']) : null;
        $history = $this->get_history(get_current_user_id(), $session);

        wp_send_json_success(array('history' => $history));
    }
}
