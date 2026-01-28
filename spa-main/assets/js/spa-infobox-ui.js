/**
 * SPA Infobox Wizard – sekcie
 */

window.spaFormPhase = 'edit';  // 'edit' | 'summary'

// ────────────────────────────────────────────────
// 1. Čistenie polí (bezo zmeny)
// ────────────────────────────────────────────────
window.clearAllSectionFields = function() {
    console.log('[SPA Clear] Clearing all section fields');
    
    const participantInputs = document.querySelectorAll(
        '[name^="input_"]:not([name="spa_city"]):not([name="spa_program"])'
    );
    
    participantInputs.forEach(input => {
        if (input.type === 'checkbox' || input.type === 'radio') {
            input.checked = false;
            input.removeAttribute('data-default');
        } else {
            input.value = '';
        }
    });
    
    console.log('[SPA Clear] Cleared', participantInputs.length, 'fields');
};

// ────────────────────────────────────────────────
// 2. Samostatná funkcia – iba inline preview veku
// ────────────────────────────────────────────────
window.updateAgePreview = function() {
    console.log('[SPA Preview] updateAgePreview START');

    let isChild = false;
    
    if (window.spaCurrentProgramType === 'child') {
        isChild = true;
    } else if (window.spaCurrentProgramType === 'adult') {
        isChild = false;
    } else if (window.infoboxData?.program) {
        const ageMinRaw = window.infoboxData.program.age_min;
        const ageMin = parseFloat(ageMinRaw);
        
        if (!isNaN(ageMin) && ageMin < 18) {
            isChild = true;
        } else if (!isNaN(ageMin) && ageMin >= 18) {
            isChild = false;
        }
    }

    let ageYears = null;
    let ageDisplay = '';

    if (isChild) {
        const birthdateInput = document.querySelector('input[name="spa_member_birthdate"]');
        const birthdate = birthdateInput?.value.trim();
        
        if (birthdate) {
            const parts = birthdate.split('.');
            if (parts.length === 3) {
                const day   = parseInt(parts[0], 10);
                const month = parseInt(parts[1], 10) - 1;
                const year  = parseInt(parts[2], 10);
                
                const birth = new Date(year, month, day);
                const today = new Date();
                
                ageYears = today.getFullYear() - birth.getFullYear();
                const monthDiff = today.getMonth() - birth.getMonth();
                if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
                    ageYears--;
                }
                
                ageDisplay = ageYears + ' rokov';
            }
        }
    }

    const agePreviewEl = document.getElementById('spa-age-preview');
    if (agePreviewEl) {
        agePreviewEl.innerHTML = '';

        if (isChild && ageDisplay) {
            let html = `Vek účastníka: <span style="font-weight:600;">${ageDisplay}</span>`;

            if (ageYears !== null && window.infoboxData?.program) {
                const ageMin = parseFloat(window.infoboxData.program.age_min);
                const ageMax = parseFloat(window.infoboxData.program.age_max);

                if (
                    (!isNaN(ageMin) && !isNaN(ageMax) && (ageYears < ageMin || ageYears > ageMax)) ||
                    (!isNaN(ageMin) && isNaN(ageMax) && ageYears < ageMin)
                ) {
                    html += `<br>⚠️ <span class="spa-form-warning">Nezodpovedá vybranému programu!</span>`;
                }
            }

            agePreviewEl.innerHTML = html;
        }
    }

    console.log('[SPA Preview] updateAgePreview END');
};

