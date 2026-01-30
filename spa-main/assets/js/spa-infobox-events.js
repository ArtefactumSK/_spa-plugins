/**
 * SPA Infobox Wizard – Frontend logika
 * CENTRALIZOVANÝ STATE MANAGEMENT
 */
/**
     * Filtrovanie programových options podľa mesta
     */
window.filterProgramsByCity = function(cityName) {
    const programField = document.querySelector(`[name="${spaConfig.fields.spa_program}"]`);
    
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

document.addEventListener('DOMContentLoaded', function() {    
    if (window.initialized) return;
    window.initInfobox();
    window.watchFormChanges();
    // ⭐ NEAPLIKUJ GET - GF options ešte neexistujú pri DOMContentLoaded
    window.initialized = true;
});

// Gravity Forms AJAX callback
if (typeof jQuery !== 'undefined') {
    jQuery(document).on('gform_post_render', function() {
        window.initInfobox();
        window.watchFormChanges();
        // window.hideAllSectionsOnInit() - riadené orchestrátorom
        window.applyGetParams(); 
    });
}


/**
 * Renderovanie frekvenčného selektora
 * AUTORITATÍVNY SELEKTOR: .gfield.spa-frequency-selector (z GF JSON cssClass)
 * DÔVOD: data-admin-label nie je dostupný pre radio button fields v GF
 */
window.renderFrequencySelector = function(programData) {
    // Find GF wrapper by CSS class defined in GF JSON
    const gfieldWrapper = document.querySelector('.gfield.spa-frequency-selector');
    
    if (!gfieldWrapper) {
        console.error('[SPA Frequency] GF wrapper .gfield.spa-frequency-selector not found');
        return;
    }
    
    // Find GF input container (where radio buttons live)
    const inputContainer = gfieldWrapper.querySelector('.ginput_container');
    
    if (!inputContainer) {
        console.error('[SPA Frequency] .ginput_container not found inside GF wrapper');
        return;
    }
    if (!programData) {
        inputContainer.innerHTML = '';
        window.spaFormState.frequency = false;
        return;
    }

    inputContainer.innerHTML = '';
    
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
        disabledOption.className = 'gchoice gchoice_disabled';
        disabledOption.innerHTML = `
            <input type="radio" disabled>
            <span>Pre tento program nie je dostupná platná frekvencia</span>
        `;
        inputContainer.appendChild(disabledOption);
        return;
    }
    
    // Get actual input name from existing radio buttons (or construct from field ID)
    const existingRadio = gfieldWrapper.querySelector('input[type="radio"]');
    const radioName = existingRadio ? existingRadio.name : 'input_31';
    
    activeFrequencies.forEach((freq, index) => {
        const label = document.createElement('label');
        label.className = 'gchoice';
        
        const input = document.createElement('input');
        input.type = 'radio';
        input.name = radioName;  // Use actual GF field name
        input.value = freq.key;
        input.id = `choice_${radioName}_${index}`;
        
        if (activeFrequencies.length === 1) {
            input.checked = true;
            window.spaFormState.frequency = true;
            
            setTimeout(() => {
                window.updateSectionVisibility();
            }, 150);
        }
        
        input.addEventListener('change', function() {
            if (this.checked) {
                window.spaFormState.frequency = true;
                window.updateSectionVisibility();
                if (typeof window.updatePriceSummary === 'function') {
                    window.updatePriceSummary();
                }
                console.log('[SPA Frequency] Selected:', this.value);
            }
        });

        const span = document.createElement('span');
        span.textContent = `${freq.label} – ${freq.price.toFixed(2).replace('.', ',')} €`;
        
        label.appendChild(input);
        label.appendChild(span);
        inputContainer.appendChild(label);
    });
    
    if (activeFrequencies.length === 1) {
        window.spaFormState.frequency = true;
        setTimeout(() => {
            window.updateSectionVisibility();
            if (typeof window.updatePriceSummary === 'function') {
                window.updatePriceSummary();
            }
        }, 150);
    }
    
    console.log('[SPA Frequency] Rendered:', activeFrequencies.length, 'options');
};

 // Trigger pri blur (pre meno a adresu)
 document.addEventListener('blur', function(e) {
    if (!e.target || !e.target.name) return;
    
    const blurFields = ['spa_member_name_first', 'spa_member_name_last', 'spa_client_address'];
    
    if (blurFields.includes(e.target.name)) {
        setTimeout(window.updatePriceSummary, 100);
    }
}, true);
