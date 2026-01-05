/**
 * SPA Infobox Wizard – Frontend logika
 * CENTRALIZOVANÝ STATE MANAGEMENT
 */

(function() {
    'use strict';

    if (typeof spaConfig === 'undefined') {
        console.error('[SPA Infobox] spaConfig nie je definovaný.');
        return;
    }

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
     * CENTRÁLNE URČENIE CASE
     * Jediný bod rozhodnutia o tom, aký stav sa má zobraziť
     */
    function determineCaseState() {
        // CASE 0: Žiadne mesto
        if (!wizardData.city_name) {
            return 0;
        }
        
        // CASE 1: Mesto vybrané, program NIE
        if (wizardData.city_name && !wizardData.program_name) {
            return 1;
        }
        
        // CASE 2+: Mesto + Program vybrané
        if (wizardData.city_name && wizardData.program_name) {
            return 2;
        }
        
        // Fallback (nemalo by nastať)
        return 0;
    }

    /**
     * CENTRÁLNY UPDATE STAVU
     * Jediná funkcia, ktorá mení currentState a spúšťa render
     */
    function updateInfoboxState() {
        const newState = determineCaseState();
        
        console.log('[SPA Infobox] State transition:', {
            from: currentState,
            to: newState,
            wizardData: wizardData
        });
        
        currentState = newState;
        loadInfoboxContent(currentState);
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
    
        // Vytvor loader, ak ešte neexistuje
        if (!document.getElementById('spa-infobox-loader')) {
            const loaderDiv = document.createElement('div');
            loaderDiv.id = 'spa-infobox-loader';
            loaderDiv.innerHTML = `
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 54.08 92.4">
                    <path d="M36.29,0C-3.91,29.7.49,65.3,32.79,69.8-1.91,69-20.51,38.3,36.29,0Z" fill="#ff1439"/>
                    <path d="M16.99,60.2c2.5,1.8,5.1,1.8,5.6-.2s-1.1-5.1-3.7-7-5.1-1.8-5.6.2,1.1,5.1,3.7,7Z" fill="#ff1439"/>
                    <path d="M16.49,92.4c40.2-29.7,35.8-65.3,3.5-69.8,34.7.8,53.3,31.5-3.5,69.8Z" fill="#ff1439"/>
                    <path d="M48.39,30.5c2.6,1.9,5.1,1.8,5.6-.2s-1.1-5.1-3.7-7-5.1-1.8-5.6.2,1.1,5.1,3.7,7Z" fill="#ff1439"/>
                </svg>
            `;
            infoboxContainer.appendChild(loaderDiv);
        }
    
        // Prvotné načítanie
        updateInfoboxState();
        
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
                
                console.log('[SPA Infobox] City changed:', {
                    value: this.value,
                    text: selectedOption.text
                });
                
                if (this.value && this.value !== '0') {
                    // Mesto vybrané
                    wizardData.city_name = selectedOption.text;
                } else {
                    // Reset mesta → vymaže celý wizard
                    wizardData.city_name = '';
                    wizardData.program_name = '';
                    wizardData.program_id = null;
                    wizardData.program_age = '';
                }
                
                // Centrálny update
                updateInfoboxState();
            });
        }
        
        // Sleduj zmenu programu
        const programField = document.querySelector(`[name="${spaConfig.fields.spa_program}"]`);

        console.log('[SPA Infobox] Program field selector:', `[name="${spaConfig.fields.spa_program}"]`);
        console.log('[SPA Infobox] Program field element:', programField);

        if (programField) {
            programField.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                
                console.log('[SPA Infobox] Program changed:', {
                    value: this.value,
                    text: selectedOption.text
                });
                
                if (this.value) {
                    // Program vybraný
                    wizardData.program_name = selectedOption.text;
                    wizardData.program_id = selectedOption.getAttribute('data-program-id') || this.value;
                    
                    // Parsuj vek z názvu programu
                    const ageMatch = selectedOption.text.match(/(\d+)[–-](\d+)/);
                    if (ageMatch) {
                        wizardData.program_age = ageMatch[1] + '–' + ageMatch[2];
                    } else {
                        const agePlusMatch = selectedOption.text.match(/(\d+)\+/);
                        if (agePlusMatch) {
                            wizardData.program_age = agePlusMatch[1] + '+';
                        } else {
                            wizardData.program_age = '';
                        }
                    }
                } else {
                    // Reset programu - NEMAZEME mesto
                    wizardData.program_name = '';
                    wizardData.program_id = null;
                    wizardData.program_age = '';
                }
                
                // Centrálny update
                updateInfoboxState();
            });
        } else {
            console.error('[SPA Infobox] Program field NOT FOUND!');
        }
    }

    /**
     * Načítanie obsahu infoboxu cez AJAX
     */
    function loadInfoboxContent(state) {
        console.log('[SPA Infobox] Loading content for state:', state, wizardData);
    
        // Zapni loader
        showLoader();
    
        const formData = new FormData();
        formData.append('action', 'spa_get_infobox_content');
        formData.append('program_id', wizardData.program_id || '');
        formData.append('state', state);
        formData.append('city_name', wizardData.city_name);
        formData.append('program_name', wizardData.program_name);
        formData.append('program_age', wizardData.program_age);
    
        fetch(spaConfig.ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            console.log('[SPA Infobox] AJAX Response:', data);
            
            if (data.success) {
                renderInfobox(data.data, data.data.icons, data.data.capacity_free, data.data.price);
            } else {
                console.error('[SPA Infobox] Chyba:', data.data?.message);
                hideLoader();
            }
        })
        .catch(error => {
            console.error('[SPA Infobox] AJAX error:', error);
            hideLoader();
        });
    }

    /**
     * Vykreslenie infoboxu
     */
    function renderInfobox(data, icons, capacityFree, price) {
        console.log('[renderInfobox] Rendering state:', currentState, 'wizardData:', wizardData);
        
        const content = data.content;
        const programData = data.program;
        
        const container = document.getElementById('spa-infobox-container');
        if (!container) return;
    
        // Vyčisti kontajner
        container.innerHTML = '';

        /* ==================================================
        OBSAH – WP stránka (pre CASE 0 a 1)
        ================================================== */
        if (currentState < 2) {
            const contentDiv = document.createElement('div');
            contentDiv.className = 'spa-infobox-content';
            contentDiv.innerHTML = content;
            container.appendChild(contentDiv);
        }
        
        /* ==================================================
        ÚDAJE PROGRAMU (ikona, názov, obsah) - CASE 2+
        ================================================== */
        if (currentState >= 2 && wizardData.program_name && programData) {
            console.log('[renderInfobox] Rendering program data:', programData);
            
            const programDiv = document.createElement('div');
            programDiv.className = 'spa-infobox-program';
            
            let programHtml = '';
            
            // Ikona programu
            if (programData.icon) {
                programHtml += `<div class="spa-program-icon-large">${programData.icon}</div>`;
            }
            
            // Názov programu
            if (programData.title) {
                programHtml += `<h4 class="spa-program-title">${programData.title}</h4>`;
            }
            
            // Obsah CPT
            if (programData.content) {
                programHtml += `<div class="spa-program-content">${programData.content}</div>`;
            }
            
            programDiv.innerHTML = programHtml;
            container.appendChild(programDiv);
        }
        
        /* ==================================================
        DYNAMICKÝ SUMMARY (mesto, program, vek, kapacita, cena) - CASE 1+
        ================================================== */
        if (currentState >= 1 && (wizardData.city_name || wizardData.program_name)) {
            const summaryDiv = document.createElement('div');
            summaryDiv.className = 'spa-infobox-summary';

            let summaryHtml = '<hr><ul class="spa-summary-list">';

            // MESTO (CASE 1+)
            if (wizardData.city_name) {
                const locationIcon = icons && icons.location ? icons.location : '';
                
                summaryHtml += `
                    <li class="spa-summary-item spa-summary-city">
                        <span class="spa-summary-icon">${locationIcon}</span>
                        ${wizardData.city_name}
                    </li>`;
            }

            // PROGRAM (CASE 2+)
            if (currentState >= 2 && wizardData.program_name) {
                const programIconSvg = icons && icons.spa_program ? icons.spa_program : '🎯';

                summaryHtml += `
                    <li class="spa-summary-item spa-summary-program">
                        <span class="spa-summary-icon">${programIconSvg}</span>
                        ${wizardData.program_name}
                    </li>`;
            }

            // VEK (CASE 2+)
            if (currentState >= 2 && wizardData.program_age) {
                const ageLabel = wizardData.program_age.includes('+') ? 'rokov' : 'roky';
                const ageIconSvg = icons && icons.age ? icons.age : '<span class="spa-icon-placeholder">👶</span>';
                
                summaryHtml += `
                    <li class="spa-summary-item spa-summary-age">
                        <span class="spa-summary-icon">${ageIconSvg}</span>
                        <strong>${wizardData.program_age}</strong> ${ageLabel}
                    </li>`;
            }

            // KAPACITA (CASE 2+)
            if (currentState >= 2 && capacityFree !== null && capacityFree !== undefined) {
                const capacityIconSvg = icons && icons.capacity ? icons.capacity : '';
                const capacityLabel = getCapacityLabel(capacityFree);
            
                summaryHtml += `
                    <li class="spa-summary-item spa-summary-capacity">
                        <span class="spa-summary-icon">${capacityIconSvg}</span>
                        <strong>${capacityFree}</strong> ${capacityLabel}
                    </li>`;
            }
            
            // CENA (CASE 2+)
            if (currentState >= 2 && price) {
                const priceIconSvg = icons && icons.price ? icons.price : '€';
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
        // Vypni loader po kompletnom renderi
        hideLoader();
    }

    /**
     * Helper: Gramatika pre kapacitu
     */
    function getCapacityLabel(count) {
        if (count === 1) {
            return 'voľné miesto';
        }
        if (count >= 2 && count <= 4) {
            return 'voľné miesta';
        }
        return 'voľných miest';
    }
/**
 * Zobraz loader
 */
function showLoader() {
    console.log('[SPA LOADER] start');
    const loader = document.getElementById('spa-infobox-loader');
    if (loader) {
        loader.classList.add('active');
    }
}

/**
 * Skry loader
 */
function hideLoader() {
    console.log('[SPA LOADER] end');
    const loader = document.getElementById('spa-infobox-loader');
    if (loader) {
        loader.classList.remove('active');
    }
}
})();