// ────────────────────────────────────────────────
// 3. Samostatná funkcia – iba render prehľadu (summary)
// ────────────────────────────────────────────────
window.renderSummary = function() {
    console.log('[SPA Summary] renderSummary START');

    // Najdôležitejšia ochrana – explicitný stav
    if (window.spaFormPhase !== 'summary') {
        console.log('[SPA Summary] Nie sme v summary fáze → preskočené');
        return;
    }

    const summaryContainer = document.querySelector('.spa-price-summary');
    if (!summaryContainer) {
        console.log('[SPA Summary] .spa-price-summary nenájdený');
        return;
    }

    // ── Zbieranie údajov ────────────────────────────────────────
    const firstNameInput = document.querySelector('input[name="spa_member_name_first"]');
    const lastNameInput  = document.querySelector('input[name="spa_member_name_last"]');
    const participantName = [
        firstNameInput?.value.trim(),
        lastNameInput?.value.trim()
    ].filter(Boolean).join(' ');

    let address = '';
    const addrSingle = document.querySelector('input[name="spa_client_address"]');
    const addrStreet = document.querySelector('input[name="spa_client_address_street"]');
    const addrCity   = document.querySelector('input[name="spa_client_city"]');
    if (addrSingle?.value.trim()) {
        address = addrSingle.value.trim();
    } else {
        const parts = [];
        if (addrStreet?.value.trim()) parts.push(addrStreet.value.trim());
        if (addrCity?.value.trim())   parts.push(addrCity.value.trim());
        address = parts.filter(Boolean).join(', ');
    }

    const phone = document.querySelector('input[name="spa_client_phone"]')?.value.trim() || '';

    // isChild už vieme z preview logiky, ale pre istotu znova
    let isChild = window.spaCurrentProgramType === 'child';

    let birthNumber = '';
    if (isChild) {
        birthNumber = document.querySelector('input[name="spa_member_birthnumber"]')?.value.trim() || '';
    }

    let guardianName = '', guardianEmail = '', guardianPhone = '';
    if (isChild) {
        const gFirst = document.querySelector('input[name="spa_guardian_name_first"]')?.value.trim();
        const gLast  = document.querySelector('input[name="spa_guardian_name_last"]')?.value.trim();
        guardianName = [gFirst, gLast].filter(Boolean).join(' ');
        guardianEmail = document.querySelector('input[name="spa_parent_email"]')?.value.trim() || '';
        guardianPhone = document.querySelector('input[name="spa_parent_phone"]')?.value.trim() || '';
    }

    let participantEmail = '';
    if (isChild) {
        participantEmail = document.querySelector('input[name="spa_client_email"]')?.value.trim() || '';
    } else {
        participantEmail = document.querySelector('input[name="input_16"]')?.value.trim() || '';
    }

    const programDisplay = window.wizardData?.program_name || '';
    let placeDisplay = '';
    if (window.infoboxData?.place) {
        const parts = [];
        if (window.infoboxData.place.name)    parts.push(window.infoboxData.place.name);
        if (window.infoboxData.place.address) parts.push(window.infoboxData.place.address);
        if (window.infoboxData.place.city)    parts.push(window.infoboxData.place.city);
        placeDisplay = parts.join(', ');
    } else if (window.wizardData?.city_name) {
        placeDisplay = window.wizardData.city_name;
    }

    const selectedFreq = document.querySelector('input[name="spa_frequency"]:checked');
    let frequencyText = selectedFreq ? selectedFreq.parentElement?.textContent.trim() : '';

    // ── Render ──────────────────────────────────────────────────
    let html = '<h6>Prehľad registrácie</h6>';
    html += '<div class="spa-summary-list">';

    let personal = '';
    if (participantName && address) {
        personal += `<strong>Meno a adresa účastníka:</strong> ${participantName}, ${address}`;
    } else if (participantName) {
        personal += `<strong>Meno účastníka:</strong> ${participantName}`;
    } else if (address) {
        personal += `<strong>Adresa účastníka:</strong> ${address}`;
    }

    if (isChild && birthNumber) {
        if (personal) personal += '<br>';
        personal += `<strong>Rodné číslo:</strong> ${birthNumber}`;
    }

    if (isChild && (guardianName || guardianEmail || guardianPhone)) {
        if (personal) personal += '<br>';
        let parts = [];
        if (guardianName) parts.push(guardianName);
        if (guardianEmail) parts.push('✉️ ' + guardianEmail);
        if (guardianPhone) parts.push('📱 ' + guardianPhone);
        personal += `<strong>Zákonný zástupca:</strong> ${parts.join(', ')}`;
    }

    let contact = [];
    if (participantEmail) contact.push('✉️ ' + participantEmail);
    if (phone) contact.push('📱 ' + phone);
    if (contact.length) {
        if (personal) personal += '<br>';
        personal += `<strong>Kontakt na účastníka:</strong> ${contact.join(', ')}`;
    }

    if (personal) html += `<p>${personal}</p>`;

    let progHtml = '';
    if (programDisplay) progHtml += `🤸 <strong>Vybraný program:</strong> ${programDisplay}`;
    if (placeDisplay)   progHtml += `<br>📍 <strong>Miesto tréningov:</strong> ${placeDisplay}`;
    progHtml += `<br>ℹ️ <span class="spa-form-warning">Na základe tejto registrácie a jej schválení vás tréner zaradí do vybraného tréningového dňa (rozvrh je uvedený vyššie).</span>`;

    if (progHtml) html += `<p>${progHtml}</p>`;

    if (frequencyText) {
        html += `<p><strong>Cena / Frekvencia:</strong> ${frequencyText}</p>`;
    }

    html += `<p><strong>Platba:</strong> Platba po schválení registrácie</p>`;
    html += '</div>';

    summaryContainer.innerHTML = html;

    console.log('[SPA Summary] renderSummary END');
};

