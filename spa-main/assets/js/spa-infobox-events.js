/**
 * SPA Infobox Wizard – Frontend logika
 * CENTRALIZOVANÝ STATE MANAGEMENT
 */
/**
     * Filtrovanie programových options podľa mesta
     */
window.filterProgramsByCity = function(cityName) {
    const programField = document.querySelector(`[name="${spaRegistrationConfig.fields.spa_program}"]`);
    
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
};

// LISTENERS - pri prvom načítaní stránky
document.addEventListener('DOMContentLoaded', function () {
    if (window.listenersAttached) return;
    window.initInfobox();
    window.watchFormChanges();
    window.listenersAttached = true;
});


// Gravity Forms AJAX callback
if (typeof jQuery !== 'undefined') {
    jQuery(document).on('gform_post_render', function(event, form_id, current_page) {
        window.initInfobox();
        window.watchFormChanges();
        // window.hideAllSectionsOnInit() - riadené orchestrátorom
        
        // ✅ OCHRANA: applyGetParams sa NESMIE spustiť počas restore
        if (!window.__spaRestoreInProgress) {
            window.applyGetParams();
        } else {
            console.log('[SPA Events] applyGetParams SKIPPED - restore in progress');
        }

        // 🔍 CASE2 restore – znovu aplikuj scope pre frekvenciu (GF ju po pagebreaku skryl)
    if (window.currentState === 2) {
        const freqInput = document.querySelector(`[name="${spaConfig.fields.spa_frequency}"]`);
        if (freqInput) {
            const wrap = freqInput.closest('.gfield');
            if (wrap) {
                wrap.style.display = '';
                wrap.dataset.conditionalLogic = 'visible';
                console.log('[SPA Restore] frequency scope re-applied');
            }
        }
        
        // ✅ FREQUENCY RESTORE (po GF rerender)
        /* const freqBackup = document.querySelector(`[name="${spaConfig.fields.spa_frequency_value}"]`);
        const hasFreqBackup = freqBackup && freqBackup.value && freqBackup.value.trim() !== '';
        
        if (hasFreqBackup) {
            console.log('[SPA Restore] frequency value detected:', freqBackup.value);
            
            window.__spaRestoringFrequency = true;
            
            const freqWrapper = document.querySelector('.gfield.spa-frequency-selector');
            
            if (!freqWrapper) {
                console.log('[SPA Restore] frequency restore skipped: no wrapper');
                window.__spaRestoringFrequency = false;
            } else {
                const targetRadio = freqWrapper.querySelector(`input[type="radio"][value="${freqBackup.value}"]`);
                
                if (!targetRadio) {
                    console.log('[SPA Restore] frequency restore skipped: no radio for value ' + freqBackup.value);
                    window.__spaRestoringFrequency = false;
                } else if (!targetRadio.checked) {
                    targetRadio.checked = true;
                    window.spaFormState.frequency = true;
                    
                    const changeEvent = new Event('change', { bubbles: true });
                    targetRadio.dispatchEvent(changeEvent);
                    
                    console.log('[SPA Restore] frequency radio restored:', freqBackup.value);
                    window.__spaRestoringFrequency = false;
                } else {
                    window.__spaRestoringFrequency = false;
                }
            }
        } */
    }

    });
}


/**
 * Renderovanie frekvenčného selektora
 * AUTORITATÍVNY SELEKTOR: .gfield.spa-frequency-selector (z GF JSON cssClass)
 * POZNÁMKA: Wrapper je <fieldset>, nie <div>
 */
