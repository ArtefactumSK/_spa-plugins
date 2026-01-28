/**
 * SPA Infobox Wizard – sekcie
 */

// ⭐ GLOBÁLNA PREMENNÁ PRE TYP PROGRAMU (child/adult)
window.spaCurrentProgramType = null;

/**
 * =========================================================
 * SPA FIELD SCOPE – JEDINÝ ZDROJ PRAVDY
 * =========================================================
 * - child_only   → zobraz len pri CHILD
 * - adult_only   → zobraz len pri ADULT
 * - always       → VŽDY zobraz (default)
 */
window.spaFieldScopes = {
    child_only: [
        spaConfig.fields.spa_guardian_name_first,
        spaConfig.fields.spa_guardian_name_last,
        spaConfig.fields.spa_parent_email,
        spaConfig.fields.spa_parent_phone,
        spaConfig.fields.spa_client_email,
        spaConfig.fields.spa_consent_guardian
    ],
    adult_only: [
        spaConfig.fields.spa_client_email_required,
        spaConfig.fields.spa_member_birthnumber
    ]
};

/**
 * Vráti scope poľa: 'child' | 'adult' | 'always'
 */
window.getSpaFieldScope = function (fieldName) {
    if (window.spaFieldScopes.child_only.includes(fieldName)) return 'child';
    if (window.spaFieldScopes.adult_only.includes(fieldName)) return 'adult';
    return 'always';
};

/**
 * Bezpečne vráti GF "name" atribút podľa spa_* kľúča z spaConfig.fields.
 * (Jediný zdroj pravdy je fields.json → fields.js)
 */
window.spaFieldName = function (key) {
    return (window.spaConfig && spaConfig.fields && spaConfig.fields[key]) ? spaConfig.fields[key] : null;
};

/**
 * Skrytie všetkých sekcií pri inicializácii
 */
window.hideAllSectionsOnInit = function () {
    console.log('[SPA Init] ========== INIT RESET ==========');

    if (window.spa_sections_hidden) {
        console.log('[SPA Init] Already initialized, skipping');
        return;
    }
    window.spa_sections_hidden = true;

    // Skry všetky sekcie
    document.querySelectorAll('[data-section]').forEach(sec => {
        sec.style.display = 'none';
    });

    // Skry common/child/adult sekcie
    document.querySelectorAll('.spa-section-common, .spa-section-child, .spa-section-adult').forEach(sec => {
        sec.style.display = 'none';
    });

    console.log('[SPA Init] Sections hidden');
};

/**
 * Pomocná – nastav wrapper viditeľnosť
 */
window.spaSetFieldWrapperVisibility = function (fieldName, visible) {
    const el = document.querySelector(`[name="${fieldName}"]`);
    if (!el) return;

    const wrap = el.closest('.gfield');
    if (!wrap) return;

    wrap.style.display = visible ? '' : 'none';
    el.disabled = !visible;

    if (!visible) {
        if (el.type === 'radio' || el.type === 'checkbox') {
            el.checked = false;
        } else {
            el.value = '';
        }
    }
};

