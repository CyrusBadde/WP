<?php
/**
 * StyleGenius Before-After Class
 *
 * Handles Before-After transformation images and comparisons.
 *
 * @package    StyleGenius_Pro
 * @subpackage StyleGenius_Pro/includes/features
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Before-After transformation management class.
 */
class StyleGenius_Before_After {

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
     * Constructor.
     */
    public function __construct() {
        $this->db = new StyleGenius_Database();
        $this->ai = new StyleGenius_AI_Manager();
        $this->points = new StyleGenius_Points();
    }

    /**
     * Create before-after transformation.
     *
     * @param int   $user_id User ID.
     * @param array $data    Transformation data.
     * @return array Result.
     */
    public function create(int $user_id, array $data): array {
        global $wpdb;

        // Validate required fields
        if (empty($data['before_image_id']) || empty($data['after_image_id'])) {
            return array(
                'success' => false,
                'error'   => 'Beide Bilder (Vorher und Nachher) sind erforderlich.',
            );
        }

        // Validate images
        $before_url = wp_get_attachment_url($data['before_image_id']);
        $after_url = wp_get_attachment_url($data['after_image_id']);

        if (!$before_url || !$after_url) {
            return array(
                'success' => false,
                'error'   => 'Bilder nicht gefunden.',
            );
        }

        // Get AI analysis for VIP users
        $ai_analysis = null;
        $tier = get_user_meta($user_id, 'sg_tier', true) ?: 'free';

        if ($tier === 'vip' && !empty($data['analyze'])) {
            $ai_analysis = $this->analyze_transformation($user_id, $before_url, $after_url);
        }

        $table = $wpdb->prefix . 'sg_before_after';

        $result = $wpdb->insert(
            $table,
            array(
                'user_id'         => $user_id,
                'before_image_id' => intval($data['before_image_id']),
                'after_image_id'  => intval($data['after_image_id']),
                'title'           => sanitize_text_field($data['title'] ?? ''),
                'description'     => sanitize_textarea_field($data['description'] ?? ''),
                'occasion'        => sanitize_text_field($data['occasion'] ?? ''),
                'ai_analysis'     => $ai_analysis ? wp_json_encode($ai_analysis) : null,
                'is_public'       => isset($data['is_public']) ? 1 : 0,
                'created_at'      => current_time('mysql'),
            )
        );

        if (false === $result) {
            return array(
                'success' => false,
                'error'   => 'Fehler beim Speichern.',
            );
        }

        $id = $wpdb->insert_id;

        // Award points
        $this->points->award_points($user_id, 'before_after', 20, 'Vorher-Nachher erstellt');

        return array(
            'success'     => true,
            'id'          => $id,
            'ai_analysis' => $ai_analysis,
            'share_url'   => $this->get_share_url($id),
        );
    }

    /**
     * Analyze transformation with AI.
     *
     * @param int    $user_id    User ID.
     * @param string $before_url Before image URL.
     * @param string $after_url  After image URL.
     * @return array|null
     */
    private function analyze_transformation(int $user_id, string $before_url, string $after_url): ?array {
        $prompt = "Analysiere diese Styling-Transformation (Vorher-Nachher):

Beschreibe:
1. Die wichtigsten Veränderungen im Stil
2. Welche Verbesserungen wurden erzielt
3. Was funktioniert besonders gut
4. Welche weiteren Tipps würdest du geben";

        $response = $this->ai->chat_with_vision(
            $user_id,
            $prompt,
            array($before_url, $after_url),
            array('type' => 'before_after')
        );

        if (!$response['success']) {
            return null;
        }

        return array(
            'analysis'     => $response['response'],
            'generated_at' => current_time('mysql'),
        );
    }

