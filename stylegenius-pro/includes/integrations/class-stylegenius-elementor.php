<?php
/**
 * StyleGenius Pro Elementor Integration
 *
 * Provides Elementor widgets for StyleGenius features
 *
 * @package    StyleGenius_Pro
 * @subpackage StyleGenius_Pro/includes/integrations
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Elementor Integration Class
 */
class StyleGenius_Elementor {

    /**
     * Initialize hooks
     */
    public function init() {
        // Check if Elementor is active
        if (!$this->is_elementor_active()) {
            return;
        }

        // Register widgets
        add_action('elementor/widgets/register', array($this, 'register_widgets'));

        // Register widget categories
        add_action('elementor/elements/categories_registered', array($this, 'register_categories'));

        // Register controls
        add_action('elementor/controls/register', array($this, 'register_controls'));

        // Enqueue editor scripts
        add_action('elementor/editor/before_enqueue_scripts', array($this, 'enqueue_editor_scripts'));

        // Frontend scripts
        add_action('elementor/frontend/after_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));

        // Dynamic tags
        add_action('elementor/dynamic_tags/register', array($this, 'register_dynamic_tags'));
    }

    /**
     * Check if Elementor is active
     *
     * @return bool
     */
    public function is_elementor_active() {
        return did_action('elementor/loaded');
    }

    /**
     * Register widget category
     *
     * @param \Elementor\Elements_Manager $elements_manager Elements manager
     */
    public function register_categories($elements_manager) {
        $elements_manager->add_category(
            'stylegenius',
            array(
                'title' => __('StyleGenius', 'stylegenius-pro'),
                'icon' => 'eicon-star',
            )
        );
    }

    /**
     * Register widgets
     *
     * @param \Elementor\Widgets_Manager $widgets_manager Widgets manager
     */
    public function register_widgets($widgets_manager) {
        // Load widget base class
        require_once STYLEGENIUS_PLUGIN_PATH . 'includes/integrations/elementor/class-widget-base.php';

        // Load individual widgets
        $widgets = array(
            'quiz' => 'StyleGenius_Elementor_Quiz_Widget',
            'chat' => 'StyleGenius_Elementor_Chat_Widget',
            'wardrobe' => 'StyleGenius_Elementor_Wardrobe_Widget',
            'capsule' => 'StyleGenius_Elementor_Capsule_Widget',
            'challenges' => 'StyleGenius_Elementor_Challenges_Widget',
            'leaderboard' => 'StyleGenius_Elementor_Leaderboard_Widget',
            'dashboard' => 'StyleGenius_Elementor_Dashboard_Widget',
            'profile' => 'StyleGenius_Elementor_Profile_Widget',
            'progress' => 'StyleGenius_Elementor_Progress_Widget',
            'tier-comparison' => 'StyleGenius_Elementor_Tier_Widget',
            'referral' => 'StyleGenius_Elementor_Referral_Widget',
            'upload' => 'StyleGenius_Elementor_Upload_Widget',
        );

        foreach ($widgets as $widget_file => $widget_class) {
            $file_path = STYLEGENIUS_PLUGIN_PATH . "includes/integrations/elementor/widgets/class-{$widget_file}-widget.php";
            if (file_exists($file_path)) {
                require_once $file_path;
                if (class_exists($widget_class)) {
                    $widgets_manager->register(new $widget_class());
                }
            }
        }
    }

    /**
     * Register custom controls
     *
     * @param \Elementor\Controls_Manager $controls_manager Controls manager
     */
    public function register_controls($controls_manager) {
        // Custom controls if needed
    }

    /**
     * Enqueue editor scripts
     */
    public function enqueue_editor_scripts() {
        wp_enqueue_style(
            'stylegenius-elementor-editor',
            STYLEGENIUS_PLUGIN_URL . 'assets/css/elementor-editor.css',
            array(),
            STYLEGENIUS_VERSION
        );

        wp_enqueue_script(
            'stylegenius-elementor-editor',
            STYLEGENIUS_PLUGIN_URL . 'assets/js/elementor-editor.js',
            array('jquery'),
            STYLEGENIUS_VERSION,
            true
        );
    }

    /**
     * Enqueue frontend scripts
     */
    public function enqueue_frontend_scripts() {
        // Frontend styles and scripts are already loaded by the public class
    }

    /**
     * Register dynamic tags
     *
     * @param \Elementor\Core\DynamicTags\Manager $dynamic_tags Dynamic tags manager
     */
    public function register_dynamic_tags($dynamic_tags) {
        // Register dynamic tag group
        $dynamic_tags->register_group(
            'stylegenius',
            array(
                'title' => __('StyleGenius', 'stylegenius-pro'),
            )
        );

        // Load and register tags
        $tags = array(
            'user-tier' => 'StyleGenius_Dynamic_Tag_User_Tier',
            'user-points' => 'StyleGenius_Dynamic_Tag_User_Points',
            'user-level' => 'StyleGenius_Dynamic_Tag_User_Level',
            'user-streak' => 'StyleGenius_Dynamic_Tag_User_Streak',
            'style-type' => 'StyleGenius_Dynamic_Tag_Style_Type',
        );

        foreach ($tags as $tag_file => $tag_class) {
            $file_path = STYLEGENIUS_PLUGIN_PATH . "includes/integrations/elementor/tags/class-{$tag_file}-tag.php";
            if (file_exists($file_path)) {
                require_once $file_path;
                if (class_exists($tag_class)) {
                    $dynamic_tags->register(new $tag_class());
                }
            }
        }
    }

    /**
     * Create Elementor widget base files
     */
    public function create_widget_files() {
        // Create base widget class
        $this->create_widget_base();

        // Create individual widgets
        $this->create_quiz_widget();
        $this->create_chat_widget();
        $this->create_wardrobe_widget();
        $this->create_dashboard_widget();
        $this->create_progress_widget();
        $this->create_leaderboard_widget();
        $this->create_challenges_widget();
        $this->create_tier_widget();
        $this->create_referral_widget();
    }

    /**
     * Create widget base class
     */
    private function create_widget_base() {
        $content = '<?php
/**
 * StyleGenius Elementor Widget Base
 */

if (!defined("ABSPATH")) {
    exit;
}

abstract class StyleGenius_Elementor_Widget_Base extends \Elementor\Widget_Base {

    public function get_categories() {
        return array("stylegenius");
    }

    protected function get_tier_options() {
        return array(
            "free" => __("Kostenlos", "stylegenius-pro"),
            "premium" => __("Premium", "stylegenius-pro"),
            "vip" => __("VIP", "stylegenius-pro"),
        );
    }

    protected function render_access_denied() {
        $teaser = new StyleGenius_Teaser();
        echo $teaser->render($this->get_feature_key());
    }

    protected function get_feature_key() {
        return "general";
    }

    protected function check_access() {
        $gate = new StyleGenius_Content_Gate();
        return $gate->can_access($this->get_feature_key());
    }

    protected function add_common_style_controls() {
        $this->add_control(
            "primary_color",
            array(
                "label" => __("Primärfarbe", "stylegenius-pro"),
                "type" => \Elementor\Controls_Manager::COLOR,
                "default" => "#9333ea",
                "selectors" => array(
                    "{{WRAPPER}} .sg-button--primary" => "background-color: {{VALUE}}",
                    "{{WRAPPER}} .sg-accent" => "color: {{VALUE}}",
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                "name" => "content_typography",
                "selector" => "{{WRAPPER}} .sg-content",
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            array(
                "name" => "card_shadow",
                "selector" => "{{WRAPPER}} .sg-card",
            )
        );
    }
}';

        $dir = STYLEGENIUS_PLUGIN_PATH . 'includes/integrations/elementor/';
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }

        file_put_contents($dir . 'class-widget-base.php', $content);
    }

    /**
     * Create Quiz widget
     */
    private function create_quiz_widget() {
        $content = '<?php
/**
 * StyleGenius Quiz Elementor Widget
 */

if (!defined("ABSPATH")) {
    exit;
}

class StyleGenius_Elementor_Quiz_Widget extends StyleGenius_Elementor_Widget_Base {

    public function get_name() {
        return "stylegenius_quiz";
    }

    public function get_title() {
        return __("Style Quiz", "stylegenius-pro");
    }

    public function get_icon() {
        return "eicon-form-horizontal";
    }

    protected function get_feature_key() {
        return "quiz";
    }

    protected function register_controls() {
        $this->start_controls_section(
            "content_section",
            array(
                "label" => __("Inhalt", "stylegenius-pro"),
                "tab" => \Elementor\Controls_Manager::TAB_CONTENT,
            )
        );

        $this->add_control(
            "show_results",
            array(
                "label" => __("Ergebnisse anzeigen", "stylegenius-pro"),
                "type" => \Elementor\Controls_Manager::SWITCHER,
                "default" => "yes",
            )
        );

        $this->add_control(
            "redirect_url",
            array(
                "label" => __("Weiterleitung nach Quiz", "stylegenius-pro"),
                "type" => \Elementor\Controls_Manager::URL,
                "placeholder" => "https://example.com/results",
            )
        );

        $this->end_controls_section();

        // Style section
        $this->start_controls_section(
            "style_section",
            array(
                "label" => __("Stil", "stylegenius-pro"),
                "tab" => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_common_style_controls();

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();

        $atts = array(
            "show_results" => $settings["show_results"],
            "redirect" => !empty($settings["redirect_url"]["url"]) ? $settings["redirect_url"]["url"] : "",
        );

        $shortcodes = new StyleGenius_Shortcodes();
        echo $shortcodes->render_quiz($atts);
    }
}';

        $this->save_widget_file('quiz', $content);
    }

    /**
     * Create Chat widget
     */
    private function create_chat_widget() {
        $content = '<?php
/**
 * StyleGenius Chat Elementor Widget
 */

if (!defined("ABSPATH")) {
    exit;
}

class StyleGenius_Elementor_Chat_Widget extends StyleGenius_Elementor_Widget_Base {

    public function get_name() {
        return "stylegenius_chat";
    }

    public function get_title() {
        return __("AI Chat", "stylegenius-pro");
    }

    public function get_icon() {
        return "eicon-comments";
    }

    protected function get_feature_key() {
        return "chat";
    }

    protected function register_controls() {
        $this->start_controls_section(
            "content_section",
            array(
                "label" => __("Inhalt", "stylegenius-pro"),
                "tab" => \Elementor\Controls_Manager::TAB_CONTENT,
            )
        );

        $this->add_control(
            "style",
            array(
                "label" => __("Stil", "stylegenius-pro"),
                "type" => \Elementor\Controls_Manager::SELECT,
                "default" => "full",
                "options" => array(
                    "full" => __("Vollständig", "stylegenius-pro"),
                    "compact" => __("Kompakt", "stylegenius-pro"),
                ),
            )
        );

        $this->add_control(
            "height",
            array(
                "label" => __("Höhe", "stylegenius-pro"),
                "type" => \Elementor\Controls_Manager::SLIDER,
                "size_units" => array("px", "vh"),
                "range" => array(
                    "px" => array("min" => 300, "max" => 800),
                    "vh" => array("min" => 30, "max" => 80),
                ),
                "default" => array("unit" => "px", "size" => 500),
            )
        );

        $this->end_controls_section();
    }

    protected function render() {
        if (!$this->check_access()) {
            $this->render_access_denied();
            return;
        }

        $settings = $this->get_settings_for_display();

        $height = $settings["height"]["size"] . $settings["height"]["unit"];

        $atts = array(
            "style" => $settings["style"],
            "height" => $height,
        );

        $shortcodes = new StyleGenius_Shortcodes();
        echo $shortcodes->render_chat($atts);
    }
}';

        $this->save_widget_file('chat', $content);
    }

    /**
     * Create Wardrobe widget
     */
    private function create_wardrobe_widget() {
        $content = '<?php
/**
 * StyleGenius Wardrobe Elementor Widget
 */

if (!defined("ABSPATH")) {
    exit;
}

class StyleGenius_Elementor_Wardrobe_Widget extends StyleGenius_Elementor_Widget_Base {

    public function get_name() {
        return "stylegenius_wardrobe";
    }

    public function get_title() {
        return __("Virtuelle Garderobe", "stylegenius-pro");
    }

    public function get_icon() {
        return "eicon-gallery-grid";
    }

    protected function get_feature_key() {
        return "wardrobe";
    }

    protected function register_controls() {
        $this->start_controls_section(
            "content_section",
            array(
                "label" => __("Inhalt", "stylegenius-pro"),
                "tab" => \Elementor\Controls_Manager::TAB_CONTENT,
            )
        );

        $this->add_control(
            "view",
            array(
                "label" => __("Ansicht", "stylegenius-pro"),
                "type" => \Elementor\Controls_Manager::SELECT,
                "default" => "grid",
                "options" => array(
                    "grid" => __("Raster", "stylegenius-pro"),
                    "list" => __("Liste", "stylegenius-pro"),
                ),
            )
        );

        $this->add_control(
            "category",
            array(
                "label" => __("Kategorie filtern", "stylegenius-pro"),
                "type" => \Elementor\Controls_Manager::SELECT,
                "default" => "",
                "options" => array(
                    "" => __("Alle", "stylegenius-pro"),
                    "tops" => __("Oberteile", "stylegenius-pro"),
                    "bottoms" => __("Unterteile", "stylegenius-pro"),
                    "dresses" => __("Kleider", "stylegenius-pro"),
                    "outerwear" => __("Oberbekleidung", "stylegenius-pro"),
                    "shoes" => __("Schuhe", "stylegenius-pro"),
                    "accessories" => __("Accessoires", "stylegenius-pro"),
                ),
            )
        );

        $this->end_controls_section();
    }

    protected function render() {
        if (!$this->check_access()) {
            $this->render_access_denied();
            return;
        }

        $settings = $this->get_settings_for_display();

        $atts = array(
            "view" => $settings["view"],
            "category" => $settings["category"],
        );

        $shortcodes = new StyleGenius_Shortcodes();
        echo $shortcodes->render_wardrobe($atts);
    }
}';

        $this->save_widget_file('wardrobe', $content);
    }

    /**
     * Create Dashboard widget
     */
    private function create_dashboard_widget() {
        $content = '<?php
/**
 * StyleGenius Dashboard Elementor Widget
 */

if (!defined("ABSPATH")) {
    exit;
}

class StyleGenius_Elementor_Dashboard_Widget extends StyleGenius_Elementor_Widget_Base {

    public function get_name() {
        return "stylegenius_dashboard";
    }

    public function get_title() {
        return __("Benutzer-Dashboard", "stylegenius-pro");
    }

    public function get_icon() {
        return "eicon-dashboard";
    }

    protected function register_controls() {
        $this->start_controls_section(
            "content_section",
            array(
                "label" => __("Inhalt", "stylegenius-pro"),
                "tab" => \Elementor\Controls_Manager::TAB_CONTENT,
            )
        );

        $this->add_control(
            "info",
            array(
                "type" => \Elementor\Controls_Manager::RAW_HTML,
                "raw" => __("Zeigt das vollständige Benutzer-Dashboard mit allen Statistiken und Aktivitäten.", "stylegenius-pro"),
            )
        );

        $this->end_controls_section();
    }

    protected function render() {
        $shortcodes = new StyleGenius_Shortcodes();
        echo $shortcodes->render_dashboard(array());
    }
}';

        $this->save_widget_file('dashboard', $content);
    }

    /**
     * Create Progress widget
     */
    private function create_progress_widget() {
        $content = '<?php
/**
 * StyleGenius Progress Elementor Widget
 */

if (!defined("ABSPATH")) {
    exit;
}

class StyleGenius_Elementor_Progress_Widget extends StyleGenius_Elementor_Widget_Base {

    public function get_name() {
        return "stylegenius_progress";
    }

    public function get_title() {
        return __("Fortschrittsanzeige", "stylegenius-pro");
    }

    public function get_icon() {
        return "eicon-skill-bar";
    }

    protected function register_controls() {
        $this->start_controls_section(
            "content_section",
            array(
                "label" => __("Inhalt", "stylegenius-pro"),
                "tab" => \Elementor\Controls_Manager::TAB_CONTENT,
            )
        );

        $this->add_control(
            "info",
            array(
                "type" => \Elementor\Controls_Manager::RAW_HTML,
                "raw" => __("Zeigt Punkte, Level, Streak und Abzeichen des Nutzers.", "stylegenius-pro"),
            )
        );

        $this->end_controls_section();

        $this->start_controls_section(
            "style_section",
            array(
                "label" => __("Stil", "stylegenius-pro"),
                "tab" => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_common_style_controls();

        $this->end_controls_section();
    }

    protected function render() {
        $shortcodes = new StyleGenius_Shortcodes();
        echo $shortcodes->render_progress(array());
    }
}';

        $this->save_widget_file('progress', $content);
    }

    /**
     * Create Leaderboard widget
     */
    private function create_leaderboard_widget() {
        $content = '<?php
/**
 * StyleGenius Leaderboard Elementor Widget
 */

if (!defined("ABSPATH")) {
    exit;
}

class StyleGenius_Elementor_Leaderboard_Widget extends StyleGenius_Elementor_Widget_Base {

    public function get_name() {
        return "stylegenius_leaderboard";
    }

    public function get_title() {
        return __("Rangliste", "stylegenius-pro");
    }

    public function get_icon() {
        return "eicon-posts-ticker";
    }

    protected function register_controls() {
        $this->start_controls_section(
            "content_section",
            array(
                "label" => __("Inhalt", "stylegenius-pro"),
                "tab" => \Elementor\Controls_Manager::TAB_CONTENT,
            )
        );

        $this->add_control(
            "type",
            array(
                "label" => __("Typ", "stylegenius-pro"),
                "type" => \Elementor\Controls_Manager::SELECT,
                "default" => "points",
                "options" => array(
                    "points" => __("Punkte", "stylegenius-pro"),
                    "level" => __("Level", "stylegenius-pro"),
                    "streak" => __("Streak", "stylegenius-pro"),
                    "challenges" => __("Challenges", "stylegenius-pro"),
                ),
            )
        );

        $this->add_control(
            "period",
            array(
                "label" => __("Zeitraum", "stylegenius-pro"),
                "type" => \Elementor\Controls_Manager::SELECT,
                "default" => "weekly",
                "options" => array(
                    "daily" => __("Täglich", "stylegenius-pro"),
                    "weekly" => __("Wöchentlich", "stylegenius-pro"),
                    "monthly" => __("Monatlich", "stylegenius-pro"),
                    "all" => __("Alle Zeit", "stylegenius-pro"),
                ),
            )
        );

        $this->add_control(
            "limit",
            array(
                "label" => __("Anzahl", "stylegenius-pro"),
                "type" => \Elementor\Controls_Manager::NUMBER,
                "default" => 10,
                "min" => 5,
                "max" => 50,
            )
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();

        $atts = array(
            "type" => $settings["type"],
            "period" => $settings["period"],
            "limit" => $settings["limit"],
        );

        $shortcodes = new StyleGenius_Shortcodes();
        echo $shortcodes->render_leaderboard($atts);
    }
}';

        $this->save_widget_file('leaderboard', $content);
    }

    /**
     * Create Challenges widget
     */
    private function create_challenges_widget() {
        $content = '<?php
/**
 * StyleGenius Challenges Elementor Widget
 */

if (!defined("ABSPATH")) {
    exit;
}

class StyleGenius_Elementor_Challenges_Widget extends StyleGenius_Elementor_Widget_Base {

    public function get_name() {
        return "stylegenius_challenges";
    }

    public function get_title() {
        return __("Challenges", "stylegenius-pro");
    }

    public function get_icon() {
        return "eicon-trophy";
    }

    protected function register_controls() {
        $this->start_controls_section(
            "content_section",
            array(
                "label" => __("Inhalt", "stylegenius-pro"),
                "tab" => \Elementor\Controls_Manager::TAB_CONTENT,
            )
        );

        $this->add_control(
            "status",
            array(
                "label" => __("Status", "stylegenius-pro"),
                "type" => \Elementor\Controls_Manager::SELECT,
                "default" => "active",
                "options" => array(
                    "active" => __("Aktiv", "stylegenius-pro"),
                    "all" => __("Alle", "stylegenius-pro"),
                    "past" => __("Vergangene", "stylegenius-pro"),
                ),
            )
        );

        $this->add_control(
            "limit",
            array(
                "label" => __("Anzahl", "stylegenius-pro"),
                "type" => \Elementor\Controls_Manager::NUMBER,
                "default" => 6,
                "min" => 1,
                "max" => 20,
            )
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();

        $atts = array(
            "status" => $settings["status"],
            "limit" => $settings["limit"],
        );

        $shortcodes = new StyleGenius_Shortcodes();
        echo $shortcodes->render_challenges($atts);
    }
}';

        $this->save_widget_file('challenges', $content);
    }

    /**
     * Create Tier Comparison widget
     */
    private function create_tier_widget() {
        $content = '<?php
/**
 * StyleGenius Tier Comparison Elementor Widget
 */

if (!defined("ABSPATH")) {
    exit;
}

class StyleGenius_Elementor_Tier_Widget extends StyleGenius_Elementor_Widget_Base {

    public function get_name() {
        return "stylegenius_tier_comparison";
    }

    public function get_title() {
        return __("Preisvergleich", "stylegenius-pro");
    }

    public function get_icon() {
        return "eicon-price-table";
    }

    protected function register_controls() {
        $this->start_controls_section(
            "style_section",
            array(
                "label" => __("Stil", "stylegenius-pro"),
                "tab" => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_common_style_controls();

        $this->end_controls_section();
    }

    protected function render() {
        $shortcodes = new StyleGenius_Shortcodes();
        echo $shortcodes->render_tier_comparison(array());
    }
}';

        $this->save_widget_file('tier-comparison', $content);
    }

    /**
     * Create Referral widget
     */
    private function create_referral_widget() {
        $content = '<?php
/**
 * StyleGenius Referral Elementor Widget
 */

if (!defined("ABSPATH")) {
    exit;
}

class StyleGenius_Elementor_Referral_Widget extends StyleGenius_Elementor_Widget_Base {

    public function get_name() {
        return "stylegenius_referral";
    }

    public function get_title() {
        return __("Empfehlungsprogramm", "stylegenius-pro");
    }

    public function get_icon() {
        return "eicon-user-circle-o";
    }

    protected function register_controls() {
        $this->start_controls_section(
            "content_section",
            array(
                "label" => __("Inhalt", "stylegenius-pro"),
                "tab" => \Elementor\Controls_Manager::TAB_CONTENT,
            )
        );

        $this->add_control(
            "show_rewards",
            array(
                "label" => __("Belohnungen anzeigen", "stylegenius-pro"),
                "type" => \Elementor\Controls_Manager::SWITCHER,
                "default" => "yes",
            )
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();

        $atts = array(
            "show_rewards" => $settings["show_rewards"],
        );

        $shortcodes = new StyleGenius_Shortcodes();
        echo $shortcodes->render_referral($atts);
    }
}';

        $this->save_widget_file('referral', $content);
    }

    /**
     * Save widget file
     *
     * @param string $name    Widget name
     * @param string $content File content
     */
    private function save_widget_file($name, $content) {
        $dir = STYLEGENIUS_PLUGIN_PATH . 'includes/integrations/elementor/widgets/';
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }

        file_put_contents($dir . "class-{$name}-widget.php", $content);
    }
}
