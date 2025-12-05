<?php
/**
 * StyleGenius Teaser Class
 *
 * Handles teaser content for locked features to encourage upgrades.
 *
 * @package    StyleGenius_Pro
 * @subpackage StyleGenius_Pro/includes/gating
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Teaser content management class.
 */
class StyleGenius_Teaser {

    /**
     * Tiers instance.
     *
     * @var StyleGenius_Tiers
     */
    private $tiers;

    /**
     * Feature descriptions for teasers.
     *
     * @var array
     */
    private $feature_info = array(
        'photo_analysis' => array(
            'title'       => 'Foto-Analyse',
            'description' => 'Lass deine Kleidungsstücke von unserer KI analysieren und erhalte detaillierte Empfehlungen.',
            'icon'        => 'camera',
            'preview'     => 'Lade ein Foto hoch und erhalte sofort Styling-Tipps, Farbempfehlungen und Kombinationsideen.',
            'benefits'    => array(
                'Automatische Kategorisierung deiner Kleidung',
                'Farbanalyse und Kombinationsvorschläge',
                'Outfit-Bewertungen und Verbesserungstipps',
            ),
        ),
        'color_analysis' => array(
            'title'       => 'Farbtyp-Analyse',
            'description' => 'Entdecke deinen Farbtyp durch Selfie-Analyse und erhalte deine persönliche Farbpalette.',
            'icon'        => 'palette',
            'preview'     => 'Lade ein Selfie hoch und erfahre, welche Farben dir am besten stehen.',
            'benefits'    => array(
                'Bestimmung deines Farbtyps (Frühling, Sommer, Herbst, Winter)',
                'Persönliche Farbpalette mit 20+ Farben',
                'Metall-Empfehlung (Gold/Silber)',
                'Shareable Farbtyp-Karte',
            ),
        ),
        'capsule_wardrobe' => array(
            'title'       => 'Capsule Wardrobe',
            'description' => 'Erstelle eine perfekt abgestimmte Capsule Wardrobe aus deinen vorhandenen Kleidungsstücken.',
            'icon'        => 'grid',
            'preview'     => 'Unsere KI wählt die besten Teile aus deiner Garderobe für eine vielseitige Capsule.',
            'benefits'    => array(
                '30-Tage Outfit-Plan',
                'Mix & Match Grid',
                'Saisonale Capsule-Vorschläge',
                'KI-generierte Kombinationen',
            ),
        ),
        'shopping_assistant' => array(
            'title'       => 'Shopping-Assistent',
            'description' => 'Erhalte personalisierte Shopping-Empfehlungen basierend auf deinem Stil und Garderobenlücken.',
            'icon'        => 'shopping-bag',
            'preview'     => 'Der VIP Shopping-Assistent findet die perfekten Ergänzungen für deine Garderobe.',
            'benefits'    => array(
                'Personalisierte Produktempfehlungen',
                'Budget-optimierte Vorschläge',
                'Links zu Top-Shops (Zalando, AboutYou, etc.)',
                'Ähnliche Produkte für vorhandene Lieblingsstücke',
            ),
        ),
        'ai_unlimited' => array(
            'title'       => 'Unbegrenzte KI-Beratung',
            'description' => 'Nutze unseren KI-Styling-Berater so oft du möchtest ohne monatliche Limits.',
            'icon'        => 'infinity',
            'preview'     => 'VIP-Mitglieder haben unbegrenzten Zugang zu allen KI-Features.',
            'benefits'    => array(
                'Unbegrenzte Styling-Chats',
                'Unbegrenzte Foto-Analysen',
                'Prioritäre Verarbeitung',
                'Erweiterte KI-Modelle',
            ),
        ),
        'before_after' => array(
            'title'       => 'Vorher-Nachher Generator',
            'description' => 'Dokumentiere deine Style-Transformation mit dem Vorher-Nachher-Tool.',
            'icon'        => 'refresh',
            'preview'     => 'Zeige deinen Style-Fortschritt mit professionellen Vorher-Nachher-Vergleichen.',
            'benefits'    => array(
                'Slider-Vergleichsansicht',
                'KI-Analyse der Transformation',
                'Shareable Bilder',
                'Transformation-Timeline',
            ),
        ),
        'personal_stylist' => array(
            'title'       => 'Persönlicher Stylist',
            'description' => 'Erhalte exklusiven Zugang zu einem persönlichen Stylist für individuelle Beratung.',
            'icon'        => 'user-check',
            'preview'     => 'VIP-exklusiv: Ein persönlicher Stylist beantwortet deine Fragen.',
            'benefits'    => array(
                'Direkte Kommunikation mit Styling-Experten',
                'Individuelle Stil-Beratung',
                'Event-spezifische Outfit-Planung',
                'Persönliche Shopping-Begleitung (virtuell)',
            ),
        ),
    );

