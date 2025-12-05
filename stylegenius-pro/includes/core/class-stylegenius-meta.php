<?php
/**
 * Meta-Klasse
 *
 * Verwaltet User-Meta-Daten mit Präfix und Caching.
 *
 * @package StyleGenius_Pro
 * @subpackage StyleGenius_Pro/includes/core
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class StyleGenius_Meta
 */
class StyleGenius_Meta {

    /**
     * Meta-Präfix
     *
     * @var string
     */
    const META_PREFIX = 'sg_';

    /**
     * Benutzer-ID
     *
     * @var int
     */
    private int $user_id;

    /**
     * Cache für Meta-Daten
     *
     * @var array
     */
    private array $cache = array();

    /**
     * Konstruktor
     *
     * @param int $user_id Benutzer-ID.
     */
    public function __construct( int $user_id ) {
        $this->user_id = $user_id;
        $this->load_cache();
    }

    /**
     * Lädt alle Meta-Daten in den Cache
     */
    private function load_cache(): void {
        $all_meta = get_user_meta( $this->user_id );

        foreach ( $all_meta as $key => $value ) {
            if ( strpos( $key, self::META_PREFIX ) === 0 ) {
                $short_key                = substr( $key, strlen( self::META_PREFIX ) );
                $this->cache[ $short_key ] = maybe_unserialize( $value[0] );
            }
        }
    }

    /**
     * Holt einen Meta-Wert
     *
     * @param string $key     Meta-Schlüssel (ohne Präfix).
     * @param mixed  $default Standardwert.
     * @return mixed
     */
    public function get( string $key, mixed $default = null ): mixed {
        if ( isset( $this->cache[ $key ] ) ) {
            return $this->cache[ $key ];
        }

        $value = get_user_meta( $this->user_id, self::META_PREFIX . $key, true );

        if ( '' === $value || false === $value ) {
            return $default;
        }

        $this->cache[ $key ] = $value;
        return $value;
    }

    /**
     * Setzt einen Meta-Wert
     *
     * @param string $key   Meta-Schlüssel (ohne Präfix).
     * @param mixed  $value Wert.
     * @return bool
     */
    public function set( string $key, mixed $value ): bool {
        if ( ! $this->validate_key( $key ) ) {
            return false;
        }

        $value                = $this->sanitize_value( $key, $value );
        $this->cache[ $key ]  = $value;

        return false !== update_user_meta( $this->user_id, self::META_PREFIX . $key, $value );
    }

    /**
     * Löscht einen Meta-Wert
     *
     * @param string $key Meta-Schlüssel (ohne Präfix).
     * @return bool
     */
    public function delete( string $key ): bool {
        unset( $this->cache[ $key ] );
        return delete_user_meta( $this->user_id, self::META_PREFIX . $key );
    }

    /**
     * Prüft ob ein Meta-Wert existiert
     *
     * @param string $key Meta-Schlüssel (ohne Präfix).
     * @return bool
     */
    public function exists( string $key ): bool {
        return metadata_exists( 'user', $this->user_id, self::META_PREFIX . $key );
    }

    /**
     * Holt alle Meta-Daten
     *
     * @return array
     */
    public function get_all(): array {
        return $this->cache;
    }

