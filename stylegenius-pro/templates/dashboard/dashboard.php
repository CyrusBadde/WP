<?php
/**
 * User Dashboard Template
 *
 * @package StyleGenius_Pro
 */

if (!defined('ABSPATH')) {
    exit;
}

$data = $dashboard_data ?? array();
$profile = $data['profile'] ?? array();
$gamification = $data['gamification'] ?? array();
$stats = $data['stats'] ?? array();
?>

<div class="sg-dashboard">
    <!-- Header -->
    <div class="sg-dashboard-header">
        <div class="sg-dashboard-welcome">
            <h1><?php printf(__('Hallo, %s!', 'stylegenius-pro'), esc_html($profile['name'] ?? __('Style-Fan', 'stylegenius-pro'))); ?></h1>
            <p><?php _e('Willkommen in deinem Style-Dashboard', 'stylegenius-pro'); ?></p>
        </div>
        <div class="sg-dashboard-tier">
            <span class="sg-tier-badge sg-tier-badge--<?php echo esc_attr($profile['tier'] ?? 'free'); ?>">
                <?php
                $tier_names = array(
                    'free' => __('Kostenlos', 'stylegenius-pro'),
                    'premium' => __('Premium', 'stylegenius-pro'),
                    'vip' => __('VIP', 'stylegenius-pro'),
                );
                echo esc_html($tier_names[$profile['tier']] ?? $profile['tier']);
                ?>
            </span>
            <?php if (($profile['tier'] ?? 'free') === 'free'): ?>
                <a href="<?php echo home_url('/preise/'); ?>" class="sg-button sg-button--small sg-button--gradient">
                    <?php _e('Upgrade', 'stylegenius-pro'); ?>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="sg-dashboard-stats">
        <div class="sg-stat-card sg-stat-card--points">
            <div class="sg-stat-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                </svg>
            </div>
            <div class="sg-stat-content">
                <span class="sg-stat-value"><?php echo number_format_i18n($gamification['points']['total_points'] ?? 0); ?></span>
                <span class="sg-stat-label"><?php _e('Punkte', 'stylegenius-pro'); ?></span>
            </div>
        </div>

        <div class="sg-stat-card sg-stat-card--level">
            <div class="sg-stat-icon">
                <span class="sg-level-number"><?php echo absint($gamification['level']['level'] ?? 1); ?></span>
            </div>
            <div class="sg-stat-content">
                <span class="sg-stat-value"><?php echo esc_html($gamification['level']['title'] ?? 'Starter'); ?></span>
                <span class="sg-stat-label"><?php _e('Level', 'stylegenius-pro'); ?></span>
                <div class="sg-level-progress-mini">
                    <div class="sg-level-progress-bar" style="width: <?php echo absint($gamification['level_progress'] ?? 0); ?>%;"></div>
                </div>
            </div>
        </div>

        <div class="sg-stat-card sg-stat-card--streak">
            <div class="sg-stat-icon">
                <span class="sg-streak-flame">🔥</span>
            </div>
            <div class="sg-stat-content">
                <span class="sg-stat-value"><?php echo absint($gamification['streak'] ?? 0); ?></span>
                <span class="sg-stat-label"><?php _e('Tage Streak', 'stylegenius-pro'); ?></span>
            </div>
        </div>

        <div class="sg-stat-card sg-stat-card--badges">
            <div class="sg-stat-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="8" r="6"></circle>
                    <path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"></path>
                </svg>
            </div>
            <div class="sg-stat-content">
                <span class="sg-stat-value"><?php echo absint($gamification['badges_count'] ?? 0); ?></span>
                <span class="sg-stat-label"><?php _e('Abzeichen', 'stylegenius-pro'); ?></span>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="sg-dashboard-grid">
        <!-- Style Profile Card -->
        <div class="sg-dashboard-card sg-dashboard-card--profile">
            <div class="sg-card-header">
                <h3><?php _e('Dein Stil-Profil', 'stylegenius-pro'); ?></h3>
                <a href="<?php echo home_url('/style-quiz/'); ?>" class="sg-card-action">
                    <?php echo empty($profile['style_type']) ? __('Quiz starten', 'stylegenius-pro') : __('Quiz wiederholen', 'stylegenius-pro'); ?>
                </a>
            </div>
            <div class="sg-card-content">
                <?php if (!empty($profile['style_type'])): ?>
                    <div class="sg-style-summary">
                        <div class="sg-style-type-badge sg-style-type-badge--<?php echo esc_attr($profile['style_type']); ?>">
                            <?php echo ucfirst(esc_html($profile['style_type'])); ?>
                        </div>
                        <?php if (!empty($profile['color_profile'])): ?>
                            <div class="sg-color-season">
                                <span><?php _e('Farbtyp:', 'stylegenius-pro'); ?></span>
                                <span class="sg-color-badge"><?php echo ucfirst(esc_html($profile['color_profile']['season'] ?? '')); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="sg-empty-state sg-empty-state--small">
                        <p><?php _e('Starte den Style-Quiz, um dein persönliches Profil zu erstellen!', 'stylegenius-pro'); ?></p>
                        <a href="<?php echo home_url('/style-quiz/'); ?>" class="sg-button sg-button--primary">
                            <?php _e('Jetzt starten', 'stylegenius-pro'); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="sg-dashboard-card sg-dashboard-card--actions">
            <div class="sg-card-header">
                <h3><?php _e('Schnellzugriff', 'stylegenius-pro'); ?></h3>
            </div>
            <div class="sg-card-content">
                <div class="sg-quick-actions-grid">
                    <a href="<?php echo home_url('/chat/'); ?>" class="sg-quick-action">
                        <div class="sg-quick-action-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                            </svg>
                        </div>
                        <span><?php _e('Chat', 'stylegenius-pro'); ?></span>
                    </a>
                    <a href="<?php echo home_url('/garderobe/'); ?>" class="sg-quick-action">
                        <div class="sg-quick-action-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                                <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                            </svg>
                        </div>
                        <span><?php _e('Garderobe', 'stylegenius-pro'); ?></span>
                    </a>
                    <a href="<?php echo home_url('/foto-analyse/'); ?>" class="sg-quick-action">
                        <div class="sg-quick-action-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                                <circle cx="12" cy="13" r="4"></circle>
                            </svg>
                        </div>
                        <span><?php _e('Foto-Analyse', 'stylegenius-pro'); ?></span>
                    </a>
                    <a href="<?php echo home_url('/challenges/'); ?>" class="sg-quick-action">
                        <div class="sg-quick-action-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"></path>
                                <path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"></path>
                                <path d="M4 22h16"></path>
                                <path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"></path>
                                <path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"></path>
                                <path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"></path>
                            </svg>
                        </div>
                        <span><?php _e('Challenges', 'stylegenius-pro'); ?></span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Activity Stats -->
        <div class="sg-dashboard-card sg-dashboard-card--activity">
            <div class="sg-card-header">
                <h3><?php _e('Deine Aktivität', 'stylegenius-pro'); ?></h3>
            </div>
            <div class="sg-card-content">
                <div class="sg-activity-stats">
                    <div class="sg-activity-stat">
                        <span class="sg-activity-value"><?php echo absint($stats['wardrobe_items'] ?? 0); ?></span>
                        <span class="sg-activity-label"><?php _e('Garderobe-Teile', 'stylegenius-pro'); ?></span>
                    </div>
                    <div class="sg-activity-stat">
                        <span class="sg-activity-value"><?php echo absint($stats['chat_sessions'] ?? 0); ?></span>
                        <span class="sg-activity-label"><?php _e('Beratungen', 'stylegenius-pro'); ?></span>
                    </div>
                    <div class="sg-activity-stat">
                        <span class="sg-activity-value"><?php echo absint($stats['challenges_entered'] ?? 0); ?></span>
                        <span class="sg-activity-label"><?php _e('Challenges', 'stylegenius-pro'); ?></span>
                    </div>
                    <div class="sg-activity-stat">
                        <span class="sg-activity-value"><?php echo absint($stats['referrals'] ?? 0); ?></span>
                        <span class="sg-activity-label"><?php _e('Empfehlungen', 'stylegenius-pro'); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Badges -->
        <div class="sg-dashboard-card sg-dashboard-card--badges">
            <div class="sg-card-header">
                <h3><?php _e('Deine Abzeichen', 'stylegenius-pro'); ?></h3>
                <a href="<?php echo home_url('/abzeichen/'); ?>" class="sg-card-action"><?php _e('Alle anzeigen', 'stylegenius-pro'); ?></a>
            </div>
            <div class="sg-card-content">
                <?php if (!empty($gamification['badges'])): ?>
                    <div class="sg-badges-preview">
                        <?php
                        $displayed = 0;
                        foreach (array_slice($gamification['badges'], 0, 6) as $badge):
                            $displayed++;
                        ?>
                            <div class="sg-badge-item" title="<?php echo esc_attr($badge['name']); ?>">
                                <?php if (!empty($badge['icon'])): ?>
                                    <img src="<?php echo esc_url($badge['icon']); ?>" alt="">
                                <?php else: ?>
                                    <div class="sg-badge-placeholder">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <circle cx="12" cy="8" r="6"></circle>
                                            <path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"></path>
                                        </svg>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        <?php if (count($gamification['badges']) > 6): ?>
                            <div class="sg-badge-more">+<?php echo count($gamification['badges']) - 6; ?></div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="sg-empty-state sg-empty-state--mini">
                        <p><?php _e('Noch keine Abzeichen verdient', 'stylegenius-pro'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Referral Banner -->
    <div class="sg-dashboard-referral">
        <div class="sg-referral-banner">
            <div class="sg-referral-content">
                <h3><?php _e('Freunde einladen & Belohnungen kassieren!', 'stylegenius-pro'); ?></h3>
                <p><?php _e('Teile StyleGenius mit deinen Freunden und erhalte exklusive Belohnungen.', 'stylegenius-pro'); ?></p>
            </div>
            <a href="<?php echo home_url('/empfehlen/'); ?>" class="sg-button sg-button--secondary">
                <?php _e('Jetzt einladen', 'stylegenius-pro'); ?>
            </a>
        </div>
    </div>
</div>
