/**
 * SPA Register GF – Scope Section Control
 *
 * Zdroj scope: window.spaRegisterScope (injected by PreRenderHooks via wp_add_inline_script)
 * Zdroj field mapovania: window.spaRegisterFields (injected by EnqueueHooks via wp_localize_script)
 *
 * Logika:
 *   1. Ak scope chýba → všetko zostane viditeľné (server nerozhodol)
 *   2. Ak scope existuje → JS riadi sekcie + scope polia
 *   3. FIELD LEVEL CONTROL – vždy prebehne, bez ohľadu na existenciu sekcií
 *
 * Server NESMIE nastavovať isHidden. Iba tento súbor rozhoduje o viditeľnosti.
 */

(function () {
    'use strict';

    // ========== GUARD ==========
    if (!window.spaRegisterScope) {
        console.warn('[SPA Scope] window.spaRegisterScope is not set – skipping section control');
        return;
    }

    const scope = window.spaRegisterScope; // 'child' | 'adult'

    // ========== FIELD SCOPE DEFINITION ==========
    // Polia zo fields.json rozdelené podľa scope
    // Rozšíriteľné – pridaj sem iba polia, ktoré sú VÝLUČNE pre daný scope.
    const fieldScopes = {
        child_only: [
            'spa_member_birthdate',
            'spa_member_birthnumber',
            'spa_member_health_restrictions',
            'spa_guardian_name_first',
            'spa_guardian_name_last',
            'spa_parent_email',
            'spa_parent_phone',
            'spa_consent_guardian'
        ],
        adult_only: [
            'spa_client_address_street',
            'spa_client_address_city',
            'spa_client_address_postcode',
            'spa_client_email',
            'spa_client_email_required',
            'spa_client_phone',
            'spa_consent_marketing'
        ],
        common: [
            'spa_member_name_first',
            'spa_member_name_last',
            'spa_consent_gdpr',
            'spa_consent_health',
            'spa_consent_statutes',
            'spa_consent_terms',
            'payment_method'
        ]
    };

    // ========== HELPERS ==========

    /**
     * Vráti scope pre dané pole ('child' | 'adult' | 'common' | null)
     * @param {string} fieldName
     * @returns {string|null}
     */
    function getFieldScope(fieldName) {
        if (fieldScopes.child_only.includes(fieldName)) return 'child';
        if (fieldScopes.adult_only.includes(fieldName)) return 'adult';
        if (fieldScopes.common.includes(fieldName)) return 'common';
        return null;
    }

    /**
     * Prevedie logický názov poľa na GF input ID cez window.spaRegisterFields
     * Fallback: použije fields.json mapu ak je dostupná.
     * @param {string} fieldName
     * @returns {string|null} napr. "input_7"
     */
    function resolveInputId(fieldName) {
        const fields = window.spaRegisterFields || {};
        return fields[fieldName] || null;
    }

    /**
     * Nájde wrapper .gfield pre dané pole podľa input ID zo fields.json.
     * GF wrapper má triedu gfield a obsahuje input s daným id.
     * @param {string} fieldName
     * @returns {Element|null}
     */
    function findFieldWrapper(fieldName) {
        const inputId = resolveInputId(fieldName);
        if (!inputId) {
            console.warn('[SPA Scope] No input ID mapping for field:', fieldName);
            return null;
        }

        // inputId môže byť napr. "input_7" alebo "input_6.3" (sub-field)
        // Normalizuj – zoberie prvú časť pred bodkou
        const baseId = inputId.replace('.', '_').split('_').slice(0, 2).join('_'); // "input_7"

        // Hľadáme wrapper cez data-field-id alebo id atribút
        // GF wrapper má id="field_FORMID_FIELDID" – ale FORMID nepoznáme staticky
        // Preto hľadáme cez input element priamo
        const fullInputId = inputId.replace('.', '_'); // "input_6_3"
        const inputEl = document.getElementById(fullInputId) || document.getElementById(baseId);

        if (inputEl) {
            return inputEl.closest('.gfield');
        }

        // Fallback – hľadaj cez triedu obsahujúcu fieldName (napr. ak má GF CSS triedu)
        console.warn('[SPA Scope] Could not find wrapper for field:', fieldName, '(inputId:', inputId, ')');
        return null;
    }

    /**
     * Nastaví viditeľnosť wrappera poľa.
     * @param {string} fieldName
     * @param {boolean} visible
     */
    function setFieldWrapperVisibility(fieldName, visible) {
        const wrapper = findFieldWrapper(fieldName);
        if (!wrapper) return;
        wrapper.style.display = visible ? '' : 'none';
        wrapper.setAttribute('aria-hidden', visible ? 'false' : 'true');
    }

    // ========== SECTION DETECTION ==========

    const sectionWrappersExist = {
        common: document.querySelectorAll('.spa-section-common').length > 0,
        child:  document.querySelectorAll('.spa-section-child').length > 0,
        adult:  document.querySelectorAll('.spa-section-adult').length > 0
    };

    const hasScopeWrappers = sectionWrappersExist.child || sectionWrappersExist.adult;

    console.log('[SPA Scope] scope =', scope);
    console.log('[SPA Scope] section wrappers =', sectionWrappersExist);

    if (!hasScopeWrappers) {
        console.warn('[SPA Scope] ⚠️ SECTION WRAPPERS MISSING – field-level control will handle visibility');
    }

    // ========== SECTIONS VISIBILITY ==========

    // Najprv skry všetky scope sekcie
    document.querySelectorAll('.spa-section-child').forEach(el => {
        el.style.display = 'none';
        el.setAttribute('aria-hidden', 'true');
    });
    document.querySelectorAll('.spa-section-adult').forEach(el => {
        el.style.display = 'none';
        el.setAttribute('aria-hidden', 'true');
    });

    // Vždy zobraz common sekcie
    if (sectionWrappersExist.common) {
        document.querySelectorAll('.spa-section-common').forEach(el => {
            el.style.display = '';
            el.style.visibility = 'visible';
            el.setAttribute('aria-hidden', 'false');
        });
    }

    // Zobraz sekciu podľa scope
    if (scope === 'child' && sectionWrappersExist.child) {
        document.querySelectorAll('.spa-section-child').forEach(el => {
            el.style.display = '';
            el.style.visibility = 'visible';
            el.setAttribute('aria-hidden', 'false');
        });
        console.log('[SPA Scope] ✅ CHILD sections shown');
    } else if (scope === 'adult' && sectionWrappersExist.adult) {
        document.querySelectorAll('.spa-section-adult').forEach(el => {
            el.style.display = '';
            el.style.visibility = 'visible';
            el.setAttribute('aria-hidden', 'false');
        });
        console.log('[SPA Scope] ✅ ADULT sections shown');
    }

    // ========== FIELD LEVEL CONTROL (always applied) ==========
    // Prebehne vždy – sekcie sú iba nadstavba.
    // Polia mimo sekcií sú riadené priamo; polia vnútri sekcií sú riadené redundantne (no side-effect).

    [...fieldScopes.child_only, ...fieldScopes.adult_only].forEach(fieldName => {
        const fieldScopeValue = getFieldScope(fieldName);
        let visible = false;
        if (fieldScopeValue === 'child') visible = (scope === 'child');
        if (fieldScopeValue === 'adult') visible = (scope === 'adult');
        setFieldWrapperVisibility(fieldName, visible);
    });

    // Common polia vždy viditeľné
    fieldScopes.common.forEach(fieldName => {
        setFieldWrapperVisibility(fieldName, true);
    });

    console.log('[SPA Scope] ✅ FIELD LEVEL CONTROL applied for scope:', scope);

    // ========== EXPOSE GLOBALS (pre prípadné rozšírenie z iných modulov) ==========
    window.spaGetFieldScope             = getFieldScope;
    window.spaSetFieldWrapperVisibility = setFieldWrapperVisibility;
    window.spaFieldScopes               = fieldScopes;

})();