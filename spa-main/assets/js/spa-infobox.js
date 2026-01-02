/**
 * SPA Infobox Wizard – Frontend logika
 */

(function() {
    'use strict';

    if (typeof spaConfig === 'undefined') {
        console.error('[SPA Infobox] spaConfig nie je definovaný.');
        return;
    }
    if (typeof spaConfig === 'undefined') {
        console.error('[SPA Infobox] spaConfig nie je definovaný.');
        return; // ← ZASTAV VYKONÁVANIE
    }
    let lastCapacityFree = null;
    let currentState = 0;
    let wizardData = {
        program_id: null,
        city_name: '',
        program_name: '',
        program_age: ''
    };

    document.addEventListener('DOMContentLoaded', function() {
        initInfobox();
        watchFormChanges();
    });

    // Gravity Forms AJAX callback
    if (typeof jQuery !== 'undefined') {
        jQuery(document).on('gform_post_render', function() {
            initInfobox();
            watchFormChanges();
        });
    }

    /**
     * Inicializácia infoboxu
     */
    function initInfobox() {
        const infoboxContainer = document.getElementById('spa-infobox-container');
        
        if (!infoboxContainer) {
            console.warn('[SPA Infobox] Container nenájdený v DOM.');
            return;
        }

        // Načítaj úvodný stav
        loadInfoboxContent(0);
        
        console.log('[SPA Infobox] Inicializovaný.');
    }

    /**
     * Sledovanie zmien vo formulári
     */
    function watchFormChanges() {
        // Sleduj zmenu mesta
        const cityField = document.querySelector(`[name="${spaConfig.fields.spa_city}"]`);
        if (cityField) {
            cityField.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                
                if (this.value && this.value !== '0') {
                    wizardData.city_name = selectedOption.text;
                    currentState = 1;
                } else {
                    // Reset - vyčisti všetko
                    wizardData.city_name = '';
                    wizardData.program_name = '';
                    wizardData.program_id = null;
                    wizardData.program_age = '';
                    currentState = 0;
                }
                
                loadInfoboxContent(currentState);
            });
        }
        
        // Sleduj zmenu programu
        const programField = document.querySelector(`[name="${spaConfig.fields.spa_program}"]`);

        console.log('[SPA Infobox] Program field selector:', `[name="${spaConfig.fields.spa_program}"]`);
        console.log('[SPA Infobox] Program field element:', programField);

        if (programField) {
            programField.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                
                console.log('[SPA Infobox] Program changed - value:', this.value);
                console.log('[SPA Infobox] Program changed - text:', selectedOption.text);
                
                if (this.value) {
                    wizardData.program_name = selectedOption.text;
                    wizardData.program_id = selectedOption.getAttribute('data-program-id') || this.value;
                    
                    console.log('[SPA Infobox] Program ID:', wizardData.program_id);
                    
                    // Parsuj vek z názvu programu
                    const ageMatch = selectedOption.text.match(/(\d+)[–-](\d+)/);
                    if (ageMatch) {
                        wizardData.program_age = ageMatch[1] + '–' + ageMatch[2];
                    } else {
                        const agePlusMatch = selectedOption.text.match(/(\d+)\+/);
                        if (agePlusMatch) {
                            wizardData.program_age = agePlusMatch[1] + '+';
                        }
                    }
                    
                    currentState = 2;
                    console.log('[SPA Infobox] State changed to 2, wizardData:', wizardData);
                } else {
                    // Reset programu - vráť sa do stavu 1 (mesto) alebo 0
                    wizardData.program_name = '';
                    wizardData.program_id = null;
                    wizardData.program_age = '';
                    currentState = wizardData.city_name ? 1 : 0;
                }
                
                loadInfoboxContent(currentState);
            });
        } else {
            console.error('[SPA Infobox] Program field NOT FOUND!');
        }
    }

    /**
     * Načítanie obsahu infoboxu cez AJAX
     */
    function loadInfoboxContent(state) {
        console.log('[SPA Infobox] Loading state:', state, wizardData); // 🔍 DEBUG

        const formData = new FormData();
        formData.append('action', 'spa_get_infobox_content');
        formData.append('program_id', wizardData.program_id);
        formData.append('state', state);
        formData.append('city_name', wizardData.city_name);
        formData.append('program_name', wizardData.program_name);
        formData.append('program_age', wizardData.program_age);

        // Uložíme si ikonu location pre inline použitie v summary
        let locationIconSvg = null;

        fetch(spaConfig.ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderInfobox(data.data, data.data.icons, data.data.capacity_free, data.data.price);
            } else {
                console.error('[SPA Infobox] Chyba:', data.data?.message);
            }
        })
        .catch(error => {
            console.error('[SPA Infobox] AJAX error:', error);
        });
    }

    /**
     * Vykreslenie infoboxu
     */
    function renderInfobox(data, icons, capacityFree, price) {
        const content = data.content;
        console.log('[renderInfobox]', {
            capacityFree,
            currentState,
            wizardData
        });
        
        const container = document.getElementById('spa-infobox-container');
        if (!container) return;

        // 0. Vyčisti kontajner (JEDINÝ render bod)
        container.innerHTML = '';

        /* ==================================================
        1. OBSAH – WP stránka (SPA Infobox Wizard)
        ================================================== */
        if (!wizardData.program_name) {
            const contentDiv = document.createElement('div');
            contentDiv.className = 'spa-infobox-content';
            contentDiv.innerHTML = content;
            container.appendChild(contentDiv);
        }
        
        /* ==================================================
        1.3 ÚDAJE PROGRAMU (ikona, názov, obsah)
        ================================================== */
        if (currentState === 2 && wizardData.program_name && content.program) {
            const programDiv = document.createElement('div');
            programDiv.className = 'spa-infobox-program';
            
            let programHtml = '';
            
            // Ikona programu (zväčšená)
            if (content.program.icon) {
                programHtml += `<div class="spa-program-icon-large">${content.program.icon}</div>`;
            }
            
            // Názov programu
            programHtml += `<h4 class="spa-program-title">${content.program.title}</h4>`;
            
            // Obsah CPT (čistý WordPress content)
            if (content.program.content) {
                programHtml += `<div class="spa-program-content">${content.program.content}</div>`;
            }
            
            programDiv.innerHTML = programHtml;
            container.appendChild(programDiv);
        }
        /* ==================================================
        1.5 DYNAMICKÝ SUMMARY (mesto, vek, kapacita)
        ================================================== */
        if (wizardData.city_name || wizardData.program_age) {

            const summaryDiv = document.createElement('div');
            summaryDiv.className = 'spa-infobox-summary';

            let summaryHtml = '<hr><ul class="spa-summary-list">';

            // MESTO s inline ikonou
            if (wizardData.city_name) {
                // Získaj location ikonu z už renderovaných ikon
                const locationIcon = icons && icons.location ? icons.location : '';
                
                summaryHtml += `
                    <li class="spa-summary-item spa-summary-city">
                        <span class="spa-summary-icon">${locationIcon}</span>
                        ${wizardData.city_name}
                    </li>`;
            }

            // PROGRAM (len ak je vybraný)
            if (wizardData.program_name) {
                const spa_programIconSvg = icons && icons.spa_program ? icons.spa_program : '🎯';

                summaryHtml += `
                    <li class="spa-summary-item spa-summary-program">
                        <span class="spa-summary-icon">${spa_programIconSvg}</span>
                        ${wizardData.program_name}
                    </li>`;
            }

            // VEK s ikonou (načítanou z témy)
            if (wizardData.program_age) {
                // Gramatika: 8+ = "rokov", 6-8 = "roky"
                const ageLabel = wizardData.program_age.includes('+') ? 'rokov' : 'roky';
                
                // Ikona age (z témy)
                const ageIconSvg = icons && icons.age ? icons.age : '<span class="spa-icon-placeholder">👶</span>';
                
                summaryHtml += `
                    <li class="spa-summary-item spa-summary-age">
                        <span class="spa-summary-icon">${ageIconSvg}</span>
                        <strong>${wizardData.program_age}</strong> ${ageLabel}
                    </li>`;
            }

            // KAPACITA (len v stave 2)
            if (currentState === 2 && wizardData.program_name && capacityFree !== null && capacityFree !== undefined) {            

                const capacityIconSvg = icons && icons.capacity
                    ? icons.capacity
                    : '';
                const capacityLabel = getCapacityLabel(capacityFree);
            
                summaryHtml += `
                    <li class="spa-summary-item spa-summary-capacity">
                        <span class="spa-summary-icon">${capacityIconSvg}</span>
                        <strong>${capacityFree}</strong> ${capacityLabel}
                    </li>`;
            }
            
            // CENA (len ak je vybraný program)
            if (price && wizardData.program_name) {
                const priceIconSvg = icons && icons.price ? icons.price : '€';
                
                // Rozdeľ cenu a kontext (napr. "130 € / 2× týždenne")
                const priceFormatted = price.replace(/(\d+\s*€)/g, '<strong>$1</strong>');

                summaryHtml += `
                    <li class="spa-summary-item spa-summary-price">
                        <span class="spa-summary-icon">${priceIconSvg}</span>
                        ${priceFormatted}
                    </li>`;
            }


            summaryHtml += '</ul>';

            summaryDiv.innerHTML = summaryHtml;
            container.appendChild(summaryDiv);
        }

        function getCapacityLabel(count) {
            if (count === 1) {
                return 'voľné miesto';
            }
            if (count >= 2 && count <= 4) {
                return 'voľné miesta';
            }
            return 'voľných miest';
        }
    }

})();