    /**
     * Löscht alle StyleGenius Meta-Daten des Benutzers
     *
     * @return bool
     */
    public function delete_all(): bool {
        global $wpdb;

        $result = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key LIKE %s",
                $this->user_id,
                self::META_PREFIX . '%'
            )
        );

        $this->cache = array();

        return false !== $result;
    }

    /**
     * Erhöht einen numerischen Meta-Wert
     *
     * @param string $key    Meta-Schlüssel (ohne Präfix).
     * @param int    $amount Erhöhungsbetrag.
     * @return int Neuer Wert.
     */
    public function increment( string $key, int $amount = 1 ): int {
        $current = (int) $this->get( $key, 0 );
        $new     = $current + $amount;
        $this->set( $key, $new );
        return $new;
    }

    /**
     * Verringert einen numerischen Meta-Wert
     *
     * @param string $key    Meta-Schlüssel (ohne Präfix).
     * @param int    $amount Verringerungsbetrag.
     * @return int Neuer Wert.
     */
    public function decrement( string $key, int $amount = 1 ): int {
        $current = (int) $this->get( $key, 0 );
        $new     = max( 0, $current - $amount );
        $this->set( $key, $new );
        return $new;
    }

    /**
     * Fügt einen Wert zu einem Array hinzu
     *
     * @param string $key   Meta-Schlüssel (ohne Präfix).
     * @param mixed  $value Hinzuzufügender Wert.
     * @return bool
     */
    public function append_to_array( string $key, mixed $value ): bool {
        $array = $this->get( $key, array() );

        if ( ! is_array( $array ) ) {
            $array = array();
        }

        $array[] = $value;
        return $this->set( $key, $array );
    }

    /**
     * Entfernt einen Wert aus einem Array
     *
     * @param string $key   Meta-Schlüssel (ohne Präfix).
     * @param mixed  $value Zu entfernender Wert.
     * @return bool
     */
    public function remove_from_array( string $key, mixed $value ): bool {
        $array = $this->get( $key, array() );

        if ( ! is_array( $array ) ) {
            return false;
        }

        $array = array_filter( $array, fn( $item ) => $item !== $value );
        $array = array_values( $array );

        return $this->set( $key, $array );
    }

    /**
     * Prüft ob ein Wert in einem Array vorhanden ist
     *
     * @param string $key   Meta-Schlüssel (ohne Präfix).
     * @param mixed  $value Zu suchender Wert.
     * @return bool
     */
    public function in_array( string $key, mixed $value ): bool {
        $array = $this->get( $key, array() );

        if ( ! is_array( $array ) ) {
            return false;
        }

        return in_array( $value, $array, true );
    }

    /**
     * Sanitiert einen Wert basierend auf dem Schlüssel
     *
     * @param string $key   Meta-Schlüssel.
     * @param mixed  $value Wert.
     * @return mixed
     */
    private function sanitize_value( string $key, mixed $value ): mixed {
        $schema = self::get_key_schema( $key );

        if ( empty( $schema ) ) {
            if ( is_string( $value ) ) {
                return sanitize_text_field( $value );
            }
            return $value;
        }

        switch ( $schema['type'] ) {
            case 'string':
                return sanitize_text_field( $value );

            case 'int':
                return absint( $value );

            case 'float':
                return floatval( $value );

            case 'bool':
                return (bool) $value;

            case 'array':
                if ( ! is_array( $value ) ) {
                    return array();
                }
                if ( isset( $schema['item_type'] ) && 'string' === $schema['item_type'] ) {
                    return array_map( 'sanitize_text_field', $value );
                }
                return $value;

            case 'datetime':
                if ( $value instanceof DateTime ) {
                    return $value->format( 'Y-m-d H:i:s' );
                }
                return sanitize_text_field( $value );

            default:
                return $value;
        }
    }

    /**
     * Validiert einen Schlüssel
     *
     * @param string $key Meta-Schlüssel.
     * @return bool
     */
    private function validate_key( string $key ): bool {
        // Prüfe auf erlaubte Zeichen
        if ( ! preg_match( '/^[a-z_]+$/', $key ) ) {
            return false;
        }

        return true;
    }

    /**
     * Exportiert Daten für DSGVO
     *
     * @return array
     */
    public function export_for_gdpr(): array {
        $export = array();

        foreach ( $this->cache as $key => $value ) {
            $schema = self::get_key_schema( $key );
            $label  = $schema['label'] ?? $key;

            if ( is_array( $value ) ) {
                $value = wp_json_encode( $value );
            }

            $export[] = array(
                'name'  => $label,
                'value' => $value,
            );
        }

        return $export;
    }

    /**
     * Gibt alle registrierten Meta-Schlüssel zurück
     *
     * @return array
     */
    public static function get_registered_keys(): array {
        return array(
            'tier'                  => array(
                'type'    => 'string',
                'label'   => __( 'Mitgliedsstufe', 'stylegenius-pro' ),
                'default' => 'free',
            ),
            'usage_count'           => array(
                'type'    => 'int',
                'label'   => __( 'Nutzungszähler', 'stylegenius-pro' ),
                'default' => 0,
            ),
            'usage_reset_date'      => array(
                'type'    => 'datetime',
                'label'   => __( 'Nutzungs-Reset-Datum', 'stylegenius-pro' ),
                'default' => '',
            ),
            'style_type'            => array(
                'type'    => 'string',
                'label'   => __( 'Style-Typ', 'stylegenius-pro' ),
                'default' => '',
            ),
            'quiz_answers'          => array(
                'type'    => 'array',
                'label'   => __( 'Quiz-Antworten', 'stylegenius-pro' ),
                'default' => array(),
            ),
            'quiz_completed_at'     => array(
                'type'    => 'datetime',
                'label'   => __( 'Quiz abgeschlossen am', 'stylegenius-pro' ),
                'default' => '',
            ),
            'points'                => array(
                'type'    => 'int',
                'label'   => __( 'Style Points', 'stylegenius-pro' ),
                'default' => 0,
            ),
            'level'                 => array(
                'type'    => 'int',
                'label'   => __( 'Level', 'stylegenius-pro' ),
                'default' => 1,
            ),
            'badges'                => array(
                'type'    => 'array',
                'label'   => __( 'Badges', 'stylegenius-pro' ),
                'default' => array(),
            ),
            'referral_code'         => array(
                'type'    => 'string',
                'label'   => __( 'Empfehlungscode', 'stylegenius-pro' ),
                'default' => '',
            ),
            'referred_by'           => array(
                'type'    => 'int',
                'label'   => __( 'Empfohlen von', 'stylegenius-pro' ),
                'default' => 0,
            ),
            'referral_count'        => array(
                'type'    => 'int',
                'label'   => __( 'Anzahl Empfehlungen', 'stylegenius-pro' ),
                'default' => 0,
            ),
            'favorite_brands'       => array(
                'type'      => 'array',
                'item_type' => 'string',
                'label'     => __( 'Lieblingsmarken', 'stylegenius-pro' ),
                'default'   => array(),
            ),
            'budget_min'            => array(
                'type'    => 'int',
                'label'   => __( 'Mindestbudget', 'stylegenius-pro' ),
                'default' => 0,
            ),
            'budget_max'            => array(
                'type'    => 'int',
                'label'   => __( 'Maximalbudget', 'stylegenius-pro' ),
                'default' => 0,
            ),
            'body_type'             => array(
                'type'    => 'string',
                'label'   => __( 'Körpertyp', 'stylegenius-pro' ),
                'default' => '',
            ),
            'profession'            => array(
                'type'    => 'string',
                'label'   => __( 'Beruf', 'stylegenius-pro' ),
                'default' => '',
            ),
            'typical_occasions'     => array(
                'type'      => 'array',
                'item_type' => 'string',
                'label'     => __( 'Typische Anlässe', 'stylegenius-pro' ),
                'default'   => array(),
            ),
            'disliked_styles'       => array(
                'type'      => 'array',
                'item_type' => 'string',
                'label'     => __( 'Unbeliebte Styles', 'stylegenius-pro' ),
                'default'   => array(),
            ),
            'disliked_colors'       => array(
                'type'      => 'array',
                'item_type' => 'string',
                'label'     => __( 'Unbeliebte Farben', 'stylegenius-pro' ),
                'default'   => array(),
            ),
            'privacy_settings'      => array(
                'type'    => 'array',
                'label'   => __( 'Datenschutz-Einstellungen', 'stylegenius-pro' ),
                'default' => array(),
            ),
            'notification_settings' => array(
                'type'    => 'array',
                'label'   => __( 'Benachrichtigungs-Einstellungen', 'stylegenius-pro' ),
                'default' => array(),
            ),
            'onboarding_completed'  => array(
                'type'    => 'bool',
                'label'   => __( 'Onboarding abgeschlossen', 'stylegenius-pro' ),
                'default' => false,
            ),
            'last_activity'         => array(
                'type'    => 'datetime',
                'label'   => __( 'Letzte Aktivität', 'stylegenius-pro' ),
                'default' => '',
            ),
        );
    }

    /**
     * Gibt das Schema für einen Schlüssel zurück
     *
     * @param string $key Meta-Schlüssel.
     * @return array
     */
    public static function get_key_schema( string $key ): array {
        $keys = self::get_registered_keys();
        return $keys[ $key ] ?? array();
    }
}
