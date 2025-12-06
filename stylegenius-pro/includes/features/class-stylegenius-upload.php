<?php
/**
 * StyleGenius Upload Class
 *
 * Handles all file upload functionality with security and validation.
 *
 * @package    StyleGenius_Pro
 * @subpackage StyleGenius_Pro/includes/features
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Upload management class.
 */
class StyleGenius_Upload {

    /**
     * Allowed mime types.
     *
     * @var array
     */
    private $allowed_types = array(
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/heic',
        'image/heif',
    );

    /**
     * Maximum file size in bytes (10MB).
     *
     * @var int
     */
    private $max_file_size = 10485760;

    /**
     * Upload directory within wp-content/uploads.
     *
     * @var string
     */
    private $upload_subdir = 'stylegenius';

    /**
     * Constructor.
     */
    public function __construct() {
        add_filter('upload_mimes', array($this, 'add_heic_mime_type'));
    }

    /**
     * Add HEIC mime type support.
     *
     * @param array $mimes Allowed mime types.
     * @return array
     */
    public function add_heic_mime_type(array $mimes): array {
        $mimes['heic'] = 'image/heic';
        $mimes['heif'] = 'image/heif';
        return $mimes;
    }

    /**
     * Handle file upload.
     *
     * @param int    $user_id   User ID.
     * @param array  $file      $_FILES array item.
     * @param string $category  Upload category (wardrobe, selfie, outfit, etc.).
     * @param array  $metadata  Additional metadata.
     * @return array Upload result.
     */
    public function upload(int $user_id, array $file, string $category = 'general', array $metadata = array()): array {
        // Validate user
        if (!$user_id || !get_userdata($user_id)) {
            return array(
                'success' => false,
                'error'   => 'Ungültiger Benutzer.',
            );
        }

        // Check file upload errors
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            return array(
                'success' => false,
                'error'   => $this->get_upload_error_message($file['error'] ?? UPLOAD_ERR_NO_FILE),
            );
        }

        // Validate file size
        if ($file['size'] > $this->max_file_size) {
            return array(
                'success' => false,
                'error'   => sprintf(
                    'Die Datei ist zu groß. Maximum: %s MB.',
                    round($this->max_file_size / 1048576, 1)
                ),
            );
        }

