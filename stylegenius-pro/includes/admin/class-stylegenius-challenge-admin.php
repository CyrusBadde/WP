<?php
/**
 * StyleGenius Pro Challenge Admin Class
 *
 * Handles challenge management in the admin area
 *
 * @package    StyleGenius_Pro
 * @subpackage StyleGenius_Pro/includes/admin
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Challenge Admin Class
 */
class StyleGenius_Challenge_Admin {

    /**
     * Database instance
     *
     * @var StyleGenius_Database
     */
    private $db;

    /**
     * Challenges instance
     *
     * @var StyleGenius_Challenges
     */
    private $challenges;

    /**
     * Constructor
     */
    public function __construct() {
        $this->db = new StyleGenius_Database();
        $this->challenges = new StyleGenius_Challenges();
    }

    /**
     * Initialize hooks
     */
    public function init() {
        add_action('wp_ajax_sg_create_challenge', array($this, 'ajax_create_challenge'));
        add_action('wp_ajax_sg_update_challenge', array($this, 'ajax_update_challenge'));
        add_action('wp_ajax_sg_delete_challenge', array($this, 'ajax_delete_challenge'));
        add_action('wp_ajax_sg_complete_challenge', array($this, 'ajax_complete_challenge'));
        add_action('wp_ajax_sg_moderate_entry', array($this, 'ajax_moderate_entry'));
    }

    /**
     * Render challenges page
     */
    public function render_page() {
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        $challenge_id = isset($_GET['challenge_id']) ? absint($_GET['challenge_id']) : 0;

        ?>
        <div class="wrap stylegenius-admin">
            <h1>
                <?php _e('Challenges verwalten', 'stylegenius-pro'); ?>
                <?php if ($action === 'list'): ?>
                    <a href="<?php echo add_query_arg('action', 'new'); ?>" class="page-title-action">
                        <?php _e('Neue Challenge erstellen', 'stylegenius-pro'); ?>
                    </a>
                <?php endif; ?>
            </h1>

            <?php
            switch ($action) {
                case 'new':
                    $this->render_challenge_form();
                    break;
                case 'edit':
                    $this->render_challenge_form($challenge_id);
                    break;
                case 'entries':
                    $this->render_entries_list($challenge_id);
                    break;
                case 'list':
                default:
                    $this->render_challenges_list();
                    break;
            }
            ?>
        </div>
        <?php
    }

