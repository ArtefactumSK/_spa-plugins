/**
 * SPA Infobox Wizard – sekcie
 */

// ⭐ GLOBÁLNA PREMENNÁ PRE TYP PROGRAMU (child/adult)
window.spaCurrentProgramType = null;

/**
 * Skrytie všetkých sekcií pri inicializácii
 */
window.hideAllSectionsOnInit = function () {
    console.log('[SPA Init] ========== INIT RESET ==========');

    if (window.spa_sections_hidden) {
        console.log('[SPA Init] Already initialized, skipping');
        return;
    }

    /* ----------------------------------------------------
     * 1️⃣ Skry výber typu účastníka
     * ---------------------------------------------------- */
    document
        .querySelectorAll('.gfield--input-type-radio')
        .forEach(el => {
            el.style.display = 'none';
            console.log('[SPA Init] Hidden: registration type radio');
        });

    /* ----------------------------------------------------
     * 2️⃣ Skry email polia (len init)
     * ---------------------------------------------------- */
    ['spa_client_email', 'spa_parent_email'].forEach(name => {
        const field = document.querySelector(`input[name="${name}"]`)?.closest('.gfield');
        if (field) {
            field.style.display = 'none';
            console.log(`[SPA Init] Hidden: ${name}`);
        }
    });

    /* ----------------------------------------------------
     * 3️⃣ Skry SPA sekcie (common / adult / child)
     * ---------------------------------------------------- */
    [
        'spa-section-common',
        'spa-section-adult',
        'spa-section-child'
    ].forEach(cls => {
        document.querySelectorAll(`.${cls}`).forEach(section => {
            window.toggleSection(section, false);
            console.log(`[SPA Init] ❌ Hidden section: ${cls}`);
        });
    });

    /* ----------------------------------------------------
     * 4️⃣ FOUC ochrana – IBA pagebreak
     * ❗ input_9 SA TU UŽ NESKRÝVA
     * ---------------------------------------------------- */
    const hidePagebreak = () => {
        const pb = document.querySelector('.gform_page_footer');
        if (pb) {
            pb.style.display = 'none';
            console.log('[SPA Init] ❌ Hidden: pagebreak');
            return true;
        }
        return false;
    };

    // okamžitý pokus
    hidePagebreak();

    // fallback po GF renderi
    setTimeout(hidePagebreak, 0);

    /* ----------------------------------------------------
     * 5️⃣ Final
     * ---------------------------------------------------- */
    window.spa_sections_hidden = true;
    console.log('[SPA Init] ========== INIT COMPLETE ==========');
};


/**
 * RIADENIE VIDITEĽNOSTI SEKCIÍ
 */
window.updateSectionVisibility = function () {
    console.log('[SPA Section Control] ========== UPDATE START ==========');

    const citySelected = !!(window.wizardData.city_name && window.wizardData.city_name.trim() !== '');
    const programSelected = !!(window.wizardData.program_name && window.wizardData.program_name.trim() !== '');
    const canShowProgramFlow = citySelected && programSelected;

    // PROGRAM TYPE (iba program, nie účastník)
    let programType = null; // 'child' | 'adult'
    const programField = document.querySelector(`[name="${spaConfig.fields.spa_program}"]`);
    if (programField && programField.value) {
        const opt = programField.options[programField.selectedIndex];
        const ageMin = parseInt(opt?.getAttribute('data-age-min'), 10);
        if (!isNaN(ageMin)) programType = ageMin < 18 ? 'child' : 'adult';
    }
    window.spaCurrentProgramType = programType;

    // PARTICIPANT TYPE (radio input_4) – len do hidden fieldu
    const participantRadio = document.querySelector('input[name="input_4"]:checked');
    const participantType = participantRadio
        ? (participantRadio.value.toLowerCase().includes('dieť') ? 'child' : 'adult')
        : null;

    const resolvedTypeField = document.querySelector('input[name="input_34"]');
    if (resolvedTypeField) resolvedTypeField.value = participantType || '';

    // input_4 visibility + auto-select podľa PROGRAM TYPE
    const registrationTypeField = document.querySelector('.gfield--input-type-radio');
    if (registrationTypeField) {
        registrationTypeField.style.display = canShowProgramFlow ? '' : 'none';

        if (canShowProgramFlow && programType) {
            const radios = document.querySelectorAll('input[name="input_4"]');
            radios.forEach(radio => {
                const isChild = radio.value.toLowerCase().includes('dieť');
                radio.disabled = false;

                if (programType === 'child') {
                    radio.checked = isChild;
                    radio.disabled = !isChild;
                } else {
                    radio.checked = !isChild;
                    radio.disabled = isChild;
                }
            });
        }

        if (!canShowProgramFlow) {
            document.querySelectorAll('input[name="input_4"]').forEach(r => {
                r.checked = false;
                r.disabled = false;
            });
        }
    }

    // SEKČIE
    const commonSections = document.querySelectorAll('.spa-section-common');
    const childSections = document.querySelectorAll('.spa-section-child');
    const adultSections = document.querySelectorAll('.spa-section-adult');

    if (!canShowProgramFlow) {
        [...commonSections, ...childSections, ...adultSections].forEach(sec => window.toggleSection(sec, false));
    } else {
        commonSections.forEach(sec => window.toggleSection(sec, true));

        if (programType === 'child') {
            childSections.forEach(sec => window.toggleSection(sec, true));
            adultSections.forEach(sec => window.toggleSection(sec, false));
        } else {
            adultSections.forEach(sec => window.toggleSection(sec, true));
            childSections.forEach(sec => window.toggleSection(sec, false));
        }
    }

    // === EXPLICITNÉ RIADENIE KRITICKÝCH POLÍ ===
    // input_9 – health restrictions (vždy riadené tu, nie sekciami)
    const healthField = document.querySelector('textarea[name="input_9"]');
    if (healthField) {
        const shouldBeVisible = canShowProgramFlow;
        const wrap = healthField.closest('.gfield');
        if (wrap) {
            wrap.style.display = shouldBeVisible ? 'block' : 'none';
        }
        healthField.disabled = !shouldBeVisible;
        healthField.style.visibility = shouldBeVisible ? 'visible' : 'hidden';
        healthField.style.opacity = shouldBeVisible ? '1' : '0';
        console.log('[SPA HEALTH FLOW]', shouldBeVisible ? 'VISIBLE + ENABLED' : 'HIDDEN + DISABLED');
    } else {
        console.warn('[SPA HEALTH FLOW] ⚠️ input_9 NOT FOUND');
    }

    // input_15 – email dieťaťa
    const emailChild = document.querySelector('input[name="input_15"]');
    if (emailChild) {
        const visible = canShowProgramFlow && programType === 'child';
        const wrap = emailChild.closest('.gfield');
        if (wrap) wrap.style.display = visible ? 'block' : 'none';
        //emailChild.disabled = !visible;
        if (!visible) emailChild.value = '';
    }

    // input_16 – email dospelý
    const emailAdult = document.querySelector('input[name="input_16"]');
    if (emailAdult) {
        const visible = canShowProgramFlow && programType === 'adult';
        const wrap = emailAdult.closest('.gfield');
        if (wrap) wrap.style.display = visible ? 'block' : 'none';
        //emailAdult.disabled = !visible;
        if (!visible) emailAdult.value = '';
    }

    // input_8 – rodné číslo (dieťa)
    const birthNumber = document.querySelector('input[name="input_8"]');
    if (birthNumber) {
        const visible = canShowProgramFlow && programType === 'child';
        const wrap = birthNumber.closest('.gfield');
        if (wrap) wrap.style.display = visible ? 'block' : 'none';
        birthNumber.disabled = !visible;
        if (!visible) birthNumber.value = '';
    }

    // input_19 – telefón účastníka
    const phone = document.querySelector('input[name="input_19"]');
    if (phone) {
        const visible = canShowProgramFlow;
        const wrap = phone.closest('.gfield');
        if (wrap) wrap.style.display = visible ? 'block' : 'none';
        //phone.disabled = !visible;
    }

    // pagebreak
    const pagebreak = document.querySelector('.gform_page_footer');
    if (pagebreak) {
        pagebreak.style.display = canShowProgramFlow ? '' : 'none';
    }

    console.log('[SPA Section Control] ========== UPDATE END ==========');
};
 
