<?php
/**
 * Style Quiz Template
 *
 * @package StyleGenius_Pro
 */

if (!defined('ABSPATH')) {
    exit;
}

$quiz = $quiz ?? new StyleGenius_Quiz();
$questions = $quiz->get_questions();
$user_id = get_current_user_id();
$existing_result = $user_id ? $quiz->get_user_result($user_id) : null;
?>

<div class="sg-quiz-container" id="sg-quiz" data-show-results="<?php echo esc_attr($atts['show_results']); ?>">
    <?php if ($existing_result && !isset($_GET['retake'])): ?>
        <!-- Existing Result -->
        <div class="sg-quiz-result-existing">
            <div class="sg-quiz-result-header">
                <h2><?php _e('Dein Stil-Profil', 'stylegenius-pro'); ?></h2>
                <p><?php printf(__('Du hast den Quiz am %s abgeschlossen.', 'stylegenius-pro'), date_i18n('d.m.Y', strtotime($existing_result['created_at']))); ?></p>
            </div>

            <div class="sg-style-type-card sg-style-type-card--<?php echo esc_attr($existing_result['style_type']); ?>">
                <div class="sg-style-type-icon">
                    <?php echo $quiz->get_style_icon($existing_result['style_type']); ?>
                </div>
                <h3 class="sg-style-type-name"><?php echo esc_html($quiz->get_style_name($existing_result['style_type'])); ?></h3>
                <p class="sg-style-type-description"><?php echo esc_html($quiz->get_style_description($existing_result['style_type'])); ?></p>
            </div>

            <div class="sg-quiz-actions">
                <a href="?retake=1" class="sg-button sg-button--secondary">
                    <?php _e('Quiz wiederholen', 'stylegenius-pro'); ?>
                </a>
                <a href="<?php echo home_url('/dashboard/'); ?>" class="sg-button sg-button--primary">
                    <?php _e('Zum Dashboard', 'stylegenius-pro'); ?>
                </a>
            </div>
        </div>
    <?php else: ?>
        <!-- Quiz Form -->
        <div class="sg-quiz-intro" id="sg-quiz-intro">
            <div class="sg-quiz-intro-icon">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 2l2 7h7l-5.5 4 2 7L12 16l-5.5 4 2-7L3 9h7z"></path>
                </svg>
            </div>
            <h2><?php _e('Entdecke deinen persönlichen Stil', 'stylegenius-pro'); ?></h2>
            <p><?php _e('Beantworte ein paar kurze Fragen und wir erstellen dein individuelles Stil-Profil.', 'stylegenius-pro'); ?></p>
            <ul class="sg-quiz-intro-benefits">
                <li><?php _e('Nur 2-3 Minuten', 'stylegenius-pro'); ?></li>
                <li><?php _e('Personalisierte Empfehlungen', 'stylegenius-pro'); ?></li>
                <li><?php _e('Dein einzigartiger Stil-Typ', 'stylegenius-pro'); ?></li>
            </ul>
            <button type="button" class="sg-button sg-button--primary sg-button--large" id="sg-quiz-start">
                <?php _e('Quiz starten', 'stylegenius-pro'); ?>
            </button>
        </div>

        <form class="sg-quiz-form" id="sg-quiz-form" style="display: none;">
            <?php wp_nonce_field('stylegenius_quiz', 'sg_quiz_nonce'); ?>

            <!-- Progress Bar -->
            <div class="sg-quiz-progress">
                <div class="sg-quiz-progress-bar">
                    <div class="sg-quiz-progress-fill" id="sg-quiz-progress-fill"></div>
                </div>
                <span class="sg-quiz-progress-text">
                    <span id="sg-quiz-current">1</span> / <?php echo count($questions); ?>
                </span>
            </div>

            <!-- Questions -->
            <?php foreach ($questions as $index => $question): ?>
                <div class="sg-quiz-question" data-question="<?php echo $index; ?>" style="<?php echo $index > 0 ? 'display: none;' : ''; ?>">
                    <h3 class="sg-quiz-question-title"><?php echo esc_html($question['question']); ?></h3>

                    <?php if ($question['type'] === 'single'): ?>
                        <div class="sg-quiz-options sg-quiz-options--single">
                            <?php foreach ($question['options'] as $option_key => $option): ?>
                                <label class="sg-quiz-option">
                                    <input type="radio" name="q<?php echo $index; ?>" value="<?php echo esc_attr($option_key); ?>" required>
                                    <?php if (!empty($option['image'])): ?>
                                        <div class="sg-quiz-option-image">
                                            <img src="<?php echo esc_url($option['image']); ?>" alt="">
                                        </div>
                                    <?php endif; ?>
                                    <span class="sg-quiz-option-text"><?php echo esc_html($option['text']); ?></span>
                                    <span class="sg-quiz-option-check">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                            <polyline points="20 6 9 17 4 12"></polyline>
                                        </svg>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>

                    <?php elseif ($question['type'] === 'multiple'): ?>
                        <div class="sg-quiz-options sg-quiz-options--multiple">
                            <p class="sg-quiz-hint"><?php _e('Du kannst mehrere auswählen', 'stylegenius-pro'); ?></p>
                            <?php foreach ($question['options'] as $option_key => $option): ?>
                                <label class="sg-quiz-option">
                                    <input type="checkbox" name="q<?php echo $index; ?>[]" value="<?php echo esc_attr($option_key); ?>">
                                    <?php if (!empty($option['image'])): ?>
                                        <div class="sg-quiz-option-image">
                                            <img src="<?php echo esc_url($option['image']); ?>" alt="">
                                        </div>
                                    <?php endif; ?>
                                    <span class="sg-quiz-option-text"><?php echo esc_html($option['text']); ?></span>
                                    <span class="sg-quiz-option-check">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                            <polyline points="20 6 9 17 4 12"></polyline>
                                        </svg>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>

                    <?php elseif ($question['type'] === 'image_grid'): ?>
                        <div class="sg-quiz-options sg-quiz-options--images">
                            <?php foreach ($question['options'] as $option_key => $option): ?>
                                <label class="sg-quiz-option sg-quiz-option--image">
                                    <input type="radio" name="q<?php echo $index; ?>" value="<?php echo esc_attr($option_key); ?>" required>
                                    <div class="sg-quiz-option-image">
                                        <img src="<?php echo esc_url($option['image']); ?>" alt="<?php echo esc_attr($option['text']); ?>">
                                        <span class="sg-quiz-option-overlay"><?php echo esc_html($option['text']); ?></span>
                                    </div>
                                    <span class="sg-quiz-option-check">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                            <polyline points="20 6 9 17 4 12"></polyline>
                                        </svg>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>

                    <?php elseif ($question['type'] === 'scale'): ?>
                        <div class="sg-quiz-scale">
                            <input type="range" name="q<?php echo $index; ?>" min="1" max="5" value="3"
                                   class="sg-quiz-range" id="q<?php echo $index; ?>_range">
                            <div class="sg-quiz-scale-labels">
                                <span><?php echo esc_html($question['scale_labels']['min'] ?? ''); ?></span>
                                <span><?php echo esc_html($question['scale_labels']['max'] ?? ''); ?></span>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <!-- Navigation -->
            <div class="sg-quiz-nav">
                <button type="button" class="sg-button sg-button--secondary" id="sg-quiz-prev" style="display: none;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="15 18 9 12 15 6"></polyline>
                    </svg>
                    <?php _e('Zurück', 'stylegenius-pro'); ?>
                </button>
                <button type="button" class="sg-button sg-button--primary" id="sg-quiz-next">
                    <?php _e('Weiter', 'stylegenius-pro'); ?>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </button>
                <button type="submit" class="sg-button sg-button--primary sg-button--gradient" id="sg-quiz-submit" style="display: none;">
                    <?php _e('Auswertung anzeigen', 'stylegenius-pro'); ?>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 2l2 7h7l-5.5 4 2 7L12 16l-5.5 4 2-7L3 9h7z"></path>
                    </svg>
                </button>
            </div>
        </form>

        <!-- Processing -->
        <div class="sg-quiz-processing" id="sg-quiz-processing" style="display: none;">
            <div class="sg-quiz-processing-animation">
                <div class="sg-spinner"></div>
            </div>
            <h3><?php _e('Dein Stil wird analysiert...', 'stylegenius-pro'); ?></h3>
            <p class="sg-quiz-processing-step" id="sg-processing-step"></p>
        </div>

        <!-- Result -->
        <div class="sg-quiz-result" id="sg-quiz-result" style="display: none;">
            <div class="sg-quiz-result-celebration">
                <div class="sg-confetti-container" id="sg-confetti"></div>
            </div>

            <div class="sg-quiz-result-content">
                <h2><?php _e('Dein Stil-Typ', 'stylegenius-pro'); ?></h2>
                <div class="sg-style-type-card" id="sg-result-card">
                    <!-- Filled via JavaScript -->
                </div>

                <div class="sg-quiz-result-details" id="sg-result-details">
                    <!-- Filled via JavaScript -->
                </div>

                <div class="sg-quiz-result-actions">
                    <?php if (is_user_logged_in()): ?>
                        <a href="<?php echo home_url('/dashboard/'); ?>" class="sg-button sg-button--primary">
                            <?php _e('Zum Dashboard', 'stylegenius-pro'); ?>
                        </a>
                        <a href="<?php echo home_url('/chat/'); ?>" class="sg-button sg-button--secondary">
                            <?php _e('Mit Style-Beraterin chatten', 'stylegenius-pro'); ?>
                        </a>
                    <?php else: ?>
                        <a href="<?php echo wp_registration_url(); ?>" class="sg-button sg-button--primary">
                            <?php _e('Kostenlos registrieren', 'stylegenius-pro'); ?>
                        </a>
                        <p class="sg-quiz-result-save-hint">
                            <?php _e('Registriere dich, um dein Ergebnis zu speichern und personalisierte Empfehlungen zu erhalten!', 'stylegenius-pro'); ?>
                        </p>
                    <?php endif; ?>
                </div>

                <div class="sg-quiz-result-share">
                    <h4><?php _e('Teile dein Ergebnis', 'stylegenius-pro'); ?></h4>
                    <div class="sg-share-buttons" id="sg-result-share">
                        <!-- Filled via JavaScript -->
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
