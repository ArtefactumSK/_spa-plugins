/**
 * SPA Infobox Wizard – sekcie
 */
/**
 * Vyčistenie všetkých polí v sekciách
 */
window.clearAllSectionFields = function() {
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
};

    window.updatePriceSummary = function() {
        console.log('[DEBUG] ========== updatePriceSummary START ==========');
        
        const summaryContainer = document.querySelector('.spa-price-summary');
        
        if (!summaryContainer) {
            console.log('[DEBUG] Container NOT FOUND');
            return;
        }
        
        console.log('[DEBUG] Container found');

        // Detekcia typu
        let isChild = false;
        
        if (window.spaCurrentProgramType === 'child') {
            isChild = true;
        } else if (window.spaCurrentProgramType === 'adult') {
            isChild = false;
        } else if (window.infoboxData?.program) {
            const ageMinRaw = window.infoboxData.program.age_min;
            const ageMin = parseFloat(ageMinRaw);
            
            if (!isNaN(ageMin) && ageMin !== null && ageMin !== '' && ageMin < 18) {
                isChild = true;
            } else if (!isNaN(ageMin) && ageMin >= 18) {
                isChild = false;
            } else {
                isChild = false;
                console.log('[DEBUG] age_min is empty, defaulting to ADULT');
            }
            console.log('[DEBUG] Fallback detection - age_min:', ageMinRaw, '→ parsed:', ageMin, '→ isChild:', isChild);
        } else {
            isChild = false;
            console.log('[DEBUG] No detection method, defaulting to ADULT');
        }
        
        console.log('[DEBUG] Final isChild:', isChild);

        // Zbieranie dát
        const firstNameInput = document.querySelector('input[name="input_6.3"]'); // Meno účastníka
        const lastNameInput = document.querySelector('input[name="input_6.6"]'); // Priezvisko účastníka
        const participantName = [
            firstNameInput?.value.trim(),
            lastNameInput?.value.trim()
        ].filter(Boolean).join(' ');

        let address = '';
        const addressSingleInput = document.querySelector('input[name="input_17"]'); // Adresa
        if (addressSingleInput) {
            address = addressSingleInput.value.trim();
        }

        const phoneInput = document.querySelector('input[name="input_19"]'); // Telefón účastníka
        const phone = phoneInput?.value.trim();

        let programDisplay = window.wizardData?.program_name || '';
        // Miesto tréningov
        let placeDisplay = '';
        if (window.infoboxData?.place) {
            const parts = [];
            if (window.infoboxData.place.name) parts.push(window.infoboxData.place.name);
            if (window.infoboxData.place.address) parts.push(window.infoboxData.place.address);
            if (window.infoboxData.place.city) parts.push(window.infoboxData.place.city);
            placeDisplay = parts.join(', ');
        } else if (window.wizardData?.city_name) {
            placeDisplay = window.wizardData.city_name;
        }

        const selectedFrequency = document.querySelector('input[name="spa_frequency"]:checked');
        let frequencyText = '';
        if (selectedFrequency) {
            const parentLabel = selectedFrequency.parentElement;
            frequencyText = parentLabel?.textContent.trim();
        }

        console.log('[DEBUG] Data collected:', {
            participantName,
            address,
            phone,
            programDisplay,
            placeDisplay,
            frequencyText
        });

        // === RENDER HTML ===
        console.log('[DEBUG] Starting HTML render...');
        
        let html = '<h6>Prehľad registrácie</h6>';
        html += '<div class="spa-summary-list">';
        
        console.log('[DEBUG] Header added');

        // OSOBNÉ ÚDAJE
        let personalInfoHtml = '';
        
        if (participantName && address) {
            personalInfoHtml += `<strong>Meno a adresa účastníka:</strong> ${participantName}, ${address}`;
        } else if (participantName) {
            personalInfoHtml += `<strong>Meno účastníka:</strong> ${participantName}`;
        } else if (address) {
            personalInfoHtml += `<strong>Adresa účastníka:</strong> ${address}`;
        }
        
        if (phone) {
            if (personalInfoHtml) personalInfoHtml += '<br>';
            personalInfoHtml += `<strong>Telefón:</strong> ${phone}`;
        }
        
        if (personalInfoHtml) {
            html += `<p>${personalInfoHtml}</p>`;
            console.log('[DEBUG] Personal info added');
        } else {
            console.log('[DEBUG] No personal info to add');
        }

        // PROGRAM - TEST: Pridaj VŽDY bez podmienky
        console.log('[DEBUG] Adding program section...');
        console.log('[DEBUG] programDisplay:', programDisplay);
        
        let programInfoHtml = '';
        
        if (programDisplay) {
            programInfoHtml += `🤸 <strong>Vybraný program:</strong> ${programDisplay}`;
            console.log('[DEBUG] Program text added');
        } else {
            console.log('[DEBUG] programDisplay is empty!');
        }
        
        if (placeDisplay) {
            programInfoHtml += `<br>📍 <strong>Miesto tréningov:</strong> ${placeDisplay}`;
            console.log('[DEBUG] Place text added');
        }
        
        programInfoHtml += `<br>ℹ️ <span class="spa-form-warning">Na základe tejto registrácie a jej schválení vás tréner zaradí do vybraného tréningového dňa (rozvrh je uvedený vyššie).</span>`;
        
        console.log('[DEBUG] programInfoHtml length:', programInfoHtml.length);
        
        if (programInfoHtml) {
            html += `<p>${programInfoHtml}</p>`;
            console.log('[DEBUG] Program section added to HTML');
        } else {
            console.log('[DEBUG] WARNING: programInfoHtml is empty!');
        }

        // CENA
        if (frequencyText) {
            html += `<p><strong>Cena / Frekvencia:</strong> ${frequencyText}</p>`;
            console.log('[DEBUG] Frequency added');
        }

        // PLATBA
        html += `<p><strong>Platba:</strong> Platba po schválení registrácie</p>`;
        console.log('[DEBUG] Payment added');

        html += '</div>';
        
        console.log('[DEBUG] Final HTML length:', html.length);
        console.log('[DEBUG] Final HTML:', html);
        
        summaryContainer.innerHTML = html;
        
        console.log('[DEBUG] ========== updatePriceSummary END ==========');
    };

