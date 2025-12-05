<?php
/**
 * StyleGenius OG Images Class
 *
 * Generates dynamic Open Graph images for social sharing.
 *
 * @package    StyleGenius_Pro
 * @subpackage StyleGenius_Pro/includes/social
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * OG Image generation class.
 */
class StyleGenius_OG_Images {

    /**
     * Image width.
     *
     * @var int
     */
    private $width = 1200;

    /**
     * Image height.
     *
     * @var int
     */
    private $height = 630;

    /**
     * Cache directory.
     *
     * @var string
     */
    private $cache_dir;

    /**
     * Cache URL.
     *
     * @var string
     */
    private $cache_url;

    /**
     * Constructor.
     */
    public function __construct() {
        $upload_dir = wp_upload_dir();
        $this->cache_dir = $upload_dir['basedir'] . '/stylegenius/og-images/';
        $this->cache_url = $upload_dir['baseurl'] . '/stylegenius/og-images/';

        // Ensure directory exists
        if (!file_exists($this->cache_dir)) {
            wp_mkdir_p($this->cache_dir);
        }
    }

    /**
     * Generate OG image for content.
     *
     * @param string $type       Content type.
     * @param int    $content_id Content ID.
     * @param array  $data       Additional data.
     * @return string|null Image URL.
     */
    public function generate(string $type, int $content_id, array $data = array()): ?string {
        // Check cache
        $cache_key = $this->get_cache_key($type, $content_id, $data);
        $cached = $this->get_cached($cache_key);

        if ($cached) {
            return $cached;
        }

        // Generate based on type
        $image_path = null;

        switch ($type) {
            case 'quiz_result':
                $image_path = $this->generate_quiz_result($content_id, $data);
                break;

            case 'color_profile':
                $image_path = $this->generate_color_profile($content_id, $data);
                break;

            case 'badge':
                $image_path = $this->generate_badge($content_id, $data);
                break;

            case 'level':
                $image_path = $this->generate_level($content_id, $data);
                break;

            case 'streak':
                $image_path = $this->generate_streak($content_id, $data);
                break;

            case 'challenge':
                $image_path = $this->generate_challenge($content_id, $data);
                break;

            case 'capsule':
                $image_path = $this->generate_capsule($content_id, $data);
                break;

            default:
                $image_path = $this->generate_default($data);
        }

        if (!$image_path) {
            return null;
        }

        return $this->cache_url . basename($image_path);
    }

    /**
     * Get cache key.
     *
     * @param string $type       Content type.
     * @param int    $content_id Content ID.
     * @param array  $data       Additional data.
     * @return string
     */
    private function get_cache_key(string $type, int $content_id, array $data): string {
        $hash = md5(serialize(array($type, $content_id, $data)));
        return "{$type}_{$content_id}_{$hash}";
    }

    /**
     * Get cached image if exists.
     *
     * @param string $cache_key Cache key.
     * @return string|null Image URL.
     */
    private function get_cached(string $cache_key): ?string {
        $file_path = $this->cache_dir . $cache_key . '.png';

        if (file_exists($file_path)) {
            // Check if not too old (7 days)
            if (filemtime($file_path) > strtotime('-7 days')) {
                return $this->cache_url . $cache_key . '.png';
            }
        }

        return null;
    }

    /**
     * Create base image.
     *
     * @param string $primary_color   Primary color hex.
     * @param string $secondary_color Secondary color hex.
     * @return resource|false GD image resource.
     */
    private function create_base_image(string $primary_color = '#1a1a2e', string $secondary_color = '#16213e') {
        if (!extension_loaded('gd')) {
            return false;
        }

        $image = imagecreatetruecolor($this->width, $this->height);

        // Create gradient background
        $primary = $this->hex_to_rgb($primary_color);
        $secondary = $this->hex_to_rgb($secondary_color);

        for ($y = 0; $y < $this->height; $y++) {
            $ratio = $y / $this->height;

            $r = intval($primary['r'] + ($secondary['r'] - $primary['r']) * $ratio);
            $g = intval($primary['g'] + ($secondary['g'] - $primary['g']) * $ratio);
            $b = intval($primary['b'] + ($secondary['b'] - $primary['b']) * $ratio);

            $color = imagecolorallocate($image, $r, $g, $b);
            imageline($image, 0, $y, $this->width, $y, $color);
        }

        return $image;
    }

