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

    /**
     * Filtrovanie programových options podľa mesta
     */
    function filterProgramsByCity(cityName) {
        const programField = document.querySelector('[name="input_2"]');
        
        if (!programField) {
            console.warn('[SPA Filter] Program field not found');
            return;
        }
        
        if (!window.spaConfig || !window.spaConfig.programCities) {
            console.warn('[SPA Filter] programCities map not available');
            return;
        }
        console.log('[SPA DEBUG] === FILTERING START ===');
        console.log('[SPA DEBUG] Selected city:', cityName);
        console.log('[SPA DEBUG] Program cities map:', window.spaConfig.programCities);
        const options = programField.querySelectorAll('option');
        let visibleCount = 0;
        
        options.forEach(option => {
            const programID = option.value;
            
            if (!programID) {
                option.style.display = '';
                return;
            }
            
            // Získaj mesto pre tento program
            const programCity = window.spaConfig.programCities[programID];
            console.log('[SPA DEBUG] Program ID:', programID, '→ City:', programCity, '| Comparing to:', cityName);
            
            if (!programCity) {
                console.warn('[SPA Filter] No city found for program:', programSlug);
                option.style.display = 'none';
                return;
            }
            
            // Porovnaj mesto
            if (programCity === cityName) {
                option.style.display = '';
                visibleCount++;
            } else {
                option.style.display = 'none';
            }
        });
        
        console.log('[SPA Filter] Filtered for city:', cityName, '- visible programs:', visibleCount);
        
        // Ak žiadne programy, disable select
        programField.disabled = (visibleCount === 0);
        
        if (visibleCount === 0) {
            console.warn('[SPA Filter] No programs available for:', cityName);
        }
    }

    document.addEventListener('DOMContentLoaded', function() {    
        if (initialized) return; // ⭐ Už inicializované
        initInfobox();
        watchFormChanges();
        initialized = true; // ⭐ Označ ako hotové
    });
    
    // Gravity Forms AJAX callback
    if (typeof jQuery !== 'undefined') {
    jQuery(document).on('gform_post_render', function() {
        initInfobox();
        watchFormChanges();
        // ⭐ KRITICKÉ: Skry sekcie AŽ PO GF renderi
        hideAllSectionsOnInit();
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
     * Skrytie všetkých sekcií pri inicializácii
     */
    function hideAllSectionsOnInit() {
        console.log('[SPA Init] ========== HIDING ALL SECTIONS ==========');
        
        // ⭐ GUARD: Neexecutuj ak už boli sekcie skryté
        if (window.spa_sections_hidden) {
            console.log('[SPA Init] Sections already hidden, skipping');
            return;
        }
        
        // Skry pole "Kto bude účastníkom tréningov?"
        const registrationTypeField = document.querySelector('.gfield--input-type-radio');
        if (registrationTypeField) {
            registrationTypeField.style.display = 'none';
            console.log('[SPA Init] Hidden: Registration type field (input_4)');
        }
        
        // ⭐ Skry EMAIL polia
        const childEmailField = document.querySelector('input[name="input_15"]')?.closest('.gfield');
        const adultEmailField = document.querySelector('input[name="input_16"]')?.closest('.gfield');
        
        if (childEmailField) {
            childEmailField.style.display = 'none';
            console.log('[SPA Init] Hidden: Child email field (input_15)');
        }
        if (adultEmailField) {
            adultEmailField.style.display = 'none';
            console.log('[SPA Init] Hidden: Adult email field (input_16)');
        }
        
        // Skry všetky sekcie podľa CSS tried
        const sections = [
            'spa-section-common',
            'spa-section-adult',
            'spa-section-child'
        ];

        sections.forEach(cssClass => {
            // ⭐ Nájdi VŠETKY sekcie s danou triedou (nie len prvú!)
            const allSections = document.querySelectorAll(`.${cssClass}`);
            
            console.log(`[SPA Init] Found ${allSections.length} sections with class: ${cssClass}`);
            
            allSections.forEach(section => {
                toggleSection(section, false);
                console.log(`[SPA Init] ❌ Hidden: ${cssClass} (ID: ${section.id || 'no-id'})`);
            });
        });
        
        // ⭐ Označ že sekcie boli skryté
        window.spa_sections_hidden = true;
        console.log('[SPA Init] ========== SECTIONS HIDDEN COMPLETE ==========');
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
        
        // ⭐ DELEGOVANÝ listener pre mesto (funguje aj po GF rerenderi)
        document.addEventListener('change', function(e) {
            console.log('[SPA DEBUG] Change event:', e.target.name, e.target.value);
            // Skontroluj či ide o city field
            if (e.target.name === 'input_1') {
                console.log('[SPA DEBUG] City field detected!');
                const cityField = e.target;
                const selectedOption = cityField.options[cityField.selectedIndex];
                const selectedCityName = selectedOption ? selectedOption.text.trim() : '';
                
                console.log('[SPA City Change] Selected:', selectedCityName);
                
                // ⭐ VŽDY RESETUJ PROGRAM pri zmene mesta
                wizardData.program_name = '';
                wizardData.program_id = null;
                wizardData.program_age = '';
                wizardData.frequency = '';
                window.spaFormState.program = false;
                window.spaFormState.frequency = false;
                
                // Vyčisti program select
                const programField = document.querySelector('[name="input_2"]');
                if (programField) {
                    programField.value = '';
                    
                    // ⭐ FILTRUJ options podľa mesta
                    if (selectedCityName && selectedCityName.trim() !== '') {
                        filterProgramsByCity(selectedCityName);
                    }
                }
                
                // VYČISTI frekvenčný selector
                const frequencySelector = document.querySelector('.spa-frequency-selector');
                if (frequencySelector) {
                    frequencySelector.innerHTML = '';
                }
                
                // ⭐ VYČISTI VŠETKY POLIA V SEKCIÁCH
                clearAllSectionFields();
                
                if (cityField.value && cityField.value !== '0' && cityField.value !== '') {
                    wizardData.city_name = selectedCityName;
                    window.spaFormState.city = true;
                    currentState = 1;
                } else {
                    // Úplné vymazanie mesta
                    wizardData.city_name = '';
                    window.spaFormState.city = false;
                    currentState = 0;
                }
                
                loadInfoboxContent(currentState);
                updateSectionVisibility();
            }
        });
        
        // ⭐ DEFINUJ programField PRED použitím!
        const programField = document.querySelector(`[name="${spaConfig.fields.spa_program}"]`);
        
        // Sleduj zmenu programu
        if (programField) {
            programField.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                
                console.log('[SPA Infobox] Program changed - value:', this.value);
                console.log('[SPA Infobox] Program changed - text:', selectedOption.text);
                
                if (this.value) {
                    wizardData.program_name = selectedOption.text;
                    wizardData.program_id = selectedOption.getAttribute('data-program-id') || this.value;
                    
                    console.log('[SPA Infobox] Program ID:', wizardData.program_id);
                    
                    // RESET veku pred novým parsovaním
                    wizardData.program_age = '';
                    
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
                    
                    window.spaFormState.program = true;
                    currentState = 2;
                } else {
                    // ⭐ RESET PROGRAMU
                    wizardData.program_name = '';
                    wizardData.program_id = null;
                    wizardData.program_age = '';
                    window.spaFormState.program = false;
                    window.spaFormState.frequency = false;
                    currentState = wizardData.city_name ? 1 : 0;
                    
                    // VYČISTI frekvenčný selector
                    const frequencySelector = document.querySelector('.spa-frequency-selector');
                    if (frequencySelector) {
                        frequencySelector.innerHTML = '';
                    }
                    
                    // ⭐ VYČISTI POLIA
                    clearAllSectionFields();
                }
                
                loadInfoboxContent(currentState);
                
                updateSectionVisibility();
            });
        } else {
            console.error('[SPA Infobox] Program field NOT FOUND!');
        }
        
        
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
          
        // Wrapper pre ikonu/vek
        programHtml += '<div class="spa-program-icon">';
        
        
        // Ikona programu (zväčšená) + aplikácia CSS premenných
        if (programData.icon) {
            const colorStyle = [
                programData.primary_color ? `--program-primary-color: ${programData.primary_color};` : '',
                programData.secondary_color ? `--program-secondary-color: ${programData.secondary_color};` : ''
            ].filter(Boolean).join(' ');
            
            programHtml += `<div class="spa-program-icon-large" style="${colorStyle}">${programData.icon}</div>`;
        }
        else {
            // Pre program bez veku použij &nbsp;, inak zobraz vek
            const ageText = wizardData.program_age ? wizardData.program_age : '&nbsp;';
            programHtml += `<div class="spa-age-range-text no-svg-icon">${ageText}</div>`;
        }
        
        // VEĽKÝ TEXT VEKU POD SVG
        if (wizardData.program_age) {
            const primaryColor = programData.primary_color || '#6d71b2';
            programHtml += `<div class="spa-age-range-text" style="color: ${primaryColor};">${wizardData.program_age} r.</div>`;
        }
        programHtml += '</div>'; // .spa-program-icon
        // Názov programu s SPA logom
        if (programData.title) {
            const spaLogoSvg = icons && icons.spa_logo ? icons.spa_logo : '';
            programHtml += `<h4 class="spa-program-title">${spaLogoSvg}${programData.title}</h4>`;
        }
        
        // Obsah CPT (čistý WordPress content)
        if (programData.content) {
            programHtml += `<div class="spa-program-content">${programData.content}</div>`;
        }
        // ⭐ Len pre-označenie radio buttonu podľa veku (BEZ zobrazenia sekcií!)
        setTimeout(() => {
            const isChild = programData.age_min && programData.age_min < 18;
            
            console.log('[SPA Program Type] Age-based detection:', {
                age_min: programData.age_min,
                age_max: programData.age_max,
                isChild: isChild
            });
            
            // ⭐ RODNÉ ČÍSLO - ulož info o type programu
            const birthNumberField = document.querySelector('input[name="input_8"]');
            const birthNumberWrapper = birthNumberField ? birthNumberField.closest('.gfield') : null;
        
            if (birthNumberField && birthNumberWrapper) {
                // Vždy SKRY pri prvotnom výbere programu
                birthNumberWrapper.style.display = 'none';
                birthNumberField.setAttribute('data-is-child', isChild ? 'true' : 'false');
                console.log('[SPA] Saved program type:', isChild ? 'CHILD' : 'ADULT');
            }
            
            // ⭐ NEOZNAČUJ RADIO BUTTONY – to sa spraví až v updateSectionVisibility()
        }, 100);
        
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
            } else {
                ageText = '';
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
                
                // ⭐ OKAMŽITE AKTUALIZUJ VIDITEĽNOSŤ SEKCIÍ + PAGE BREAK
                setTimeout(() => {
                    updateSectionVisibility();
                    
                }, 150);
            }
            
            // EVENT LISTENER na zmenu frekvencie
            input.addEventListener('change', function() {
                if (this.checked) {
                    window.spaFormState.frequency = true;
                    
                    updateSectionVisibility(); 
                    console.log('[SPA Frequency] Selected:', this.value);
                }
            });
            
            const span = document.createElement('span');
            span.textContent = `${freq.label} – ${freq.price.toFixed(2).replace('.', ',')} €`;
            
            label.appendChild(input);
            label.appendChild(span);
            selector.appendChild(label);
        });
        // Aktualizuj stav page break po renderi frekvencie
        if (activeFrequencies.length === 1) {
            // Ak je len 1 frekvencia, je automaticky vybraná
            
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
 * RIADENIE VIDITEĽNOSTI SEKCIÍ
 */
    function updateSectionVisibility() {
        console.log('[SPA Section Control] ========== UPDATE START ==========');
        console.log('[SPA Section Control] State:', {
            city: wizardData.city_name,
            program: wizardData.program_name,
            frequency: window.spaFormState.frequency
        });
    
        // ⭐ DVE RÔZNE PODMIENKY:
        // 1. Pre zobrazenie poľa input_4: mesto + program (neprázdne hodnoty!)
        const programSelected = !!(
            wizardData.city_name && 
            wizardData.city_name.trim() !== '' &&
            wizardData.program_name && 
            wizardData.program_name.trim() !== ''
        );
        
        // 2. Pre zobrazenie sekcií: mesto + program + frekvencia
        const allSelected = !!(
            wizardData.city_name && 
            wizardData.program_name && 
            window.spaFormState.frequency
        );
    
        console.log('[SPA Section Control] Conditions:', {
            programSelected,
            allSelected
        });
    
        // ⭐ ZÍSKAJ age_min priamo z programu (nie z data atribútu)
        let isChildProgram = false;

        // ⭐ Deklaruj birthNumberField na začiatku (potrebné neskôr)
        const birthNumberField = document.querySelector('input[name="input_8"]');

        // Pokús sa získať age_min z vybraného programu
        const programField = document.querySelector(`[name="${spaConfig.fields.spa_program}"]`);
        if (programField && programField.value) {
            const selectedOption = programField.options[programField.selectedIndex];
            const ageMin = parseInt(selectedOption.getAttribute('data-age-min'));
            
            if (!isNaN(ageMin)) {
                isChildProgram = ageMin < 18;
            }
        }

        // Fallback: skontroluj data-is-child atribút
        if (!programField || !programField.value) {
            isChildProgram = birthNumberField?.getAttribute('data-is-child') === 'true';
        }

        console.log('[SPA Section Control] Program type:', {
            isChildProgram,
            source: programField?.value ? 'age_min' : 'data-is-child'
        });

        // ⭐ ZAPÍŠ RESOLVED TYPE DO HIDDEN FIELD (input_34)
        const resolvedTypeField = document.querySelector('input[name="input_34"]');
        if (resolvedTypeField) {
            const resolvedValue = isChildProgram ? 'child' : 'adult';
            resolvedTypeField.value = resolvedValue;
            console.log('[SPA Section Control] Resolved type written to input_34:', resolvedValue);
        } else {
            console.warn('[SPA Section Control] ⚠️ Hidden field input_34 NOT FOUND');
        }
    
        // ⭐ POLE "Kto bude účastníkom tréningov?" (input_4)
        // Zobrazuje sa hneď po výbere PROGRAMU (nie až po frekvencii)
        const registrationTypeField = document.querySelector('.gfield--input-type-radio');
        
        if (registrationTypeField) {
            if (programSelected) {  // ← ZMENA: stačí program
                // 1. NAJPRV ZOBRAZ pole
                registrationTypeField.style.display = '';
                console.log('[SPA Section Control] Registration type field: VISIBLE (program selected)');
                
                // 2. ⭐ POČKAJ NA RENDER a POTOM OZNAČ radio button
                setTimeout(() => {
                    // Nájdi všetky radio buttony
                    const allRadios = document.querySelectorAll('input[name="input_4"]');
                    
                    console.log('[SPA Section Control] Found radio buttons:', allRadios.length);
                    
                    let childRadio = null;
                    let adultRadio = null;
                    
                    // Identifikuj ich podľa value/label
                    allRadios.forEach(radio => {
                        const value = radio.value.toLowerCase();
                        const label = radio.parentElement?.textContent?.toLowerCase() || '';
                        
                        if (value.includes('dieťa') || value.includes('diet') || 
                            label.includes('dieťa') || label.includes('diet') || 
                            label.includes('mladší')) {
                            childRadio = radio;
                        }
                        
                        if (value.includes('dospel') || value.includes('18+') || 
                            label.includes('dospel') || label.includes('18+')) {
                            adultRadio = radio;
                        }
                    });
                    
                    console.log('[SPA Section Control] Radio identification:', {
                        childRadio: !!childRadio,
                        adultRadio: !!adultRadio,
                        isChildProgram: isChildProgram
                    });
                    
                    // OZNAČ správny radio button a DISABLE druhý
                    if (isChildProgram && childRadio) {
                        childRadio.checked = true;
                        childRadio.disabled = false;
                        if (adultRadio) {
                            adultRadio.checked = false;
                            adultRadio.disabled = true; // ⭐ DISABLE adult pre CHILD program
                        }
                        console.log('[SPA Section Control] ✅ CHILD radio CHECKED, ADULT disabled');
                    } else if (!isChildProgram && adultRadio) {
                        adultRadio.checked = true;
                        adultRadio.disabled = false;
                        if (childRadio) {
                            childRadio.checked = false;
                            childRadio.disabled = true; // ⭐ DISABLE child pre ADULT program
                        }
                        console.log('[SPA Section Control] ✅ ADULT radio CHECKED, CHILD disabled');
                    }
                }, 100);
                
            } else {
                // Skry pole ak nie je program vybraný
                registrationTypeField.style.display = 'none';
                
                // ⭐ ENABLE všetky radio buttony pri resete
                const allRadios = document.querySelectorAll('input[name="input_4"]');
                allRadios.forEach(radio => {
                    radio.checked = false;
                    radio.disabled = false; // ⭐ ENABLE pri skrytí
                });
                
                console.log('[SPA Section Control] Registration type field: HIDDEN (no program)');
            }
        }
    
        // ⭐ EMAIL POLIA - zobrazujú sa až po výbere FREKVENCIE
        const childEmailInput = document.querySelector('input[name="input_15"]');
        const adultEmailInput = document.querySelector('input[name="input_16"]');

        const childEmailField = childEmailInput?.closest('.gfield');
        const adultEmailField = adultEmailInput?.closest('.gfield');

        console.log('[SPA Email Control] Fields found:', {
            childEmailInput: !!childEmailInput,
            adultEmailInput: !!adultEmailInput,
            childEmailField: !!childEmailField,
            adultEmailField: !!adultEmailField,
            isChildProgram: isChildProgram,
            allSelected: allSelected
        });

        if (allSelected) {
            if (isChildProgram) {
                // CHILD program: zobraz LEN input_15
                if (childEmailField && childEmailInput) {
                    childEmailField.style.display = '';
                    childEmailField.style.visibility = 'visible';
                    childEmailField.style.opacity = '1';
                    childEmailInput.disabled = false;
                    childEmailInput.style.display = '';
                    console.log('[SPA Section Control] ✅ Email CHILD (input_15) VISIBLE');
                }
                if (adultEmailField && adultEmailInput) {
                    adultEmailField.style.display = 'none';
                    adultEmailField.style.visibility = 'hidden';
                    adultEmailField.style.opacity = '0';
                    adultEmailInput.disabled = true;
                    adultEmailInput.value = ''; // Vyčisti hodnotu
                    console.log('[SPA Section Control] ❌ Email ADULT (input_16) HIDDEN');
                }
            } else {
                // ADULT program: zobraz LEN input_16
                if (adultEmailField && adultEmailInput) {
                    adultEmailField.style.display = '';
                    adultEmailField.style.visibility = 'visible';
                    adultEmailField.style.opacity = '1';
                    adultEmailInput.disabled = false;
                    adultEmailInput.style.display = '';
                    console.log('[SPA Section Control] ✅ Email ADULT (input_16) VISIBLE');
                }
                if (childEmailField && childEmailInput) {
                    childEmailField.style.display = 'none';
                    childEmailField.style.visibility = 'hidden';
                    childEmailField.style.opacity = '0';
                    childEmailInput.disabled = true;
                    childEmailInput.value = ''; // Vyčisti hodnotu
                    console.log('[SPA Section Control] ❌ Email CHILD (input_15) HIDDEN');
                }
            }
        } else {
            // Skry obidve ak nie je všetko vybrané
            if (childEmailField && childEmailInput) {
                childEmailField.style.display = 'none';
                childEmailInput.disabled = true;
            }
            if (adultEmailField && adultEmailInput) {
                adultEmailField.style.display = 'none';
                adultEmailInput.disabled = true;
            }
            console.log('[SPA Section Control] Emails: HIDDEN (frequency not selected)');
        }
    
        
        // ⭐ Použijeme querySelectorAll aby sme našli VŠETKY sekcie
        const commonSections = document.querySelectorAll('.spa-section-common');
        const adultSections = document.querySelectorAll('.spa-section-adult');
        const childSections = document.querySelectorAll('.spa-section-child');

        console.log('[SPA Section Control] Sections found:', {
            common: commonSections.length,
            adult: adultSections.length,
            child: childSections.length
        });


        // LOGIKA ZOBRAZOVANIA: stačí programSelected (mesto + program)
        if (programSelected) {
            // 1. SPOLOČNÁ SEKCIA: vždy zobrazená (VŠETKY commonSections)
            commonSections.forEach(section => {
                toggleSection(section, true);
            });
            console.log('[SPA Section Control] ✅ Common sections: VISIBLE');
            
            // 2. CHILD alebo ADULT sekcie podľa age_from
            if (isChildProgram) {
                childSections.forEach(section => toggleSection(section, true));
                adultSections.forEach(section => toggleSection(section, false));
                console.log('[SPA Section Control] ✅ Child sections: VISIBLE');
                console.log('[SPA Section Control] ❌ Adult sections: HIDDEN');
            } else {
                adultSections.forEach(section => toggleSection(section, true));
                childSections.forEach(section => toggleSection(section, false));
                console.log('[SPA Section Control] ✅ Adult sections: VISIBLE');
                console.log('[SPA Section Control] ❌ Child sections: HIDDEN');
            }
        } else {
            // Skry VŠETKY sekcie
            commonSections.forEach(section => toggleSection(section, false));
            adultSections.forEach(section => toggleSection(section, false));
            childSections.forEach(section => toggleSection(section, false));
            console.log('[SPA Section Control] ❌ All sections: HIDDEN (no program selected)');
        }
        
    
        console.log('[SPA Section Control] ========== UPDATE END ==========');
    }

    /**
     * Nájdi sekciu podľa CSS triedy
     * @param {string} cssClass - CSS trieda (napr. 'spa-section-common')
     * @returns {HTMLElement|null}
     */
    function findSectionByClass(cssClass) {
        return document.querySelector(`.${cssClass}`);
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

    /**
     * Vyčistenie všetkých polí v sekciách
     */
    function clearAllSectionFields() {
        console.log('[SPA Clear] Clearing all section fields');
        
        // Vyčisti všetky inputy OKREM mesta, programu a frekvencie
        const participantInputs = document.querySelectorAll(
            '[name^="input_"]:not([name="input_1"]):not([name="input_2"])'
        );
        
        participantInputs.forEach(input => {
            if (input.type === 'checkbox' || input.type === 'radio') {
                input.checked = false;
                // Odstráň aj data-default atribúty
                input.removeAttribute('data-default');
            } else {
                input.value = '';
            }
        });
        
        console.log('[SPA Clear] Cleared', participantInputs.length, 'fields');
    }

})();