// ────────────────────────────────────────────────
// 4. Listenery – volajú iba preview (input/change)
// ────────────────────────────────────────────────
document.addEventListener('change', function(e) {
    if (!e.target?.name) return;
    
    const relevant = [
        'spa_member_name_first','spa_member_name_last','spa_member_health_restrictions','spa_client_address',
        'spa_member_birthdate','spa_member_birthnumber','spa_client_phone',
        'spa_guardian_name_first','spa_guardian_name_last','spa_parent_email','spa_parent_phone',
        'spa_client_email','spa_client_email_required','spa_client_phone','spa_client_address',
        'spa_consent_gdpr','spa_consent_health','spa_consent_statutes','spa_consent_terms',
        'spa_consent_guardian','spa_consent_marketing','spa_registration_summary'
    ];

    if (relevant.includes(e.target.name)) {
        console.log('[SPA] Relevant change → updateAgePreview');
        setTimeout(window.updateAgePreview, 80);
    }
});

document.addEventListener('input', function(e) {
    if (e.target.matches('input, select, textarea')) {
        // Najcitlivejšie polia (meno, dátum narodenia, emaily)
        const name = e.target.name;
        if (['spa_member_birthdate','spa_member_name_first','spa_member_name_last','spa_client_email'].includes(name)) {
            setTimeout(window.updateAgePreview, 120);
        }
    }
});

// ────────────────────────────────────────────────
// 5. Inicializácia – iba preview
// ────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
    console.log('[SPA] DOMContentLoaded → initial age preview');
    
    setTimeout(function() {
        window.updateAgePreview();
        
        // Ak by sa náhodou stránka načítala už v summary (veľmi nepravdepodobné)
        // → môžeme sem dať kontrolu, ale default je 'edit'
        if (window.spaFormPhase === 'summary') {
            window.renderSummary();
        }
    }, 800);
});

// ────────────────────────────────────────────────
// 6. Prechod na summary stránku – sem patrí render
//    (musí byť zavolané z iného súboru / inline scriptu po pagebreak)
//    Príklad volania (vložiť do inline JS alebo iného súboru):
//    document.querySelector('.gform_next_button').addEventListener('click', () => {
//        window.spaFormPhase = 'summary';
//        window.renderSummary();
//    });
// ────────────────────────────────────────────────