window.renderFrequencySelector = function(programData) {
    console.log('[SPA Frequency] renderFrequencySelector called with:', !!programData);
    
    const gfieldWrapper = document.querySelector('.gfield.spa-frequency-selector');
    // ✅ FORCE VISIBILITY – Gravity Forms po pagebreaku nechá pole skryté
    gfieldWrapper.style.display = '';
    gfieldWrapper.dataset.conditionalLogic = 'visible';

    if (!gfieldWrapper) {
        console.error('[SPA Frequency] GF wrapper .gfield.spa-frequency-selector not found');
        return;
    }
    
    if (!programData) {
        gfieldWrapper.innerHTML = '';
        window.spaFormState.frequency = false;
        console.log('[SPA Frequency] Cleared (no program data)');
        return;
    }

    gfieldWrapper.innerHTML = '';
    
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
    
    // ────────────────────────────────────────────────
    // VŽDY najprv vyčistíme checked stav (dôležité!)
    // ────────────────────────────────────────────────
    window.spaFormState.frequency = false;

    if (activeFrequencies.length === 0) {
        const disabledOption = document.createElement('label');
        disabledOption.className = 'spa-frequency-option spa-frequency-disabled';
        disabledOption.innerHTML = `
            <input type="radio" disabled>
            <span>Pre tento program nie je dostupná platná frekvencia</span>
        `;
        gfieldWrapper.appendChild(disabledOption);
        console.log('[SPA Frequency] No valid frequencies available');
        return;
    }
    
    // Vytvoríme všetky možnosti – žiadna nie je defaultne checked
    activeFrequencies.forEach((freq, index) => {
        const label = document.createElement('label');
        label.className = 'spa-frequency-option';
        label.style.cursor = 'pointer';           // vizuálna nápoveda
        label.style.userSelect = 'none';          // zabráni označovaniu textu pri kliku
        
        const input = document.createElement('input');
        input.type = 'radio';
        // CRITICAL: Radio buttons MUST have a name to function as a group
        // Fallback to wrapper's field ID if spaConfig mapping unavailable
        const radioName = spaRegistrationConfig.fields?.spa_frequency || 
                        gfieldWrapper.id.replace('field_', 'input_');
        input.name = radioName;
        input.value = freq.key;
        input.disabled = false;

        // DEBUG: Verify name is set
        if (!input.name) {
            console.error('[SPA Frequency] CRITICAL: Radio button has no name attribute!');
        }
        // input.checked = false; → defaultne už je false
        
        // Klik na label → označí radio + spustí change event
        label.addEventListener('click', function(e) {
            // Ak klikol priamo na input, necháme prehliadač spracovať sám
            if (e.target === input) return;
            
            e.preventDefault(); // zabráni duplicitnému spusteniu
            input.checked = true;
            
            // Simulujeme change event – Gravity Forms / náš kód na to reaguje
            const changeEvent = new Event('change', { bubbles: true });
            input.dispatchEvent(changeEvent);
        });

        // Change listener pre výber frekvencie (autoritatívny zápis)
        input.addEventListener('change', function () {
            if (!this.checked) return;

            // 1. Stav formulára
            window.spaFormState.frequency = true;

            // 2. Autoritatívny zápis do GF hidden poľa
            const freqBackup = document.querySelector(
                `[name="${spaConfig.fields.spa_frequency_value}"]`
            );

            if (freqBackup) {
                freqBackup.value = this.value;

                // Gravity Forms musí zmenu vedieť
                freqBackup.dispatchEvent(
                    new Event('change', { bubbles: true })
                );

                console.log('[SPA Frequency] spa_frequency_value set to:', this.value);
            }

            // 3. Aktualizácia UI / sekcií / ceny
            window.updateSectionVisibility();
            window.updatePriceSummary();

            console.log('[SPA Frequency] Selected:', this.value);
        });


        const span = document.createElement('span');
        span.textContent = `${freq.label} – ${freq.price.toFixed(2).replace('.', ',')} €`;
        
        label.appendChild(input);
        label.appendChild(span);
        gfieldWrapper.appendChild(label);
    });
    
    // Auto-check iba ak je PRESNE JEDNA možnosť
    if (activeFrequencies.length === 1) {
        const singleInput = gfieldWrapper.querySelector('input[type="radio"]');
        if (singleInput) {
            singleInput.checked = true;
            window.spaFormState.frequency = true;
            
            // ✅ AUTO-CHECK BACKUP: Pri auto-check ulož aj do backup fieldu (pred dispatch)
            const freqBackup = document.querySelector(`[name="${spaConfig.fields.spa_frequency_value}"]`);
            if (freqBackup) {
                freqBackup.value = singleInput.value;
                
                // Trigger change pre GF tracking
                const backupChangeEvent = new Event('change', { bubbles: true });
                freqBackup.dispatchEvent(backupChangeEvent);
                
                console.log('[SPA Frequency] stored to spa_frequency_value:', singleInput.value);
            }
            
            // Spustíme change event aj pri auto-check (dôležité pre konzistenciu)
            const changeEvent = new Event('change', { bubbles: true });
            singleInput.dispatchEvent(changeEvent);
            
            // + istota aktualizácie sekcií a prehľadu
            setTimeout(() => {
                if (typeof window.updateSectionVisibility === 'function') {
                    window.updateSectionVisibility();
                }
                if (typeof window.updatePriceSummary === 'function') {
                    window.updatePriceSummary();
                }
            }, 120);
        }
    }
    // ✅ RESTORE: Obnov označenie z spa_frequency_value (ak existuje)
    if (activeFrequencies.length > 1) {
        const freqBackup = document.querySelector(`[name="${spaConfig.fields.spa_frequency_value}"]`);
        
        if (freqBackup && freqBackup.value) {
            console.log('[SPA Frequency Restore] Detected backup value:', freqBackup.value);
            
            const targetRadio = gfieldWrapper.querySelector(`input[type="radio"][value="${freqBackup.value}"]`);
            
            if (targetRadio) {
                targetRadio.checked = true;
                window.spaFormState.frequency = true;
                console.log('[SPA Frequency Restore] Radio restored (no event)');
            }
        }
    }
    console.log('[SPA Frequency] Rendered:', activeFrequencies.length, 'options');

    // ✅ NOVÉ: Frequency hint logic
    const hintContainer = document.getElementById('spa-frequency-hint');
    const hintText = hintContainer ? hintContainer.querySelector('.spa-frequency-hint-text') : null;

    if (hintContainer && hintText) {
        if (activeFrequencies.length > 1) {
            hintText.textContent = 'Frekvenciu si vyberiete v ďalšom kroku.';
            hintContainer.style.display = '';
        } else {
            hintContainer.style.display = 'none';
            hintText.textContent = '';
        }
    }
};

 // Trigger pri blur (pre meno a adresu)
 document.addEventListener('blur', function(e) {
    if (!e.target || !e.target.name) return;
    
    const blurFields = ['spa_member_name_first', 'spa_member_name_last', 'spa_client_address'];
    
    if (blurFields.includes(e.target.name)) {
        setTimeout(window.updatePriceSummary, 100);
    }
}, true);
