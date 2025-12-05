<?php
/**
 * Register all actions, filters and shortcodes for the plugin.
 *
 * Maintain a list of all hooks that are registered throughout
 * the plugin, and register them with the WordPress API. Call the
 * run function to execute the list of actions and filters.
 *
 * @package    StyleGenius_Pro
 * @subpackage StyleGenius_Pro/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class StyleGenius_Loader
 *
 * Orchestrates the hooks of the plugin.
 */
class StyleGenius_Loader {

    /**
     * The array of actions registered with WordPress.
     *
     * @var array
     */
    protected $actions = [];

    /**
     * The array of filters registered with WordPress.
     *
     * @var array
     */
    protected $filters = [];

    /**
     * The array of shortcodes registered with WordPress.
     *
     * @var array
     */
    protected $shortcodes = [];

    /**
     * Add a new action to the collection to be registered with WordPress.
     *
     * @param string $hook          The name of the WordPress action that is being registered.
     * @param object $component     A reference to the instance of the object on which the action is defined.
     * @param string $callback      The name of the function definition on the $component.
     * @param int    $priority      Optional. The priority at which the function should be fired. Default is 10.
     * @param int    $accepted_args Optional. The number of arguments that should be passed to the $callback. Default is 1.
     * @return void
     */
    public function add_action( string $hook, object $component, string $callback, int $priority = 10, int $accepted_args = 1 ): void {
        $this->actions = $this->add( $this->actions, $hook, $component, $callback, $priority, $accepted_args );
    }

    /**
     * Add a new filter to the collection to be registered with WordPress.
     *
     * @param string $hook          The name of the WordPress filter that is being registered.
     * @param object $component     A reference to the instance of the object on which the filter is defined.
     * @param string $callback      The name of the function definition on the $component.
     * @param int    $priority      Optional. The priority at which the function should be fired. Default is 10.
     * @param int    $accepted_args Optional. The number of arguments that should be passed to the $callback. Default is 1.
     * @return void
     */
    public function add_filter( string $hook, object $component, string $callback, int $priority = 10, int $accepted_args = 1 ): void {
        $this->filters = $this->add( $this->filters, $hook, $component, $callback, $priority, $accepted_args );
    }

    /**
     * Add a new shortcode to the collection to be registered with WordPress.
     *
     * @param string $tag       The name of the shortcode tag.
     * @param object $component A reference to the instance of the object on which the shortcode is defined.
     * @param string $callback  The name of the function definition on the $component.
     * @return void
     */
    public function add_shortcode( string $tag, object $component, string $callback ): void {
        $this->shortcodes[] = [
            'tag'       => $tag,
            'component' => $component,
            'callback'  => $callback,
        ];
    }

    /**
     * A utility function that is used to register the actions and hooks into a single collection.
     *
     * @param array  $hooks         The collection of hooks that is being registered (actions or filters).
     * @param string $hook          The name of the WordPress filter that is being registered.
     * @param object $component     A reference to the instance of the object on which the filter is defined.
     * @param string $callback      The name of the function definition on the $component.
     * @param int    $priority      The priority at which the function should be fired.
     * @param int    $accepted_args The number of arguments that should be passed to the $callback.
     * @return array The collection of actions and filters registered with WordPress.
     */
    private function add( array $hooks, string $hook, object $component, string $callback, int $priority, int $accepted_args ): array {
        $hooks[] = [
            'hook'          => $hook,
            'component'     => $component,
            'callback'      => $callback,
            'priority'      => $priority,
            'accepted_args' => $accepted_args,
        ];

        return $hooks;
    }

    /**
     * Register the filters, actions, and shortcodes with WordPress.
     *
     * @return void
     */
    public function run(): void {
        // Register all actions
        foreach ( $this->actions as $hook ) {
            add_action(
                $hook['hook'],
                [ $hook['component'], $hook['callback'] ],
                $hook['priority'],
                $hook['accepted_args']
            );
        }

        // Register all filters
        foreach ( $this->filters as $hook ) {
            add_filter(
                $hook['hook'],
                [ $hook['component'], $hook['callback'] ],
                $hook['priority'],
                $hook['accepted_args']
            );
        }

        // Register all shortcodes
        foreach ( $this->shortcodes as $shortcode ) {
            add_shortcode(
                $shortcode['tag'],
                [ $shortcode['component'], $shortcode['callback'] ]
            );
        }
    }
}