/**
 * RIADENIE VIDITEĽNOSTI SEKCIÍ + POLÍ
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

    // PARTICIPANT TYPE (radio) – len do hidden fieldu
    const participantRadio = document.querySelector(`input[name="${spaConfig.fields.spa_registration_type}"]:checked`);
    const participantType = participantRadio
        ? (participantRadio.value.toLowerCase().includes('dieť') ? 'child' : 'adult')
        : null;

    const resolvedTypeField = document.querySelector(`input[name="${spaConfig.fields.spa_resolved_type}"]`);
    if (resolvedTypeField) resolvedTypeField.value = participantType || '';

    // input_4 visibility + auto-select podľa PROGRAM TYPE (ale cez fields.json)
    const registrationTypeWrapper = document.querySelector(`[name="${spaConfig.fields.spa_registration_type}"]`)?.closest('.gfield');
    if (registrationTypeWrapper) {
        registrationTypeWrapper.style.display = canShowProgramFlow ? '' : 'none';

        const radios = document.querySelectorAll(`input[name="${spaConfig.fields.spa_registration_type}"]`);
        if (canShowProgramFlow && programType) {
            radios.forEach(radio => {
                const isChild = radio.value.toLowerCase().includes('dieťa') || radio.value.toLowerCase().includes('dieť');

                if (programType === 'child') {
                    radio.checked = isChild;
                    radio.disabled = true;
                } else {
                    radio.checked = !isChild;
                    radio.disabled = true;
                }
            });
        } else {
            radios.forEach(radio => {
                radio.checked = false;
                radio.disabled = false;
            });
        }
    }

    // SEKČIE (ponechávame existujúci systém)
    document.querySelectorAll('.spa-section-common').forEach(sec => {
        sec.style.display = canShowProgramFlow ? '' : 'none';
    });

    document.querySelectorAll('.spa-section-child').forEach(sec => {
        sec.style.display = (canShowProgramFlow && programType === 'child') ? '' : 'none';
    });

    document.querySelectorAll('.spa-section-adult').forEach(sec => {
        sec.style.display = (canShowProgramFlow && programType === 'adult') ? '' : 'none';
    });

    // =========================================================
    // FIELD SCOPE ENFORCEMENT (JEDINÉ RIADENIE POLÍ adult/child)
    // =========================================================
    if (canShowProgramFlow && programType) {
        Object.entries(spaConfig.fields).forEach(([key, fieldName]) => {
            const scope = getSpaFieldScope(fieldName);

            let visible = true;
            if (scope === 'child') visible = (programType === 'child');
            if (scope === 'adult') visible = (programType === 'adult');

            // ALWAYS = true
            spaSetFieldWrapperVisibility(fieldName, visible);
        });
    } else {
        // ak nie je vybraný program, skry podmienené polia (adult_only/child_only)
        Object.entries(spaConfig.fields).forEach(([key, fieldName]) => {
            const scope = getSpaFieldScope(fieldName);
            if (scope === 'always') return;
            spaSetFieldWrapperVisibility(fieldName, false);
        });
    }

    console.log('[SPA Section Control] ========== UPDATE END ==========');
};

/**
 * =========================================================
 * INIT + EVENT BINDING
 * =========================================================
 * Orchestrator musí byť "last writer wins" – po každej zmene zavolá updateSectionVisibility().
 * Bez form ID.
 */
window.spaInitSectionOrchestrator = function () {
    if (!window.spaConfig || !spaConfig.fields) {
        console.warn('[SPA Orchestrator] spaConfig.fields not ready');
        return;
    }

    if (typeof window.hideAllSectionsOnInit === 'function') window.hideAllSectionsOnInit();
    if (typeof window.updateSectionVisibility === 'function') window.updateSectionVisibility();

    const cityEl = document.querySelector(`[name="${spaConfig.fields.spa_city}"]`);
    const programEl = document.querySelector(`[name="${spaConfig.fields.spa_program}"]`);
    const freqEl = document.querySelector(`[name="${spaConfig.fields.spa_frequency}"]`);
    const regTypeEls = document.querySelectorAll(`input[name="${spaConfig.fields.spa_registration_type}"]`);

    const handler = () => {
        if (typeof window.updateSectionVisibility === 'function') window.updateSectionVisibility();
    };

    [cityEl, programEl, freqEl].forEach(el => {
        if (!el) return;
        el.addEventListener('change', handler);
        el.addEventListener('input', handler);
    });

    regTypeEls.forEach(el => {
        el.addEventListener('change', handler);
        el.addEventListener('input', handler);
    });

    console.log('[SPA Orchestrator] Initialized and listening');
};

// DOM ready
document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => window.spaInitSectionOrchestrator(), 50);
});

// Gravity Forms AJAX render (ak je jQuery dostupné)
if (window.jQuery) {
    jQuery(document).on('gform_post_render', function () {
        setTimeout(() => window.spaInitSectionOrchestrator(), 50);
    });
}
