<?php
/**
 * AI Chat Interface Template
 *
 * @package StyleGenius_Pro
 */

if (!defined('ABSPATH')) {
    exit;
}

$chat = $chat ?? new StyleGenius_Chat();
$user_id = get_current_user_id();
$height = $atts['height'] ?? '500px';
$style = $atts['style'] ?? 'full';

// Get usage info
$tiers = new StyleGenius_Tiers();
$user_tier = $tiers->get_user_tier($user_id);
$daily_limit = $tiers->get_feature_limit($user_tier, 'chat_messages');
$used_today = $chat->get_daily_usage($user_id);
$remaining = $daily_limit === -1 ? 999 : max(0, $daily_limit - $used_today);

// Get chat history
$history = $chat->get_recent_history($user_id, 20);

// Get user profile for context
$user = new StyleGenius_User($user_id);
$style_type = $user->get_style_type();
?>

<div class="sg-chat sg-chat--<?php echo esc_attr($style); ?>" id="sg-chat" style="height: <?php echo esc_attr($height); ?>;">
    <!-- Chat Header -->
    <div class="sg-chat-header">
        <div class="sg-chat-header-avatar">
            <div class="sg-avatar sg-avatar--ai">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 2l2 7h7l-5.5 4 2 7L12 16l-5.5 4 2-7L3 9h7z"></path>
                </svg>
            </div>
            <span class="sg-chat-status sg-chat-status--online"></span>
        </div>
        <div class="sg-chat-header-info">
            <h3><?php _e('Style-Beraterin', 'stylegenius-pro'); ?></h3>
            <p><?php _e('Deine persönliche Mode-Expertin', 'stylegenius-pro'); ?></p>
        </div>
        <div class="sg-chat-header-actions">
            <?php if ($daily_limit !== -1): ?>
                <span class="sg-chat-usage" title="<?php _e('Verbleibende Nachrichten heute', 'stylegenius-pro'); ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                    </svg>
                    <span id="sg-chat-remaining"><?php echo $remaining; ?></span>
                </span>
            <?php endif; ?>
            <button type="button" class="sg-chat-clear" id="sg-chat-clear" title="<?php _e('Verlauf löschen', 'stylegenius-pro'); ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 6h18"></path>
                    <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
                    <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
                </svg>
            </button>
        </div>
    </div>

    <!-- Chat Messages -->
    <div class="sg-chat-messages" id="sg-chat-messages">
        <!-- Welcome Message -->
        <div class="sg-chat-message sg-chat-message--assistant sg-chat-message--welcome">
            <div class="sg-chat-message-avatar">
                <div class="sg-avatar sg-avatar--ai">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 2l2 7h7l-5.5 4 2 7L12 16l-5.5 4 2-7L3 9h7z"></path>
                    </svg>
                </div>
            </div>
            <div class="sg-chat-message-content">
                <p>
                    <?php
                    if ($style_type) {
                        printf(
                            __('Hallo! Ich bin deine persönliche Style-Beraterin. Als %s-Typ kann ich dir maßgeschneiderte Empfehlungen geben. Wie kann ich dir heute helfen?', 'stylegenius-pro'),
                            ucfirst($style_type)
                        );
                    } else {
                        _e('Hallo! Ich bin deine persönliche Style-Beraterin. Wie kann ich dir heute helfen? Tipp: Mach zuerst den Style-Quiz für personalisierte Empfehlungen!', 'stylegenius-pro');
                    }
                    ?>
                </p>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="sg-chat-quick-actions" id="sg-chat-quick-actions">
            <p class="sg-chat-quick-label"><?php _e('Häufige Fragen:', 'stylegenius-pro'); ?></p>
            <div class="sg-chat-quick-buttons">
                <button type="button" class="sg-chat-quick-btn" data-message="<?php _e('Was passt zu meinem Stil?', 'stylegenius-pro'); ?>">
                    <?php _e('Was passt zu mir?', 'stylegenius-pro'); ?>
                </button>
                <button type="button" class="sg-chat-quick-btn" data-message="<?php _e('Ich brauche ein Outfit für eine Hochzeit', 'stylegenius-pro'); ?>">
                    <?php _e('Hochzeits-Outfit', 'stylegenius-pro'); ?>
                </button>
                <button type="button" class="sg-chat-quick-btn" data-message="<?php _e('Welche Farben stehen mir?', 'stylegenius-pro'); ?>">
                    <?php _e('Meine Farben', 'stylegenius-pro'); ?>
                </button>
                <button type="button" class="sg-chat-quick-btn" data-message="<?php _e('Business Outfit Tipps', 'stylegenius-pro'); ?>">
                    <?php _e('Business Look', 'stylegenius-pro'); ?>
                </button>
            </div>
        </div>

        <!-- History Messages -->
        <?php if (!empty($history)): ?>
            <?php foreach (array_reverse($history) as $msg): ?>
                <div class="sg-chat-message sg-chat-message--<?php echo esc_attr($msg['role']); ?>">
                    <?php if ($msg['role'] === 'assistant'): ?>
                        <div class="sg-chat-message-avatar">
                            <div class="sg-avatar sg-avatar--ai">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M12 2l2 7h7l-5.5 4 2 7L12 16l-5.5 4 2-7L3 9h7z"></path>
                                </svg>
                            </div>
                        </div>
                    <?php endif; ?>
                    <div class="sg-chat-message-content">
                        <p><?php echo wp_kses_post(nl2br($msg['content'])); ?></p>
                        <span class="sg-chat-message-time">
                            <?php echo human_time_diff(strtotime($msg['created_at']), current_time('timestamp')); ?> <?php _e('her', 'stylegenius-pro'); ?>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Typing Indicator -->
    <div class="sg-chat-typing" id="sg-chat-typing" style="display: none;">
        <div class="sg-chat-message-avatar">
            <div class="sg-avatar sg-avatar--ai">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 2l2 7h7l-5.5 4 2 7L12 16l-5.5 4 2-7L3 9h7z"></path>
                </svg>
            </div>
        </div>
        <div class="sg-chat-typing-indicator">
            <span></span>
            <span></span>
            <span></span>
        </div>
    </div>

    <!-- Chat Input -->
    <div class="sg-chat-input-container">
        <?php if ($remaining > 0 || $daily_limit === -1): ?>
            <form class="sg-chat-form" id="sg-chat-form">
                <div class="sg-chat-input-wrapper">
                    <textarea
                        id="sg-chat-input"
                        class="sg-chat-input"
                        placeholder="<?php _e('Frag mich etwas zum Thema Style...', 'stylegenius-pro'); ?>"
                        rows="1"
                        maxlength="1000"
                    ></textarea>
                    <button type="button" class="sg-chat-upload-btn" id="sg-chat-upload-btn" title="<?php _e('Bild hochladen', 'stylegenius-pro'); ?>">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                            <circle cx="8.5" cy="8.5" r="1.5"></circle>
                            <polyline points="21 15 16 10 5 21"></polyline>
                        </svg>
                    </button>
                    <button type="submit" class="sg-chat-send-btn" id="sg-chat-send" disabled>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="22" y1="2" x2="11" y2="13"></line>
                            <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                        </svg>
                    </button>
                </div>
                <input type="file" id="sg-chat-file-input" accept="image/*" style="display: none;">
            </form>
            <p class="sg-chat-disclaimer">
                <?php _e('KI-generierte Antworten. Für persönliche Beratung kontaktiere einen Experten.', 'stylegenius-pro'); ?>
            </p>
        <?php else: ?>
            <div class="sg-chat-limit-reached">
                <p>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <?php _e('Du hast dein tägliches Nachrichtenlimit erreicht.', 'stylegenius-pro'); ?>
                </p>
                <?php if ($user_tier === 'free'): ?>
                    <a href="<?php echo home_url('/preise/'); ?>" class="sg-button sg-button--primary sg-button--small">
                        <?php _e('Upgrade für unbegrenzte Nachrichten', 'stylegenius-pro'); ?>
                    </a>
                <?php else: ?>
                    <p class="sg-chat-limit-reset"><?php _e('Dein Limit wird morgen zurückgesetzt.', 'stylegenius-pro'); ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Image Preview -->
    <div class="sg-chat-image-preview" id="sg-chat-image-preview" style="display: none;">
        <img src="" alt="" id="sg-chat-preview-img">
        <button type="button" class="sg-chat-image-remove" id="sg-chat-image-remove">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
        </button>
    </div>
</div>

<script>
// Initialize chat data
window.sgChatConfig = {
    userId: <?php echo $user_id; ?>,
    dailyLimit: <?php echo $daily_limit === -1 ? -1 : $daily_limit; ?>,
    remaining: <?php echo $remaining; ?>,
    styleType: '<?php echo esc_js($style_type); ?>',
    hasHistory: <?php echo !empty($history) ? 'true' : 'false'; ?>
};
</script>
