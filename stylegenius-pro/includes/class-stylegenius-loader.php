<?php
/**
 * Loader-Klasse für das Plugin
 *
 * Registriert alle Actions, Filter und Shortcodes für das Plugin.
 *
 * @package StyleGenius_Pro
 * @subpackage StyleGenius_Pro/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class StyleGenius_Loader
 */
class StyleGenius_Loader {

    /**
     * Array aller registrierten Actions
     *
     * @var array
     */
    protected array $actions = array();

    /**
     * Array aller registrierten Filter
     *
     * @var array
     */
    protected array $filters = array();

    /**
     * Array aller registrierten Shortcodes
     *
     * @var array
     */
    protected array $shortcodes = array();

    /**
     * Konstruktor
     */
    public function __construct() {
        $this->actions    = array();
        $this->filters    = array();
        $this->shortcodes = array();
    }

    /**
     * Fügt eine Action hinzu
     *
     * @param string $hook          Der Name des WordPress-Hooks.
     * @param object $component     Ein Verweis auf die Instanz des Objekts.
     * @param string $callback      Der Name der Methode, die aufgerufen wird.
     * @param int    $priority      Die Priorität (Standard: 10).
     * @param int    $accepted_args Anzahl der akzeptierten Argumente (Standard: 1).
     */
    public function add_action( string $hook, object $component, string $callback, int $priority = 10, int $accepted_args = 1 ): void {
        $this->actions = $this->add( $this->actions, $hook, $component, $callback, $priority, $accepted_args );
    }

    /**
     * Fügt einen Filter hinzu
     *
     * @param string $hook          Der Name des WordPress-Hooks.
     * @param object $component     Ein Verweis auf die Instanz des Objekts.
     * @param string $callback      Der Name der Methode, die aufgerufen wird.
     * @param int    $priority      Die Priorität (Standard: 10).
     * @param int    $accepted_args Anzahl der akzeptierten Argumente (Standard: 1).
     */
    public function add_filter( string $hook, object $component, string $callback, int $priority = 10, int $accepted_args = 1 ): void {
        $this->filters = $this->add( $this->filters, $hook, $component, $callback, $priority, $accepted_args );
    }

    /**
     * Fügt einen Shortcode hinzu
     *
     * @param string $tag       Der Shortcode-Tag.
     * @param object $component Ein Verweis auf die Instanz des Objekts.
     * @param string $callback  Der Name der Methode, die aufgerufen wird.
     */
    public function add_shortcode( string $tag, object $component, string $callback ): void {
        $this->shortcodes[] = array(
            'tag'       => $tag,
            'component' => $component,
            'callback'  => $callback,
        );
    }

    /**
     * Hilfsmethode zum Hinzufügen von Hooks
     *
     * @param array  $hooks         Die Sammlung von Hooks.
     * @param string $hook          Der Name des WordPress-Hooks.
     * @param object $component     Ein Verweis auf die Instanz des Objekts.
     * @param string $callback      Der Name der Methode, die aufgerufen wird.
     * @param int    $priority      Die Priorität.
     * @param int    $accepted_args Anzahl der akzeptierten Argumente.
     *
     * @return array Die aktualisierte Sammlung.
     */
    private function add( array $hooks, string $hook, object $component, string $callback, int $priority, int $accepted_args ): array {
        $hooks[] = array(
            'hook'          => $hook,
            'component'     => $component,
            'callback'      => $callback,
            'priority'      => $priority,
            'accepted_args' => $accepted_args,
        );

        return $hooks;
    }

    /**
     * Registriert alle Hooks bei WordPress
     */
    public function run(): void {
        // Actions registrieren
        foreach ( $this->actions as $hook ) {
            add_action(
                $hook['hook'],
                array( $hook['component'], $hook['callback'] ),
                $hook['priority'],
                $hook['accepted_args']
            );
        }

        // Filter registrieren
        foreach ( $this->filters as $hook ) {
            add_filter(
                $hook['hook'],
                array( $hook['component'], $hook['callback'] ),
                $hook['priority'],
                $hook['accepted_args']
            );
        }

        // Shortcodes registrieren
        foreach ( $this->shortcodes as $shortcode ) {
            add_shortcode(
                $shortcode['tag'],
                array( $shortcode['component'], $shortcode['callback'] )
            );
        }
    }

    /**
     * Gibt alle registrierten Actions zurück
     *
     * @return array
     */
    public function get_actions(): array {
        return $this->actions;
    }

    /**
     * Gibt alle registrierten Filter zurück
     *
     * @return array
     */
    public function get_filters(): array {
        return $this->filters;
    }

    /**
     * Gibt alle registrierten Shortcodes zurück
     *
     * @return array
     */
    public function get_shortcodes(): array {
        return $this->shortcodes;
    }
}