    /**
     * Add text to image.
     *
     * @param resource $image     GD image resource.
     * @param string   $text      Text to add.
     * @param int      $size      Font size.
     * @param int      $x         X position.
     * @param int      $y         Y position.
     * @param string   $color     Hex color.
     * @param string   $align     Text alignment (left, center, right).
     * @return void
     */
    private function add_text($image, string $text, int $size, int $x, int $y, string $color = '#ffffff', string $align = 'left'): void {
        $rgb = $this->hex_to_rgb($color);
        $text_color = imagecolorallocate($image, $rgb['r'], $rgb['g'], $rgb['b']);

        // Use built-in font as fallback
        $font = 5; // Largest built-in font

        // Calculate text width for alignment
        $text_width = imagefontwidth($font) * strlen($text);

        switch ($align) {
            case 'center':
                $x = ($this->width - $text_width) / 2;
                break;
            case 'right':
                $x = $this->width - $text_width - $x;
                break;
        }

        // Scale font size (built-in fonts are small)
        $scale = max(1, intval($size / 10));

        if ($scale > 1) {
            // For larger text, use multiple lines
            $this->add_large_text($image, $text, $scale, $x, $y, $text_color);
        } else {
            imagestring($image, $font, intval($x), intval($y), $text, $text_color);
        }
    }

    /**
     * Add large text using scaled approach.
     *
     * @param resource $image  GD image resource.
     * @param string   $text   Text to add.
     * @param int      $scale  Scale factor.
     * @param int      $x      X position.
     * @param int      $y      Y position.
     * @param int      $color  GD color.
     * @return void
     */
    private function add_large_text($image, string $text, int $scale, int $x, int $y, int $color): void {
        // Create temporary larger image for text
        $font = 5;
        $text_width = imagefontwidth($font) * strlen($text);
        $text_height = imagefontheight($font);

        $temp = imagecreatetruecolor($text_width, $text_height);
        $transparent = imagecolorallocatealpha($temp, 0, 0, 0, 127);
        imagefill($temp, 0, 0, $transparent);
        imagesavealpha($temp, true);

        // Write text on temp
        $temp_color = imagecolorallocate($temp, ($color >> 16) & 0xFF, ($color >> 8) & 0xFF, $color & 0xFF);
        imagestring($temp, $font, 0, 0, $text, $temp_color);

        // Scale and copy to main image
        $new_width = $text_width * $scale;
        $new_height = $text_height * $scale;

        imagecopyresampled(
            $image,
            $temp,
            intval($x),
            intval($y),
            0,
            0,
            $new_width,
            $new_height,
            $text_width,
            $text_height
        );

        imagedestroy($temp);
    }

    /**
     * Add logo to image.
     *
     * @param resource $image GD image resource.
     * @return void
     */
    private function add_logo($image): void {
        // Add text logo
        $this->add_text($image, 'StyleGenius Pro', 24, 50, $this->height - 60, '#ffffff');
    }

    /**
     * Save image.
     *
     * @param resource $image     GD image resource.
     * @param string   $cache_key Cache key.
     * @return string|null File path.
     */
    private function save_image($image, string $cache_key): ?string {
        $file_path = $this->cache_dir . $cache_key . '.png';

        if (imagepng($image, $file_path)) {
            imagedestroy($image);
            return $file_path;
        }

        imagedestroy($image);
        return null;
    }

    /**
     * Generate quiz result OG image.
     *
     * @param int   $user_id User ID.
     * @param array $data    Additional data.
     * @return string|null File path.
     */
    private function generate_quiz_result(int $user_id, array $data): ?string {
        $quiz = new StyleGenius_Quiz();
        $result = $quiz->get_result($user_id);

        if (!$result) {
            return null;
        }

        $style_type = $result['style_type'];
        $style_info = $result['style_details'];

        // Use style colors for gradient
        $colors = $style_info['colors'];
        $image = $this->create_base_image($colors[0], $colors[1]);

        if (!$image) {
            return null;
        }

        // Add title
        $this->add_text($image, 'Mein Style-Typ:', 20, 0, 150, '#ffffff', 'center');
        $this->add_text($image, strtoupper($style_info['name']), 48, 0, 220, '#ffffff', 'center');

        // Add description (shortened)
        $desc = mb_substr($style_info['description'], 0, 80) . '...';
        $this->add_text($image, $desc, 14, 0, 340, 'rgba(255,255,255,0.8)', 'center');

        // Add color swatches
        $swatch_size = 40;
        $swatch_y = 420;
        $swatch_start = ($this->width - (count($colors) * ($swatch_size + 10))) / 2;

        foreach ($colors as $i => $color) {
            $rgb = $this->hex_to_rgb($color);
            $swatch_color = imagecolorallocate($image, $rgb['r'], $rgb['g'], $rgb['b']);
            $x = $swatch_start + ($i * ($swatch_size + 10));
            imagefilledrectangle($image, intval($x), $swatch_y, intval($x) + $swatch_size, $swatch_y + $swatch_size, $swatch_color);
        }

        $this->add_logo($image);

        return $this->save_image($image, $this->get_cache_key('quiz_result', $user_id, $data));
    }

