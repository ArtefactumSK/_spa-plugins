<?php
/**
 * Shortcode: SPA Child Selector (BULK MODE)
 *
 * Zobrazuje výber dieťaťa s možnosťou hromadnej registrácie
 *
 * Použitie:
 * [spa_child_selector]
 *
 * @package SPA Core
 */

if (!defined('ABSPATH')) exit;

add_shortcode('spa_child_selector', function () {

    if (!is_user_logged_in()) {
        return '<p>Pre registráciu sa prosím prihláste.</p>';
    }

    $current_user = wp_get_current_user();
    $parent_id = (int) $current_user->ID;

    global $wpdb;
    $table = $wpdb->prefix . 'spa_children';

    // Ak je tréner/manager/owner/admin → zobraz VŠETKY deti
    $privileged_roles = ['spa_trainer', 'spa_manager', 'spa_owner', 'administrator'];
    $is_privileged = !empty(array_intersect($privileged_roles, (array) $current_user->roles));

    if ($is_privileged) {
        // Tréner vidí všetky deti
        $children = $wpdb->get_results(
            "SELECT c.id, c.name, c.birthdate, u.user_email as parent_email
            FROM {$table} c
            LEFT JOIN {$wpdb->users} u ON c.parent_id = u.ID
            ORDER BY c.name"
        );
        
        error_log('[SPA CHILD SELECTOR] Privileged user → showing ALL children: ' . count($children));
    } else {
        // Rodič vidí len svoje deti
        $children = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, name, birthdate FROM {$table} WHERE parent_id = %d ORDER BY name",
                $parent_id
            )
        );
        
        error_log('[SPA CHILD SELECTOR] Parent user ID=' . $parent_id . ' → showing OWN children: ' . count($children));
    }

    if (!$children) {
        return '<p>Zatiaľ nemáte pridané žiadne dieťa.</p>';
    }

    ob_start();
    ?>

    <div class="spa-child-selector-wrapper">
        <h3>Vyber deti na registráciu</h3>
        
        <div class="spa-selector-actions">
            <label class="spa-select-all">
                <input type="checkbox" id="spa-select-all-children">
                Vybrať všetky (<?php echo count($children); ?>)
            </label>
            <button type="button" class="spa-clear-selection">Zrušiť výber</button>
        </div>

        <div class="spa-children-list">
            <?php foreach ($children as $child): ?>
                <label class="spa-child-item">
                    <input 
                        type="checkbox" 
                        class="spa-child-checkbox" 
                        data-child-id="<?php echo esc_attr($child->id); ?>"
                        data-child-name="<?php echo esc_attr($child->name); ?>"
                    >
                    
                    <div class="spa-child-info">
                        <strong class="spa-child-name"><?php echo esc_html($child->name); ?></strong>
                        
                        <div class="spa-child-meta">
                            <?php if (!empty($child->birthdate)): ?>
                                <span class="spa-meta-item">
                                    🎂 <?php echo date('d.m.Y', strtotime($child->birthdate)); ?>
                                </span>
                            <?php endif; ?>
                            
                            <?php if ($is_privileged && !empty($child->parent_email)): ?>
                                <span class="spa-meta-item">
                                    👤 <?php echo esc_html($child->parent_email); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </label>
            <?php endforeach; ?>
        </div>

        <div class="spa-selection-summary">
            <p class="spa-selected-count">Vybrané: <strong id="spa-selected-count">0</strong></p>
            <p class="spa-selected-names" id="spa-selected-names"></p>
        </div>
    </div>

    <?php
    return ob_get_clean();
});