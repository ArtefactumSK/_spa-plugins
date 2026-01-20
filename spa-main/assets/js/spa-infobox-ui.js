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

/**
* Aktualizácia PREHĽADU REGISTRÁCIE (.spa-price-summary)
*/
/* window.updatePriceSummary = function() {
    const summaryContainer = document.querySelector('.spa-price-summary');
    
    if (!summaryContainer) {
        console.log('[SPA Summary] Container NOT FOUND');
        return;
    }
    
    console.log('[SPA Summary] ========== START ==========');

    // Načítaj typ účastníka z GLOBÁLNEJ PREMENNEJ (nastavuje sa v orchestrator.js)
    // PRAVIDLO: Ak age_min EXISTUJE A < 18 → CHILD, INAK → ADULT
    let isChild = false; // Default ADULT

    // Primárny zdroj: globálna premenná
    if (window.spaCurrentProgramType === 'child') {
        isChild = true;
    } else if (window.spaCurrentProgramType === 'adult') {
        isChild = false;
    }
    // Ak globálna premenná nie je nastavená, použij fallback
    else if (window.infoboxData?.program) {
        const ageMinRaw = window.infoboxData.program.age_min;
        const ageMin = parseFloat(ageMinRaw);
        
        // Ak age_min je ČÍSLO (nie prázdny reťazec) A je < 18 → CHILD
        if (!isNaN(ageMin) && ageMin !== null && ageMin !== '' && ageMin < 18) {
            isChild = true;
        } else if (!isNaN(ageMin) && ageMin >= 18) {
            isChild = false; // ADULT (age_min >= 18)
        } else {
            // Ak age_min je prázdny/"" → DEFAULT ADULT
            isChild = false;
            console.log('[SPA Summary] age_min is empty, defaulting to ADULT');
        }
        console.log('[SPA Summary] Fallback detection - age_min:', ageMinRaw, '→ parsed:', ageMin, '→ isChild:', isChild);
    } else {
        // Last resort: default ADULT (aby sa prehľad zobrazil)
        isChild = false;
        console.log('[SPA Summary] No age_min found, defaulting to ADULT');
    }

    console.log('[SPA Summary] Final isChild:', isChild, '| spaCurrentProgramType:', window.spaCurrentProgramType);

    // === ZBIERAJ DÁTA ===
    
    // Meno a adresa
    const firstNameInput = document.querySelector('input[name="input_6.3"]');
    const lastNameInput = document.querySelector('input[name="input_6.6"]');
    const participantName = [
        firstNameInput?.value.trim(),
        lastNameInput?.value.trim()
    ].filter(Boolean).join(' ');

    // Adresa môže byť text field ALEBO address field
    let address = '';
    const addressSingleInput = document.querySelector('input[name="input_17"]');
    const addressStreetInput = document.querySelector('input[name="input_17.1"]');
    const addressCityInput = document.querySelector('input[name="input_17.3"]');

    if (addressSingleInput) {
        // Jednoduchý text field
        address = addressSingleInput.value.trim();
    } else if (addressStreetInput || addressCityInput) {
        // Address field - skombinuj ulicu + PSČ + mesto
        const parts = [];
        if (addressStreetInput) parts.push(addressStreetInput.value.trim());
        if (addressCityInput) parts.push(addressCityInput.value.trim());
        address = parts.filter(Boolean).join(', ');
    }

    console.log('[SPA Summary] Address:', address, {
        single: !!addressSingleInput,
        street: addressStreetInput?.value,
        city: addressCityInput?.value
    });

    // Vek
    const birthdateInput = document.querySelector('input[name="input_7"]');
    const birthdate = birthdateInput?.value.trim();
    let age = '';
    
    if (birthdate) {
        const parts = birthdate.split('.');
        if (parts.length === 3) {
            const day = parseInt(parts[0], 10);
            const month = parseInt(parts[1], 10) - 1;
            const year = parseInt(parts[2], 10);
            const birth = new Date(year, month, day);
            const today = new Date();
            let ageYears = today.getFullYear() - birth.getFullYear();
            const monthDiff = today.getMonth() - birth.getMonth();
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
                ageYears--;
            }
            age = ageYears + ' r.';
        }
    }

    // Zákonný zástupca (len child)
    let guardianName = '';
    let guardianEmail = '';
    let guardianPhone = '';
    
    if (isChild) {
        const guardianFirstInput = document.querySelector('input[name="input_18.3"]');
        const guardianLastInput = document.querySelector('input[name="input_18.6"]');
        guardianName = [
            guardianFirstInput?.value.trim(),
            guardianLastInput?.value.trim()
        ].filter(Boolean).join(' ');
        
        const guardianEmailInput = document.querySelector('input[name="input_12"]');
        guardianEmail = guardianEmailInput?.value.trim();
        
        const guardianPhoneInput = document.querySelector('input[name="input_13"]');
        guardianPhone = guardianPhoneInput?.value.trim();
    }

    // Telefón účastníka
    const phoneInput = document.querySelector('input[name="input_19"]');
    const phone = phoneInput?.value.trim();

    // Program + úroveň
    let programDisplay = window.wizardData.program_name || '';
    if (window.infoboxData?.program?.spa_level && programDisplay) {
        // Mapping úrovní s emoji (podľa CPT UI)
        const levelLabels = {
            'beginner': '🟢 Začiatočník',
            'intermediate': '🟡 Mierne pokročilý',
            'advanced': '🟠 Pokročilý',
            'professional': '🔴 Profesionál'
        };
        const levelValue = window.infoboxData.program.spa_level;
        const levelDisplay = levelLabels[levelValue] || levelValue;
        programDisplay += ' ' + levelDisplay;
    }

    // Miesto tréningov
    let placeDisplay = '';
    if (window.infoboxData?.place) {
        const parts = [];
        if (window.infoboxData.place.name) parts.push(window.infoboxData.place.name);
        if (window.infoboxData.place.address) parts.push(window.infoboxData.place.address);
        if (window.infoboxData.place.city) parts.push(window.infoboxData.place.city);
        placeDisplay = parts.join(', ');
    }

   // Veková kategória (age range programu)
   let ageCategory = '';
   if (window.infoboxData?.program) {
       const ageMin = window.infoboxData.program.age_min;
       const ageMax = window.infoboxData.program.age_max;
       
       if (ageMin && ageMax) {
           ageCategory = ageMin + '-' + ageMax + ' r.';
       } else if (ageMin) {
           ageCategory = ageMin + '+ r.';
       }
   }

   // Rozvrh
   let schedule = '';
   if (window.infoboxData?.program?.schedule) {
       schedule = window.infoboxData.program.schedule;
   }

    // Frekvencia / Cena
    const selectedFrequency = document.querySelector('input[name="spa_frequency"]:checked');
    let frequencyText = '';
    
    if (selectedFrequency) {
        const parentLabel = selectedFrequency.parentElement;
        frequencyText = parentLabel?.textContent.trim();
    }

    // === RENDER HTML - PREHĽAD SA ZOBRAZÍ VŽDY ===

    let html = '<h6>Prehľad registrácie</h6>';
    html += '<div class="spa-summary-list">';

    // 1. OSOBNÉ ÚDAJE (voliteľné)
    let personalInfoHtml = '';

    if (participantName && address) {
        personalInfoHtml += `<strong>Meno a adresa účastníka:</strong> ${participantName}, ${address}`;
    } else if (participantName) {
        personalInfoHtml += `<strong>Meno účastníka:</strong> ${participantName}`;
    } else if (address) {
        personalInfoHtml += `<strong>Adresa účastníka:</strong> ${address}`;
    }

    // 2. Vek účastníka (LEN pre CHILD)
    if (age && isChild) {
        let ageWarning = '';
        if (ageCategory && window.infoboxData?.program) {
            const ageYears = parseInt(age);
            const ageMin = parseFloat(window.infoboxData.program.age_min);
            const ageMax = parseFloat(window.infoboxData.program.age_max);
            
            if (ageMax && (ageYears < ageMin || ageYears > ageMax)) {
                ageWarning = ' ⚠️ <span class="spa-form-warning">Pozor: Vek nezodpovedá vekovej kategórii programu!</span>';
            } else if (!ageMax && ageYears < ageMin) {
                ageWarning = ' ⚠️ <span class="spa-form-warning">Pozor: Vek nezodpovedá vekovej kategórii programu!</span>';
            }
        }
        
        if (personalInfoHtml) personalInfoHtml += '<br>';
        personalInfoHtml += `<strong>Vek účastníka:</strong> ${age}${ageWarning}`;
    }

    // 3. Zákonný zástupca (LEN child)
    if (isChild && guardianName && guardianEmail && guardianPhone) {
        if (personalInfoHtml) personalInfoHtml += '<br>';
        personalInfoHtml += `<strong>Zákonný zástupca:</strong> 👩‍👧 ${guardianName}, 
            <span class="spa-form-contact spa-form-contact-email">✉️ ${guardianEmail}</span>, 
            <span class="spa-form-contact spa-form-contact-phone">📱 ${guardianPhone}</span>`;
    }

    // 4. Kontakt na účastníka
    let participantEmail = '';

    if (isChild) {
        const childEmailInput = document.querySelector('input[name="input_15"]');
        participantEmail = childEmailInput?.value.trim() || '';
    } else {
        const adultEmailInput = document.querySelector('input[name="input_16"]');
        participantEmail = adultEmailInput?.value.trim() || '';
    }

    if (participantEmail || phone) {
        const contactParts = [];
        if (participantEmail) {
            contactParts.push(`<span class="spa-form-contact spa-form-contact-email">✉️ ${participantEmail}</span>`);
        }
        if (phone) {
            contactParts.push(`<span class="spa-form-contact spa-form-contact-phone">📱 ${phone}</span>`);
        }
        
        if (personalInfoHtml) personalInfoHtml += '<br>';
        personalInfoHtml += `<strong>Kontakt na účastníka:</strong> ${contactParts.join(', ')}`;
    }

    // OSOBNÉ ÚDAJE - pridaj len ak niečo existuje
    if (personalInfoHtml) {
        html += `<p>${personalInfoHtml}</p>`;
    }

    // 5. PROGRAM - VŽDY zobraz (v samostatnom <p>)
    if (programDisplay) {
        let programInfoHtml = '';
        
        programInfoHtml += `🤸 <strong>Vybraný program:</strong> ${programDisplay}`;
        
        if (placeDisplay) {
            programInfoHtml += `<br>📍 <strong>Miesto tréningov:</strong> ${placeDisplay}`;
        }
        
        if (ageCategory && isChild) {
            programInfoHtml += `<br>👶 <strong>Veková kategória:</strong> ${ageCategory}`;
        }
        
        programInfoHtml += `<br>ℹ️ <span class="spa-form-warning">Na základe tejto registrácie vás tréner po jej schválení zaradí do vybraného tréningového dňa z dostupných termínov uvedených vyššie.</span>`;
        
        html += `<p>${programInfoHtml}</p>`;
    }

    // 6. CENA (v samostatnom <p>)
    if (frequencyText) {
        const match = frequencyText.match(/^(.+?)\s*[–-]\s*(.+)$/);
        let displayText = frequencyText;
        
        if (match) {
            const frequency = match[1].trim();
            const price = match[2].trim();
            displayText = `${price} / ${frequency}`;
        }
        
        html += `<p><strong>Cena / Frekvencia:</strong> ${displayText}</p>`;
    }

    // 7. PLATBA (v samostatnom <p>)
    html += `<p><strong>Platba:</strong> Platba po schválení registrácie</p>`;

    html += '</div>';

    summaryContainer.innerHTML = html;
    
    console.log('[SPA Price Summary] Updated:', {
        participantName,
        address,
        age,
        guardianName,
        guardianEmail,
        guardianPhone,
        phone,
        program: programDisplay,
        place: placeDisplay,
        ageCategory,
        schedule,
        frequency: frequencyText
    });

    console.log('[SPA Price Summary] Updated:', {
        participantName,
        address,
        age,
        guardianName,
        guardianEmail,
        guardianPhone,
        phone,
        program: programDisplay,
        place: placeDisplay,
        ageCategory,
        schedule,
        frequency: frequencyText
    });
    
    // ⭐ Ulož timestamp poslednej aktualizácie
    window.spaLastSummaryUpdate = Date.now();
    }; */

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
        const firstNameInput = document.querySelector('input[name="input_6.3"]');
        const lastNameInput = document.querySelector('input[name="input_6.6"]');
        const participantName = [
            firstNameInput?.value.trim(),
            lastNameInput?.value.trim()
        ].filter(Boolean).join(' ');

        let address = '';
        const addressSingleInput = document.querySelector('input[name="input_17"]');
        if (addressSingleInput) {
            address = addressSingleInput.value.trim();
        }

        const phoneInput = document.querySelector('input[name="input_19"]');
        const phone = phoneInput?.value.trim();

        let programDisplay = window.wizardData?.program_name || '';
        let placeDisplay = '';
        if (window.infoboxData?.place) {
            const parts = [];
            if (window.infoboxData.place.name) parts.push(window.infoboxData.place.name);
            if (window.infoboxData.place.address) parts.push(window.infoboxData.place.address);
            if (window.infoboxData.place.city) parts.push(window.infoboxData.place.city);
            placeDisplay = parts.join(', ');
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
        'input_17',                 // Adresa
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