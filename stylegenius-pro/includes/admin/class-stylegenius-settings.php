<?php
/**
 * StyleGenius Settings Class
 *
 * Handles plugin settings management.
 *
 * @package    StyleGenius_Pro
 * @subpackage StyleGenius_Pro/includes/admin
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Settings management class.
 */
class StyleGenius_Settings {

    /**
     * Option prefix.
     *
     * @var string
     */
    private $prefix = 'sg_';

    /**
     * Default settings.
     *
     * @var array
     */
    private $defaults = array(
        // General
        'default_language'       => 'de',
        'enable_gdpr_features'   => true,
        'delete_on_uninstall'    => false,

        // API
        'ai_provider'            => 'claude',
        'claude_api_key'         => '',
        'claude_model'           => 'claude-3-sonnet-20240229',
        'openai_api_key'         => '',
        'openai_model'           => 'gpt-4-turbo-preview',

        // Tiers
        'free_ai_limit'          => 10,
        'premium_ai_limit'       => 100,
        'free_wardrobe_limit'    => 20,

        // Gamification
        'enable_points'          => true,
        'enable_levels'          => true,
        'enable_streaks'         => true,
        'enable_leaderboard'     => true,
        'streak_reset_hour'      => 4,

        // Affiliates
        'affiliate_zalando_id'   => '',
        'affiliate_aboutyou_id'  => '',
        'affiliate_amazon_id'    => '',
    );

    /**
     * Save settings.
     *
     * @param array $data POST data.
     * @return bool
     */
    public function save(array $data): bool {
        foreach ($this->defaults as $key => $default) {
            $option_key = $this->prefix . $key;

            if (isset($data[$option_key])) {
                $value = $this->sanitize_setting($key, $data[$option_key]);
                update_option($option_key, $value);
            } elseif (is_bool($default)) {
                // Checkbox not checked
                update_option($option_key, false);
            }
        }

        return true;
    }

    /**
     * Get setting value.
     *
     * @param string $key Setting key.
     * @return mixed
     */
    public function get(string $key) {
        $option_key = $this->prefix . $key;
        $default = $this->defaults[$key] ?? null;

        return get_option($option_key, $default);
    }

    /**
     * Sanitize setting value.
     *
     * @param string $key   Setting key.
     * @param mixed  $value Value to sanitize.
     * @return mixed
     */
    private function sanitize_setting(string $key, $value) {
        $default = $this->defaults[$key] ?? '';

        if (is_bool($default)) {
            return (bool) $value;
        }

        if (is_int($default)) {
            return intval($value);
        }

        // API keys
        if (strpos($key, 'api_key') !== false) {
            return sanitize_text_field($value);
        }

        // IDs
        if (strpos($key, '_id') !== false) {
            return sanitize_text_field($value);
        }

        return sanitize_text_field($value);
    }

