/**
 * SPA Infobox Wizard – Frontend logika
 * CENTRALIZOVANÝ STATE MANAGEMENT
 */

(function() {
    'use strict';
    // GLOBÁLNY STAV FORMULÁRA
    window.spaFormState = {
        city: false,
        program: false,
        frequency: false
    };

    if (typeof spaConfig === 'undefined') {
        console.error('[SPA Infobox] spaConfig nie je definovaný.');
        return;
    }
    if (typeof spaConfig === 'undefined') {
        console.error('[SPA Infobox] spaConfig nie je definovaný.');
        return;
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
     * CENTRÁLNE URČENIE CASE
     */
    function determineCaseState() {
        if (!wizardData.city_name) {
            return 0;
        }
        if (wizardData.city_name && !wizardData.program_name) {
            return 1;
        }
        if (wizardData.city_name && wizardData.program_name) {
            return 2;
        }
        return 0;
    }

    /**
     * CENTRÁLNY UPDATE STAVU
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
    
        // Načítaj úvodný stav
        updateInfoboxState();
        // Inicializuj validáciu krokov
        updateNextButtonState();
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
                    wizardData.city_name = selectedOption.text;
                    window.spaFormState.city = true;
                } else {
                    wizardData.city_name = '';
                    wizardData.program_name = '';
                    wizardData.program_id = null;
                    wizardData.program_age = '';
                    window.spaFormState.city = false;
                    window.spaFormState.program = false;
                    window.spaFormState.frequency = false;
                }
                
                updateInfoboxState();
                updateNextButtonState();
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
                    window.spaFormState.program = true;
                    window.spaFormState.frequency = false; // reset frekvencie
                    
                    console.log('[SPA Infobox] Program ID:', wizardData.program_id);
                    
                    const ageRangeMatch = selectedOption.text.match(/(\d+(?:,\d+)?)\s*[–-]\s*(\d+(?:,\d+)?)/);
                    if (ageRangeMatch) {
                        wizardData.program_age = ageRangeMatch[1] + ' - ' + ageRangeMatch[2];
                    } else {
                        const agePlusMatch = selectedOption.text.match(/(\d+(?:,\d+)?)\+/);
                        if (agePlusMatch) {
                            wizardData.program_age = agePlusMatch[1] + '+';
                        } else {
                            wizardData.program_age = '';
                        }
                    }
                } else {
                    wizardData.program_name = '';
                    wizardData.program_id = null;
                    wizardData.program_age = '';
                    window.spaFormState.program = false;
                    window.spaFormState.frequency = false;
                }
                
                updateInfoboxState();
                updateNextButtonState();
            });
        } else {
            console.error('[SPA Infobox] Program field NOT FOUND!');
        }
    }

    /**
     * Načítanie obsahu infoboxu cez AJAX
     */
    function loadInfoboxContent(state) {
        console.log('[SPA Infobox] Loading state:', state, wizardData);
        showLoader();

        const formData = new FormData();
        formData.append('action', 'spa_get_infobox_content');
        formData.append('program_id', wizardData.program_id);
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
    console.log('[renderInfobox] ========== START ==========');
    console.log('[renderInfobox] State:', currentState);
    console.log('[renderInfobox] wizardData:', JSON.stringify(wizardData));
    console.log('[renderInfobox] programData:', data.program);
    console.log('[renderInfobox] programData.title:', data.program?.title);
    console.log('[renderInfobox] programData.primary_color:', data.program?.primary_color);
    console.log('[renderInfobox] capacityFree:', capacityFree);
    console.log('[renderInfobox] price:', price);
    
    const content = data.content;
    const programData = data.program;
    
    const container = document.getElementById('spa-infobox-container');
    if (!container) {
        hideLoader();
        return;
    }

    // Vyčisti kontajner - OKREM loadera
    const existingLoader = document.getElementById('spa-infobox-loader');
    Array.from(container.children).forEach(child => {
        if (child.id !== 'spa-infobox-loader') {
            child.remove();
        }
    });

    /* ==================================================
    1. OBSAH – WP stránka (SPA Infobox Wizard)
    ================================================== */
    if (!wizardData.program_name) {
        const contentDiv = document.createElement('div');
        contentDiv.className = 'spa-infobox-content';
        contentDiv.innerHTML = content;
        container.appendChild(contentDiv);
        hideLoader();
        return; // Skončiť render pre state 0/1
    }
    
    /* ==================================================
    1.3 ÚDAJE PROGRAMU (ikona, názov, obsah)
    ================================================== */
    if (currentState === 2 && wizardData.program_name && programData) {
        console.log('[renderInfobox] Rendering program data:', programData);
        
        const programDiv = document.createElement('div');
        programDiv.className = 'spa-infobox-program';
        
        let programHtml = '';
        
        // Ikona programu (zväčšená) + aplikácia CSS premenných
        if (programData.icon) {
            const colorStyle = [
                programData.primary_color ? `--program-primary-color: ${programData.primary_color};` : '',
                programData.secondary_color ? `--program-secondary-color: ${programData.secondary_color};` : ''
            ].filter(Boolean).join(' ');
            
            programHtml += `<div class="spa-program-icon-large" style="${colorStyle}">${programData.icon}</div>`;
        }
        
        // VEĽKÝ TEXT VEKU POD SVG
        if (wizardData.program_age) {
            const primaryColor = programData.primary_color || '#6d71b2';
            programHtml += `<div class="spa-age-range-text" style="color: ${primaryColor};">${wizardData.program_age} r.</div>`;
        }
        
        // Názov programu s SPA logom
        if (programData.title) {
            const spaLogoSvg = icons && icons.spa_logo ? icons.spa_logo : '';
            programHtml += `<h4 class="spa-program-title">${spaLogoSvg}${programData.title}</h4>`;
        }
        
        // Obsah CPT (čistý WordPress content)
        if (programData.content) {
            programHtml += `<div class="spa-program-content">${programData.content}</div>`;
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
            const locationIcon = icons && icons.location ? icons.location : '';
            
            let locationText = wizardData.city_name;
            
            if (data.place && currentState === 2) {
                const addressParts = [];
                if (data.place.name) addressParts.push(data.place.name);
                if (data.place.address) addressParts.push(data.place.address);
                
                const cityPart = data.place.city ? `<strong>${data.place.city}</strong>` : wizardData.city_name;
                const addressText = addressParts.filter(Boolean).join(', ');
                
                locationText = addressText ? `${cityPart} • ${addressText}` : cityPart;
            }
            
            summaryHtml += `
                <li class="spa-summary-item spa-summary-city">
                    <span class="spa-summary-icon">${locationIcon}</span>
                    ${locationText}
                </li>`;
        }

        // VEK s ikonou
        if (wizardData.program_age) {
            const ageLabel = wizardData.program_age.includes('+') ? 'rokov' : 'roky';
            const ageIconSvg = icons && icons.age ? icons.age : '<span class="spa-icon-placeholder">👶</span>';
            
            summaryHtml += `
            <li class="spa-summary-item spa-summary-age">
                <span class="spa-summary-icon">${ageIconSvg}</span>
                <strong>${wizardData.program_age}</strong> ${ageLabel}
            </li>`;
        }

        if (currentState === 2 && programData) {
            renderFrequencySelector(programData);
        } else {
            renderFrequencySelector(null);
        }

        // KAPACITA (len v stave 2)
        if (currentState === 2 && wizardData.program_name && capacityFree !== null && capacityFree !== undefined) {                
            const capacityIconSvg = icons && icons.capacity ? icons.capacity : '';
            const capacityLabel = getCapacityLabel(capacityFree);
        
            summaryHtml += `
                <li class="spa-summary-item spa-summary-capacity">
                    <span class="spa-summary-icon">${capacityIconSvg}</span>
                    <strong>${capacityFree}</strong> ${capacityLabel}
                </li>`;
        }            
       
        // CENA (len ak je vybraný program)
        if (price && wizardData.program_name) {
            const priceIconSvg = icons && icons.price ? icons.price : '<span class="spa-icon-placeholder">€</span>';
            const priceFormatted = price.replace(/(\d+\s*€)/g, '<strong>$1</strong>');

            summaryHtml += `
                <li class="spa-summary-item spa-summary-price">
                    <span class="spa-summary-icon">${priceIconSvg}</span>
                    ${priceFormatted}
                </li>`;
        }

        // VEKOVÝ ROZSAH (len v stave 2)
        if (currentState === 2 && wizardData.program_name && data.program) {
            const ageFrom = data.program.age_min;
            const ageTo = data.program.age_max;
            
            let ageText = '';
            
            if (ageFrom && ageTo) {
                ageText = ageFrom.toString().replace('.', ',') + ' - ' + ageTo.toString().replace('.', ',') + ' r.';
            } else if (ageFrom) {
                ageText = ageFrom.toString().replace('.', ',') + '+ r.';
            }
            
            if (ageText) {
                setTimeout(function() {
                    const iconLarge = container.querySelector('.spa-program-icon-large');
                    if (iconLarge) {
                        if (!iconLarge.querySelector('.spa-age-range-text')) {
                            let ageRangeText = container.querySelector('.spa-age-range-text');
                            
                            if (ageRangeText) {
                                ageRangeText.parentElement.removeChild(ageRangeText);
                            } else {
                                ageRangeText = document.createElement('div');
                                ageRangeText.className = 'spa-age-range-text';
                                ageRangeText.textContent = ageText;
                            }

                            const svg = iconLarge.querySelector('svg');
                            if (svg) {
                                if (svg.nextSibling) {
                                    iconLarge.insertBefore(ageRangeText, svg.nextSibling);
                                } else {
                                    iconLarge.appendChild(ageRangeText);
                                }
                            } else {
                                iconLarge.appendChild(ageRangeText);
                            }
                        }
                    }
                }, 0);
            }
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

    // Aplikuj farby na SVG elementy (override inline fill atribútov)
    if (programData && (programData.primary_color || programData.secondary_color)) {
        setTimeout(() => {
            const iconContainer = container.querySelector('.spa-program-icon-large');
            if (iconContainer) {
                const svg = iconContainer.querySelector('svg');
                if (svg) {
                    // Shirt (primary color)
                    const shirtPaths = svg.querySelectorAll('#shirt, #shirt path');
                    shirtPaths.forEach(el => {
                        if (programData.primary_color) {
                            el.style.fill = programData.primary_color;
                        }
                    });
                    
                    // Shirt shadow (tmavšia primary)
                    const shadowPaths = svg.querySelectorAll('#shirt-shadow path');
                    if (programData.primary_color) {
                        shadowPaths.forEach(path => {
                            path.style.fill = `color-mix(in srgb, ${programData.primary_color} 70%, black)`;
                        });
                    }
                    
                    // Shirt highlight (svetlejšia primary)
                    const highlightPaths = svg.querySelectorAll('#shirt-highlight path');
                    if (programData.primary_color) {
                        highlightPaths.forEach(path => {
                            path.style.fill = `color-mix(in srgb, ${programData.primary_color} 70%, white)`;
                        });
                    }
                    
                    // Logo SPA (secondary color)
                    const logoPaths = svg.querySelectorAll('#logoSPA path');
                    if (programData.secondary_color) {
                        logoPaths.forEach(path => {
                            path.style.fill = programData.secondary_color;
                        });
                    }
                }
            }
            
            // Vypni loader AŽ PO aplikácii farieb
            hideLoader();
        }, 100);
    } else {
        // Ak nie sú farby, vypni loader hneď
        hideLoader();
    }
}

    /**
     * Renderovanie frekvenčného selektora
     */
    function renderFrequencySelector(programData) {
        const selector = document.querySelector('.spa-frequency-selector');
        
        if (!selector) {
            console.warn('[SPA Frequency] Selector .spa-frequency-selector nebol nájdený');
            return;
        }

        if (!programData) {
            selector.innerHTML = '';
            window.spaFormState.frequency = false;
            updateNextButtonState();
            return;
        }

        selector.innerHTML = '';
        
        const frequencies = [
            { key: 'spa_price_1x_weekly', label: '1× týždenne' },
            { key: 'spa_price_2x_weekly', label: '2× týždenne' },
            { key: 'spa_price_monthly', label: 'Mesačný paušál' },
            { key: 'spa_price_semester', label: 'Cena za semester' }
        ];
        
        const surcharge = programData.spa_external_surcharge || '';
        const activeFrequencies = [];
        
        frequencies.forEach(freq => {
            const priceRaw = programData[freq.key];
            
            if (!priceRaw || priceRaw === '0' || priceRaw === 0) {
                return;
            }
            
            let finalPrice = parseFloat(priceRaw);
            
            if (surcharge) {
                if (String(surcharge).includes('%')) {
                    const percent = parseFloat(surcharge);
                    finalPrice = finalPrice * (1 + percent / 100);
                } else {
                    finalPrice += parseFloat(surcharge);
                }
            }
            
            finalPrice = Math.round(finalPrice * 100) / 100;
            
            activeFrequencies.push({
                key: freq.key,
                label: freq.label,
                price: finalPrice
            });
        });
        
        if (activeFrequencies.length === 0) {
            const disabledOption = document.createElement('label');
            disabledOption.className = 'spa-frequency-option spa-frequency-disabled';
            disabledOption.innerHTML = `
                <input type="radio" disabled>
                <span>Pre tento program nie je dostupná platná frekvencia</span>
            `;
            selector.appendChild(disabledOption);
            return;
        }
        
        activeFrequencies.forEach((freq, index) => {
            const label = document.createElement('label');
            label.className = 'spa-frequency-option';
            
            const input = document.createElement('input');
            input.type = 'radio';
            input.name = 'spa_frequency';
            input.value = freq.key;
            
            if (activeFrequencies.length === 1) {
                input.checked = true;
            }
            
            const span = document.createElement('span');
            span.textContent = `${freq.label} – ${freq.price.toFixed(2).replace('.', ',')} €`;
            
            label.appendChild(input);
            label.appendChild(span);
            selector.appendChild(label);
        });
        // Skry/zobraz label poľa podľa počtu frekvencií
        setTimeout(() => {
            const gfieldRadio = document.querySelector('.gfield--type-radio');
            if (gfieldRadio) {
                const label = gfieldRadio.querySelector('.gfield_label');
                if (label) {
                    if (activeFrequencies.length <= 1) {
                        label.style.display = 'none';
                    } else {
                        label.style.display = '';
                    }
                    console.log('[SPA Frequency] Label visibility set:', activeFrequencies.length <= 1 ? 'HIDDEN' : 'VISIBLE');
                }
            }
        }, 50);
        // Listener na zmenu frekvencie
        activeFrequencies.forEach((freq) => {
            const radioInput = selector.querySelector(`input[value="${freq.key}"]`);
            if (radioInput) {
                radioInput.addEventListener('change', function() {
                    if (this.checked) {
                        window.spaFormState.frequency = true;
                        updateNextButtonState();
                    }
                });
            }
        });
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

    /**
     * Aktualizácia stavu Next button
     */
    /* function updateNextButtonState() {
        const isStep1Complete = window.spaFormState.city && 
                               window.spaFormState.program && 
                               window.spaFormState.frequency;
        
        const nextButton = document.querySelector('.gform_next_button');
        if (nextButton) {
            if (isStep1Complete) {
                nextButton.disabled = false;
                nextButton.style.opacity = '1';
                nextButton.style.cursor = 'pointer';
            } else {
                nextButton.disabled = true;
                nextButton.style.opacity = '0.5';
                nextButton.style.cursor = 'not-allowed';
            }
        }
        
        console.log('[SPA Step Validation]', {
            city: window.spaFormState.city,
            program: window.spaFormState.program,
            frequency: window.spaFormState.frequency,
            complete: isStep1Complete
        });
    } */

        /**
         * Aktualizácia stavu Next button
         */
        function updateNextButtonState() {
            const nextButton = document.querySelector('.gform_next_button');
            
            // Kontrola: ak button neexistuje (nie sme na správnej stránke), return
            if (!nextButton) {
                return;
            }
            
            const isStep1Complete = window.spaFormState.city && 
                                   window.spaFormState.program && 
                                   window.spaFormState.frequency;
            if (nextButton) {
                // Odstráň starý listener (aby sa neduplikoval)
                const newButton = nextButton.cloneNode(true);
                nextButton.parentNode.replaceChild(newButton, nextButton);
                
                if (isStep1Complete) {
                    newButton.style.opacity = '1';
                    newButton.style.cursor = 'pointer';
                } else {
                    newButton.style.opacity = '0.5';
                    newButton.style.cursor = 'not-allowed';
                    
                    // Prevencia submitu ak nie je kompletné
                    newButton.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        alert('Prosím, vyplňte všetky povinné polia:\n\n• Vyberte mesto\n• Vyberte program\n• Vyberte frekvenciu tréningov');
                        return false;
                    }, true);
                }
            }
            
            console.log('[SPA Step Validation]', {
                city: window.spaFormState.city,
                program: window.spaFormState.program,
                frequency: window.spaFormState.frequency,
                complete: isStep1Complete
            });
        }
})();

