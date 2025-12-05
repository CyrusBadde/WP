<?php
/**
 * Plugin-Deinstallation
 *
 * Wird beim Löschen des Plugins ausgeführt.
 * Entfernt alle Plugin-Daten wenn in den Einstellungen aktiviert.
 *
 * @package StyleGenius_Pro
 */

// Wenn nicht von WordPress aufgerufen, abbrechen
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Optionen laden
$options = get_option( 'stylegenius_options', array() );

// Prüfen ob Daten bei Deinstallation gelöscht werden sollen
if ( empty( $options['delete_on_uninstall'] ) ) {
    return;
}

global $wpdb;

/**
 * Alle Plugin-Tabellen löschen
 */
$tables = array(
    $wpdb->prefix . 'sg_points_log',
    $wpdb->prefix . 'sg_achievements',
    $wpdb->prefix . 'sg_streaks',
    $wpdb->prefix . 'sg_referrals',
    $wpdb->prefix . 'sg_shares',
    $wpdb->prefix . 'sg_capsules',
    $wpdb->prefix . 'sg_color_profiles',
    $wpdb->prefix . 'sg_before_after',
    $wpdb->prefix . 'sg_challenges',
    $wpdb->prefix . 'sg_challenge_entries',
    $wpdb->prefix . 'sg_challenge_votes',
    $wpdb->prefix . 'sg_user_progress',
    $wpdb->prefix . 'sg_affiliate_clicks',
    $wpdb->prefix . 'sg_chat_history',
    $wpdb->prefix . 'sg_wardrobe',
);

foreach ( $tables as $table ) {
    $wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

/**
 * Alle Plugin-Optionen löschen
 */
$option_keys = array(
    'stylegenius_options',
    'stylegenius_db_version',
    'stylegenius_pages',
    'stylegenius_wc_products',
    'stylegenius_install_date',
    'stylegenius_welcome_dismissed',
);

foreach ( $option_keys as $key ) {
    delete_option( $key );
}

/**
 * Alle User-Meta-Daten löschen
 */
$user_meta_keys = array(
    'sg_tier',
    'sg_usage_count',
    'sg_usage_reset_date',
    'sg_style_type',
    'sg_quiz_answers',
    'sg_quiz_completed_at',
    'sg_points',
    'sg_level',
    'sg_badges',
    'sg_referral_code',
    'sg_referred_by',
    'sg_referral_count',
    'sg_favorite_brands',
    'sg_budget_min',
    'sg_budget_max',
    'sg_body_type',
    'sg_profession',
    'sg_typical_occasions',
    'sg_disliked_styles',
    'sg_disliked_colors',
    'sg_privacy_settings',
    'sg_notification_settings',
    'sg_onboarding_completed',
    'sg_last_activity',
);

foreach ( $user_meta_keys as $meta_key ) {
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->usermeta} WHERE meta_key = %s",
            $meta_key
        )
    );
}

/**
 * Alle Transients löschen
 */
$wpdb->query(
    "DELETE FROM {$wpdb->options}
    WHERE option_name LIKE '_transient_stylegenius_%'
    OR option_name LIKE '_transient_timeout_stylegenius_%'"
);

/**
 * Upload-Verzeichnis löschen
 */
$upload_dir = wp_upload_dir();
$sg_dir     = $upload_dir['basedir'] . '/stylegenius';

if ( is_dir( $sg_dir ) ) {
    stylegenius_delete_directory( $sg_dir );
}

/**
 * Hilfsfunktion: Verzeichnis rekursiv löschen
 *
 * @param string $dir Pfad zum Verzeichnis.
 */
function stylegenius_delete_directory( string $dir ): void {
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
            unlink( $file->getRealPath() );
        }
    }

    rmdir( $dir );
}

/**
 * Media-Attachments mit StyleGenius-Meta löschen (optional, standardmäßig deaktiviert)
 * Auskommentiert, da dies permanente Daten löscht
 */
/*
$attachments = $wpdb->get_col(
    "SELECT post_id FROM {$wpdb->postmeta}
    WHERE meta_key = '_stylegenius_type'"
);

foreach ( $attachments as $attachment_id ) {
    wp_delete_attachment( $attachment_id, true );
}
*/

/**
 * Capabilities von Rollen entfernen
 */
$roles = array( 'administrator', 'shop_manager' );
$caps  = array( 'manage_stylegenius', 'manage_stylegenius_challenges', 'view_stylegenius_stats' );

foreach ( $roles as $role_name ) {
    $role = get_role( $role_name );
    if ( $role ) {
        foreach ( $caps as $cap ) {
            $role->remove_cap( $cap );
        }
    }
}

/**
 * Cron-Jobs entfernen (Sicherheitshalber nochmal)
 */
wp_unschedule_hook( 'stylegenius_daily_cron' );
wp_unschedule_hook( 'stylegenius_hourly_cron' );
wp_unschedule_hook( 'stylegenius_weekly_cron' );
wp_unschedule_hook( 'stylegenius_cleanup_cron' );

/**
 * Rewrite-Rules flushen
 */
flush_rewrite_rules();

/**
 * Object-Cache leeren
 */
if ( function_exists( 'wp_cache_flush' ) ) {
    wp_cache_flush();
}