    /**
     * Get user's transformations.
     *
     * @param int $user_id User ID.
     * @param int $limit   Number of results.
     * @return array
     */
    public function get_user_transformations(int $user_id, int $limit = 20): array {
        global $wpdb;

        $table = $wpdb->prefix . 'sg_before_after';

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                WHERE user_id = %d
                ORDER BY created_at DESC
                LIMIT %d",
                $user_id,
                $limit
            ),
            ARRAY_A
        );

        return array_map(array($this, 'format_transformation'), $results);
    }

    /**
     * Get public transformations.
     *
     * @param int $limit  Number of results.
     * @param int $offset Offset.
     * @return array
     */
    public function get_public_transformations(int $limit = 20, int $offset = 0): array {
        global $wpdb;

        $table = $wpdb->prefix . 'sg_before_after';

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                WHERE is_public = 1
                ORDER BY created_at DESC
                LIMIT %d OFFSET %d",
                $limit,
                $offset
            ),
            ARRAY_A
        );

        return array_map(array($this, 'format_transformation'), $results);
    }

    /**
     * Get single transformation.
     *
     * @param int      $id      Transformation ID.
     * @param int|null $user_id User ID for ownership check.
     * @return array|null
     */
    public function get_transformation(int $id, ?int $user_id = null): ?array {
        global $wpdb;

        $table = $wpdb->prefix . 'sg_before_after';

        $where = "id = %d";
        $params = array($id);

        // If user_id provided, check ownership or public status
        if ($user_id !== null) {
            $where .= " AND (user_id = %d OR is_public = 1)";
            $params[] = $user_id;
        } else {
            $where .= " AND is_public = 1";
        }

        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE {$where}",
                $params
            ),
            ARRAY_A
        );

        if (!$result) {
            return null;
        }

        return $this->format_transformation($result);
    }

    /**
     * Format transformation data.
     *
     * @param array $data Raw data.
     * @return array
     */
    private function format_transformation(array $data): array {
        $user = get_userdata($data['user_id']);

        return array(
            'id'               => intval($data['id']),
            'user_id'          => intval($data['user_id']),
            'user_name'        => $user ? $user->display_name : 'Unbekannt',
            'user_avatar'      => get_avatar_url($data['user_id'], array('size' => 64)),
            'before_image_id'  => intval($data['before_image_id']),
            'before_image_url' => wp_get_attachment_url($data['before_image_id']),
            'before_thumbnail' => wp_get_attachment_image_url($data['before_image_id'], 'medium'),
            'after_image_id'   => intval($data['after_image_id']),
            'after_image_url'  => wp_get_attachment_url($data['after_image_id']),
            'after_thumbnail'  => wp_get_attachment_image_url($data['after_image_id'], 'medium'),
            'title'            => $data['title'],
            'description'      => $data['description'],
            'occasion'         => $data['occasion'],
            'ai_analysis'      => $data['ai_analysis'] ? json_decode($data['ai_analysis'], true) : null,
            'is_public'        => (bool) $data['is_public'],
            'share_url'        => $this->get_share_url($data['id']),
            'created_at'       => $data['created_at'],
        );
    }

    /**
     * Update transformation.
     *
     * @param int   $id      Transformation ID.
     * @param int   $user_id User ID.
     * @param array $data    Updated data.
     * @return array Result.
     */
    public function update(int $id, int $user_id, array $data): array {
        global $wpdb;

        $table = $wpdb->prefix . 'sg_before_after';

        // Verify ownership
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE id = %d AND user_id = %d",
                $id,
                $user_id
            )
        );

        if (!$existing) {
            return array(
                'success' => false,
                'error'   => 'Transformation nicht gefunden.',
            );
        }

        $update_data = array();

        if (isset($data['title'])) {
            $update_data['title'] = sanitize_text_field($data['title']);
        }

        if (isset($data['description'])) {
            $update_data['description'] = sanitize_textarea_field($data['description']);
        }

        if (isset($data['occasion'])) {
            $update_data['occasion'] = sanitize_text_field($data['occasion']);
        }

        if (isset($data['is_public'])) {
            $update_data['is_public'] = $data['is_public'] ? 1 : 0;
        }

        if (empty($update_data)) {
            return array(
                'success' => false,
                'error'   => 'Keine Änderungen.',
            );
        }

        $wpdb->update($table, $update_data, array('id' => $id));

        return array(
            'success' => true,
            'data'    => $this->get_transformation($id, $user_id),
        );
    }

    /**
     * Delete transformation.
     *
     * @param int $id      Transformation ID.
     * @param int $user_id User ID.
     * @return array Result.
     */
    public function delete(int $id, int $user_id): array {
        global $wpdb;

        $table = $wpdb->prefix . 'sg_before_after';

        // Get transformation for image IDs
        $transformation = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d AND user_id = %d",
                $id,
                $user_id
            )
        );

        if (!$transformation) {
            return array(
                'success' => false,
                'error'   => 'Transformation nicht gefunden.',
            );
        }

        // Delete record
        $wpdb->delete($table, array('id' => $id), array('%d'));

        // Optionally delete images
        if (!empty($transformation->before_image_id)) {
            wp_delete_attachment($transformation->before_image_id, true);
        }
        if (!empty($transformation->after_image_id)) {
            wp_delete_attachment($transformation->after_image_id, true);
        }

        return array('success' => true);
    }

    /**
     * Get share URL for transformation.
     *
     * @param int $id Transformation ID.
     * @return string
     */
    private function get_share_url(int $id): string {
        return add_query_arg(
            array('transformation' => $id),
            home_url('/vorher-nachher/')
        );
    }

    /**
     * Generate comparison image.
     *
     * @param int    $id     Transformation ID.
     * @param string $style  Comparison style (side-by-side, slider, fade).
     * @return string|null Image URL or base64.
     */
    public function generate_comparison_image(int $id, string $style = 'side-by-side'): ?string {
        $transformation = $this->get_transformation($id);

        if (!$transformation) {
            return null;
        }

        $before_path = get_attached_file($transformation['before_image_id']);
        $after_path = get_attached_file($transformation['after_image_id']);

        if (!$before_path || !$after_path) {
            return null;
        }

        // Get image dimensions
        $before_size = getimagesize($before_path);
        $after_size = getimagesize($after_path);

        if (!$before_size || !$after_size) {
            return null;
        }

        // Use GD to create comparison
        if (!extension_loaded('gd')) {
            return null;
        }

        $width = 800;
        $height = 600;

        $comparison = imagecreatetruecolor($width * 2 + 20, $height);

        // Background
        $bg_color = imagecolorallocate($comparison, 255, 255, 255);
        imagefill($comparison, 0, 0, $bg_color);

        // Load and resize images
        $before_img = $this->load_image($before_path);
        $after_img = $this->load_image($after_path);

        if ($before_img && $after_img) {
            // Resize and center before image
            $this->copy_resized_centered($comparison, $before_img, 0, 0, $width, $height);

            // Resize and center after image
            $this->copy_resized_centered($comparison, $after_img, $width + 20, 0, $width, $height);

            // Add labels
            $label_color = imagecolorallocate($comparison, 50, 50, 50);
            $label_bg = imagecolorallocatealpha($comparison, 255, 255, 255, 50);

            imagefilledrectangle($comparison, 10, 10, 100, 35, $label_bg);
            imagestring($comparison, 5, 20, 15, 'VORHER', $label_color);

            imagefilledrectangle($comparison, $width + 30, 10, $width + 120, 35, $label_bg);
            imagestring($comparison, 5, $width + 40, 15, 'NACHHER', $label_color);

            // Save to temp file
            $upload_dir = wp_upload_dir();
            $temp_file = $upload_dir['basedir'] . '/stylegenius/temp/comparison-' . $id . '.jpg';

            // Ensure directory exists
            wp_mkdir_p(dirname($temp_file));

            imagejpeg($comparison, $temp_file, 90);

            imagedestroy($comparison);
            imagedestroy($before_img);
            imagedestroy($after_img);

            return str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $temp_file);
        }

        return null;
    }

    /**
     * Load image from file.
     *
     * @param string $path File path.
     * @return resource|false
     */
    private function load_image(string $path) {
        $info = getimagesize($path);

        if (!$info) {
            return false;
        }

        switch ($info[2]) {
            case IMAGETYPE_JPEG:
                return imagecreatefromjpeg($path);
            case IMAGETYPE_PNG:
                return imagecreatefrompng($path);
            case IMAGETYPE_WEBP:
                return imagecreatefromwebp($path);
            default:
                return false;
        }
    }

    /**
     * Copy and resize image centered.
     *
     * @param resource $dst      Destination image.
     * @param resource $src      Source image.
     * @param int      $dst_x    Destination X.
     * @param int      $dst_y    Destination Y.
     * @param int      $dst_w    Destination width.
     * @param int      $dst_h    Destination height.
     * @return void
     */
    private function copy_resized_centered($dst, $src, int $dst_x, int $dst_y, int $dst_w, int $dst_h): void {
        $src_w = imagesx($src);
        $src_h = imagesy($src);

        // Calculate aspect ratios
        $src_ratio = $src_w / $src_h;
        $dst_ratio = $dst_w / $dst_h;

        if ($src_ratio > $dst_ratio) {
            // Source is wider
            $new_h = $dst_h;
            $new_w = intval($dst_h * $src_ratio);
            $new_x = $dst_x - intval(($new_w - $dst_w) / 2);
            $new_y = $dst_y;
        } else {
            // Source is taller
            $new_w = $dst_w;
            $new_h = intval($dst_w / $src_ratio);
            $new_x = $dst_x;
            $new_y = $dst_y - intval(($new_h - $dst_h) / 2);
        }

        imagecopyresampled(
            $dst,
            $src,
            max(0, $new_x),
            max(0, $new_y),
            0,
            0,
            min($dst_w + ($new_x < 0 ? abs($new_x) : 0), $new_w),
            min($dst_h + ($new_y < 0 ? abs($new_y) : 0), $new_h),
            $src_w,
            $src_h
        );
    }

    /**
     * Generate HTML for slider comparison.
     *
     * @param int $id Transformation ID.
     * @return string HTML.
     */
    public function get_slider_html(int $id): string {
        $transformation = $this->get_transformation($id);

        if (!$transformation) {
            return '';
        }

        $before_url = $transformation['before_image_url'];
        $after_url = $transformation['after_image_url'];

        return sprintf(
            '<div class="sg-before-after-slider" data-id="%d">
                <div class="sg-ba-container">
                    <div class="sg-ba-before" style="background-image: url(%s);">
                        <span class="sg-ba-label">Vorher</span>
                    </div>
                    <div class="sg-ba-after" style="background-image: url(%s);">
                        <span class="sg-ba-label">Nachher</span>
                    </div>
                    <div class="sg-ba-slider">
                        <div class="sg-ba-handle"></div>
                    </div>
                </div>
            </div>',
            $id,
            esc_url($before_url),
            esc_url($after_url)
        );
    }

    /**
     * Get statistics.
     *
     * @return array
     */
    public function get_statistics(): array {
        global $wpdb;

        $table = $wpdb->prefix . 'sg_before_after';

        return array(
            'total'        => intval($wpdb->get_var("SELECT COUNT(*) FROM {$table}")),
            'public'       => intval($wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE is_public = 1")),
            'this_week'    => intval($wpdb->get_var(
                "SELECT COUNT(*) FROM {$table}
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)"
            )),
            'unique_users' => intval($wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM {$table}")),
        );
    }

    /**
     * Export user data for GDPR.
     *
     * @param int $user_id User ID.
     * @return array
     */
    public function export_data(int $user_id): array {
        return $this->get_user_transformations($user_id, 1000);
    }

    /**
     * Delete user data (GDPR).
     *
     * @param int $user_id User ID.
     * @return bool
     */
    public function delete_data(int $user_id): bool {
        global $wpdb;

        $table = $wpdb->prefix . 'sg_before_after';

        // Get all image IDs
        $images = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT before_image_id, after_image_id FROM {$table}
                WHERE user_id = %d",
                $user_id
            )
        );

        // Delete records
        $wpdb->delete($table, array('user_id' => $user_id), array('%d'));

        // Delete images
        foreach ($images as $row) {
            if ($row->before_image_id) {
                wp_delete_attachment($row->before_image_id, true);
            }
            if ($row->after_image_id) {
                wp_delete_attachment($row->after_image_id, true);
            }
        }

        return true;
    }
}
