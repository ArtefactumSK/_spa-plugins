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
window.updatePriceSummary = function() {
    const summaryContainer = document.querySelector('.spa-price-summary');
    
    if (!summaryContainer) {
        console.log('[SPA Summary] Container NOT FOUND');
        return;
    }
    
    console.log('[SPA Summary] ========== START ==========');

    // Načítaj typ účastníka z GLOBÁLNEJ PREMENNEJ (nastavuje sa v orchestrator.js)
    const isChild = window.spaCurrentProgramType === 'child';

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

    // === RENDER HTML (len vyplnené hodnoty) ===
    
    let html = '<h6>Prehľad registrácie</h6>';
    html += '<div class="spa-summary-list">';

    // 1. Meno a adresa účastníka
    if (participantName && address) {
        html += `<p><strong>Meno a adresa účastníka:</strong> ${participantName}, ${address}`;
    }

    // 2. Vek účastníka (LEN pre CHILD)
    if (age && isChild) {
        // Validácia veku vs veková kategória
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
        
        html += `<br><strong>Vek účastníka:</strong> ${age}${ageWarning}`;
    }

    // 3. Zákonný zástupca (LEN child, len ak sú všetky 3 hodnoty)
    if (isChild && guardianName && guardianEmail && guardianPhone) {
        html += `<br><strong>Zákonný zástupca:</strong> 👩‍👧 ${guardianName}, 
            <span class="spa-form-contact spa-form-contact-email">✉️ ${guardianEmail}</span>, 
            <span class="spa-form-contact spa-form-contact-phone">📱 ${guardianPhone}</span>`;
    }

    // 4. Kontakt na účastníka (email a/alebo telefón - nepovinné pre CHILD aj ADULT)
    let participantEmail = '';
    
    if (isChild) {
        // CHILD: použij input_15
        const childEmailInput = document.querySelector('input[name="input_15"]');
        participantEmail = childEmailInput?.value.trim() || '';
    } else {
        // ADULT: použij input_16
        const adultEmailInput = document.querySelector('input[name="input_16"]');
        participantEmail = adultEmailInput?.value.trim() || '';
    }
    
    // Zobraz len ak je ASPOŇ JEDNO pole vyplnené
    if (participantEmail || phone) {
        const contactParts = [];

        if (participantEmail) {
            contactParts.push(
                `<span class="spa-form-contact spa-form-contact-email">✉️ ${participantEmail}</span>`
            );
        }

        if (phone) {
            contactParts.push(
                `<span class="spa-form-contact spa-form-contact-phone">📱 ${phone}</span>`
            );
        }

        html += `<br><strong>Kontakt na účastníka:</strong> ${contactParts.join(', ')}`;
    }
    
    html += '</p>';

    // 5. Vybraný program
    if (programDisplay) {
        html += `<p>🤸 <strong>Vybraný program:</strong> ${programDisplay}`;
    }

    // 6. Miesto tréningov
    if (placeDisplay) {
        html += `<br>📍 <strong>Miesto tréningov:</strong> ${placeDisplay}`;
    }

    // 7. Veková kategória (LEN pre CHILD)
    if (ageCategory && isChild) {
        html += `<br>👶 <strong>Veková kategória:</strong> ${ageCategory}`;
    }

    // 8. Rozvrh
    /* if (schedule) {
        html += `<br>🕘 <strong>Rozvrh:</strong> ${schedule}</p>`;
    } */
     html += `<br>ℹ️ <span class="spa-form-warning">Na základe tejto registrácie vás tréner po jej schválení zaradí do vybraného tréningového dňa z dostupných termínov uvedených vyššie.</span>`;

    // 9. Cena/Frekvencia
    if (frequencyText) {
        // Preformátuj z "1× týždenne – 50,00 €" → "50,00 € / 1× týždenne"
        const match = frequencyText.match(/^(.+?)\s*[–-]\s*(.+)$/);
        let displayText = frequencyText;
        
        if (match) {
            const frequency = match[1].trim();
            const price = match[2].trim();
            displayText = `${price} / ${frequency}`;
        }
        
        html += `<p><strong>Cena / Frekvencia:</strong> ${displayText}</p>`;
    }

    // 10. Platba (vždy zobrazená)
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