    /**
     * Render challenges list
     */
    private function render_challenges_list() {
        global $wpdb;

        $tables = $this->db->get_table_names();

        // Get all challenges
        $challenges = $wpdb->get_results(
            "SELECT c.*,
                    (SELECT COUNT(*) FROM {$tables['challenge_entries']} WHERE challenge_id = c.id) as entry_count
             FROM {$tables['challenges']} c
             ORDER BY c.created_at DESC"
        );

        ?>
        <div class="stylegenius-challenges-list">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th class="column-title"><?php _e('Titel', 'stylegenius-pro'); ?></th>
                        <th class="column-type"><?php _e('Typ', 'stylegenius-pro'); ?></th>
                        <th class="column-dates"><?php _e('Zeitraum', 'stylegenius-pro'); ?></th>
                        <th class="column-entries"><?php _e('Teilnahmen', 'stylegenius-pro'); ?></th>
                        <th class="column-status"><?php _e('Status', 'stylegenius-pro'); ?></th>
                        <th class="column-actions"><?php _e('Aktionen', 'stylegenius-pro'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($challenges)): ?>
                        <tr>
                            <td colspan="6"><?php _e('Keine Challenges vorhanden.', 'stylegenius-pro'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($challenges as $challenge): ?>
                            <tr>
                                <td class="column-title">
                                    <strong>
                                        <a href="<?php echo add_query_arg(array('action' => 'edit', 'challenge_id' => $challenge->id)); ?>">
                                            <?php echo esc_html($challenge->title); ?>
                                        </a>
                                    </strong>
                                    <p class="description"><?php echo esc_html(wp_trim_words($challenge->description, 15)); ?></p>
                                </td>
                                <td class="column-type">
                                    <?php echo $this->get_challenge_type_label($challenge->type); ?>
                                </td>
                                <td class="column-dates">
                                    <?php
                                    echo date_i18n('d.m.Y', strtotime($challenge->start_date));
                                    echo ' - ';
                                    echo date_i18n('d.m.Y', strtotime($challenge->end_date));
                                    ?>
                                </td>
                                <td class="column-entries">
                                    <a href="<?php echo add_query_arg(array('action' => 'entries', 'challenge_id' => $challenge->id)); ?>">
                                        <?php echo (int) $challenge->entry_count; ?> <?php _e('Teilnahmen', 'stylegenius-pro'); ?>
                                    </a>
                                </td>
                                <td class="column-status">
                                    <?php echo $this->get_status_badge($challenge->status); ?>
                                </td>
                                <td class="column-actions">
                                    <a href="<?php echo add_query_arg(array('action' => 'edit', 'challenge_id' => $challenge->id)); ?>" class="button button-small">
                                        <?php _e('Bearbeiten', 'stylegenius-pro'); ?>
                                    </a>
                                    <?php if ($challenge->status === 'active'): ?>
                                        <button type="button" class="button button-small sg-complete-challenge" data-id="<?php echo $challenge->id; ?>">
                                            <?php _e('Abschließen', 'stylegenius-pro'); ?>
                                        </button>
                                    <?php endif; ?>
                                    <button type="button" class="button button-small button-link-delete sg-delete-challenge" data-id="<?php echo $challenge->id; ?>">
                                        <?php _e('Löschen', 'stylegenius-pro'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Delete challenge
            $('.sg-delete-challenge').on('click', function() {
                if (!confirm('<?php _e('Challenge wirklich löschen?', 'stylegenius-pro'); ?>')) {
                    return;
                }

                var button = $(this);
                var challengeId = button.data('id');

                $.post(ajaxurl, {
                    action: 'sg_delete_challenge',
                    challenge_id: challengeId,
                    nonce: '<?php echo wp_create_nonce('sg_admin_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        button.closest('tr').fadeOut(function() {
                            $(this).remove();
                        });
                    } else {
                        alert(response.data.message || '<?php _e('Fehler beim Löschen', 'stylegenius-pro'); ?>');
                    }
                });
            });

            // Complete challenge
            $('.sg-complete-challenge').on('click', function() {
                if (!confirm('<?php _e('Challenge jetzt abschließen und Gewinner ermitteln?', 'stylegenius-pro'); ?>')) {
                    return;
                }

                var button = $(this);
                var challengeId = button.data('id');

                $.post(ajaxurl, {
                    action: 'sg_complete_challenge',
                    challenge_id: challengeId,
                    nonce: '<?php echo wp_create_nonce('sg_admin_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data.message || '<?php _e('Fehler beim Abschließen', 'stylegenius-pro'); ?>');
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Render challenge form
     *
     * @param int $challenge_id Challenge ID for editing
     */
    private function render_challenge_form($challenge_id = 0) {
        global $wpdb;

        $tables = $this->db->get_table_names();
        $challenge = null;

        if ($challenge_id > 0) {
            $challenge = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$tables['challenges']} WHERE id = %d",
                    $challenge_id
                )
            );

            if (!$challenge) {
                echo '<div class="notice notice-error"><p>' . __('Challenge nicht gefunden.', 'stylegenius-pro') . '</p></div>';
                return;
            }
        }

        $is_edit = !empty($challenge);
        $form_action = $is_edit ? 'sg_update_challenge' : 'sg_create_challenge';

        ?>
        <form id="sg-challenge-form" class="stylegenius-form">
            <input type="hidden" name="action" value="<?php echo $form_action; ?>">
            <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('sg_admin_nonce'); ?>">
            <?php if ($is_edit): ?>
                <input type="hidden" name="challenge_id" value="<?php echo $challenge->id; ?>">
            <?php endif; ?>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="title"><?php _e('Titel', 'stylegenius-pro'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="title" id="title" class="regular-text"
                               value="<?php echo $is_edit ? esc_attr($challenge->title) : ''; ?>" required>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="description"><?php _e('Beschreibung', 'stylegenius-pro'); ?></label>
                    </th>
                    <td>
                        <textarea name="description" id="description" rows="4" class="large-text"
                                  required><?php echo $is_edit ? esc_textarea($challenge->description) : ''; ?></textarea>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="type"><?php _e('Typ', 'stylegenius-pro'); ?></label>
                    </th>
                    <td>
                        <select name="type" id="type" required>
                            <?php
                            $types = array(
                                'outfit' => __('Outfit-Challenge', 'stylegenius-pro'),
                                'color' => __('Farb-Challenge', 'stylegenius-pro'),
                                'capsule' => __('Capsule-Challenge', 'stylegenius-pro'),
                                'accessory' => __('Accessoire-Challenge', 'stylegenius-pro'),
                                'seasonal' => __('Saison-Challenge', 'stylegenius-pro'),
                                'theme' => __('Themen-Challenge', 'stylegenius-pro'),
                            );
                            foreach ($types as $value => $label):
                                $selected = $is_edit && $challenge->type === $value ? 'selected' : '';
                            ?>
                                <option value="<?php echo $value; ?>" <?php echo $selected; ?>><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="start_date"><?php _e('Startdatum', 'stylegenius-pro'); ?></label>
                    </th>
                    <td>
                        <input type="date" name="start_date" id="start_date"
                               value="<?php echo $is_edit ? date('Y-m-d', strtotime($challenge->start_date)) : date('Y-m-d'); ?>" required>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="end_date"><?php _e('Enddatum', 'stylegenius-pro'); ?></label>
                    </th>
                    <td>
                        <input type="date" name="end_date" id="end_date"
                               value="<?php echo $is_edit ? date('Y-m-d', strtotime($challenge->end_date)) : date('Y-m-d', strtotime('+7 days')); ?>" required>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="min_tier"><?php _e('Mindest-Stufe', 'stylegenius-pro'); ?></label>
                    </th>
                    <td>
                        <select name="min_tier" id="min_tier">
                            <?php
                            $tiers = array(
                                'free' => __('Kostenlos', 'stylegenius-pro'),
                                'premium' => __('Premium', 'stylegenius-pro'),
                                'vip' => __('VIP', 'stylegenius-pro'),
                            );
                            $current_tier = $is_edit ? $challenge->min_tier : 'free';
                            foreach ($tiers as $value => $label):
                                $selected = $current_tier === $value ? 'selected' : '';
                            ?>
                                <option value="<?php echo $value; ?>" <?php echo $selected; ?>><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php _e('Welche Stufe ist mindestens erforderlich, um teilzunehmen?', 'stylegenius-pro'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="points_reward"><?php _e('Punkte-Belohnung', 'stylegenius-pro'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="points_reward" id="points_reward" min="0"
                               value="<?php echo $is_edit ? absint($challenge->points_reward) : 100; ?>">
                        <p class="description"><?php _e('Punkte, die der Gewinner erhält.', 'stylegenius-pro'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="status"><?php _e('Status', 'stylegenius-pro'); ?></label>
                    </th>
                    <td>
                        <select name="status" id="status">
                            <?php
                            $statuses = array(
                                'draft' => __('Entwurf', 'stylegenius-pro'),
                                'scheduled' => __('Geplant', 'stylegenius-pro'),
                                'active' => __('Aktiv', 'stylegenius-pro'),
                            );
                            $current_status = $is_edit ? $challenge->status : 'draft';
                            if ($current_status === 'completed') {
                                $statuses['completed'] = __('Abgeschlossen', 'stylegenius-pro');
                            }
                            foreach ($statuses as $value => $label):
                                $selected = $current_status === $value ? 'selected' : '';
                            ?>
                                <option value="<?php echo $value; ?>" <?php echo $selected; ?>><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="rules"><?php _e('Regeln (optional)', 'stylegenius-pro'); ?></label>
                    </th>
                    <td>
                        <?php
                        $rules = $is_edit && !empty($challenge->rules) ? json_decode($challenge->rules, true) : array();
                        ?>
                        <textarea name="rules" id="rules" rows="5" class="large-text"
                                  placeholder="<?php _e('Eine Regel pro Zeile', 'stylegenius-pro'); ?>"><?php echo esc_textarea(implode("\n", $rules)); ?></textarea>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="featured_image"><?php _e('Challenge-Bild', 'stylegenius-pro'); ?></label>
                    </th>
                    <td>
                        <?php
                        $featured_image = $is_edit && !empty($challenge->featured_image) ? $challenge->featured_image : '';
                        ?>
                        <input type="hidden" name="featured_image" id="featured_image" value="<?php echo esc_attr($featured_image); ?>">
                        <div id="featured-image-preview">
                            <?php if ($featured_image): ?>
                                <img src="<?php echo esc_url($featured_image); ?>" style="max-width: 300px; height: auto;">
                            <?php endif; ?>
                        </div>
                        <button type="button" class="button" id="select-image"><?php _e('Bild auswählen', 'stylegenius-pro'); ?></button>
                        <button type="button" class="button" id="remove-image" <?php echo $featured_image ? '' : 'style="display:none;"'; ?>><?php _e('Bild entfernen', 'stylegenius-pro'); ?></button>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <button type="submit" class="button button-primary">
                    <?php echo $is_edit ? __('Challenge aktualisieren', 'stylegenius-pro') : __('Challenge erstellen', 'stylegenius-pro'); ?>
                </button>
                <a href="<?php echo remove_query_arg(array('action', 'challenge_id')); ?>" class="button">
                    <?php _e('Abbrechen', 'stylegenius-pro'); ?>
                </a>
            </p>
        </form>

        <script>
        jQuery(document).ready(function($) {
            // Media upload
            var frame;
            $('#select-image').on('click', function(e) {
                e.preventDefault();

                if (frame) {
                    frame.open();
                    return;
                }

                frame = wp.media({
                    title: '<?php _e('Challenge-Bild auswählen', 'stylegenius-pro'); ?>',
                    button: {
                        text: '<?php _e('Verwenden', 'stylegenius-pro'); ?>'
                    },
                    multiple: false
                });

                frame.on('select', function() {
                    var attachment = frame.state().get('selection').first().toJSON();
                    $('#featured_image').val(attachment.url);
                    $('#featured-image-preview').html('<img src="' + attachment.url + '" style="max-width: 300px; height: auto;">');
                    $('#remove-image').show();
                });

                frame.open();
            });

            $('#remove-image').on('click', function(e) {
                e.preventDefault();
                $('#featured_image').val('');
                $('#featured-image-preview').html('');
                $(this).hide();
            });

            // Form submission
            $('#sg-challenge-form').on('submit', function(e) {
                e.preventDefault();

                var formData = $(this).serialize();
                var button = $(this).find('button[type="submit"]');
                button.prop('disabled', true);

                $.post(ajaxurl, formData, function(response) {
                    if (response.success) {
                        window.location.href = '<?php echo remove_query_arg(array('action', 'challenge_id')); ?>';
                    } else {
                        alert(response.data.message || '<?php _e('Fehler beim Speichern', 'stylegenius-pro'); ?>');
                        button.prop('disabled', false);
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Render entries list for a challenge
     *
     * @param int $challenge_id Challenge ID
     */
    private function render_entries_list($challenge_id) {
        global $wpdb;

        $tables = $this->db->get_table_names();

        $challenge = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$tables['challenges']} WHERE id = %d",
                $challenge_id
            )
        );

        if (!$challenge) {
            echo '<div class="notice notice-error"><p>' . __('Challenge nicht gefunden.', 'stylegenius-pro') . '</p></div>';
            return;
        }

        $entries = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ce.*, u.display_name, u.user_email
                 FROM {$tables['challenge_entries']} ce
                 LEFT JOIN {$wpdb->users} u ON ce.user_id = u.ID
                 WHERE ce.challenge_id = %d
                 ORDER BY ce.votes DESC, ce.created_at ASC",
                $challenge_id
            )
        );

        ?>
        <div class="stylegenius-entries-list">
            <p>
                <a href="<?php echo remove_query_arg(array('action', 'challenge_id')); ?>" class="button">
                    &larr; <?php _e('Zurück zur Übersicht', 'stylegenius-pro'); ?>
                </a>
            </p>

            <h2><?php echo esc_html($challenge->title); ?></h2>
            <p class="description"><?php echo esc_html($challenge->description); ?></p>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th class="column-rank">#</th>
                        <th class="column-user"><?php _e('Teilnehmer', 'stylegenius-pro'); ?></th>
                        <th class="column-image"><?php _e('Bild', 'stylegenius-pro'); ?></th>
                        <th class="column-votes"><?php _e('Stimmen', 'stylegenius-pro'); ?></th>
                        <th class="column-status"><?php _e('Status', 'stylegenius-pro'); ?></th>
                        <th class="column-date"><?php _e('Eingereicht', 'stylegenius-pro'); ?></th>
                        <th class="column-actions"><?php _e('Aktionen', 'stylegenius-pro'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($entries)): ?>
                        <tr>
                            <td colspan="7"><?php _e('Keine Teilnahmen vorhanden.', 'stylegenius-pro'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php $rank = 1; foreach ($entries as $entry): ?>
                            <tr>
                                <td class="column-rank">
                                    <?php if ($rank <= 3 && $challenge->status === 'completed'): ?>
                                        <span class="sg-rank sg-rank-<?php echo $rank; ?>">
                                            <?php echo $rank; ?>
                                        </span>
                                    <?php else: ?>
                                        <?php echo $rank; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="column-user">
                                    <strong><?php echo esc_html($entry->display_name ?: $entry->user_email); ?></strong>
                                    <br>
                                    <small>ID: <?php echo $entry->user_id; ?></small>
                                </td>
                                <td class="column-image">
                                    <?php if (!empty($entry->image_url)): ?>
                                        <a href="<?php echo esc_url($entry->image_url); ?>" target="_blank">
                                            <img src="<?php echo esc_url($entry->image_url); ?>"
                                                 style="max-width: 100px; height: auto;">
                                        </a>
                                    <?php else: ?>
                                        <span class="dashicons dashicons-format-image" style="font-size: 40px; color: #ccc;"></span>
                                    <?php endif; ?>
                                </td>
                                <td class="column-votes">
                                    <strong><?php echo (int) $entry->votes; ?></strong>
                                </td>
                                <td class="column-status">
                                    <?php echo $this->get_entry_status_badge($entry->status); ?>
                                </td>
                                <td class="column-date">
                                    <?php echo date_i18n('d.m.Y H:i', strtotime($entry->created_at)); ?>
                                </td>
                                <td class="column-actions">
                                    <?php if ($entry->status === 'pending'): ?>
                                        <button type="button" class="button button-small sg-moderate-entry"
                                                data-id="<?php echo $entry->id; ?>" data-action="approve">
                                            <?php _e('Genehmigen', 'stylegenius-pro'); ?>
                                        </button>
                                        <button type="button" class="button button-small button-link-delete sg-moderate-entry"
                                                data-id="<?php echo $entry->id; ?>" data-action="reject">
                                            <?php _e('Ablehnen', 'stylegenius-pro'); ?>
                                        </button>
                                    <?php elseif ($entry->status === 'approved'): ?>
                                        <button type="button" class="button button-small button-link-delete sg-moderate-entry"
                                                data-id="<?php echo $entry->id; ?>" data-action="reject">
                                            <?php _e('Ablehnen', 'stylegenius-pro'); ?>
                                        </button>
                                    <?php elseif ($entry->status === 'rejected'): ?>
                                        <button type="button" class="button button-small sg-moderate-entry"
                                                data-id="<?php echo $entry->id; ?>" data-action="approve">
                                            <?php _e('Genehmigen', 'stylegenius-pro'); ?>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php $rank++; endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <style>
        .sg-rank {
            display: inline-flex;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #fff;
        }
        .sg-rank-1 { background: linear-gradient(135deg, #ffd700, #b8860b); }
        .sg-rank-2 { background: linear-gradient(135deg, #c0c0c0, #808080); }
        .sg-rank-3 { background: linear-gradient(135deg, #cd7f32, #8b4513); }
        </style>

        <script>
        jQuery(document).ready(function($) {
            $('.sg-moderate-entry').on('click', function() {
                var button = $(this);
                var entryId = button.data('id');
                var action = button.data('action');

                $.post(ajaxurl, {
                    action: 'sg_moderate_entry',
                    entry_id: entryId,
                    moderate_action: action,
                    nonce: '<?php echo wp_create_nonce('sg_admin_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data.message || '<?php _e('Fehler', 'stylegenius-pro'); ?>');
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Get challenge type label
     *
     * @param string $type Challenge type
     * @return string Label
     */
    private function get_challenge_type_label($type) {
        $types = array(
            'outfit' => __('Outfit', 'stylegenius-pro'),
            'color' => __('Farbe', 'stylegenius-pro'),
            'capsule' => __('Capsule', 'stylegenius-pro'),
            'accessory' => __('Accessoire', 'stylegenius-pro'),
            'seasonal' => __('Saison', 'stylegenius-pro'),
            'theme' => __('Thema', 'stylegenius-pro'),
        );

        return $types[$type] ?? ucfirst($type);
    }

    /**
     * Get status badge HTML
     *
     * @param string $status Status
     * @return string Badge HTML
     */
    private function get_status_badge($status) {
        $badges = array(
            'draft' => '<span class="sg-badge sg-badge-gray">' . __('Entwurf', 'stylegenius-pro') . '</span>',
            'scheduled' => '<span class="sg-badge sg-badge-blue">' . __('Geplant', 'stylegenius-pro') . '</span>',
            'active' => '<span class="sg-badge sg-badge-green">' . __('Aktiv', 'stylegenius-pro') . '</span>',
            'completed' => '<span class="sg-badge sg-badge-purple">' . __('Abgeschlossen', 'stylegenius-pro') . '</span>',
        );

        return $badges[$status] ?? '<span class="sg-badge">' . ucfirst($status) . '</span>';
    }

    /**
     * Get entry status badge HTML
     *
     * @param string $status Status
     * @return string Badge HTML
     */
    private function get_entry_status_badge($status) {
        $badges = array(
            'pending' => '<span class="sg-badge sg-badge-yellow">' . __('Ausstehend', 'stylegenius-pro') . '</span>',
            'approved' => '<span class="sg-badge sg-badge-green">' . __('Genehmigt', 'stylegenius-pro') . '</span>',
            'rejected' => '<span class="sg-badge sg-badge-red">' . __('Abgelehnt', 'stylegenius-pro') . '</span>',
            'winner' => '<span class="sg-badge sg-badge-gold">' . __('Gewinner', 'stylegenius-pro') . '</span>',
        );

        return $badges[$status] ?? '<span class="sg-badge">' . ucfirst($status) . '</span>';
    }

    /**
     * AJAX: Create challenge
     */
    public function ajax_create_challenge() {
        check_ajax_referer('sg_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Keine Berechtigung', 'stylegenius-pro')));
        }

        global $wpdb;
        $tables = $this->db->get_table_names();

        $title = sanitize_text_field($_POST['title'] ?? '');
        $description = sanitize_textarea_field($_POST['description'] ?? '');
        $type = sanitize_text_field($_POST['type'] ?? 'outfit');
        $start_date = sanitize_text_field($_POST['start_date'] ?? '');
        $end_date = sanitize_text_field($_POST['end_date'] ?? '');
        $min_tier = sanitize_text_field($_POST['min_tier'] ?? 'free');
        $points_reward = absint($_POST['points_reward'] ?? 100);
        $status = sanitize_text_field($_POST['status'] ?? 'draft');
        $rules_text = sanitize_textarea_field($_POST['rules'] ?? '');
        $featured_image = esc_url_raw($_POST['featured_image'] ?? '');

        // Parse rules
        $rules = array_filter(array_map('trim', explode("\n", $rules_text)));

        if (empty($title) || empty($description)) {
            wp_send_json_error(array('message' => __('Titel und Beschreibung sind erforderlich', 'stylegenius-pro')));
        }

        $result = $wpdb->insert(
            $tables['challenges'],
            array(
                'title' => $title,
                'description' => $description,
                'type' => $type,
                'start_date' => $start_date,
                'end_date' => $end_date,
                'min_tier' => $min_tier,
                'points_reward' => $points_reward,
                'status' => $status,
                'rules' => wp_json_encode($rules),
                'featured_image' => $featured_image,
                'created_at' => current_time('mysql'),
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s')
        );

        if ($result === false) {
            wp_send_json_error(array('message' => __('Fehler beim Erstellen', 'stylegenius-pro')));
        }

        wp_send_json_success(array(
            'message' => __('Challenge erstellt', 'stylegenius-pro'),
            'challenge_id' => $wpdb->insert_id,
        ));
    }

    /**
     * AJAX: Update challenge
     */
    public function ajax_update_challenge() {
        check_ajax_referer('sg_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Keine Berechtigung', 'stylegenius-pro')));
        }

        global $wpdb;
        $tables = $this->db->get_table_names();

        $challenge_id = absint($_POST['challenge_id'] ?? 0);

        if (!$challenge_id) {
            wp_send_json_error(array('message' => __('Ungültige Challenge-ID', 'stylegenius-pro')));
        }

        $title = sanitize_text_field($_POST['title'] ?? '');
        $description = sanitize_textarea_field($_POST['description'] ?? '');
        $type = sanitize_text_field($_POST['type'] ?? 'outfit');
        $start_date = sanitize_text_field($_POST['start_date'] ?? '');
        $end_date = sanitize_text_field($_POST['end_date'] ?? '');
        $min_tier = sanitize_text_field($_POST['min_tier'] ?? 'free');
        $points_reward = absint($_POST['points_reward'] ?? 100);
        $status = sanitize_text_field($_POST['status'] ?? 'draft');
        $rules_text = sanitize_textarea_field($_POST['rules'] ?? '');
        $featured_image = esc_url_raw($_POST['featured_image'] ?? '');

        // Parse rules
        $rules = array_filter(array_map('trim', explode("\n", $rules_text)));

        if (empty($title) || empty($description)) {
            wp_send_json_error(array('message' => __('Titel und Beschreibung sind erforderlich', 'stylegenius-pro')));
        }

        $result = $wpdb->update(
            $tables['challenges'],
            array(
                'title' => $title,
                'description' => $description,
                'type' => $type,
                'start_date' => $start_date,
                'end_date' => $end_date,
                'min_tier' => $min_tier,
                'points_reward' => $points_reward,
                'status' => $status,
                'rules' => wp_json_encode($rules),
                'featured_image' => $featured_image,
            ),
            array('id' => $challenge_id),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s'),
            array('%d')
        );

        if ($result === false) {
            wp_send_json_error(array('message' => __('Fehler beim Aktualisieren', 'stylegenius-pro')));
        }

        wp_send_json_success(array('message' => __('Challenge aktualisiert', 'stylegenius-pro')));
    }

    /**
     * AJAX: Delete challenge
     */
    public function ajax_delete_challenge() {
        check_ajax_referer('sg_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Keine Berechtigung', 'stylegenius-pro')));
        }

        global $wpdb;
        $tables = $this->db->get_table_names();

        $challenge_id = absint($_POST['challenge_id'] ?? 0);

        if (!$challenge_id) {
            wp_send_json_error(array('message' => __('Ungültige Challenge-ID', 'stylegenius-pro')));
        }

        // Delete entries first
        $wpdb->delete(
            $tables['challenge_entries'],
            array('challenge_id' => $challenge_id),
            array('%d')
        );

        // Delete challenge
        $result = $wpdb->delete(
            $tables['challenges'],
            array('id' => $challenge_id),
            array('%d')
        );

        if ($result === false) {
            wp_send_json_error(array('message' => __('Fehler beim Löschen', 'stylegenius-pro')));
        }

        wp_send_json_success(array('message' => __('Challenge gelöscht', 'stylegenius-pro')));
    }

    /**
     * AJAX: Complete challenge
     */
    public function ajax_complete_challenge() {
        check_ajax_referer('sg_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Keine Berechtigung', 'stylegenius-pro')));
        }

        $challenge_id = absint($_POST['challenge_id'] ?? 0);

        if (!$challenge_id) {
            wp_send_json_error(array('message' => __('Ungültige Challenge-ID', 'stylegenius-pro')));
        }

        $result = $this->challenges->complete_challenge($challenge_id);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array('message' => __('Challenge abgeschlossen', 'stylegenius-pro')));
    }

    /**
     * AJAX: Moderate entry
     */
    public function ajax_moderate_entry() {
        check_ajax_referer('sg_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Keine Berechtigung', 'stylegenius-pro')));
        }

        global $wpdb;
        $tables = $this->db->get_table_names();

        $entry_id = absint($_POST['entry_id'] ?? 0);
        $action = sanitize_text_field($_POST['moderate_action'] ?? '');

        if (!$entry_id || !in_array($action, array('approve', 'reject'))) {
            wp_send_json_error(array('message' => __('Ungültige Parameter', 'stylegenius-pro')));
        }

        $status = $action === 'approve' ? 'approved' : 'rejected';

        $result = $wpdb->update(
            $tables['challenge_entries'],
            array('status' => $status),
            array('id' => $entry_id),
            array('%s'),
            array('%d')
        );

        if ($result === false) {
            wp_send_json_error(array('message' => __('Fehler beim Moderieren', 'stylegenius-pro')));
        }

        wp_send_json_success(array('message' => __('Status aktualisiert', 'stylegenius-pro')));
    }
}
