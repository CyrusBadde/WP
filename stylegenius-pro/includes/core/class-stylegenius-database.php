<?php
/**
 * Database-Klasse
 *
 * Abstraktion für alle Datenbank-Operationen.
 *
 * @package StyleGenius_Pro
 * @subpackage StyleGenius_Pro/includes/core
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class StyleGenius_Database
 */
class StyleGenius_Database {

    /**
     * WordPress Database-Objekt
     *
     * @var wpdb
     */
    private $wpdb;

    /**
     * Tabellen-Präfix
     *
     * @var string
     */
    private string $prefix;

    /**
     * Konstruktor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb   = $wpdb;
        $this->prefix = $wpdb->prefix . 'sg_';
    }

    /**
     * Gibt den vollständigen Tabellennamen zurück
     *
     * @param string $table Kurzname der Tabelle.
     * @return string Vollständiger Tabellenname.
     */
    public function get_table_name( string $table ): string {
        return $this->prefix . $table;
    }

    /**
     * Prüft ob eine Tabelle existiert
     *
     * @param string $table Kurzname der Tabelle.
     * @return bool
     */
    public function table_exists( string $table ): bool {
        $table_name = $this->get_table_name( $table );
        return $this->wpdb->get_var(
            $this->wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name )
        ) === $table_name;
    }

    /**
     * Fügt einen Datensatz ein
     *
     * @param string     $table  Kurzname der Tabelle.
     * @param array      $data   Einzufügende Daten.
     * @param array|null $format Format der Daten.
     * @return int|false Insert-ID oder false bei Fehler.
     */
    public function insert( string $table, array $data, array $format = null ): int|false {
        $table_name = $this->get_table_name( $table );
        $result     = $this->wpdb->insert( $table_name, $data, $format );

        if ( false === $result ) {
            return false;
        }

        return $this->wpdb->insert_id;
    }

    /**
     * Aktualisiert Datensätze
     *
     * @param string     $table        Kurzname der Tabelle.
     * @param array      $data         Zu aktualisierende Daten.
     * @param array      $where        WHERE-Bedingungen.
     * @param array|null $format       Format der Daten.
     * @param array|null $where_format Format der WHERE-Werte.
     * @return int|false Anzahl aktualisierter Zeilen oder false.
     */
    public function update( string $table, array $data, array $where, array $format = null, array $where_format = null ): int|false {
        $table_name = $this->get_table_name( $table );
        return $this->wpdb->update( $table_name, $data, $where, $format, $where_format );
    }

    /**
     * Löscht Datensätze
     *
     * @param string     $table        Kurzname der Tabelle.
     * @param array      $where        WHERE-Bedingungen.
     * @param array|null $where_format Format der WHERE-Werte.
     * @return int|false Anzahl gelöschter Zeilen oder false.
     */
    public function delete( string $table, array $where, array $where_format = null ): int|false {
        $table_name = $this->get_table_name( $table );
        return $this->wpdb->delete( $table_name, $where, $where_format );
    }

    /**
     * Holt eine einzelne Zeile
     *
     * @param string $table  Kurzname der Tabelle.
     * @param array  $where  WHERE-Bedingungen.
     * @param string $output Ausgabetyp (OBJECT, ARRAY_A, ARRAY_N).
     * @return object|array|null
     */
    public function get_row( string $table, array $where, string $output = OBJECT ): object|array|null {
        $table_name = $this->get_table_name( $table );

        $conditions = array();
        $values     = array();

        foreach ( $where as $column => $value ) {
            $conditions[] = "`{$column}` = %s";
            $values[]     = $value;
        }

        $where_clause = implode( ' AND ', $conditions );

        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE {$where_clause}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $values
            ),
            $output
        );
    }

    /**
     * Holt mehrere Zeilen
     *
     * @param string $table Kurzname der Tabelle.
     * @param array  $where WHERE-Bedingungen.
     * @param array  $args  Zusätzliche Argumente (orderby, order, limit, offset).
     * @return array
     */
    public function get_results( string $table, array $where = array(), array $args = array() ): array {
        $table_name = $this->get_table_name( $table );

        $defaults = array(
            'orderby' => 'id',
            'order'   => 'DESC',
            'limit'   => 100,
            'offset'  => 0,
            'output'  => OBJECT,
        );

        $args = wp_parse_args( $args, $defaults );

        $query = "SELECT * FROM {$table_name}";

        $values = array();

        if ( ! empty( $where ) ) {
            $conditions = array();
            foreach ( $where as $column => $value ) {
                if ( is_array( $value ) ) {
                    $placeholders = implode( ', ', array_fill( 0, count( $value ), '%s' ) );
                    $conditions[] = "`{$column}` IN ({$placeholders})";
                    $values       = array_merge( $values, $value );
                } else {
                    $conditions[] = "`{$column}` = %s";
                    $values[]     = $value;
                }
            }
            $query .= ' WHERE ' . implode( ' AND ', $conditions );
        }

        // Order
        $allowed_orders = array( 'ASC', 'DESC' );
        $order          = in_array( strtoupper( $args['order'] ), $allowed_orders, true ) ? strtoupper( $args['order'] ) : 'DESC';
        $orderby        = sanitize_sql_orderby( $args['orderby'] ) ?: 'id';
        $query         .= " ORDER BY `{$orderby}` {$order}";

        // Limit & Offset
        $query .= $this->wpdb->prepare( ' LIMIT %d OFFSET %d', absint( $args['limit'] ), absint( $args['offset'] ) );

        if ( ! empty( $values ) ) {
            $query = $this->wpdb->prepare( $query, $values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        }

        return $this->wpdb->get_results( $query, $args['output'] ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    }

    /**
     * Holt einen einzelnen Wert
     *
     * @param string $query SQL-Query.
     * @return mixed
     */
    public function get_var( string $query ): mixed {
        return $this->wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    }

    /**
     * Führt eine Query aus
     *
     * @param string $query SQL-Query.
     * @return int|bool
     */
    public function query( string $query ): int|bool {
        return $this->wpdb->query( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    }

    /**
     * Prepared Statement erstellen
     *
     * @param string $query SQL-Query mit Platzhaltern.
     * @param mixed  ...$args Werte für die Platzhalter.
     * @return string
     */
    public function prepare( string $query, ...$args ): string {
        return $this->wpdb->prepare( $query, ...$args ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    }

    /**
     * Zählt Datensätze
     *
     * @param string $table Kurzname der Tabelle.
     * @param array  $where WHERE-Bedingungen.
     * @return int
     */
    public function get_count( string $table, array $where = array() ): int {
        $table_name = $this->get_table_name( $table );

        $query  = "SELECT COUNT(*) FROM {$table_name}";
        $values = array();

        if ( ! empty( $where ) ) {
            $conditions = array();
            foreach ( $where as $column => $value ) {
                $conditions[] = "`{$column}` = %s";
                $values[]     = $value;
            }
            $query .= ' WHERE ' . implode( ' AND ', $conditions );
        }

        if ( ! empty( $values ) ) {
            $query = $this->wpdb->prepare( $query, $values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        }

        return (int) $this->wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    }

    /**
     * Leert eine Tabelle
     *
     * @param string $table Kurzname der Tabelle.
     * @return bool
     */
    public function truncate( string $table ): bool {
        $table_name = $this->get_table_name( $table );
        return false !== $this->wpdb->query( "TRUNCATE TABLE {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    /**
     * Gibt den letzten Fehler zurück
     *
     * @return string
     */
    public function get_last_error(): string {
        return $this->wpdb->last_error;
    }

    /**
     * Gibt die letzte Insert-ID zurück
     *
     * @return int
     */
    public function get_last_insert_id(): int {
        return (int) $this->wpdb->insert_id;
    }

    /**
     * Holt Chat-Verlauf eines Benutzers
     *
     * @param int $user_id Benutzer-ID.
     * @param int $limit   Limit.
     * @return array
     */
    public function get_user_chat_history( int $user_id, int $limit = 50 ): array {
        $table_name = $this->get_table_name( 'chat_history' );

        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE user_id = %d ORDER BY created_at ASC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $user_id,
                $limit
            )
        );
    }

    /**
     * Fügt eine Chat-Nachricht hinzu
     *
     * @param int    $user_id Benutzer-ID.
     * @param string $role    Rolle (user/assistant).
     * @param string $content Nachrichteninhalt.
     * @param int    $tokens  Verwendete Tokens.
     * @return int Insert-ID.
     */
    public function add_chat_message( int $user_id, string $role, string $content, int $tokens = 0 ): int {
        return $this->insert(
            'chat_history',
            array(
                'user_id'     => $user_id,
                'role'        => $role,
                'content'     => $content,
                'tokens_used' => $tokens,
                'created_at'  => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%d', '%s' )
        );
    }

    /**
     * Holt die Garderobe eines Benutzers
     *
     * @param int   $user_id Benutzer-ID.
     * @param array $filters Filter-Optionen.
     * @return array
     */
    public function get_user_wardrobe( int $user_id, array $filters = array() ): array {
        $table_name = $this->get_table_name( 'wardrobe' );

        $query  = "SELECT * FROM {$table_name} WHERE user_id = %d";
        $values = array( $user_id );

        if ( ! empty( $filters['category'] ) ) {
            $query   .= ' AND category = %s';
            $values[] = $filters['category'];
        }

        if ( ! empty( $filters['color'] ) ) {
            $query   .= ' AND color = %s';
            $values[] = $filters['color'];
        }

        if ( ! empty( $filters['season'] ) ) {
            $query   .= ' AND season = %s';
            $values[] = $filters['season'];
        }

        if ( isset( $filters['is_favorite'] ) ) {
            $query   .= ' AND is_favorite = %d';
            $values[] = $filters['is_favorite'] ? 1 : 0;
        }

        $orderby = ! empty( $filters['orderby'] ) ? sanitize_sql_orderby( $filters['orderby'] ) : 'created_at';
        $order   = ! empty( $filters['order'] ) && strtoupper( $filters['order'] ) === 'ASC' ? 'ASC' : 'DESC';

        $query .= " ORDER BY {$orderby} {$order}";

        if ( ! empty( $filters['limit'] ) ) {
            $query   .= ' LIMIT %d';
            $values[] = absint( $filters['limit'] );
        }

        return $this->wpdb->get_results(
            $this->wpdb->prepare( $query, $values ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        );
    }

    /**
     * Holt Punkte-Verlauf eines Benutzers
     *
     * @param int $user_id Benutzer-ID.
     * @param int $limit   Limit.
     * @return array
     */
    public function get_user_points_history( int $user_id, int $limit = 50 ): array {
        $table_name = $this->get_table_name( 'points_log' );

        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE user_id = %d ORDER BY created_at DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $user_id,
                $limit
            )
        );
    }

    /**
     * Holt Leaderboard-Daten
     *
     * @param string $type   Typ (points, badges, challenges, referrals).
     * @param string $period Zeitraum (week, month, all).
     * @param int    $limit  Limit.
     * @return array
     */
    public function get_leaderboard( string $type, string $period, int $limit = 10 ): array {
        global $wpdb;

        $date_condition = '';
        if ( 'week' === $period ) {
            $date_condition = "AND pl.created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
        } elseif ( 'month' === $period ) {
            $date_condition = "AND pl.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        }

        switch ( $type ) {
            case 'points':
                $points_table = $this->get_table_name( 'points_log' );
                $query        = $wpdb->prepare(
                    "SELECT pl.user_id, SUM(pl.points) as total, u.display_name
                    FROM {$points_table} pl
                    INNER JOIN {$wpdb->users} u ON pl.user_id = u.ID
                    WHERE 1=1 {$date_condition}
                    GROUP BY pl.user_id
                    ORDER BY total DESC
                    LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $limit
                );
                break;

            case 'badges':
                $achievements_table = $this->get_table_name( 'achievements' );
                $query              = $wpdb->prepare(
                    "SELECT a.user_id, COUNT(*) as total, u.display_name
                    FROM {$achievements_table} a
                    INNER JOIN {$wpdb->users} u ON a.user_id = u.ID
                    GROUP BY a.user_id
                    ORDER BY total DESC
                    LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $limit
                );
                break;

            case 'challenges':
                $entries_table = $this->get_table_name( 'challenge_entries' );
                $query         = $wpdb->prepare(
                    "SELECT ce.user_id, SUM(ce.is_winner) as total, u.display_name
                    FROM {$entries_table} ce
                    INNER JOIN {$wpdb->users} u ON ce.user_id = u.ID
                    GROUP BY ce.user_id
                    ORDER BY total DESC
                    LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $limit
                );
                break;

            case 'referrals':
                $referrals_table = $this->get_table_name( 'referrals' );
                $query           = $wpdb->prepare(
                    "SELECT r.referrer_id as user_id, COUNT(*) as total, u.display_name
                    FROM {$referrals_table} r
                    INNER JOIN {$wpdb->users} u ON r.referrer_id = u.ID
                    WHERE r.status = 'completed'
                    GROUP BY r.referrer_id
                    ORDER BY total DESC
                    LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $limit
                );
                break;

            default:
                return array();
        }

        $results = $wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        // Rang hinzufügen
        $rank = 1;
        foreach ( $results as $row ) {
            $row->rank = $rank++;
        }

        return $results;
    }

    /**
     * Bereinigt alte Daten
     *
     * @param int $days Alter in Tagen.
     * @return int Anzahl gelöschter Datensätze.
     */
    public function cleanup_old_data( int $days = 365 ): int {
        $deleted = 0;

        // Chat-Historie älter als X Tage
        $chat_table = $this->get_table_name( 'chat_history' );
        $deleted   += (int) $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$chat_table} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $days
            )
        );

        // Alte Affiliate-Klicks ohne Conversion
        $clicks_table = $this->get_table_name( 'affiliate_clicks' );
        $deleted     += (int) $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$clicks_table} WHERE converted = 0 AND clicked_at < DATE_SUB(NOW(), INTERVAL %d DAY)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                90
            )
        );

        return $deleted;
    }

    /**
     * Startet eine Transaktion
     */
    public function start_transaction(): void {
        $this->wpdb->query( 'START TRANSACTION' );
    }

    /**
     * Commitet eine Transaktion
     */
    public function commit(): void {
        $this->wpdb->query( 'COMMIT' );
    }

    /**
     * Rollt eine Transaktion zurück
     */
    public function rollback(): void {
        $this->wpdb->query( 'ROLLBACK' );
    }
}