    /**
     * Generate color profile OG image.
     *
     * @param int   $user_id User ID.
     * @param array $data    Additional data.
     * @return string|null File path.
     */
    private function generate_color_profile(int $user_id, array $data): ?string {
        $color_analysis = new StyleGenius_Color_Analysis();
        $profile = $color_analysis->get_profile($user_id);

        if (!$profile) {
            return null;
        }

        $colors = $profile['color_palette']['primär'];
        $image = $this->create_base_image($colors[0], $colors[1] ?? $colors[0]);

        if (!$image) {
            return null;
        }

        // Add title
        $this->add_text($image, 'Mein Farbtyp:', 20, 0, 150, '#ffffff', 'center');
        $this->add_text($image, strtoupper($profile['name']), 48, 0, 220, '#ffffff', 'center');

        // Add metal recommendation
        $metal = $profile['metals'] === 'gold' ? 'Gold' : 'Silber';
        $this->add_text($image, 'Metall-Empfehlung: ' . $metal, 16, 0, 340, '#ffffff', 'center');

        // Add color palette
        $swatch_size = 50;
        $swatch_y = 400;
        $swatch_start = ($this->width - (count($colors) * ($swatch_size + 10))) / 2;

        foreach ($colors as $i => $color) {
            $rgb = $this->hex_to_rgb($color);
            $swatch_color = imagecolorallocate($image, $rgb['r'], $rgb['g'], $rgb['b']);
            $x = $swatch_start + ($i * ($swatch_size + 10));
            imagefilledrectangle($image, intval($x), $swatch_y, intval($x) + $swatch_size, $swatch_y + $swatch_size, $swatch_color);
        }

        $this->add_logo($image);

        return $this->save_image($image, $this->get_cache_key('color_profile', $user_id, $data));
    }

    /**
     * Generate badge OG image.
     *
     * @param int   $user_id User ID.
     * @param array $data    Badge data.
     * @return string|null File path.
     */
    private function generate_badge(int $user_id, array $data): ?string {
        $image = $this->create_base_image('#6c5ce7', '#a29bfe');

        if (!$image) {
            return null;
        }

        // Add badge icon placeholder
        $white = imagecolorallocate($image, 255, 255, 255);
        $center_x = $this->width / 2;
        imagefilledellipse($image, intval($center_x), 250, 150, 150, $white);

        // Add text
        $this->add_text($image, 'Badge freigeschaltet!', 24, 0, 380, '#ffffff', 'center');
        $this->add_text($image, $data['badge_name'] ?? 'Achievement', 32, 0, 430, '#ffffff', 'center');

        if (!empty($data['badge_description'])) {
            $this->add_text($image, $data['badge_description'], 14, 0, 490, 'rgba(255,255,255,0.8)', 'center');
        }

        $this->add_logo($image);

        return $this->save_image($image, $this->get_cache_key('badge', $user_id, $data));
    }

    /**
     * Generate level OG image.
     *
     * @param int   $user_id User ID.
     * @param array $data    Level data.
     * @return string|null File path.
     */
    private function generate_level(int $user_id, array $data): ?string {
        $level = $data['level'] ?? 1;

        // Color based on level
        $level_colors = array(
            1  => array('#667eea', '#764ba2'),
            2  => array('#11998e', '#38ef7d'),
            3  => array('#fc466b', '#3f5efb'),
            4  => array('#f857a6', '#ff5858'),
            5  => array('#4facfe', '#00f2fe'),
            6  => array('#43e97b', '#38f9d7'),
            7  => array('#fa709a', '#fee140'),
            8  => array('#a18cd1', '#fbc2eb'),
            9  => array('#ffecd2', '#fcb69f'),
            10 => array('#ffd700', '#ffa500'),
        );

        $colors = $level_colors[$level] ?? $level_colors[1];
        $image = $this->create_base_image($colors[0], $colors[1]);

        if (!$image) {
            return null;
        }

        // Add level number
        $this->add_text($image, 'Level', 24, 0, 180, '#ffffff', 'center');
        $this->add_text($image, (string) $level, 96, 0, 230, '#ffffff', 'center');

        // Add "erreicht" text
        $this->add_text($image, 'erreicht!', 24, 0, 400, '#ffffff', 'center');

        $this->add_logo($image);

        return $this->save_image($image, $this->get_cache_key('level', $user_id, $data));
    }

    /**
     * Generate streak OG image.
     *
     * @param int   $user_id User ID.
     * @param array $data    Streak data.
     * @return string|null File path.
     */
    private function generate_streak(int $user_id, array $data): ?string {
        $days = $data['days'] ?? 1;

        $image = $this->create_base_image('#ff9a00', '#ff6600');

        if (!$image) {
            return null;
        }

        // Add flame emoji placeholder (circle)
        $red = imagecolorallocate($image, 255, 100, 50);
        $center_x = $this->width / 2;
        imagefilledellipse($image, intval($center_x), 200, 100, 120, $red);

        // Add streak days
        $this->add_text($image, (string) $days, 72, 0, 300, '#ffffff', 'center');
        $this->add_text($image, 'Tage Streak!', 28, 0, 400, '#ffffff', 'center');

        $this->add_logo($image);

        return $this->save_image($image, $this->get_cache_key('streak', $user_id, $data));
    }

