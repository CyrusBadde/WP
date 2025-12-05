<?php
/**
 * Plugin-Deaktivierung
 *
 * Wird bei der Deaktivierung des Plugins ausgeführt.
 *
 * @package StyleGenius_Pro
 * @subpackage StyleGenius_Pro/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class StyleGenius_Deactivator
 */
class StyleGenius_Deactivator {

    /**
     * Deaktiviert das Plugin
     *
     * Räumt temporäre Daten auf und entfernt geplante Cron-Jobs.
     * Datenbank-Tabellen und Benutzer-Daten werden NICHT gelöscht.
     */
    public static function deactivate(): void {
        // Cron-Jobs entfernen
        self::clear_scheduled_events();

        // Transients löschen
        self::clear_transients();

        // Temp-Dateien bereinigen
        self::cleanup_temp_files();

        // Rewrite-Rules flushen
        flush_rewrite_rules();
    }

    /**
     * Entfernt alle geplanten Cron-Events
     */
    private static function clear_scheduled_events(): void {
        $events = array(
            'stylegenius_daily_cron',
            'stylegenius_hourly_cron',
            'stylegenius_weekly_cron',
            'stylegenius_cleanup_cron',
        );

        foreach ( $events as $event ) {
            $timestamp = wp_next_scheduled( $event );
            if ( $timestamp ) {
                wp_unschedule_event( $timestamp, $event );
            }
        }

        // Alle StyleGenius-Cron-Jobs entfernen
        wp_unschedule_hook( 'stylegenius_daily_cron' );
        wp_unschedule_hook( 'stylegenius_hourly_cron' );
        wp_unschedule_hook( 'stylegenius_weekly_cron' );
        wp_unschedule_hook( 'stylegenius_cleanup_cron' );
    }

    /**
     * Löscht alle Plugin-Transients
     */
    private static function clear_transients(): void {
        global $wpdb;

        // Alle StyleGenius-Transients löschen
        $wpdb->query(
            "DELETE FROM {$wpdb->options}
            WHERE option_name LIKE '_transient_stylegenius_%'
            OR option_name LIKE '_transient_timeout_stylegenius_%'"
        );

        // Site-Transients (Multisite)
        if ( is_multisite() ) {
            $wpdb->query(
                "DELETE FROM {$wpdb->sitemeta}
                WHERE meta_key LIKE '_site_transient_stylegenius_%'
                OR meta_key LIKE '_site_transient_timeout_stylegenius_%'"
            );
        }

        // Object Cache leeren wenn vorhanden
        if ( function_exists( 'wp_cache_flush' ) ) {
            wp_cache_delete( 'stylegenius_leaderboard_points', 'stylegenius' );
            wp_cache_delete( 'stylegenius_leaderboard_badges', 'stylegenius' );
            wp_cache_delete( 'stylegenius_stats', 'stylegenius' );
        }
    }

    /**
     * Bereinigt temporäre Dateien
     */
    private static function cleanup_temp_files(): void {
        $upload_dir = wp_upload_dir();
        $temp_dir   = $upload_dir['basedir'] . '/stylegenius/temp';

        if ( is_dir( $temp_dir ) ) {
            self::delete_directory_contents( $temp_dir );
        }
    }

    /**
     * Löscht den Inhalt eines Verzeichnisses
     *
     * @param string $dir Pfad zum Verzeichnis.
     */
    private static function delete_directory_contents( string $dir ): void {
        if ( ! is_dir( $dir ) ) {
            return;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS ),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ( $files as $file ) {
            if ( $file->isDir() ) {
                rmdir( $file->getRealPath() );
            } else {
                // Nur temporäre Dateien löschen, keine Index-Dateien
                if ( $file->getFilename() !== 'index.php' && $file->getFilename() !== '.htaccess' ) {
                    unlink( $file->getRealPath() );
                }
            }
        }
    }

    /**
     * Setzt Benutzer-Sessions zurück (optional, für Sicherheit)
     */
    private static function reset_user_sessions(): void {
        // Alle StyleGenius-bezogenen Session-Daten löschen
        // Dies ist optional und wird nur in bestimmten Situationen benötigt
    }
}