// ⭐ SPUSTI updatePriceSummary pri zmenách VŠETKÝCH relevantných polí
document.addEventListener('change', function(e) {
    // Skontroluj či je target input/select a má name atribút
    if (!e.target || !e.target.name) return;
    
    const fieldName = e.target.name;
    
    const relevantFields = [
        'input_6.3', 'input_6.6',   // Meno
        'input_17',                 // Adresa (single field)
        'input_17.1',               // Adresa - ulica
        'input_17.3',               // Adresa - mesto
        'input_7',                  // Dátum narodenia
        'input_19',                 // Telefón účastníka
        'input_18.3', 'input_18.6', // Meno zástupcu
        'input_12',                 // Email zástupcu
        'input_13',                 // Telefón zástupcu
        'input_15',                 // Email dieťaťa
        'input_16',                 // Email dospelého účastníka
        'spa_frequency'             // Frekvencia
    ];
    
    if (relevantFields.includes(fieldName)) {
        console.log('[SPA] Field changed:', fieldName, '→ updating summary');
        setTimeout(window.updatePriceSummary, 100);
    }
});

// ⭐ DEBUG: Volaj updatePriceSummary() pri KAŽDEJ zmene
document.addEventListener('DOMContentLoaded', function() {
    console.log('[SPA DEBUG] Forcing initial updatePriceSummary()');
    
    // Zavolaj hneď po načítaní
    setTimeout(function() {
        if (typeof window.updatePriceSummary === 'function') {
            window.updatePriceSummary();
            console.log('[SPA DEBUG] Initial summary rendered');
        }
    }, 1000);
    
    // Volaj pri KAŽDEJ zmene v celom formulári
    document.addEventListener('input', function(e) {
        if (e.target.matches('input, select, textarea')) {
            console.log('[SPA DEBUG] Input changed:', e.target.name);
            setTimeout(function() {
                if (typeof window.updatePriceSummary === 'function') {
                    window.updatePriceSummary();
                }
            }, 100);
        }
    });
});