/**
 * Zobraz/skry sekciu + všetky nasledujúce polia až po ďalšiu sekciu
 */
window.toggleSection = function (sectionElement, show) {
    if (!sectionElement) return;

    sectionElement.style.display = show ? 'block' : 'none';

    let next = sectionElement.nextElementSibling;

    while (next) {
        if (next.classList.contains('gfield--type-section')) break;

        // ❌ spa-field-health tu už neriešime (riadi ho updateSectionVisibility)

        // Telefón účastníka (input_19) – len ak je program vybraný globálne
        const phone = next.querySelector('input[name="input_19"]');
        if (phone) {
            const wrap = phone.closest('.gfield');
            const canShow = !!(window.wizardData.program_name && window.wizardData.program_name.trim() !== '');
            if (wrap) wrap.style.display = canShow ? 'block' : 'none';
            //phone.disabled = !canShow;
            next = next.nextElementSibling;
            continue;
        }

        // Email CHILD (input_15) – podľa PROGRAM TYPE
        const programType = window.spaCurrentProgramType;

        const emailChild = next.querySelector('input[name="input_15"]');
        if (emailChild) {
            const wrap = emailChild.closest('.gfield');
            const visible = show && programType === 'child';
            if (wrap) wrap.style.display = visible ? 'block' : 'none';
            //emailChild.disabled = !visible;
            if (!visible) emailChild.value = '';
            next = next.nextElementSibling;
            continue;
        }

        // Email ADULT (input_16) – podľa PROGRAM TYPE
        const emailAdult = next.querySelector('input[name="input_16"]');
        if (emailAdult) {
            const wrap = emailAdult.closest('.gfield');
            const visible = show && programType === 'adult';
            if (wrap) wrap.style.display = visible ? 'block' : 'none';
            //emailAdult.disabled = !visible;
            if (!visible) emailAdult.value = '';
            next = next.nextElementSibling;
            continue;
        }

        // Rodné číslo (input_8) – len CHILD program
        const birth = next.querySelector('input[name="input_8"]');
        if (birth) {
            const wrap = birth.closest('.gfield');
            const visible = show && programType === 'child';
            if (wrap) wrap.style.display = visible ? 'block' : 'none';
            //birth.disabled = !visible;
            if (!visible) birth.value = '';
            next = next.nextElementSibling;
            continue;
        }

        // Default show/hide
        next.style.display = show ? 'block' : 'none';
        /* next.querySelectorAll('input, select, textarea').forEach(i => {
            i.disabled = !show;
        }); */

        next = next.nextElementSibling;
    }

    console.log('[SPA toggleSection]', show ? 'SHOW' : 'HIDE', sectionElement.className);
};