        // Validate mime type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime_type, $this->allowed_types, true)) {
            return array(
                'success' => false,
                'error'   => 'Dieser Dateityp ist nicht erlaubt. Erlaubt sind: JPEG, PNG, WebP, HEIC.',
            );
        }

        // Convert HEIC to JPEG if needed
        if (in_array($mime_type, array('image/heic', 'image/heif'), true)) {
            $converted = $this->convert_heic_to_jpeg($file['tmp_name']);
            if ($converted) {
                $file['tmp_name'] = $converted;
                $file['name'] = pathinfo($file['name'], PATHINFO_FILENAME) . '.jpg';
            }
        }

        // Generate unique filename
        $extension = $this->get_extension_from_mime($mime_type);
        $filename = $this->generate_filename($user_id, $category, $extension);

        // Prepare upload
        $upload_dir = wp_upload_dir();
        $target_dir = $upload_dir['basedir'] . '/' . $this->upload_subdir . '/' . $category . '/' . $user_id;

        // Create directory if needed
        if (!file_exists($target_dir)) {
            wp_mkdir_p($target_dir);
            // Add index.php for security
            file_put_contents($target_dir . '/index.php', '<?php // Silence is golden');
        }

        $target_path = $target_dir . '/' . $filename;
        $target_url = $upload_dir['baseurl'] . '/' . $this->upload_subdir . '/' . $category . '/' . $user_id . '/' . $filename;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $target_path)) {
            return array(
                'success' => false,
                'error'   => 'Fehler beim Speichern der Datei.',
            );
        }

        // Set permissions
        chmod($target_path, 0644);

        // Resize if too large
        $resized = $this->maybe_resize_image($target_path, 2048, 2048);

        // Get image dimensions
        $image_size = getimagesize($target_path);

        // Create WordPress attachment
        $attachment_data = array(
            'post_mime_type' => $mime_type === 'image/heic' ? 'image/jpeg' : $mime_type,
            'post_title'     => sanitize_file_name($file['name']),
            'post_content'   => '',
            'post_status'    => 'private',
            'post_author'    => $user_id,
        );

        $attachment_id = wp_insert_attachment($attachment_data, $target_path);

        if (is_wp_error($attachment_id)) {
            @unlink($target_path);
            return array(
                'success' => false,
                'error'   => 'Fehler beim Erstellen des Anhangs.',
            );
        }

        // Generate attachment metadata
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attach_data = wp_generate_attachment_metadata($attachment_id, $target_path);
        wp_update_attachment_metadata($attachment_id, $attach_data);

        // Store custom metadata
        update_post_meta($attachment_id, '_sg_category', $category);
        update_post_meta($attachment_id, '_sg_user_id', $user_id);
        update_post_meta($attachment_id, '_sg_uploaded_at', current_time('mysql'));

        if (!empty($metadata)) {
            update_post_meta($attachment_id, '_sg_metadata', $metadata);
        }

        // Create thumbnail
        $thumbnail_url = $this->create_thumbnail($attachment_id, $target_path);

        return array(
            'success'       => true,
            'attachment_id' => $attachment_id,
            'url'           => $target_url,
            'thumbnail_url' => $thumbnail_url,
            'filename'      => $filename,
            'width'         => $image_size[0] ?? 0,
            'height'        => $image_size[1] ?? 0,
            'size'          => filesize($target_path),
            'mime_type'     => $mime_type,
        );
    }

    /**
     * Handle multiple file uploads.
     *
     * @param int    $user_id  User ID.
     * @param array  $files    Array of $_FILES items.
     * @param string $category Upload category.
     * @return array Results for each file.
     */
    public function upload_multiple(int $user_id, array $files, string $category = 'general'): array {
        $results = array();

        foreach ($files as $file) {
            $results[] = $this->upload($user_id, $file, $category);
        }

        return array(
            'success'   => true,
            'results'   => $results,
            'uploaded'  => count(array_filter($results, fn($r) => $r['success'])),
            'failed'    => count(array_filter($results, fn($r) => !$r['success'])),
        );
    }

    /**
     * Get upload error message.
     *
     * @param int $error_code PHP upload error code.
     * @return string
     */
    private function get_upload_error_message(int $error_code): string {
        $messages = array(
            UPLOAD_ERR_INI_SIZE   => 'Die Datei überschreitet die maximale Größe.',
            UPLOAD_ERR_FORM_SIZE  => 'Die Datei überschreitet die maximale Größe.',
            UPLOAD_ERR_PARTIAL    => 'Die Datei wurde nur teilweise hochgeladen.',
            UPLOAD_ERR_NO_FILE    => 'Es wurde keine Datei hochgeladen.',
            UPLOAD_ERR_NO_TMP_DIR => 'Temporärer Ordner fehlt.',
            UPLOAD_ERR_CANT_WRITE => 'Fehler beim Schreiben der Datei.',
            UPLOAD_ERR_EXTENSION  => 'Upload wurde durch eine Erweiterung gestoppt.',
        );

        return $messages[$error_code] ?? 'Unbekannter Upload-Fehler.';
    }

    /**
     * Generate unique filename.
     *
     * @param int    $user_id   User ID.
     * @param string $category  Category.
     * @param string $extension File extension.
     * @return string
     */
    private function generate_filename(int $user_id, string $category, string $extension): string {
        $hash = substr(md5(uniqid($user_id . $category, true)), 0, 12);
        return sprintf('sg_%s_%s_%s.%s', $category, $user_id, $hash, $extension);
    }

    /**
     * Get file extension from mime type.
     *
     * @param string $mime Mime type.
     * @return string
     */
    private function get_extension_from_mime(string $mime): string {
        $map = array(
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/heic' => 'jpg', // Will be converted
            'image/heif' => 'jpg', // Will be converted
        );

        return $map[$mime] ?? 'jpg';
    }

    /**
     * Convert HEIC to JPEG.
     *
     * @param string $source_path Source file path.
     * @return string|false Converted file path or false.
     */
    private function convert_heic_to_jpeg(string $source_path) {
        // Try ImageMagick first
        if (extension_loaded('imagick')) {
            try {
                $imagick = new Imagick($source_path);
                $imagick->setImageFormat('jpeg');
                $imagick->setImageCompressionQuality(90);

                $new_path = $source_path . '.jpg';
                $imagick->writeImage($new_path);
                $imagick->clear();
                $imagick->destroy();

                @unlink($source_path);
                return $new_path;
            } catch (Exception $e) {
                // Fall through
            }
        }

        // Try command line convert
        if (function_exists('shell_exec')) {
            $new_path = $source_path . '.jpg';
            $command = sprintf(
                'convert %s %s 2>&1',
                escapeshellarg($source_path),
                escapeshellarg($new_path)
            );

            $output = shell_exec($command);

            if (file_exists($new_path)) {
                @unlink($source_path);
                return $new_path;
            }
        }

        return false;
    }

    /**
     * Resize image if too large.
     *
     * @param string $file_path  File path.
     * @param int    $max_width  Maximum width.
     * @param int    $max_height Maximum height.
     * @return bool Whether resized.
     */
    private function maybe_resize_image(string $file_path, int $max_width, int $max_height): bool {
        $editor = wp_get_image_editor($file_path);

        if (is_wp_error($editor)) {
            return false;
        }

        $size = $editor->get_size();

        if ($size['width'] <= $max_width && $size['height'] <= $max_height) {
            return false;
        }

        $editor->resize($max_width, $max_height, false);
        $editor->set_quality(90);
        $result = $editor->save($file_path);

        return !is_wp_error($result);
    }

    /**
     * Create thumbnail for image.
     *
     * @param int    $attachment_id Attachment ID.
     * @param string $file_path     Original file path.
     * @return string|null Thumbnail URL.
     */
    private function create_thumbnail(int $attachment_id, string $file_path): ?string {
        $thumbnail = wp_get_attachment_image_src($attachment_id, 'thumbnail');

        if ($thumbnail) {
            return $thumbnail[0];
        }

        // Generate manually if needed
        $editor = wp_get_image_editor($file_path);

        if (is_wp_error($editor)) {
            return null;
        }

        $editor->resize(150, 150, true);
        $thumb_info = pathinfo($file_path);
        $thumb_path = $thumb_info['dirname'] . '/' . $thumb_info['filename'] . '-150x150.' . $thumb_info['extension'];

        $result = $editor->save($thumb_path);

        if (is_wp_error($result)) {
            return null;
        }

        $upload_dir = wp_upload_dir();
        return str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $thumb_path);
    }

    /**
     * Delete an uploaded file.
     *
     * @param int $attachment_id Attachment ID.
     * @param int $user_id       User ID (for permission check).
     * @return bool
     */
    public function delete(int $attachment_id, int $user_id): bool {
        $attachment = get_post($attachment_id);

        if (!$attachment || $attachment->post_type !== 'attachment') {
            return false;
        }

        // Check ownership
        $owner = get_post_meta($attachment_id, '_sg_user_id', true);
        if (intval($owner) !== $user_id && $attachment->post_author !== $user_id) {
            return false;
        }

        return wp_delete_attachment($attachment_id, true);
    }

    /**
     * Get user's uploads.
     *
     * @param int         $user_id  User ID.
     * @param string|null $category Filter by category.
     * @param int         $limit    Number of results.
     * @return array
     */
    public function get_user_uploads(int $user_id, ?string $category = null, int $limit = 50): array {
        $args = array(
            'post_type'      => 'attachment',
            'post_status'    => 'any',
            'posts_per_page' => $limit,
            'meta_query'     => array(
                array(
                    'key'   => '_sg_user_id',
                    'value' => $user_id,
                    'type'  => 'NUMERIC',
                ),
            ),
            'orderby'        => 'date',
            'order'          => 'DESC',
        );

        if ($category) {
            $args['meta_query'][] = array(
                'key'   => '_sg_category',
                'value' => $category,
            );
        }

        $attachments = get_posts($args);
        $uploads = array();

        foreach ($attachments as $attachment) {
            $uploads[] = array(
                'id'           => $attachment->ID,
                'url'          => wp_get_attachment_url($attachment->ID),
                'thumbnail'    => wp_get_attachment_image_url($attachment->ID, 'thumbnail'),
                'medium'       => wp_get_attachment_image_url($attachment->ID, 'medium'),
                'category'     => get_post_meta($attachment->ID, '_sg_category', true),
                'metadata'     => get_post_meta($attachment->ID, '_sg_metadata', true),
                'uploaded_at'  => get_post_meta($attachment->ID, '_sg_uploaded_at', true),
            );
        }

        return $uploads;
    }

    /**
     * Get upload statistics for user.
     *
     * @param int $user_id User ID.
     * @return array
     */
    public function get_user_upload_stats(int $user_id): array {
        global $wpdb;

        $stats = array(
            'total'      => 0,
            'by_category' => array(),
            'total_size' => 0,
        );

        // Count by category
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT pm.meta_value as category, COUNT(*) as count
                FROM {$wpdb->postmeta} pm
                INNER JOIN {$wpdb->postmeta} pm2 ON pm.post_id = pm2.post_id
                WHERE pm.meta_key = '_sg_category'
                AND pm2.meta_key = '_sg_user_id'
                AND pm2.meta_value = %d
                GROUP BY pm.meta_value",
                $user_id
            ),
            ARRAY_A
        );

        foreach ($results as $row) {
            $stats['by_category'][$row['category']] = intval($row['count']);
            $stats['total'] += intval($row['count']);
        }

        return $stats;
    }

    /**
     * Clean up old temporary uploads.
     *
     * @param int $days_old Minimum age in days.
     * @return int Number of files cleaned.
     */
    public function cleanup_old_uploads(int $days_old = 30): int {
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/' . $this->upload_subdir . '/temp';

        if (!is_dir($temp_dir)) {
            return 0;
        }

        $count = 0;
        $threshold = time() - ($days_old * DAY_IN_SECONDS);

        $files = new DirectoryIterator($temp_dir);
        foreach ($files as $file) {
            if ($file->isDot() || $file->isDir()) {
                continue;
            }

            if ($file->getMTime() < $threshold) {
                @unlink($file->getPathname());
                $count++;
            }
        }

        return $count;
    }

    /**
     * Validate image for specific use case.
     *
     * @param int    $attachment_id Attachment ID.
     * @param string $purpose       Validation purpose (selfie, clothing, outfit).
     * @return array Validation result.
     */
    public function validate_for_purpose(int $attachment_id, string $purpose): array {
        $file_path = get_attached_file($attachment_id);

        if (!$file_path || !file_exists($file_path)) {
            return array(
                'valid' => false,
                'error' => 'Datei nicht gefunden.',
            );
        }

        $image_size = getimagesize($file_path);

        if (!$image_size) {
            return array(
                'valid' => false,
                'error' => 'Konnte Bild nicht lesen.',
            );
        }

        $requirements = array(
            'selfie' => array(
                'min_width'  => 400,
                'min_height' => 400,
                'max_ratio'  => 2.0,
            ),
            'clothing' => array(
                'min_width'  => 300,
                'min_height' => 300,
                'max_ratio'  => 3.0,
            ),
            'outfit' => array(
                'min_width'  => 400,
                'min_height' => 600,
                'max_ratio'  => 3.0,
            ),
        );

        $req = $requirements[$purpose] ?? $requirements['clothing'];

        // Check dimensions
        if ($image_size[0] < $req['min_width'] || $image_size[1] < $req['min_height']) {
            return array(
                'valid' => false,
                'error' => sprintf(
                    'Bild zu klein. Mindestens %dx%d Pixel benötigt.',
                    $req['min_width'],
                    $req['min_height']
                ),
            );
        }

        // Check aspect ratio
        $ratio = max($image_size[0], $image_size[1]) / min($image_size[0], $image_size[1]);
        if ($ratio > $req['max_ratio']) {
            return array(
                'valid' => false,
                'error' => 'Das Seitenverhältnis des Bildes ist zu extrem.',
            );
        }

        return array(
            'valid'  => true,
            'width'  => $image_size[0],
            'height' => $image_size[1],
            'ratio'  => round($ratio, 2),
        );
    }

    /**
     * AJAX: Handle upload.
     */
    public function ajax_handle_upload(): void {
        check_ajax_referer('stylegenius_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Bitte melde dich an.', 'stylegenius-pro')));
        }

        if (empty($_FILES['file'])) {
            wp_send_json_error(array('message' => __('Keine Datei hochgeladen.', 'stylegenius-pro')));
        }

        $category = isset($_POST['category']) ? sanitize_text_field($_POST['category']) : 'general';
        $result = $this->upload(get_current_user_id(), $_FILES['file'], $category);

        if (isset($result['error'])) {
            wp_send_json_error($result);
        }

        wp_send_json_success($result);
    }

    /**
     * AJAX: Delete upload.
     */
    public function ajax_delete_upload(): void {
        check_ajax_referer('stylegenius_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Bitte melde dich an.', 'stylegenius-pro')));
        }

        $attachment_id = isset($_POST['attachment_id']) ? absint($_POST['attachment_id']) : 0;
        if (!$attachment_id) {
            wp_send_json_error(array('message' => __('Ungültige Datei-ID.', 'stylegenius-pro')));
        }

        $result = $this->delete($attachment_id, get_current_user_id());
        if ($result) {
            wp_send_json_success(array('message' => __('Datei gelöscht.', 'stylegenius-pro')));
        } else {
            wp_send_json_error(array('message' => __('Fehler beim Löschen.', 'stylegenius-pro')));
        }
    }
}
