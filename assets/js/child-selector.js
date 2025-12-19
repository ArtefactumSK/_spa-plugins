/**
 * SPA Child Selector – Bulk Selection JavaScript
 * 
 * Umožňuje vybrať viac detí naraz pre hromadnú registráciu
 */

(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        
        // Nájdi GF hidden field (field ID 26)
        const childIdField = document.querySelector('input[name="input_26"]');
        
        if (!childIdField) {
            console.error('[SPA] GF field input_26 (child_id) not found!');
            return;
        }

        console.log('[SPA] Bulk child selector initialized');

        // Elements
        const selectAllCheckbox = document.getElementById('spa-select-all-children');
        const clearButton = document.querySelector('.spa-clear-selection');
        const childCheckboxes = document.querySelectorAll('.spa-child-checkbox');
        const selectedCountEl = document.getElementById('spa-selected-count');
        const selectedNamesEl = document.getElementById('spa-selected-names');

        if (!childCheckboxes.length) {
            console.log('[SPA] No child checkboxes found');
            return;
        }

        // Update summary
        function updateSummary() {
            const selected = Array.from(childCheckboxes).filter(cb => cb.checked);
            const count = selected.length;
            const names = selected.map(cb => cb.dataset.childName).join(', ');
            const ids = selected.map(cb => cb.dataset.childId).join(',');

            selectedCountEl.textContent = count;
            selectedNamesEl.textContent = names || 'Žiadne deti nie sú vybrané';

            // Vlož IDs do hidden fieldu (oddelené čiarkou)
            childIdField.value = ids;

            console.log('[SPA] Selected children: ' + count + ' (IDs: ' + ids + ')');
        }

        // Select all
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                childCheckboxes.forEach(cb => {
                    cb.checked = this.checked;
                });
                updateSummary();
            });
        }

        // Clear selection
        if (clearButton) {
            clearButton.addEventListener('click', function() {
                childCheckboxes.forEach(cb => {
                    cb.checked = false;
                });
                if (selectAllCheckbox) {
                    selectAllCheckbox.checked = false;
                }
                updateSummary();
            });
        }

        // Individual checkbox change
        childCheckboxes.forEach(function(checkbox) {
            checkbox.addEventListener('change', function() {
                updateSummary();

                // Update "select all" checkbox state
                if (selectAllCheckbox) {
                    const allChecked = Array.from(childCheckboxes).every(cb => cb.checked);
                    selectAllCheckbox.checked = allChecked;
                }
            });
        });

        // Form submit validation
        const gfForm = document.querySelector('#gform_1');
        
        if (gfForm) {
            gfForm.addEventListener('submit', function(e) {
                const childIds = childIdField.value;

                if (!childIds) {
                    e.preventDefault();
                    alert('⚠️ Prosím vyberte aspoň jedno dieťa zo zoznamu.');
                    console.error('[SPA] Form submit blocked: no children selected');
                    return false;
                }

                console.log('[SPA] Form submitted with child_ids=' + childIds);
            });
        }

        // Initial update
        updateSummary();
    });

})();