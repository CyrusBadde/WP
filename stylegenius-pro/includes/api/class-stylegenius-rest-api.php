<?php
/**
 * StyleGenius REST API Class
 *
 * Registers and handles all REST API endpoints.
 *
 * @package    StyleGenius_Pro
 * @subpackage StyleGenius_Pro/includes/api
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * REST API management class.
 */
class StyleGenius_REST_API {

    /**
     * API namespace.
     *
     * @var string
     */
    private $namespace = 'stylegenius/v1';

    /**
     * Constructor.
     */
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    /**
     * Register all REST routes.
     *
     * @return void
     */
    public function register_routes(): void {
        // User Profile
        $this->register_user_routes();

        // Quiz
        $this->register_quiz_routes();

        // Chat
        $this->register_chat_routes();

        // Wardrobe
        $this->register_wardrobe_routes();

        // Color Analysis
        $this->register_color_routes();

        // Capsule
        $this->register_capsule_routes();

        // Challenges
        $this->register_challenge_routes();

        // Gamification
        $this->register_gamification_routes();

        // Social
        $this->register_social_routes();
    }

    /**
     * Register user profile routes.
     *
     * @return void
     */
    private function register_user_routes(): void {
        // Get profile
        register_rest_route($this->namespace, '/user/profile', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'get_user_profile'),
            'permission_callback' => array($this, 'check_auth'),
        ));

        // Update profile
        register_rest_route($this->namespace, '/user/profile', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'update_user_profile'),
            'permission_callback' => array($this, 'check_auth'),
        ));

        // Get dashboard data
        register_rest_route($this->namespace, '/user/dashboard', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'get_dashboard'),
            'permission_callback' => array($this, 'check_auth'),
        ));

        // Export data (GDPR)
        register_rest_route($this->namespace, '/user/export', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'export_user_data'),
            'permission_callback' => array($this, 'check_auth'),
        ));
    }

    /**
     * Register quiz routes.
     *
     * @return void
     */
    private function register_quiz_routes(): void {
        // Get questions
        register_rest_route($this->namespace, '/quiz/questions', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'get_quiz_questions'),
            'permission_callback' => '__return_true',
        ));

        // Submit answers
        register_rest_route($this->namespace, '/quiz/submit', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'submit_quiz'),
            'permission_callback' => array($this, 'check_auth'),
        ));

        // Get result
        register_rest_route($this->namespace, '/quiz/result', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'get_quiz_result'),
            'permission_callback' => array($this, 'check_auth'),
        ));
    }

    /**
     * Register chat routes.
     *
     * @return void
     */
    private function register_chat_routes(): void {
        // Send message
        register_rest_route($this->namespace, '/chat/message', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'send_chat_message'),
            'permission_callback' => array($this, 'check_auth'),
        ));

        // Send message with image
        register_rest_route($this->namespace, '/chat/message-with-image', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'send_chat_message_with_image'),
            'permission_callback' => array($this, 'check_auth'),
        ));

        // Get history
        register_rest_route($this->namespace, '/chat/history', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'get_chat_history'),
            'permission_callback' => array($this, 'check_auth'),
        ));

        // Get sessions
        register_rest_route($this->namespace, '/chat/sessions', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'get_chat_sessions'),
            'permission_callback' => array($this, 'check_auth'),
        ));
    }

    /**
     * Register wardrobe routes.
     *
     * @return void
     */
    private function register_wardrobe_routes(): void {
        // Get items
        register_rest_route($this->namespace, '/wardrobe', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'get_wardrobe_items'),
            'permission_callback' => array($this, 'check_auth'),
        ));

        // Add item
        register_rest_route($this->namespace, '/wardrobe', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'add_wardrobe_item'),
            'permission_callback' => array($this, 'check_auth'),
        ));

        // Get single item
        register_rest_route($this->namespace, '/wardrobe/(?P<id>\d+)', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'get_wardrobe_item'),
            'permission_callback' => array($this, 'check_auth'),
        ));

        // Update item
        register_rest_route($this->namespace, '/wardrobe/(?P<id>\d+)', array(
            'methods'             => 'PUT',
            'callback'            => array($this, 'update_wardrobe_item'),
            'permission_callback' => array($this, 'check_auth'),
        ));

        // Delete item
        register_rest_route($this->namespace, '/wardrobe/(?P<id>\d+)', array(
            'methods'             => 'DELETE',
            'callback'            => array($this, 'delete_wardrobe_item'),
            'permission_callback' => array($this, 'check_auth'),
        ));

        // Get statistics
        register_rest_route($this->namespace, '/wardrobe/stats', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'get_wardrobe_stats'),
            'permission_callback' => array($this, 'check_auth'),
        ));

        // Suggest outfit
        register_rest_route($this->namespace, '/wardrobe/suggest-outfit', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'suggest_outfit'),
            'permission_callback' => array($this, 'check_auth'),
        ));
    }

    /**
     * Register color analysis routes.
     *
     * @return void
     */
    private function register_color_routes(): void {
        // Analyze
        register_rest_route($this->namespace, '/color/analyze', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'analyze_color'),
            'permission_callback' => array($this, 'check_auth'),
        ));

        // Get profile
        register_rest_route($this->namespace, '/color/profile', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'get_color_profile'),
            'permission_callback' => array($this, 'check_auth'),
        ));

        // Check color match
        register_rest_route($this->namespace, '/color/check', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'check_color_match'),
            'permission_callback' => array($this, 'check_auth'),
        ));
    }

    /**
     * Register capsule routes.
     *
     * @return void
     */
    private function register_capsule_routes(): void {
        // Generate
        register_rest_route($this->namespace, '/capsule/generate', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'generate_capsule'),
            'permission_callback' => array($this, 'check_auth'),
        ));

        // Get capsules
        register_rest_route($this->namespace, '/capsule', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'get_capsules'),
            'permission_callback' => array($this, 'check_auth'),
        ));

        // Get single capsule
        register_rest_route($this->namespace, '/capsule/(?P<id>\d+)', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'get_capsule'),
            'permission_callback' => array($this, 'check_auth'),
        ));

        // Get outfit of the day
        register_rest_route($this->namespace, '/capsule/ootd', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'get_ootd'),
            'permission_callback' => array($this, 'check_auth'),
        ));
    }

    /**
     * Register challenge routes.
     *
     * @return void
     */
    private function register_challenge_routes(): void {
        // Get active challenges
        register_rest_route($this->namespace, '/challenges', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'get_challenges'),
            'permission_callback' => '__return_true',
        ));

        // Get single challenge
        register_rest_route($this->namespace, '/challenges/(?P<id>\d+)', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'get_challenge'),
            'permission_callback' => '__return_true',
        ));

        // Submit entry
        register_rest_route($this->namespace, '/challenges/(?P<id>\d+)/submit', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'submit_challenge_entry'),
            'permission_callback' => array($this, 'check_auth'),
        ));

        // Vote
        register_rest_route($this->namespace, '/challenges/entries/(?P<id>\d+)/vote', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'vote_challenge_entry'),
            'permission_callback' => array($this, 'check_auth'),
        ));
    }

    /**
     * Register gamification routes.
     *
     * @return void
     */
    private function register_gamification_routes(): void {
        // Get points
        register_rest_route($this->namespace, '/gamification/points', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'get_points'),
            'permission_callback' => array($this, 'check_auth'),
        ));

        // Get badges
        register_rest_route($this->namespace, '/gamification/badges', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'get_badges'),
            'permission_callback' => array($this, 'check_auth'),
        ));

        // Get leaderboard
        register_rest_route($this->namespace, '/gamification/leaderboard', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'get_leaderboard'),
            'permission_callback' => '__return_true',
        ));

        // Get streak
        register_rest_route($this->namespace, '/gamification/streak', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'get_streak'),
            'permission_callback' => array($this, 'check_auth'),
        ));

        // Record activity
        register_rest_route($this->namespace, '/gamification/activity', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'record_activity'),
            'permission_callback' => array($this, 'check_auth'),
        ));
    }

    /**
     * Register social routes.
     *
     * @return void
     */
    private function register_social_routes(): void {
        // Get share links
        register_rest_route($this->namespace, '/social/share-links', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'get_share_links'),
            'permission_callback' => array($this, 'check_auth'),
        ));

        // Track share
        register_rest_route($this->namespace, '/social/track-share', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'track_share'),
            'permission_callback' => array($this, 'check_auth'),
        ));

        // Get referral info
        register_rest_route($this->namespace, '/referral', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'get_referral_info'),
            'permission_callback' => array($this, 'check_auth'),
        ));
    }

    // ===== Permission Callbacks =====

    /**
     * Check if user is authenticated.
     *
     * @return bool|WP_Error
     */
    public function check_auth() {
        if (!is_user_logged_in()) {
            return new WP_Error(
                'rest_not_logged_in',
                'Du musst angemeldet sein.',
                array('status' => 401)
            );
        }
        return true;
    }

    /**
     * Check if user is admin.
     *
     * @return bool|WP_Error
     */
    public function check_admin() {
        if (!current_user_can('manage_options')) {
            return new WP_Error(
                'rest_forbidden',
                'Du hast keine Berechtigung für diese Aktion.',
                array('status' => 403)
            );
        }
        return true;
    }

    // ===== User Endpoints =====

    /**
     * Get user profile.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_user_profile(WP_REST_Request $request): WP_REST_Response {
        $user_id = get_current_user_id();
        $user_class = new StyleGenius_User();

        return new WP_REST_Response(array(
            'success' => true,
            'data'    => $user_class->get_profile($user_id),
        ));
    }

    /**
     * Update user profile.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function update_user_profile(WP_REST_Request $request): WP_REST_Response {
        $user_id = get_current_user_id();
        $user_class = new StyleGenius_User();
        $data = $request->get_json_params();

        $result = $user_class->update_profile($user_id, $data);

        return new WP_REST_Response($result);
    }

    /**
     * Get dashboard data.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_dashboard(WP_REST_Request $request): WP_REST_Response {
        $user_id = get_current_user_id();

        $user = new StyleGenius_User();
        $points = new StyleGenius_Points();
        $levels = new StyleGenius_Levels();
        $streaks = new StyleGenius_Streaks();
        $achievements = new StyleGenius_Achievements();

        return new WP_REST_Response(array(
            'success' => true,
            'data'    => array(
                'profile'      => $user->get_profile($user_id),
                'points'       => $points->get_user_points($user_id),
                'level'        => $levels->get_user_level($user_id),
                'streak'       => $streaks->get_user_streak($user_id),
                'achievements' => $achievements->get_user_achievements($user_id),
            ),
        ));
    }

    /**
     * Export user data.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function export_user_data(WP_REST_Request $request): WP_REST_Response {
        $user_id = get_current_user_id();
        $privacy = new StyleGenius_Privacy();

        return new WP_REST_Response(array(
            'success' => true,
            'data'    => $privacy->export_user_data($user_id),
        ));
    }

    // ===== Quiz Endpoints =====

    /**
     * Get quiz questions.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_quiz_questions(WP_REST_Request $request): WP_REST_Response {
        $quiz = new StyleGenius_Quiz();

        return new WP_REST_Response(array(
            'success'   => true,
            'questions' => $quiz->get_questions(),
            'types'     => $quiz->get_style_types(),
        ));
    }

    /**
     * Submit quiz answers.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function submit_quiz(WP_REST_Request $request): WP_REST_Response {
        $user_id = get_current_user_id();
        $quiz = new StyleGenius_Quiz();
        $answers = $request->get_param('answers');

        $result = $quiz->submit_answers($user_id, $answers);

        return new WP_REST_Response($result);
    }

    /**
     * Get quiz result.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_quiz_result(WP_REST_Request $request): WP_REST_Response {
        $user_id = get_current_user_id();
        $quiz = new StyleGenius_Quiz();

        $result = $quiz->get_result($user_id);

        if (!$result) {
            return new WP_REST_Response(array(
                'success' => false,
                'error'   => 'Kein Quiz-Ergebnis vorhanden.',
            ), 404);
        }

        return new WP_REST_Response(array(
            'success' => true,
            'data'    => $result,
        ));
    }

    // ===== Chat Endpoints =====

    /**
     * Send chat message.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function send_chat_message(WP_REST_Request $request): WP_REST_Response {
        $user_id = get_current_user_id();
        $chat = new StyleGenius_Chat();

        $message = $request->get_param('message');
        $session = $request->get_param('session');

        $result = $chat->send_message($user_id, $message, $session);

        return new WP_REST_Response($result);
    }

    /**
     * Send chat message with image.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function send_chat_message_with_image(WP_REST_Request $request): WP_REST_Response {
        $user_id = get_current_user_id();
        $chat = new StyleGenius_Chat();

        $message = $request->get_param('message');
        $image_ids = $request->get_param('image_ids');
        $session = $request->get_param('session');

        $result = $chat->send_message_with_images($user_id, $message, $image_ids, $session);

        return new WP_REST_Response($result);
    }

    /**
     * Get chat history.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_chat_history(WP_REST_Request $request): WP_REST_Response {
        $user_id = get_current_user_id();
        $chat = new StyleGenius_Chat();

        $session = $request->get_param('session');
        $limit = $request->get_param('limit') ?: 50;

        return new WP_REST_Response(array(
            'success' => true,
            'data'    => $chat->get_history($user_id, $session, $limit),
        ));
    }

    /**
     * Get chat sessions.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_chat_sessions(WP_REST_Request $request): WP_REST_Response {
        $user_id = get_current_user_id();
        $chat = new StyleGenius_Chat();

        return new WP_REST_Response(array(
            'success' => true,
            'data'    => $chat->get_sessions($user_id),
        ));
    }

    // ===== Wardrobe Endpoints =====

    /**
     * Get wardrobe items.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_wardrobe_items(WP_REST_Request $request): WP_REST_Response {
        $user_id = get_current_user_id();
        $wardrobe = new StyleGenius_Wardrobe();

        $filters = array(
            'category'  => $request->get_param('category'),
            'season'    => $request->get_param('season'),
            'occasion'  => $request->get_param('occasion'),
            'favorites' => $request->get_param('favorites'),
            'search'    => $request->get_param('search'),
            'sort'      => $request->get_param('sort'),
            'limit'     => $request->get_param('limit') ?: 50,
            'offset'    => $request->get_param('offset') ?: 0,
        );

        return new WP_REST_Response(array(
            'success' => true,
            'data'    => $wardrobe->get_items($user_id, array_filter($filters)),
        ));
    }

    /**
     * Add wardrobe item.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function add_wardrobe_item(WP_REST_Request $request): WP_REST_Response {
        $user_id = get_current_user_id();
        $wardrobe = new StyleGenius_Wardrobe();

        $data = $request->get_json_params();
        $result = $wardrobe->add_item($user_id, $data);

        return new WP_REST_Response($result);
    }

    /**
     * Get single wardrobe item.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_wardrobe_item(WP_REST_Request $request): WP_REST_Response {
        $user_id = get_current_user_id();
        $item_id = intval($request->get_param('id'));
        $wardrobe = new StyleGenius_Wardrobe();

        $item = $wardrobe->get_item($item_id, $user_id);

        if (!$item) {
            return new WP_REST_Response(array(
                'success' => false,
                'error'   => 'Item nicht gefunden.',
            ), 404);
        }

        return new WP_REST_Response(array(
            'success' => true,
            'data'    => $item,
        ));
    }

    /**
     * Update wardrobe item.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function update_wardrobe_item(WP_REST_Request $request): WP_REST_Response {
        $user_id = get_current_user_id();
        $item_id = intval($request->get_param('id'));
        $wardrobe = new StyleGenius_Wardrobe();

        $data = $request->get_json_params();
        $result = $wardrobe->update_item($item_id, $user_id, $data);

        return new WP_REST_Response($result);
    }

    /**
     * Delete wardrobe item.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function delete_wardrobe_item(WP_REST_Request $request): WP_REST_Response {
        $user_id = get_current_user_id();
        $item_id = intval($request->get_param('id'));
        $wardrobe = new StyleGenius_Wardrobe();

        $result = $wardrobe->delete_item($item_id, $user_id);

        return new WP_REST_Response($result);
    }

    /**
     * Get wardrobe statistics.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_wardrobe_stats(WP_REST_Request $request): WP_REST_Response {
        $user_id = get_current_user_id();
        $wardrobe = new StyleGenius_Wardrobe();

        return new WP_REST_Response(array(
            'success' => true,
            'data'    => $wardrobe->get_statistics($user_id),
        ));
    }

    /**
     * Suggest outfit.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function suggest_outfit(WP_REST_Request $request): WP_REST_Response {
        $user_id = get_current_user_id();
        $wardrobe = new StyleGenius_Wardrobe();

        $options = array(
            'occasion' => $request->get_param('occasion'),
            'season'   => $request->get_param('season'),
        );

        $result = $wardrobe->suggest_outfit($user_id, array_filter($options));

        return new WP_REST_Response($result);
    }

    // ===== Color Endpoints =====

    /**
     * Analyze color type.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function analyze_color(WP_REST_Request $request): WP_REST_Response {
        $user_id = get_current_user_id();
        $color = new StyleGenius_Color_Analysis();

        $image_id = $request->get_param('image_id');
        $result = $color->analyze($user_id, $image_id);

        return new WP_REST_Response($result);
    }

    /**
     * Get color profile.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_color_profile(WP_REST_Request $request): WP_REST_Response {
        $user_id = get_current_user_id();
        $color = new StyleGenius_Color_Analysis();

        $profile = $color->get_profile($user_id);

        if (!$profile) {
            return new WP_REST_Response(array(
                'success' => false,
                'error'   => 'Kein Farbprofil vorhanden.',
            ), 404);
        }

        return new WP_REST_Response(array(
            'success' => true,
            'data'    => $profile,
        ));
    }

    /**
     * Check color match.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function check_color_match(WP_REST_Request $request): WP_REST_Response {
        $user_id = get_current_user_id();
        $color = new StyleGenius_Color_Analysis();

        $hex = $request->get_param('color');
        $result = $color->check_color_match($user_id, $hex);

        return new WP_REST_Response($result);
    }

    // ===== Capsule Endpoints =====

    /**
     * Generate capsule.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function generate_capsule(WP_REST_Request $request): WP_REST_Response {
        $user_id = get_current_user_id();
        $capsule = new StyleGenius_Capsule();

        $options = array(
            'preset' => $request->get_param('preset'),
            'season' => $request->get_param('season'),
        );

        $result = $capsule->generate($user_id, array_filter($options));

        return new WP_REST_Response($result);
    }

    /**
     * Get capsules.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_capsules(WP_REST_Request $request): WP_REST_Response {
        $user_id = get_current_user_id();
        $capsule = new StyleGenius_Capsule();

        return new WP_REST_Response(array(
            'success' => true,
            'data'    => $capsule->get_capsules($user_id),
        ));
    }

    /**
     * Get single capsule.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_capsule(WP_REST_Request $request): WP_REST_Response {
        $user_id = get_current_user_id();
        $capsule_id = intval($request->get_param('id'));
        $capsule = new StyleGenius_Capsule();

        $data = $capsule->get_capsule($capsule_id, $user_id);

        if (!$data) {
            return new WP_REST_Response(array(
                'success' => false,
                'error'   => 'Capsule nicht gefunden.',
            ), 404);
        }

        return new WP_REST_Response(array(
            'success' => true,
            'data'    => $data,
        ));
    }

    /**
     * Get outfit of the day.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_ootd(WP_REST_Request $request): WP_REST_Response {
        $user_id = get_current_user_id();
        $capsule_id = $request->get_param('capsule_id');
        $capsule = new StyleGenius_Capsule();

        $result = $capsule->get_outfit_of_the_day($user_id, $capsule_id ? intval($capsule_id) : null);

        return new WP_REST_Response($result);
    }

    // ===== Challenge Endpoints =====

    /**
     * Get challenges.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_challenges(WP_REST_Request $request): WP_REST_Response {
        $challenges = new StyleGenius_Challenges();
        $type = $request->get_param('type') ?: 'active';

        switch ($type) {
            case 'upcoming':
                $data = $challenges->get_upcoming_challenges();
                break;
            case 'past':
                $data = $challenges->get_past_challenges();
                break;
            case 'active':
            default:
                $data = $challenges->get_active_challenges();
        }

        return new WP_REST_Response(array(
            'success' => true,
            'data'    => $data,
        ));
    }

    /**
     * Get single challenge.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_challenge(WP_REST_Request $request): WP_REST_Response {
        $challenge_id = intval($request->get_param('id'));
        $challenges = new StyleGenius_Challenges();

        $data = $challenges->get_challenge($challenge_id);

        if (!$data) {
            return new WP_REST_Response(array(
                'success' => false,
                'error'   => 'Challenge nicht gefunden.',
            ), 404);
        }

        return new WP_REST_Response(array(
            'success' => true,
            'data'    => $data,
        ));
    }

    /**
     * Submit challenge entry.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function submit_challenge_entry(WP_REST_Request $request): WP_REST_Response {
        $user_id = get_current_user_id();
        $challenge_id = intval($request->get_param('id'));
        $challenges = new StyleGenius_Challenges();

        $data = $request->get_json_params();
        $result = $challenges->submit_entry($user_id, $challenge_id, $data);

        return new WP_REST_Response($result);
    }

    /**
     * Vote for challenge entry.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function vote_challenge_entry(WP_REST_Request $request): WP_REST_Response {
        $user_id = get_current_user_id();
        $entry_id = intval($request->get_param('id'));
        $challenges = new StyleGenius_Challenges();

        $result = $challenges->vote($user_id, $entry_id);

        return new WP_REST_Response($result);
    }

    // ===== Gamification Endpoints =====

    /**
     * Get points.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_points(WP_REST_Request $request): WP_REST_Response {
        $user_id = get_current_user_id();
        $points = new StyleGenius_Points();

        return new WP_REST_Response(array(
            'success' => true,
            'data'    => array(
                'total'   => $points->get_user_points($user_id),
                'history' => $points->get_points_history($user_id, 20),
            ),
        ));
    }

    /**
     * Get badges.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_badges(WP_REST_Request $request): WP_REST_Response {
        $user_id = get_current_user_id();
        $achievements = new StyleGenius_Achievements();

        return new WP_REST_Response(array(
            'success' => true,
            'data'    => array(
                'earned'    => $achievements->get_user_achievements($user_id),
                'available' => $achievements->get_available_achievements($user_id),
                'progress'  => $achievements->get_progress($user_id),
            ),
        ));
    }

    /**
     * Get leaderboard.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_leaderboard(WP_REST_Request $request): WP_REST_Response {
        $leaderboard = new StyleGenius_Leaderboard();

        $type = $request->get_param('type') ?: 'points';
        $period = $request->get_param('period') ?: 'all';
        $limit = $request->get_param('limit') ?: 10;

        return new WP_REST_Response(array(
            'success' => true,
            'data'    => $leaderboard->get_leaderboard($type, $period, $limit),
        ));
    }

    /**
     * Get streak.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_streak(WP_REST_Request $request): WP_REST_Response {
        $user_id = get_current_user_id();
        $streaks = new StyleGenius_Streaks();

        return new WP_REST_Response(array(
            'success' => true,
            'data'    => $streaks->get_user_streak($user_id),
        ));
    }

    /**
     * Record activity.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function record_activity(WP_REST_Request $request): WP_REST_Response {
        $user_id = get_current_user_id();
        $streaks = new StyleGenius_Streaks();

        $result = $streaks->record_activity($user_id);

        return new WP_REST_Response(array(
            'success' => true,
            'data'    => $result,
        ));
    }

    // ===== Social Endpoints =====

    /**
     * Get share links.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_share_links(WP_REST_Request $request): WP_REST_Response {
        $sharing = new StyleGenius_Sharing();

        $content_type = $request->get_param('content_type');
        $content_id = $request->get_param('content_id');
        $data = $request->get_param('data') ?: array();

        $links = $sharing->generate_share_links($content_type, $content_id, $data);

        return new WP_REST_Response(array(
            'success' => true,
            'data'    => $links,
        ));
    }

    /**
     * Track share.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function track_share(WP_REST_Request $request): WP_REST_Response {
        $user_id = get_current_user_id();
        $sharing = new StyleGenius_Sharing();

        $platform = $request->get_param('platform');
        $content_type = $request->get_param('content_type');
        $content_id = $request->get_param('content_id');

        $result = $sharing->track_share($user_id, $platform, $content_type, $content_id);

        return new WP_REST_Response(array(
            'success' => $result,
        ));
    }

    /**
     * Get referral info.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_referral_info(WP_REST_Request $request): WP_REST_Response {
        $user_id = get_current_user_id();
        $referral = new StyleGenius_Referral();

        return new WP_REST_Response(array(
            'success' => true,
            'data'    => $referral->get_user_stats($user_id),
        ));
    }
}
