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
            'spa_member_birthnumber',
            'spa_guardian_name_first',
            'spa_guardian_name_last',
            'spa_parent_email',
            'spa_parent_phone',
            'spa_consent_guardian',            
            'spa_parent_email',
            'spa_parent_phone',
            'spa_guardian_name_first',
            'spa_guardian_name_last'            
        ],
        adult_only: [
            
            'spa_client_email_required'
        ],
        common: [
            'payment_method',
            'company_name',
            'company_ico',
            'company_dic',
            'company_icdph',
            'company_address',
            'company_address_street',
            'company_address_city',
            'company_address_postcode',
            'spa_member_name_first',
            'spa_member_name_last',
            'spa_member_birthdate',
            'spa_client_address_street',
            'spa_client_address_city',
            'spa_client_address_postcode',
            'spa_client_email',
            'spa_client_phone',
            'spa_member_health_restrictions',
            'spa_consent_gdpr',
            'spa_consent_health',
            'spa_consent_statutes',
            'spa_consent_terms',
            'payment_method',
            'spa_consent_marketing'
        ]
    };

    // ========== HELPERS ==========

    /**
     * Vráti true ak je wrapper poľa hidden cez GF conditional logic.
     * Scope engine NESMIE meniť stav takéhoto poľa.
     * @param {Element} wrapperEl – .gfield element
     * @returns {boolean}
     */
    function isGFHidden(wrapperEl) {
        if (!wrapperEl) return false;
        if (wrapperEl.dataset.conditionalLogic === 'hidden') return true;
        if (wrapperEl.classList.contains('gf_hidden')) return true;
        if (wrapperEl.classList.contains('gf_invisible')) return true;
        if (wrapperEl.offsetParent === null && wrapperEl.style.display === 'none') return true;
        return false;
    }

    function isGFHidden(input) {
        if (!input) return false;
        const wrapper = input.closest('.gfield');
        if (!wrapper) return false;
        if (wrapper.classList.contains('gform_hidden')) return true;
        if (wrapper.classList.contains('gf_hidden')) return true;
        if (wrapper.classList.contains('gf_invisible')) return true;
        if (getComputedStyle(wrapper).display === 'none') return true;
        let parent = wrapper.parentElement;
        while (parent) {
            if (getComputedStyle(parent).display === 'none') return true;
            parent = parent.parentElement;
        }
        return false;
    }

    function getFieldScope(fieldName) {
        if (fieldScopes.child_only.includes(fieldName)) return 'child';
        if (fieldScopes.adult_only.includes(fieldName)) return 'adult';
        if (fieldScopes.common.includes(fieldName)) return 'common';
        return null;
    }

    /**
     * Prevedie logický názov poľa na GF input ID cez window.spaRegisterFields
     * @param {string} fieldName
     * @returns {string|null} napr. "input_6.3" alebo "input_15"
     */
    function resolveInputId(fieldName) {
        const fields = window.spaRegisterFields || {};
        return fields[fieldName] || null;
    }

    /**
     * Nájde wrapper .gfield pre dané pole podľa name atribútu (stabilný GF identifikátor).
     * Primárne: querySelector('[name="input_6.3"]')
     * Fallback: querySelector('[id^="field_"][id$="_6"]')
     * @param {string} fieldName
     * @returns {Element|null}
     */
    function findFieldWrapper(fieldName) {
        const inputId = resolveInputId(fieldName);
        if (!inputId) {
            console.warn('[SPA Scope] No input ID mapping for field:', fieldName);
            return null;
        }

        // PRIMARY: hľadaj cez name atribút – stabilný identifikátor nezávislý od form_id
        const inputEl = document.querySelector('[name="' + CSS.escape(inputId) + '"]');
        if (inputEl) {
            return inputEl.closest('.gfield');
        }

        // FALLBACK: vyparsuj fieldId z inputId (napr. "input_6.3" → 6, "input_15" → 15)
        const fieldIdMatch = inputId.match(/^input_(\d+)/);
        const fieldId = fieldIdMatch ? fieldIdMatch[1] : null;

        if (fieldId) {
            const wrapper = document.querySelector('[id^="field_"][id$="_' + fieldId + '"]');
            if (wrapper) {
                return wrapper;
            }
        }

        console.warn('[SPA Scope] Could not find wrapper for field:', fieldName, '(inputId:', inputId, ', fieldId:', fieldId, ')');
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
            // Guard: ak GF conditional logic skryl wrapper, scope ho nesmie odhaliť
            if (isGFHidden(wrapper.querySelector('input, select, textarea')) && visible) return;
            wrapper.style.display = visible ? '' : 'none';
            wrapper.setAttribute('aria-hidden', visible ? 'false' : 'true');
        }

        /**
         * Disabled všetky inputy vo GF-hidden wrapperoch.
         * Zabraňuje ghost validácii pri skrytých poliach.
         */
        function disableHiddenGFFields() {
            document.querySelectorAll('.gfield').forEach(wrapper => {
                const isHidden =
                    wrapper.classList.contains('gform_hidden') ||
                    wrapper.classList.contains('gf_hidden') ||
                    wrapper.classList.contains('gf_invisible') ||
                    getComputedStyle(wrapper).display === 'none';

                if (isHidden) {
                    wrapper.querySelectorAll('input, select, textarea').forEach(input => {
                        input.disabled = true;
                    });
                }
            });
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

    // ========== GF POST RENDER – reapply scope po GF conditional logic ==========
    // GF conditional logic sa aplikuje asynchrónne – scope musí počkať

    function reapplyScopeAfterGFRender() {
        setTimeout(function () {
            if (!hasScopeWrappers) {
                [...fieldScopes.child_only, ...fieldScopes.adult_only].forEach(fieldName => {
                    const fieldScopeValue = getFieldScope(fieldName);
                    let visible = false;
                    if (fieldScopeValue === 'child') visible = (scope === 'child');
                    if (fieldScopeValue === 'adult') visible = (scope === 'adult');
                    setFieldWrapperVisibility(fieldName, visible);
                });
                fieldScopes.common.forEach(fieldName => {
                    setFieldWrapperVisibility(fieldName, true);
                });
            } else {
                [...fieldScopes.child_only, ...fieldScopes.adult_only].forEach(fieldName => {
                    const wrapper = findFieldWrapper(fieldName);
                    if (!wrapper) return;
                    const inScopeSection =
                        wrapper.closest('.spa-section-child') ||
                        wrapper.closest('.spa-section-adult');
                    if (!inScopeSection) {
                        const fieldScopeValue = getFieldScope(fieldName);
                        let visible = false;
                        if (fieldScopeValue === 'child') visible = (scope === 'child');
                        if (fieldScopeValue === 'adult') visible = (scope === 'adult');
                        setFieldWrapperVisibility(fieldName, visible);
                    }
                });
            }
            console.log('[SPA Scope] ✅ scope reapplied after gform_post_render');
        }, 50);
    }

    if (window.jQuery) {
        jQuery(document).on('gform_post_render', function () {
            reapplyScopeAfterGFRender();
        });
    }

    // Disable hidden GF fields ihneď po scope aplikácii
    disableHiddenGFFields();

    // ========== GF POST RENDER — reapply po GF conditional logic ==========
    if (window.jQuery) {
        jQuery(document).on('gform_post_render', function () {
            setTimeout(function () {
                // Reapply scope viditeľnosť
                if (!hasScopeWrappers) {
                    [...fieldScopes.child_only, ...fieldScopes.adult_only].forEach(fieldName => {
                        const fieldScopeValue = getFieldScope(fieldName);
                        let visible = false;
                        if (fieldScopeValue === 'child') visible = (scope === 'child');
                        if (fieldScopeValue === 'adult') visible = (scope === 'adult');
                        setFieldWrapperVisibility(fieldName, visible);
                    });
                    fieldScopes.common.forEach(fieldName => {
                        setFieldWrapperVisibility(fieldName, true);
                    });
                } else {
                    [...fieldScopes.child_only, ...fieldScopes.adult_only].forEach(fieldName => {
                        const wrapper = findFieldWrapper(fieldName);
                        if (!wrapper) return;
                        const inScopeSection =
                            wrapper.closest('.spa-section-child') ||
                            wrapper.closest('.spa-section-adult');
                        if (!inScopeSection) {
                            const fieldScopeValue = getFieldScope(fieldName);
                            let visible = false;
                            if (fieldScopeValue === 'child') visible = (scope === 'child');
                            if (fieldScopeValue === 'adult') visible = (scope === 'adult');
                            setFieldWrapperVisibility(fieldName, visible);
                        }
                    });
                }
                // Vždy disabled hidden GF fields po re-renderi
                disableHiddenGFFields();
                console.log('[SPA Scope] ✅ reapplied after gform_post_render');
            }, 50);
        });
    }

    // ========== EXPOSE GLOBALS (pre prípadné rozšírenie z iných modulov) ==========
    window.spaGetFieldScope             = getFieldScope;
    window.spaSetFieldWrapperVisibility = setFieldWrapperVisibility;
    window.spaFieldScopes               = fieldScopes;
    window.spaDisableHiddenGFFields     = disableHiddenGFFields;

})();