    /**
     * Constructor.
     */
    public function __construct() {
        $this->tiers = new StyleGenius_Tiers();
    }

    /**
     * Render teaser for locked feature.
     *
     * @param string $feature Feature key or tier.
     * @param array  $options Display options.
     * @return string HTML.
     */
    public function render(string $feature, array $options = array()): string {
        $content_gate = new StyleGenius_Content_Gate();
        $required_tier = $content_gate->get_required_tier($feature);

        if (!$required_tier || $required_tier === 'free') {
            $required_tier = $feature; // Might be tier name directly
        }

        $tier_info = $this->tiers->get_tier($required_tier);
        $feature_data = $this->feature_info[$feature] ?? null;

        $show_upgrade = $options['show_upgrade'] ?? true;
        $style = $options['style'] ?? 'card'; // card, inline, modal, minimal
        $custom_message = $options['message'] ?? '';

        ob_start();

        switch ($style) {
            case 'inline':
                $this->render_inline($feature_data, $tier_info, $required_tier, $custom_message, $show_upgrade);
                break;

            case 'minimal':
                $this->render_minimal($tier_info, $required_tier, $custom_message, $show_upgrade);
                break;

            case 'modal':
                $this->render_modal($feature_data, $tier_info, $required_tier, $custom_message, $show_upgrade);
                break;

            case 'card':
            default:
                $this->render_card($feature_data, $tier_info, $required_tier, $custom_message, $show_upgrade);
                break;
        }

        return ob_get_clean();
    }