    /**
     * Render general settings.
     *
     * @return void
     */
    public function render_general_settings(): void {
        ?>
        <table class="form-table">
            <tr>
                <th><label for="sg_default_language">Sprache</label></th>
                <td>
                    <select name="sg_default_language" id="sg_default_language">
                        <option value="de" <?php selected($this->get('default_language'), 'de'); ?>>Deutsch</option>
                        <option value="en" <?php selected($this->get('default_language'), 'en'); ?>>English</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th>DSGVO-Features</th>
                <td>
                    <label>
                        <input type="checkbox" name="sg_enable_gdpr_features" value="1"
                               <?php checked($this->get('enable_gdpr_features')); ?>>
                        DSGVO-Funktionen aktivieren (Export, Löschung, Einwilligungen)
                    </label>
                </td>
            </tr>
            <tr>
                <th>Deinstallation</th>
                <td>
                    <label>
                        <input type="checkbox" name="sg_delete_on_uninstall" value="1"
                               <?php checked($this->get('delete_on_uninstall')); ?>>
                        <span class="description" style="color: #d63638;">
                            Alle Daten bei Deinstallation löschen (Vorsicht!)
                        </span>
                    </label>
                </td>
            </tr>
        </table>

        <h2>Seiten-Einstellungen</h2>
        <table class="form-table">
            <tr>
                <th><label>Dashboard-Seite</label></th>
                <td>
                    <?php
                    wp_dropdown_pages(array(
                        'name'             => 'sg_dashboard_page',
                        'selected'         => get_option('sg_dashboard_page'),
                        'show_option_none' => 'Seite wählen',
                    ));
                    ?>
                </td>
            </tr>
            <tr>
                <th><label>Quiz-Seite</label></th>
                <td>
                    <?php
                    wp_dropdown_pages(array(
                        'name'             => 'sg_quiz_page',
                        'selected'         => get_option('sg_quiz_page'),
                        'show_option_none' => 'Seite wählen',
                    ));
                    ?>
                </td>
            </tr>
            <tr>
                <th><label>Chat-Seite</label></th>
                <td>
                    <?php
                    wp_dropdown_pages(array(
                        'name'             => 'sg_chat_page',
                        'selected'         => get_option('sg_chat_page'),
                        'show_option_none' => 'Seite wählen',
                    ));
                    ?>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render API settings.
     *
     * @return void
     */
    public function render_api_settings(): void {
        ?>
        <h2>KI-Provider</h2>
        <table class="form-table">
            <tr>
                <th><label for="sg_ai_provider">Standard-Provider</label></th>
                <td>
                    <select name="sg_ai_provider" id="sg_ai_provider">
                        <option value="claude" <?php selected($this->get('ai_provider'), 'claude'); ?>>
                            Claude (Anthropic)
                        </option>
                        <option value="openai" <?php selected($this->get('ai_provider'), 'openai'); ?>>
                            OpenAI
                        </option>
                    </select>
                    <p class="description">Welcher AI-Provider soll standardmäßig verwendet werden?</p>
                </td>
            </tr>
        </table>

        <h2>Claude (Anthropic)</h2>
        <table class="form-table">
            <tr>
                <th><label for="sg_claude_api_key">API-Key</label></th>
                <td>
                    <input type="password" name="sg_claude_api_key" id="sg_claude_api_key"
                           value="<?php echo esc_attr($this->get('claude_api_key')); ?>"
                           class="regular-text" autocomplete="off">
                    <button type="button" class="button sg-toggle-password">Anzeigen</button>
                    <p class="description">
                        Erhalte deinen API-Key unter
                        <a href="https://console.anthropic.com/" target="_blank">console.anthropic.com</a>
                    </p>
                </td>
            </tr>
            <tr>
                <th><label for="sg_claude_model">Modell</label></th>
                <td>
                    <select name="sg_claude_model" id="sg_claude_model">
                        <option value="claude-3-opus-20240229" <?php selected($this->get('claude_model'), 'claude-3-opus-20240229'); ?>>
                            Claude 3 Opus (Beste Qualität)
                        </option>
                        <option value="claude-3-sonnet-20240229" <?php selected($this->get('claude_model'), 'claude-3-sonnet-20240229'); ?>>
                            Claude 3 Sonnet (Empfohlen)
                        </option>
                        <option value="claude-3-haiku-20240307" <?php selected($this->get('claude_model'), 'claude-3-haiku-20240307'); ?>>
                            Claude 3 Haiku (Schnell & Günstig)
                        </option>
                    </select>
                </td>
            </tr>
        </table>

        <h2>OpenAI</h2>
        <table class="form-table">
            <tr>
                <th><label for="sg_openai_api_key">API-Key</label></th>
                <td>
                    <input type="password" name="sg_openai_api_key" id="sg_openai_api_key"
                           value="<?php echo esc_attr($this->get('openai_api_key')); ?>"
                           class="regular-text" autocomplete="off">
                    <button type="button" class="button sg-toggle-password">Anzeigen</button>
                    <p class="description">
                        Erhalte deinen API-Key unter
                        <a href="https://platform.openai.com/" target="_blank">platform.openai.com</a>
                    </p>
                </td>
            </tr>
            <tr>
                <th><label for="sg_openai_model">Modell</label></th>
                <td>
                    <select name="sg_openai_model" id="sg_openai_model">
                        <option value="gpt-4-turbo-preview" <?php selected($this->get('openai_model'), 'gpt-4-turbo-preview'); ?>>
                            GPT-4 Turbo (Empfohlen)
                        </option>
                        <option value="gpt-4" <?php selected($this->get('openai_model'), 'gpt-4'); ?>>
                            GPT-4
                        </option>
                        <option value="gpt-4-vision-preview" <?php selected($this->get('openai_model'), 'gpt-4-vision-preview'); ?>>
                            GPT-4 Vision
                        </option>
                        <option value="gpt-3.5-turbo" <?php selected($this->get('openai_model'), 'gpt-3.5-turbo'); ?>>
                            GPT-3.5 Turbo (Günstig)
                        </option>
                    </select>
                </td>
            </tr>
        </table>

        <h2>API-Test</h2>
        <p>
            <button type="button" class="button" id="sg-test-api">API-Verbindung testen</button>
            <span id="sg-api-test-result"></span>
        </p>
        <?php
    }

    /**
     * Render tier settings.
     *
     * @return void
     */
    public function render_tier_settings(): void {
        ?>
        <h2>Free-Tier</h2>
        <table class="form-table">
            <tr>
                <th><label for="sg_free_ai_limit">KI-Anfragen pro Monat</label></th>
                <td>
                    <input type="number" name="sg_free_ai_limit" id="sg_free_ai_limit" min="0"
                           value="<?php echo esc_attr($this->get('free_ai_limit')); ?>">
                </td>
            </tr>
            <tr>
                <th><label for="sg_free_wardrobe_limit">Garderobe-Items (max)</label></th>
                <td>
                    <input type="number" name="sg_free_wardrobe_limit" id="sg_free_wardrobe_limit" min="0"
                           value="<?php echo esc_attr($this->get('free_wardrobe_limit')); ?>">
                </td>
            </tr>
        </table>

        <h2>Premium-Tier (€9,90/Monat)</h2>
        <table class="form-table">
            <tr>
                <th><label for="sg_premium_ai_limit">KI-Anfragen pro Monat</label></th>
                <td>
                    <input type="number" name="sg_premium_ai_limit" id="sg_premium_ai_limit" min="0"
                           value="<?php echo esc_attr($this->get('premium_ai_limit')); ?>">
                </td>
            </tr>
            <tr>
                <th>WooCommerce Produkt</th>
                <td>
                    <?php
                    $product_id = get_option('sg_woocommerce_premium_product_id');
                    if ($product_id && class_exists('WooCommerce')) {
                        $product = wc_get_product($product_id);
                        if ($product) {
                            echo '<p>' . esc_html($product->get_name()) . ' (ID: ' . $product_id . ')</p>';
                        }
                    } else {
                        echo '<p class="description">Produkt wird bei Aktivierung automatisch erstellt.</p>';
                    }
                    ?>
                </td>
            </tr>
        </table>

        <h2>VIP-Tier (€29,90/Monat)</h2>
        <table class="form-table">
            <tr>
                <th>KI-Anfragen</th>
                <td>
                    <p><strong>Unbegrenzt</strong></p>
                </td>
            </tr>
            <tr>
                <th>WooCommerce Produkt</th>
                <td>
                    <?php
                    $product_id = get_option('sg_woocommerce_vip_product_id');
                    if ($product_id && class_exists('WooCommerce')) {
                        $product = wc_get_product($product_id);
                        if ($product) {
                            echo '<p>' . esc_html($product->get_name()) . ' (ID: ' . $product_id . ')</p>';
                        }
                    } else {
                        echo '<p class="description">Produkt wird bei Aktivierung automatisch erstellt.</p>';
                    }
                    ?>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render gamification settings.
     *
     * @return void
     */
    public function render_gamification_settings(): void {
        ?>
        <h2>Gamification-Module</h2>
        <table class="form-table">
            <tr>
                <th>Punkte-System</th>
                <td>
                    <label>
                        <input type="checkbox" name="sg_enable_points" value="1"
                               <?php checked($this->get('enable_points')); ?>>
                        Punkte-System aktivieren
                    </label>
                </td>
            </tr>
            <tr>
                <th>Level-System</th>
                <td>
                    <label>
                        <input type="checkbox" name="sg_enable_levels" value="1"
                               <?php checked($this->get('enable_levels')); ?>>
                        Level-System aktivieren
                    </label>
                </td>
            </tr>
            <tr>
                <th>Streak-System</th>
                <td>
                    <label>
                        <input type="checkbox" name="sg_enable_streaks" value="1"
                               <?php checked($this->get('enable_streaks')); ?>>
                        Streak-System aktivieren
                    </label>
                </td>
            </tr>
            <tr>
                <th>Leaderboard</th>
                <td>
                    <label>
                        <input type="checkbox" name="sg_enable_leaderboard" value="1"
                               <?php checked($this->get('enable_leaderboard')); ?>>
                        Leaderboard aktivieren
                    </label>
                </td>
            </tr>
        </table>

        <h2>Streak-Einstellungen</h2>
        <table class="form-table">
            <tr>
                <th><label for="sg_streak_reset_hour">Reset-Stunde</label></th>
                <td>
                    <select name="sg_streak_reset_hour" id="sg_streak_reset_hour">
                        <?php for ($i = 0; $i < 24; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php selected($this->get('streak_reset_hour'), $i); ?>>
                                <?php echo sprintf('%02d:00', $i); ?> Uhr
                            </option>
                        <?php endfor; ?>
                    </select>
                    <p class="description">Wann wird geprüft, ob der Streak fortgesetzt wurde?</p>
                </td>
            </tr>
        </table>

        <h2>Punkte-Werte</h2>
        <p class="description">Die Standardwerte sind in den Klassen definiert. Anpassungen hier überschreiben die Defaults.</p>
        <table class="form-table">
            <tr>
                <th><label>Quiz abgeschlossen</label></th>
                <td>
                    <input type="number" name="sg_points_quiz_completed" min="0"
                           value="<?php echo esc_attr(get_option('sg_points_quiz_completed', 50)); ?>"> Punkte
                </td>
            </tr>
            <tr>
                <th><label>Chat-Nachricht</label></th>
                <td>
                    <input type="number" name="sg_points_chat_message" min="0"
                           value="<?php echo esc_attr(get_option('sg_points_chat_message', 5)); ?>"> Punkte
                </td>
            </tr>
            <tr>
                <th><label>Garderobe-Item hinzugefügt</label></th>
                <td>
                    <input type="number" name="sg_points_add_wardrobe_item" min="0"
                           value="<?php echo esc_attr(get_option('sg_points_add_wardrobe_item', 10)); ?>"> Punkte
                </td>
            </tr>
            <tr>
                <th><label>Empfehlung (Referral)</label></th>
                <td>
                    <input type="number" name="sg_points_referral" min="0"
                           value="<?php echo esc_attr(get_option('sg_points_referral', 100)); ?>"> Punkte
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render affiliate settings.
     *
     * @return void
     */
    public function render_affiliate_settings(): void {
        ?>
        <p class="description">
            Trage hier deine Affiliate-IDs ein, um beim Shopping-Assistenten Provisionen zu verdienen.
        </p>

        <h2>Zalando</h2>
        <table class="form-table">
            <tr>
                <th><label for="sg_affiliate_zalando_id">Partner-ID</label></th>
                <td>
                    <input type="text" name="sg_affiliate_zalando_id" id="sg_affiliate_zalando_id"
                           value="<?php echo esc_attr($this->get('affiliate_zalando_id')); ?>"
                           class="regular-text">
                    <p class="description">
                        <a href="https://www.zanox.com/" target="_blank">Zalando Partner Program</a>
                    </p>
                </td>
            </tr>
        </table>

        <h2>About You</h2>
        <table class="form-table">
            <tr>
                <th><label for="sg_affiliate_aboutyou_id">Partner-ID</label></th>
                <td>
                    <input type="text" name="sg_affiliate_aboutyou_id" id="sg_affiliate_aboutyou_id"
                           value="<?php echo esc_attr($this->get('affiliate_aboutyou_id')); ?>"
                           class="regular-text">
                </td>
            </tr>
        </table>

        <h2>Amazon</h2>
        <table class="form-table">
            <tr>
                <th><label for="sg_affiliate_amazon_id">Associates Tag</label></th>
                <td>
                    <input type="text" name="sg_affiliate_amazon_id" id="sg_affiliate_amazon_id"
                           value="<?php echo esc_attr($this->get('affiliate_amazon_id')); ?>"
                           class="regular-text" placeholder="dein-tag-21">
                    <p class="description">
                        <a href="https://affiliate-program.amazon.de/" target="_blank">Amazon PartnerNet</a>
                    </p>
                </td>
            </tr>
        </table>

        <h2>H&M</h2>
        <table class="form-table">
            <tr>
                <th><label for="sg_affiliate_hm_id">Partner-ID</label></th>
                <td>
                    <input type="text" name="sg_affiliate_hm_id" id="sg_affiliate_hm_id"
                           value="<?php echo esc_attr(get_option('sg_affiliate_hm_id')); ?>"
                           class="regular-text">
                </td>
            </tr>
        </table>

        <h2>ASOS</h2>
        <table class="form-table">
            <tr>
                <th><label for="sg_affiliate_asos_id">Partner-ID</label></th>
                <td>
                    <input type="text" name="sg_affiliate_asos_id" id="sg_affiliate_asos_id"
                           value="<?php echo esc_attr(get_option('sg_affiliate_asos_id')); ?>"
                           class="regular-text">
                </td>
            </tr>
        </table>
        <?php
    }
}