    /**
     * Generate challenge OG image.
     *
     * @param int   $challenge_id Challenge ID.
     * @param array $data         Challenge data.
     * @return string|null File path.
     */
    private function generate_challenge(int $challenge_id, array $data): ?string {
        $image = $this->create_base_image('#e91e63', '#9c27b0');

        if (!$image) {
            return null;
        }

        // Add challenge title
        $title = $data['title'] ?? 'Style Challenge';
        $this->add_text($image, 'Weekly Challenge', 20, 0, 180, 'rgba(255,255,255,0.8)', 'center');
        $this->add_text($image, $title, 36, 0, 250, '#ffffff', 'center');

        // Add call to action
        $this->add_text($image, 'Jetzt mitmachen!', 24, 0, 400, '#ffffff', 'center');

        $this->add_logo($image);

        return $this->save_image($image, $this->get_cache_key('challenge', $challenge_id, $data));
    }

    /**
     * Generate capsule OG image.
     *
     * @param int   $user_id User ID.
     * @param array $data    Capsule data.
     * @return string|null File path.
     */
    private function generate_capsule(int $user_id, array $data): ?string {
        $image = $this->create_base_image('#00b894', '#00cec9');

        if (!$image) {
            return null;
        }

        $name = $data['name'] ?? 'Capsule Wardrobe';
        $items = $data['item_count'] ?? 33;

        // Add title
        $this->add_text($image, 'Meine Capsule Wardrobe', 20, 0, 180, 'rgba(255,255,255,0.8)', 'center');
        $this->add_text($image, $name, 36, 0, 250, '#ffffff', 'center');

        // Add item count
        $this->add_text($image, $items . ' Teile', 48, 0, 350, '#ffffff', 'center');
        $this->add_text($image, 'perfekt kombiniert', 18, 0, 420, 'rgba(255,255,255,0.8)', 'center');

        $this->add_logo($image);

        return $this->save_image($image, $this->get_cache_key('capsule', $user_id, $data));
    }

    /**
     * Generate default OG image.
     *
     * @param array $data Optional data.
     * @return string|null File path.
     */
    private function generate_default(array $data): ?string {
        $image = $this->create_base_image('#1a1a2e', '#16213e');

        if (!$image) {
            return null;
        }

        // Add site name
        $this->add_text($image, 'StyleGenius Pro', 48, 0, 250, '#ffffff', 'center');
        $this->add_text($image, 'Dein KI-Styling-Berater', 24, 0, 340, 'rgba(255,255,255,0.8)', 'center');

        return $this->save_image($image, 'default_' . md5(serialize($data)));
    }

    /**
     * Convert hex color to RGB.
     *
     * @param string $hex Hex color code.
     * @return array RGB values.
     */
    private function hex_to_rgb(string $hex): array {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        return array(
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2)),
        );
    }

    /**
     * Clear cached images.
     *
     * @param string|null $type Clear specific type or all.
     * @return int Number of files deleted.
     */
    public function clear_cache(?string $type = null): int {
        $count = 0;
        $pattern = $type ? $this->cache_dir . $type . '_*' : $this->cache_dir . '*';

        foreach (glob($pattern) as $file) {
            if (is_file($file) && unlink($file)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Get meta tags for OG image.
     *
     * @param string $image_url Image URL.
     * @param array  $data      Additional meta data.
     * @return string HTML meta tags.
     */
    public function get_meta_tags(string $image_url, array $data = array()): string {
        $tags = '';

        // Open Graph
        $tags .= sprintf('<meta property="og:image" content="%s">' . "\n", esc_url($image_url));
        $tags .= sprintf('<meta property="og:image:width" content="%d">' . "\n", $this->width);
        $tags .= sprintf('<meta property="og:image:height" content="%d">' . "\n", $this->height);

        if (!empty($data['title'])) {
            $tags .= sprintf('<meta property="og:title" content="%s">' . "\n", esc_attr($data['title']));
        }

        if (!empty($data['description'])) {
            $tags .= sprintf('<meta property="og:description" content="%s">' . "\n", esc_attr($data['description']));
        }

        // Twitter Card
        $tags .= '<meta name="twitter:card" content="summary_large_image">' . "\n";
        $tags .= sprintf('<meta name="twitter:image" content="%s">' . "\n", esc_url($image_url));

        return $tags;
    }
}
