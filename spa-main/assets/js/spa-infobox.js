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
    
    // ⭐ TOP-LEVEL GUARD: Koniec ak nie je infobox container
    if (!document.getElementById('spa-infobox-container')) {
        return; // Ticho skonči, žiadne logy, žiadne listenery
    }
    let initialized = false; // ⭐ Flag proti duplicitným inicializáciám
    let listenersAttached = false; // ⭐ Flag proti duplicitným listenerom
    let lastCapacityFree = null;
    let currentState = 0;
    let wizardData = {
        program_id: null,
        city_name: '',
        program_name: '',
        program_age: ''
    };

    document.addEventListener('DOMContentLoaded', function() {
        if (initialized) return; // ⭐ Už inicializované
        initInfobox();
        watchFormChanges();
        initialized = true; // ⭐ Označ ako hotové
    });
    
    // Gravity Forms AJAX callback
    if (typeof jQuery !== 'undefined') {
        jQuery(document).on('gform_post_render', function() {
            if (initialized) return; // ⭐ Už inicializované, nevol znova
            initInfobox();
            watchFormChanges();
            initialized = true; // ⭐ Označ ako hotové
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
            loadInfoboxContent(0);

            // Oneskorené skrytie sekcií (po GF render) - DLHŠÍ timeout kvôli AJAX
            setTimeout(function() {
                updateSectionVisibility();
            }, 1000);

        // Observuj DOM a nastav page break keď sa zobrazí
        const observer = new MutationObserver(() => {
            const btn = document.querySelector('.gform_next_button');
            if (btn) {
                updatePageBreakVisibility();
                observer.disconnect(); // Prestať observovať po prvom nájdení
            }
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });

        console.log('[SPA Infobox] Inicializovaný.');
 }

    /**
     * Obnovenie wizardData z hidden backup polí
     */
    function restoreWizardData() {
        console.log('[SPA Restore] ========== START ==========');
        
        
        
        console.log('[SPA Restore] Backup fields:', {
            cityBackupValue: cityBackup?.value,
            programBackupValue: programBackup?.value
        });
        
        // Ak nemáme žiadne backup hodnoty, ukonči
        if (!cityBackup?.value && !programBackup?.value) {
            console.log('[SPA Restore] No backup values, skipping');
            return;
        }
        
        // Počkaj na načítanie selectov (GF AJAX)
        let attempts = 0;
        const maxAttempts = 20; // 20 * 100ms = 2 sekundy max
        
        const waitForSelects = setInterval(() => {
            attempts++;
            
            const citySelect = document.querySelector(`[name="${spaConfig.fields.spa_city}"]`);
            const programSelect = document.querySelector(`[name="${spaConfig.fields.spa_program}"]`);
            
            // Skontroluj, či majú selecty options
            const cityHasOptions = citySelect && citySelect.options.length > 1;
            const programHasOptions = programSelect && programSelect.options.length > 1;
            
            console.log(`[SPA Restore] Attempt ${attempts}/${maxAttempts}:`, {
                cityExists: !!citySelect,
                cityOptionsCount: citySelect?.options.length,
                programExists: !!programSelect,
                programOptionsCount: programSelect?.options.length
            });
            
            // Ak máme options ALEBO sme skúšali príliš dlho
            if ((cityHasOptions && programHasOptions) || attempts >= maxAttempts) {
                clearInterval(waitForSelects);
                
                if (!cityHasOptions || !programHasOptions) {
                    console.error('[SPA Restore] TIMEOUT - selects still not ready');
                    return;
                }
                
                // OBNOV MESTO
                if (cityBackup?.value && citySelect) {
                    citySelect.value = cityBackup.value;
                    
                    const selectedOption = citySelect.options[citySelect.selectedIndex];
                    if (selectedOption && selectedOption.value) {
                        wizardData.city_name = selectedOption.text;
                        window.spaFormState.city = true;
                        currentState = 1;
                        
                        console.log('[SPA Restore] ✅ City RESTORED:', wizardData.city_name);
                    } else {
                        console.error('[SPA Restore] ❌ City restore FAILED - no option found for value:', cityBackup.value);
                    }
                }
                
                // OBNOV PROGRAM
                if (programBackup?.value && programSelect) {
                    programSelect.value = programBackup.value;
                    
                    const selectedOption = programSelect.options[programSelect.selectedIndex];
                    if (selectedOption && selectedOption.value) {
                        wizardData.program_name = selectedOption.text;
                        wizardData.program_id = selectedOption.getAttribute('data-program-id') || selectedOption.value;
                        window.spaFormState.program = true;
                        
                        // Parsuj vek
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
                        
                        console.log('[SPA Restore] ✅ Program RESTORED:', wizardData.program_name);
                    } else {
                        console.error('[SPA Restore] ❌ Program restore FAILED - no option found for value:', programBackup.value);
                    }
                }
                
                // Načítaj infobox ak máme dáta
                if (currentState > 0) {
                    console.log('[SPA Restore] Loading infobox for state:', currentState);
                    loadInfoboxContent(currentState);
                    updatePageBreakVisibility();
                } else {
                    console.warn('[SPA Restore] ⚠️ currentState is 0, NOT loading infobox');
                }
                
                console.log('[SPA Restore] ========== DONE ==========', {
                    currentState,
                    wizardData,
                    spaFormState: window.spaFormState
                });
            }
        }, 100); // Skúšaj každých 100ms
    }
    /**
 * Ovládanie viditeľnosti GF page break
 */
    function updatePageBreakVisibility() {
        // Počkaj kým sa tlačidlo renderuje
        setTimeout(() => {
            const pageBreakButtons = document.querySelectorAll('.gform_page_footer .gform_next_button');
            
            if (pageBreakButtons.length === 0) {
                console.warn('[SPA Page Break] Tlačidlo ešte nie je v DOM');
                return;
            }
            
            // PODMIENKA: mesto + program + frekvencia
            const isComplete = window.spaFormState.city && 
                              window.spaFormState.program && 
                              window.spaFormState.frequency;
            
            pageBreakButtons.forEach(btn => {
                if (isComplete) {
                    btn.disabled = false;
                    btn.style.display = '';
                    btn.style.opacity = '1';
                    btn.style.pointerEvents = 'auto';
                    btn.style.cursor = 'pointer';
                } else {
                    btn.disabled = true;
                    btn.style.display = 'none'; // ← KRITICKÉ: SKRY TLAČIDLO
                    btn.style.opacity = '0';
                    btn.style.pointerEvents = 'none';
                    btn.style.cursor = 'not-allowed';
                }
            });
            
            console.log('[SPA Page Break]', {
                city: window.spaFormState.city,
                program: window.spaFormState.program,
                frequency: window.spaFormState.frequency,
                enabled: isComplete,
                buttonsFound: pageBreakButtons.length
            });
        }, 200); // Počkaj 200ms na render
    }
    /**
     * Sledovanie zmien vo formulári
     */
    /**
 * Sledovanie zmien vo formulári
 */
function watchFormChanges() {
    // ⭐ GUARD: Zabraň duplicitným event listenerom
    if (listenersAttached) {
        console.log('[SPA Infobox] Listeners already attached, skipping');
        return;
    }
        
    // Sleduj zmenu mesta
    const cityField = document.querySelector(`[name="${spaConfig.fields.spa_city}"]`);
    if (cityField) {
        cityField.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            
            if (this.value && this.value !== '0') {
                wizardData.city_name = selectedOption.text;
                window.spaFormState.city = true;
                currentState = 1;
            } else {
                // Reset - vyčisti všetko
                wizardData.city_name = '';
                wizardData.program_name = '';
                wizardData.program_id = null;
                wizardData.program_age = '';
                wizardData.frequency = '';
                currentState = 0;
                
                // RESET state frekvencie
                window.spaFormState.frequency = false;

                // VYČISTI frekvenčný selector
                const frequencySelector = document.querySelector('.spa-frequency-selector');
                if (frequencySelector) {
                    frequencySelector.innerHTML = '';
                }
            }
            
            loadInfoboxContent(currentState);
            updatePageBreakVisibility();
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
                
                // BACKUP do hidden field
                const backupField = document.querySelector(`[name="${spaConfig.fields.spa_program_backup}"]`);
                if (backupField) {
                    backupField.value = this.value;
                    console.log('[SPA Backup] Program backup set:', this.value);
                } else {
                    console.error('[SPA Backup] Program backup field NOT FOUND!');
                }
                
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
                // RESET PROGRAMU
                wizardData.program_name = '';
                wizardData.program_id = null;
                wizardData.program_age = '';
                window.spaFormState.program = false;
                window.spaFormState.frequency = false;
                currentState = wizardData.city_name ? 1 : 0;
                
                // Vyčisti backup
                const backupField = document.querySelector(`[name="${spaConfig.fields.spa_program_backup}"]`);
                if (backupField) {
                    backupField.value = '';
                }
            }
            // RESET DOM hodnoty frequency fieldu
            const frequencyField = document.querySelector(`[name="${spaConfig.fields.spa_frequency}"]`);
            if (frequencyField) {
                frequencyField.value = '';
                frequencyField.selectedIndex = 0;
            }
            
            loadInfoboxContent(currentState);
            updatePageBreakVisibility();
        });
    } else {
        console.error('[SPA Infobox] Program field NOT FOUND!');
    }
    // Sleduj typ registrácie (Dieťa / Dospelá osoba)
    // POUŽIJ priamy selector pre GF radio
    const registrationTypeFields = document.querySelectorAll('input[name="input_14"]');
    registrationTypeFields.forEach(function(radio) {
        radio.addEventListener('change', function() {
            if (this.checked) {
                console.log('[SPA Section Control] Registration type changed');
                updateSectionVisibility();
            }
        });
    });
    
    // ⭐ OZNAČ, že listenery sú pripojené
    listenersAttached = true;
    console.log('[SPA Infobox] Event listeners attached');
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
        
        // STATE 1: Zobraz mesto v SUMMARY
        if (currentState === 1 && wizardData.city_name) {
            const summaryDiv = document.createElement('div');
            summaryDiv.className = 'spa-infobox-summary';
            
            const locationIcon = icons && icons.location ? icons.location : '';
            
            summaryDiv.innerHTML = `
                <hr>
                <ul class="spa-summary-list">
                    <li class="spa-summary-item spa-summary-city">
                        <span class="spa-summary-icon">${locationIcon}</span>
                        ${wizardData.city_name}
                    </li>
                </ul>
            `;
            
            container.appendChild(summaryDiv);
        }
        
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
        // ⭐ PRIAME OVLÁDANIE NA ZÁKLADE VEKU PROGRAMU (bez spoliehania sa na GF conditional logic)
            setTimeout(() => {
                const isChild = programData.age_min && programData.age_min < 18;

                // ⭐ ZAVOLAJ ZOBRAZENIE SPRÁVNEHO E-MAIL POĽA
                if (typeof handleEmailFieldVisibility === 'function') {
                    handleEmailFieldVisibility(programData.age_min);
                } else {
                    // Fallback – priame nastavenie
                    const childEmailField = document.querySelector('#field_1_15');
                    const adultEmailField = document.querySelector('#field_1_16');
                    
                    if (childEmailField && adultEmailField) {
                        if (isChild) {
                            childEmailField.style.display = '';
                            adultEmailField.style.display = 'none';
                        } else {
                            childEmailField.style.display = 'none';
                            adultEmailField.style.display = '';
                        }
                    }
                }
                
                console.log('[SPA Program Type] Age-based detection:', {
                    age_min: programData.age_min,
                    age_max: programData.age_max,
                    isChild: isChild
                });
                
                // 1. Označ správny radio button
                const childRadio = document.querySelector('input[name="input_14"][value*="Dieťa"], input[name="input_14"]:first-of-type');
                const adultRadio = document.querySelector('input[name="input_14"][value*="Dospelá"], input[name="input_14"]:last-of-type');
                
                if (isChild && childRadio) {
                    childRadio.checked = true;
                    if (adultRadio) adultRadio.checked = false;
                    console.log('[SPA Program Type] Child radio selected');
                } else if (!isChild && adultRadio) {
                    adultRadio.checked = true;
                    if (childRadio) childRadio.checked = false;
                    console.log('[SPA Program Type] Adult radio selected');
                }
                
                // 2. ZOBRAZ/SKRY titulku "Kto bude účastníkom tréningov?"
                const registrationTypeSection = document.querySelector('.gfield--input-type-radio .gfield_label');
                if (registrationTypeSection) {
                    registrationTypeSection.style.display = 'block'; // Vždy zobraz
                    console.log('[SPA Program Type] Registration type label: VISIBLE');
                }
                
                // 3. ENABLE/DISABLE rodného čísla
                const birthNumberField = document.querySelector('input[name="input_8"]');
                const birthNumberWrapper = birthNumberField ? birthNumberField.closest('.gfield') : null;

                if (birthNumberField) {
                    if (isChild) {
                        birthNumberField.disabled = false;
                        birthNumberField.readOnly = false;
                        birthNumberField.style.opacity = '1';
                        birthNumberField.style.pointerEvents = 'auto';
                        birthNumberField.style.backgroundColor = '';
                        
                        if (birthNumberWrapper) {
                            birthNumberWrapper.style.display = '';
                            birthNumberWrapper.style.opacity = '1';
                        }
                        
                        console.log('[SPA Program Type] Birth number: ENABLED (child program)');
                    } else {
                        birthNumberField.disabled = true;
                        birthNumberField.readOnly = true;
                        birthNumberField.value = '';
                        birthNumberField.style.opacity = '0.5';
                        birthNumberField.style.pointerEvents = 'none';
                        birthNumberField.style.backgroundColor = '#f5f5f5';
                        
                        if (birthNumberWrapper) {
                            birthNumberWrapper.style.opacity = '0.5';
                        }
                        
                        console.log('[SPA Program Type] Birth number: DISABLED (adult program)');
                    }
                }
                
                // 4. ZOBRAZ/SKRY sekciu rodiča
                const guardianSection = findSectionByHeading('ÚDAJE O RODIČOVI / ZÁKONNOM ZÁSTUPCOVI');
                if (guardianSection) {
                    toggleSection(guardianSection, isChild);
                    console.log('[SPA Program Type] Guardian section:', isChild ? 'VISIBLE (child program)' : 'HIDDEN (adult program)');
                }
                
            }, 500);
        
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
                window.spaFormState.frequency = true;
                
                // OKAMŽITE AKTUALIZUJ VIDITEĽNOSŤ SEKCIÍ
                setTimeout(() => {
                    updateSectionVisibility();
                }, 100);
            }
            
            // EVENT LISTENER na zmenu frekvencie
            input.addEventListener('change', function() {
                if (this.checked) {
                    window.spaFormState.frequency = true;
                    updatePageBreakVisibility();
                    updateSectionVisibility(); // ← PRIDAJ TENTO RIADOK
                    console.log('[SPA Frequency] Selected:', this.value);
                }
            });
            
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
        // Aktualizuj stav page break po renderi frekvencie
        if (activeFrequencies.length === 1) {
            // Ak je len 1 frekvencia, je automaticky vybraná
            updatePageBreakVisibility();
        }
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
     * ========================================
     * RIADENIE VIDITEĽNOSTI SEKCIÍ
     * ========================================
     */
    function updateSectionVisibility() {
        console.log('[SPA Section Control] Update sections', {
            city: wizardData.city_name,
            program: wizardData.program_name,
            frequency: window.spaFormState.frequency
        });

        // SEKCIA 1: ÚDAJE O ÚČASTNÍKOVI
        const participantSection = findSectionByHeading('ÚDAJE O ÚČASTNÍKOVI TRÉNINGOV');
        
        if (participantSection) {
            const showParticipant = !!(
                wizardData.city_name && 
                wizardData.program_name
            );
            
            toggleSection(participantSection, showParticipant);
            console.log('[SPA Section Control] Participant section:', showParticipant ? 'VISIBLE' : 'HIDDEN');
        }

        // SEKCIA 2: ÚDAJE O RODIČOVI
        const guardianSection = findSectionByHeading('ÚDAJE O RODIČOVI / ZÁKONNOM ZÁSTUPCOVI');
        
        if (guardianSection) {
            // GF používa input_X_Y formát pre radio
            const registrationTypeField = document.querySelector(`input[name="input_14"]:checked`);
            
            let isChild = false;
            
            if (registrationTypeField) {
                const label = registrationTypeField.closest('label') || registrationTypeField.parentElement;
                const labelText = label ? label.textContent.trim().toLowerCase() : '';
                
                // "Dieťa (mladší ako 18 rokov)" → zobraz sekciu
                isChild = labelText.includes('dieťa') || labelText.includes('diet') || labelText.includes('mladš');
            }
            
            toggleSection(guardianSection, isChild);
            console.log('[SPA Section Control] Guardian section:', isChild ? 'VISIBLE (child)' : 'HIDDEN (adult)');
        }
        // SEKCIA 3: RODNÉ ČÍSLO (enable/disable podľa typu)
        const birthNumberField = document.querySelector('input[name*="rodne_cislo"], input[name*="birth_number"], input[placeholder*="rodné číslo"]');

        if (birthNumberField) {
            // Zisti aktuálnu hodnotu spa_registration_type
            const registrationTypeField = document.querySelector('input[name="input_14"]:checked');
            
            let isChild = false;
            
            if (registrationTypeField) {
                const label = registrationTypeField.closest('label') || registrationTypeField.parentElement;
                const labelText = label ? label.textContent.trim().toLowerCase() : '';
                isChild = labelText.includes('dieťa') || labelText.includes('diet') || labelText.includes('mladš');
            }
            
            if (isChild) {
                birthNumberField.disabled = false;
                birthNumberField.style.opacity = '1';
                birthNumberField.style.pointerEvents = 'auto';
                console.log('[SPA Section Control] Birth number field: ENABLED (child)');
            } else {
                birthNumberField.disabled = true;
                birthNumberField.value = '';
                birthNumberField.style.opacity = '0.5';
                birthNumberField.style.pointerEvents = 'none';
                console.log('[SPA Section Control] Birth number field: DISABLED (adult)');
            }
        }
    }

    /**
     * Nájdi sekciu podľa textu nadpisu
     */
    function findSectionByHeading(headingText) {
        const allHeadings = document.querySelectorAll('.gfield--type-section .gsection_title');
        
        for (let heading of allHeadings) {
            if (heading.textContent.trim().includes(headingText)) {
                return heading.closest('.gfield--type-section') || heading.closest('.gfield');
            }
        }
        
        return null;
    }

    /**
     * Zobraz/skry sekciu + všetky nasledujúce polia až po ďalšiu sekciu
     */
    function toggleSection(sectionElement, show) {
        if (!sectionElement) return;
    
        sectionElement.style.display = show ? 'block' : 'none';
    
        let nextElement = sectionElement.nextElementSibling;
    
        while (nextElement) {
            if (nextElement.classList.contains('gfield--type-section')) {
                break;
            }
    
            // Zobraz/skry element
            nextElement.style.display = show ? 'block' : 'none';
            
            // ⭐ ENABLE/DISABLE všetky polia v elemente
            if (show) {
                // Pri zobrazení ENABLE všetky polia
                const inputs = nextElement.querySelectorAll('input:not([name="input_8"]), select, textarea');
                inputs.forEach(input => {
                    input.disabled = false;
                    input.style.opacity = '1';
                    input.style.pointerEvents = 'auto';
                });
            } else {
                // Pri skrytí DISABLE všetky polia
                const inputs = nextElement.querySelectorAll('input:not([name="input_8"]), select, textarea');
                inputs.forEach(input => {
                    input.disabled = true;
                });
            }
            
            nextElement = nextElement.nextElementSibling;
        }
        
        console.log('[SPA toggleSection]', show ? 'ENABLED' : 'DISABLED', 'fields in section');
    }    
})();