    /**
     * Render card-style teaser.
     *
     * @param array|null $feature_data  Feature information.
     * @param array      $tier_info     Tier information.
     * @param string     $required_tier Required tier.
     * @param string     $message       Custom message.
     * @param bool       $show_upgrade  Show upgrade button.
     * @return void
     */
    private function render_card(?array $feature_data, array $tier_info, string $required_tier, string $message, bool $show_upgrade): void {
        ?>
        <div class="sg-teaser sg-teaser-card">
            <div class="sg-teaser-lock-icon">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                </svg>
            </div>

            <?php if ($feature_data): ?>
                <h3 class="sg-teaser-title"><?php echo esc_html($feature_data['title']); ?></h3>
                <p class="sg-teaser-description"><?php echo esc_html($feature_data['description']); ?></p>

                <?php if (!empty($feature_data['benefits'])): ?>
                    <ul class="sg-teaser-benefits">
                        <?php foreach ($feature_data['benefits'] as $benefit): ?>
                            <li><?php echo esc_html($benefit); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            <?php else: ?>
                <h3 class="sg-teaser-title">Premium-Feature</h3>
                <p class="sg-teaser-description">
                    <?php echo esc_html($message ?: 'Diese Funktion ist nur für ' . $tier_info['name'] . '-Mitglieder verfügbar.'); ?>
                </p>
            <?php endif; ?>

            <?php if ($show_upgrade): ?>
                <div class="sg-teaser-upgrade">
                    <p class="sg-teaser-tier-info">
                        Verfügbar ab <strong><?php echo esc_html($tier_info['name']); ?></strong>
                        <?php if ($tier_info['price'] > 0): ?>
                            für nur <?php echo esc_html(number_format($tier_info['price'], 2, ',', '.')); ?> €/Monat
                        <?php endif; ?>
                    </p>
                    <a href="<?php echo esc_url($this->get_upgrade_url($required_tier)); ?>" class="sg-button sg-button-primary">
                        Jetzt upgraden
                    </a>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render inline teaser.
     *
     * @param array|null $feature_data  Feature information.
     * @param array      $tier_info     Tier information.
     * @param string     $required_tier Required tier.
     * @param string     $message       Custom message.
     * @param bool       $show_upgrade  Show upgrade button.
     * @return void
     */
    private function render_inline(?array $feature_data, array $tier_info, string $required_tier, string $message, bool $show_upgrade): void {
        ?>
        <div class="sg-teaser sg-teaser-inline">
            <span class="sg-teaser-lock-icon">🔒</span>
            <span class="sg-teaser-text">
                <?php
                if ($message) {
                    echo esc_html($message);
                } elseif ($feature_data) {
                    echo esc_html($feature_data['title']) . ' ist ein ' . esc_html($tier_info['name']) . '-Feature.';
                } else {
                    echo 'Nur für ' . esc_html($tier_info['name']) . '-Mitglieder.';
                }
                ?>
            </span>
            <?php if ($show_upgrade): ?>
                <a href="<?php echo esc_url($this->get_upgrade_url($required_tier)); ?>" class="sg-teaser-upgrade-link">
                    Upgraden
                </a>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render minimal teaser.
     *
     * @param array  $tier_info     Tier information.
     * @param string $required_tier Required tier.
     * @param string $message       Custom message.
     * @param bool   $show_upgrade  Show upgrade button.
     * @return void
     */
    private function render_minimal(array $tier_info, string $required_tier, string $message, bool $show_upgrade): void {
        ?>
        <div class="sg-teaser sg-teaser-minimal">
            <?php echo esc_html($message ?: $tier_info['name'] . ' erforderlich'); ?>
            <?php if ($show_upgrade): ?>
                - <a href="<?php echo esc_url($this->get_upgrade_url($required_tier)); ?>">Upgraden</a>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render modal teaser.
     *
     * @param array|null $feature_data  Feature information.
     * @param array      $tier_info     Tier information.
     * @param string     $required_tier Required tier.
     * @param string     $message       Custom message.
     * @param bool       $show_upgrade  Show upgrade button.
     * @return void
     */
    private function render_modal(?array $feature_data, array $tier_info, string $required_tier, string $message, bool $show_upgrade): void {
        $modal_id = 'sg-upgrade-modal-' . wp_rand();
        ?>
        <div class="sg-teaser sg-teaser-modal-trigger" data-modal="<?php echo esc_attr($modal_id); ?>">
            <div class="sg-teaser-blur-content">
                <div class="sg-teaser-placeholder"></div>
            </div>
            <div class="sg-teaser-overlay">
                <span class="sg-teaser-lock-icon">🔒</span>
                <button type="button" class="sg-button sg-button-light">
                    <?php echo $feature_data ? esc_html($feature_data['title']) . ' freischalten' : 'Feature freischalten'; ?>
                </button>
            </div>
        </div>

        <div id="<?php echo esc_attr($modal_id); ?>" class="sg-modal sg-upgrade-modal" style="display: none;">
            <div class="sg-modal-content">
                <button type="button" class="sg-modal-close">&times;</button>

                <?php if ($feature_data): ?>
                    <div class="sg-modal-icon">
                        <span class="sg-icon sg-icon-<?php echo esc_attr($feature_data['icon']); ?>"></span>
                    </div>
                    <h2><?php echo esc_html($feature_data['title']); ?></h2>
                    <p class="sg-modal-preview"><?php echo esc_html($feature_data['preview']); ?></p>

                    <?php if (!empty($feature_data['benefits'])): ?>
                        <ul class="sg-modal-benefits">
                            <?php foreach ($feature_data['benefits'] as $benefit): ?>
                                <li>
                                    <span class="sg-check-icon">✓</span>
                                    <?php echo esc_html($benefit); ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                <?php else: ?>
                    <h2>Premium-Feature</h2>
                    <p><?php echo esc_html($message ?: 'Diese Funktion erfordert ein Upgrade.'); ?></p>
                <?php endif; ?>

                <?php if ($show_upgrade): ?>
                    <div class="sg-modal-cta">
                        <div class="sg-tier-badge sg-tier-<?php echo esc_attr($required_tier); ?>">
                            <?php echo esc_html($tier_info['name']); ?>
                        </div>
                        <?php if ($tier_info['price'] > 0): ?>
                            <p class="sg-modal-price">
                                Nur <strong><?php echo esc_html(number_format($tier_info['price'], 2, ',', '.')); ?> €</strong>/Monat
                            </p>
                        <?php endif; ?>
                        <a href="<?php echo esc_url($this->get_upgrade_url($required_tier)); ?>" class="sg-button sg-button-primary sg-button-large">
                            Jetzt <?php echo esc_html($tier_info['name']); ?> werden
                        </a>
                        <p class="sg-modal-guarantee">30 Tage Geld-zurück-Garantie</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render comparison table for upgrade.
     *
     * @param string $current_tier Current user tier.
     * @return string HTML.
     */
    public function render_comparison_table(string $current_tier = 'free'): string {
        $all_tiers = $this->tiers->get_all_tiers();

        ob_start();
        ?>
        <div class="sg-tier-comparison">
            <table class="sg-comparison-table">
                <thead>
                    <tr>
                        <th>Feature</th>
                        <?php foreach ($all_tiers as $tier_key => $tier): ?>
                            <th class="<?php echo $tier_key === $current_tier ? 'current-tier' : ''; ?>">
                                <?php echo esc_html($tier['name']); ?>
                                <?php if ($tier['price'] > 0): ?>
                                    <span class="tier-price"><?php echo esc_html(number_format($tier['price'], 2, ',', '.')); ?> €/M</span>
                                <?php else: ?>
                                    <span class="tier-price">Kostenlos</span>
                                <?php endif; ?>
                                <?php if ($tier_key === $current_tier): ?>
                                    <span class="current-badge">Aktuell</span>
                                <?php endif; ?>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>KI-Anfragen/Monat</td>
                        <td>10</td>
                        <td>100</td>
                        <td>Unbegrenzt</td>
                    </tr>
                    <tr>
                        <td>Style-Quiz</td>
                        <td>✓</td>
                        <td>✓</td>
                        <td>✓</td>
                    </tr>
                    <tr>
                        <td>Garderobe-Items</td>
                        <td>20</td>
                        <td>Unbegrenzt</td>
                        <td>Unbegrenzt</td>
                    </tr>
                    <tr>
                        <td>Foto-Analyse</td>
                        <td>—</td>
                        <td>✓</td>
                        <td>✓</td>
                    </tr>
                    <tr>
                        <td>Farbtyp-Analyse</td>
                        <td>—</td>
                        <td>✓</td>
                        <td>✓</td>
                    </tr>
                    <tr>
                        <td>Capsule Wardrobe</td>
                        <td>—</td>
                        <td>✓</td>
                        <td>✓</td>
                    </tr>
                    <tr>
                        <td>Shopping-Assistent</td>
                        <td>—</td>
                        <td>—</td>
                        <td>✓</td>
                    </tr>
                    <tr>
                        <td>Priority Support</td>
                        <td>—</td>
                        <td>—</td>
                        <td>✓</td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <td></td>
                        <?php foreach ($all_tiers as $tier_key => $tier): ?>
                            <td>
                                <?php if ($tier_key !== $current_tier && $tier['price'] >= ($all_tiers[$current_tier]['price'] ?? 0)): ?>
                                    <a href="<?php echo esc_url($this->get_upgrade_url($tier_key)); ?>" class="sg-button sg-button-primary">
                                        Wählen
                                    </a>
                                <?php elseif ($tier_key === $current_tier): ?>
                                    <span class="sg-current-plan">Dein Plan</span>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render upgrade CTA banner.
     *
     * @param string $tier    Target tier.
     * @param array  $options Display options.
     * @return string HTML.
     */
    public function render_upgrade_banner(string $tier = 'premium', array $options = array()): string {
        $tier_info = $this->tiers->get_tier($tier);
        $headline = $options['headline'] ?? 'Upgrade auf ' . $tier_info['name'];
        $subline = $options['subline'] ?? 'Schalte alle Premium-Features frei';

        ob_start();
        ?>
        <div class="sg-upgrade-banner sg-upgrade-banner-<?php echo esc_attr($tier); ?>">
            <div class="sg-banner-content">
                <h3><?php echo esc_html($headline); ?></h3>
                <p><?php echo esc_html($subline); ?></p>
            </div>
            <div class="sg-banner-cta">
                <?php if ($tier_info['price'] > 0): ?>
                    <span class="sg-banner-price">
                        Ab <?php echo esc_html(number_format($tier_info['price'], 2, ',', '.')); ?> €/Monat
                    </span>
                <?php endif; ?>
                <a href="<?php echo esc_url($this->get_upgrade_url($tier)); ?>" class="sg-button sg-button-light">
                    Jetzt upgraden
                </a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get upgrade URL for tier.
     *
     * @param string $tier Target tier.
     * @return string
     */
    private function get_upgrade_url(string $tier): string {
        $product_id = get_option('sg_woocommerce_' . $tier . '_product_id');

        if ($product_id && function_exists('wc_get_checkout_url')) {
            return add_query_arg('add-to-cart', $product_id, wc_get_checkout_url());
        }

        return add_query_arg('tier', $tier, home_url('/mitgliedschaft/'));
    }

    /**
     * Get feature info.
     *
     * @param string $feature Feature key.
     * @return array|null
     */
    public function get_feature_info(string $feature): ?array {
        return $this->feature_info[$feature] ?? null;
    }

    /**
     * Register custom feature info.
     *
     * @param string $feature Feature key.
     * @param array  $info    Feature information.
     * @return void
     */
    public function register_feature_info(string $feature, array $info): void {
        $this->feature_info[$feature] = $info;
    }
}
