/**
 * SPA Infobox Wizard – Frontend logika
 * CENTRALIZOVANÝ STATE MANAGEMENT
 */

if (typeof spaConfig === 'undefined') {
    console.error('[SPA Infobox] spaConfig nie je definovaný.');
}
    
    /**
     * Inicializácia infoboxu
     */
    window.initInfobox = function() {
        const infoboxContainer = document.getElementById('spa-infobox-container');
        
        if (!infoboxContainer) {
            console.warn('[SPA Infobox] Container nenájdený v DOM.');
            return;
        }
    
        // Vytvor loader, ak ešte neexistuje
        if (!document.getElementById('spa-infobox-loader')) {
            const loaderDiv = document.createElement('div');
            loaderDiv.id = 'spa-infobox-loader';
            loaderDiv.innerHTML = `
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 54.08 92.4">
                    <path d="M36.29,0C-3.91,29.7.49,65.3,32.79,69.8-1.91,69-20.51,38.3,36.29,0Z" fill="#ff1439"/>
                    <path d="M16.99,60.2c2.5,1.8,5.1,1.8,5.6-.2s-1.1-5.1-3.7-7-5.1-1.8-5.6.2,1.1,5.1,3.7,7Z" fill="#ff1439"/>
                    <path d="M16.49,92.4c40.2-29.7,35.8-65.3,3.5-69.8,34.7.8,53.3,31.5-3.5,69.8Z" fill="#ff1439"/>
                    <path d="M48.39,30.5c2.6,1.9,5.1,1.8,5.6-.2s-1.1-5.1-3.7-7-5.1-1.8-5.6.2,1.1,5.1,3.7,7Z" fill="#ff1439"/>
                </svg>
            `;
            infoboxContainer.appendChild(loaderDiv);
        }
    
        // Načítaj úvodný stav
        window.loadInfoboxContent(0);

        console.log('[SPA Infobox] Inicializovaný.');
};
  
    


 /**
 * Vykreslenie infoboxu
 */
window.renderInfobox = function(data, icons, capacityFree, price) {
    
    const content = data.content;
    const programData = data.program;

        // === SPA SCOPE RESOLUTION (SINGLE SOURCE OF TRUTH) ===
    const hasProgramSelected = !!(programData && window.wizardData?.program_name);
    const citySelected = !!(window.wizardData?.city_name);
    const ageMin = parseFloat(programData?.age_min);
    let resolvedProgramType = null;

    if (hasProgramSelected && citySelected) {
        // DEFAULT = ADULT (ak NIE je explicitne CHILD)
        // CHILD iba ak: validný age_min A age_min < 18
        if (!isNaN(ageMin) && ageMin < 18) {
            resolvedProgramType = 'child';
        } else {
            // Všetky ostatné prípady = ADULT (vrátane null/undefined/NaN age_min)
            // Explicitný default pre adult programy
            resolvedProgramType = 'adult';
        }
    } else {
        // Ak nie je program/mesto vybrané -> null
        resolvedProgramType = null;
    }

    console.log('[SPA SCOPE RESOLUTION]', {
        hasProgramSelected,
        raw_age_min: programData?.age_min,
        resolvedType: resolvedProgramType
    });

    // === SINGLE SOURCE OF TRUTH ===
    // Nastav scope pomocou settera (setter má vlastný auto-trigger s retry)
    if (typeof window.spaSetProgramType === 'function') {
        console.log('[SPA SCOPE] Calling spaSetProgramType with:', resolvedProgramType);
        window.spaSetProgramType(resolvedProgramType);
        // REMOVED: Direct updateSectionVisibility call - setter handles it with debounce/retry
    } else {
        // Fallback ak setter neexistuje (nemalo by sa stať)
        console.error('[SPA SCOPE] spaSetProgramType NOT FOUND - using fallback');
        window.wizardData = window.wizardData || {};
        window.wizardData.program_type = resolvedProgramType;
        window.spaCurrentProgramType = resolvedProgramType;
        
        // Fallback: direct call (no retry logic)
        if (typeof window.updateSectionVisibility === 'function') {
            window.updateSectionVisibility();
        }
    }
    // === KONIEC SCOPE RESOLUTION ===
    
    // 🔄 RESET odvodeného registračného typu (pri každom rerenderi infoboxu)
    const registrationTypeBlock = document.getElementById('spa_registration_type');
    if (registrationTypeBlock) {
        const labelEl = registrationTypeBlock.querySelector('.spa-reg-type-label');
        const iconEl  = registrationTypeBlock.querySelector('.spa-registration-type__icon');
        const textEl  = registrationTypeBlock.querySelector('.spa-registration-type__text');

        if (labelEl) labelEl.textContent = '';
        if (iconEl)  iconEl.textContent = '';
        if (textEl)  textEl.textContent = '';

        registrationTypeBlock.classList.remove('is-child', 'is-adult');

        console.log('[SPA] Registration type UI reset (renderInfobox)');
    }
    // ⭐ Ulož do window pre prístup z updatePriceSummary
    if (!window.infoboxData) {
        window.infoboxData = {};
    }
    window.infoboxData.program = programData;
    window.infoboxData.place = data.place;
    

    const container = document.getElementById('spa-infobox-container');
    if (!container) {
        window.hideLoader();
        return;
    }   
    
    // Vyčisti kontajner - OKREM loadera
    const existingLoader = document.getElementById('spa-infobox-loader');
    Array.from(container.children).forEach(child => {
        if (child.id !== 'spa-infobox-loader') {
            child.remove();
        }
    });

   /* ==================================================
    1. OBSAH – WP stránka (SPA Infobox Wizard) CASE 0 / CASE 1 – DEFAULT + MESTO
    ================================================== */

    if (window.currentState === 0 || window.currentState === 1) {

        // ⬅️ HARD RESET programu (musí tu byť)
        const oldProgram = container.querySelector('.spa-infobox-program');
        if (oldProgram) {
            oldProgram.remove();
        }

        // ⬅️ WP CONTENT – cache alebo fallback
        let wpContent = container.querySelector('.spa-infobox-content');

        if (!wpContent) {
            wpContent = document.createElement('div');
            wpContent.className = 'spa-infobox-content';
            wpContent.innerHTML = data?.content || window.__SPA_WP_INFOBOX_CONTENT || '';
            container.appendChild(wpContent);
        }

        // ⬅️ CACHE pre ďalšie návraty
        if (data?.content && !window.__SPA_WP_INFOBOX_CONTENT) {
            window.__SPA_WP_INFOBOX_CONTENT = data.content;
        }

        // ⬅️ CASE 1 – MESTO SUMMARY
        if (window.currentState === 1 && window.wizardData?.city_name) {

            const summaryDiv = document.createElement('div');
            summaryDiv.className = 'spa-infobox-summary';

            const locationIcon = icons?.location || '';

            summaryDiv.innerHTML = `
                <hr>
                <ul class="spa-summary-list">
                    <li class="spa-summary-item spa-summary-city">
                        <span class="spa-summary-icon">${locationIcon}</span>
                        ${window.wizardData.city_name}
                    </li>
                </ul>
            `;

            container.appendChild(summaryDiv);
        }

        window.hideLoader();
        return;
    }

    
    /* ==================================================
    1.3 ÚDAJE PROGRAMU (ikona, názov, obsah)
    ================================================== */
    if (window.currentState === 2 && window.wizardData.program_name && programData) {
        console.log('[renderInfobox] Rendering program data:', programData);
        
        const programDiv = document.createElement('div');
        programDiv.className = 'spa-infobox-program';
        
        let programMainHtml = '';
        let programIconHtml = '';
        
        // MAIN CONTENT
        programMainHtml += '<div class="spa-program-main">';
        
        // Názov programu s SPA logom
        if (programData.title) {
            const spaLogoSvg = icons && icons.spa_logo ? icons.spa_logo : '';
            programMainHtml += `<h4 class="spa-program-title">${spaLogoSvg}${programData.title}</h4>`;
        }
        
        // Obsah CPT
        programMainHtml += '<div class="spa-program-content">';
        
        if (programData.content) {
            programMainHtml += programData.content;
        }
        
        // TRÉNINGOVÉ TERMÍNY
        if (programData.schedule) {
            programMainHtml += `
                <div class="spa-training-schedule">
                        <div class="spa-program-schedule-title">
                        🕘 Rozvrh tréningového programu
                    </div>
                    <div class="spa-program-schedule-grid">
                        ${programData.schedule}
                        
                    </div>${programData.level_html ? programData.level_html : ''}
                </div>
            `;
        }
        
        programMainHtml += '</div>'; // .spa-program-content
        programMainHtml += '</div>'; // .spa-program-main
        
        // ICON PANEL
        programIconHtml += '<div class="spa-program-icon">';
        
        // Ikona programu + aplikácia CSS premenných
        if (programData.icon) {
            const colorStyle = [
                programData.primary_color ? `--program-primary-color: ${programData.primary_color};` : '',
                programData.secondary_color ? `--program-secondary-color: ${programData.secondary_color};` : ''
            ].filter(Boolean).join(' ');
            
            programIconHtml += `<div class="spa-program-icon-large" style="${colorStyle}">${programData.icon}</div>`;
        } else {
            const ageText = window.wizardData.program_age ? window.wizardData.program_age : '&nbsp;';
            programIconHtml += `<div class="spa-age-range-text no-svg-icon">${ageText}</div>`;
        }
        
        // VEĽKÝ TEXT VEKU POD SVG
        if (window.wizardData.program_age) {
            const primaryColor = programData.primary_color || '#6d71b2';
            programIconHtml += `<div class="spa-age-range-text" style="color: ${primaryColor};">${window.wizardData.program_age} r.</div>`;
        }
        
        programIconHtml += '</div>'; // .spa-program-icon
        
        // ZLOŽENIE: main + icon (icon sa použije neskôr)
        let programHtml = programMainHtml + programIconHtml;
        // Ulož ikonu do premennej pre neskoršie použitie
        window.savedProgramIconHtml = programIconHtml;

        // Aktualizovať textovú informáciu pre používateľa
        const registrationTypeBlock = document.getElementById('spa_registration_type');

        if (registrationTypeBlock) {
            const isChild = resolvedProgramType === 'child';
        
            const iconEl = registrationTypeBlock.querySelector('.spa-registration-type__icon');
            const textEl = registrationTypeBlock.querySelector('.spa-registration-type__text');            
            const labelEl = registrationTypeBlock.querySelector('.spa-reg-type-label');
        
            if (iconEl && textEl) {
                /* const labelText = isChild
                    ? 'Účastníkom tréningov bude dieťa'
                    : 'Účastníkom tréningov bude dospelá osoba'; */
                const labelText = 'Účastníkom tréningov bude';
        
                iconEl.textContent = isChild ? '👧' : '👨‍🦱';
                textEl.textContent = isChild
                    ? 'Dieťa (mladší ako 18 rokov)'
                    : 'Dospelá osoba (18+ rokov)';
                        
                if (labelEl) {
                    labelEl.textContent = labelText;
                } else {
                    console.warn('[SPA] .spa-reg-type-label nebol nájdený v #spa_registration_type');
                }
                // ^^^ KONIEC PRIDANIA ^^^
            }
        
            registrationTypeBlock.classList.toggle('is-child', isChild);
            registrationTypeBlock.classList.toggle('is-adult', !isChild);
        } else {
            console.warn('[SPA] HTML block #spa_registration_type not found – skipping update.');
        }
        
        console.log('[SPA Program Type] Age-based detection:', {
            age_min: programData.age_min,
            age_max: programData.age_max,
            programType: resolvedProgramType
        });             
            
            // ⭐ RODNÉ ČÍSLO - uloží info o type programu
            const birthNumberField = document.querySelector('input[name="spa_member_birthnumber"]');
            const birthNumberWrapper = birthNumberField ? birthNumberField.closest('.gfield') : null;    
        
        programDiv.innerHTML = programHtml;
        container.appendChild(programDiv);
        console.log('[SPA DEBUG FINAL]', {
            wizard_program_type: window.wizardData?.program_type,
            spaCurrentProgramType: window.spaCurrentProgramType,
            age_min: programData?.age_min
        });
    }
    
    /* ==================================================
    1.5 DYNAMICKÝ SUMMARY (mesto, vek, kapacita)
    ================================================== */
    if (window.currentState === 2 && window.wizardData.program_name && programData) {

        let summaryHtml = '<hr><ul class="spa-summary-list">';

        // MESTO s inline ikonou
        if (window.wizardData.city_name) {
            const locationIcon = icons && icons.location ? icons.location : '';
            
            let locationText = window.wizardData.city_name;
            
            if (data.place && window.currentState === 2) {
                const addressParts = [];
                if (data.place.name) addressParts.push(data.place.name);
                if (data.place.address) addressParts.push(data.place.address);
                
                const cityPart = data.place.city ? `<strong>${data.place.city}</strong>` : window.wizardData.city_name;
                const addressText = addressParts.filter(Boolean).join(', ');
                
                locationText = addressText ? `${cityPart} • ${addressText}` : cityPart;
            }
            
            summaryHtml += `
                <li class="spa-summary-item spa-summary-city">
                    <span class="spa-summary-icon">${locationIcon}</span>
                    ${locationText}
                </li>`;
        }

        // VEK s ikonou
        if (window.wizardData.program_age) {
            // Parsuj age_to z program_age (napr. "5–7" alebo "8+")
            const ageMatch = window.wizardData.program_age.match(/(\d+)[–-](\d+)/);
            const agePlusMatch = window.wizardData.program_age.match(/(\d+)\+/);
            
            let ageLabel = 'rokov'; // default
            
            if (agePlusMatch) {
                // Plus variant (napr. "8+") - vždy "rokov"
                ageLabel = 'rokov';
            } else if (ageMatch) {
                // Rozsah (napr. "5–7") - skloňuj podľa age_to
                const ageTo = parseInt(ageMatch[2]);
                if (ageTo === 1) {
                    ageLabel = 'rok';
                } else if (ageTo >= 2 && ageTo <= 4) {
                    ageLabel = 'roky';
                } else {
                    ageLabel = 'rokov';
                }
            }
            
            const ageIconSvg = icons && icons.age ? icons.age : '<span class="spa-icon-placeholder">👶</span>';
            
            summaryHtml += `
            <li class="spa-summary-item spa-summary-age">
                <span class="spa-summary-icon">${ageIconSvg}</span>
                <strong>${window.wizardData.program_age}</strong> ${ageLabel}
            </li>`;
        }

        if (window.currentState === 2 && programData) {
            window.renderFrequencySelector(programData);
        } else {
            window.renderFrequencySelector(null);
        }

        // KAPACITA (len v stave 2)
        if (window.currentState === 2 && window.wizardData.program_name && capacityFree !== null && capacityFree !== undefined) {                
            const capacityIconSvg = icons && icons.capacity ? icons.capacity : '';
            const capacityLabel = getCapacityLabel(capacityFree);
        
            summaryHtml += `
                <li class="spa-summary-item spa-summary-capacity">
                    <span class="spa-summary-icon">${capacityIconSvg}</span>
                    <strong>${capacityFree}</strong> ${capacityLabel}
                </li>`;
        }            
       
        // CENA (len ak je vybraný program)
        if (price && window.wizardData.program_name) {
            const priceIconSvg = icons && icons.price ? icons.price : '<span class="spa-icon-placeholder">€</span>';
            const priceFormatted = price.replace(/(\d+\s*€)/g, '<strong>$1</strong>');

            summaryHtml += `
                <li class="spa-summary-item spa-summary-price">
                    <span class="spa-summary-icon">${priceIconSvg}</span>
                    ${priceFormatted}
                </li>`;
        }

        // VEKOVÝ ROZSAH (len v stave 2)
        if (window.currentState === 2 && window.wizardData.program_name && data.program) {
            const ageFrom = data.program.age_min;
            const ageTo = data.program.age_max;
            
            let ageText = '';
            
            if (ageFrom && ageTo) {
                ageText = ageFrom.toString().replace('.', ',') + ' - ' + ageTo.toString().replace('.', ',') + ' r.';
            } else if (ageFrom) {
                ageText = ageFrom.toString().replace('.', ',') + '+ r.';
            } else {
                ageText = '';
            }
            
            if (ageText) {
                setTimeout(function() {
                    const iconLarge = container.querySelector('.spa-program-icon-large');
                    if (iconLarge) {
                        if (!iconLarge.querySelector('.spa-age-range-text')) {
                            let ageRangeText = container.querySelector('.spa-age-range-text');
                            
                            if (ageRangeText) {
                                ageRangeText.parentElement.removeChild(ageRangeText);
                            } else {
                                ageRangeText = document.createElement('div');
                                ageRangeText.className = 'spa-age-range-text';
                                ageRangeText.textContent = ageText;
                            }

                            const svg = iconLarge.querySelector('svg');
                            if (svg) {
                                if (svg.nextSibling) {
                                    iconLarge.insertBefore(ageRangeText, svg.nextSibling);
                                } else {
                                    iconLarge.appendChild(ageRangeText);
                                }
                            } else {
                                iconLarge.appendChild(ageRangeText);
                            }
                        }
                    }
                }, 0);
            }
        }

        summaryHtml += '</ul>';
        
        // Prebuduj štruktúru s wrapperom
        const programDiv = container.querySelector('.spa-infobox-program');
        if (programDiv && window.currentState === 2) {
            const mainDiv = programDiv.querySelector('.spa-program-main');
            const iconDiv = programDiv.querySelector('.spa-program-icon');
            
            if (mainDiv) {
                // Vytvor left wrapper s main + summary
                const leftHtml = '<div class="spa-program-left">' + 
                                mainDiv.outerHTML + 
                                '<div class="spa-infobox-summary">' + summaryHtml + '</div>' +
                                '</div>';
                
                // Použij uloženú ikonu
                const iconHtml = iconDiv ? iconDiv.outerHTML : (window.savedProgramIconHtml || '');
                
                // Nastav finálnu štruktúru
                programDiv.innerHTML = leftHtml + iconHtml;
            }
        }
    }

    function getCapacityLabel(count) {
        if (count === 1) {
            return 'voľné miesto';
        }
        if (count >= 2 && count <= 4) {
            return 'voľné miesta';
        }
        return 'voľných miest';
    }

    // Aplikuj farby na SVG elementy (override inline fill atribútov)
    if (programData && (programData.primary_color || programData.secondary_color)) {
        setTimeout(() => {
            const iconContainer = container.querySelector('.spa-program-icon-large');
            if (iconContainer) {
                const svg = iconContainer.querySelector('svg');
                if (svg) {
                    // Shirt (primary color)
                    const shirtPaths = svg.querySelectorAll('#shirt, #shirt path');
                    shirtPaths.forEach(el => {
                        if (programData.primary_color) {
                            el.style.fill = programData.primary_color;
                        }
                    });
                    
                    // Shirt shadow (tmavšia primary)
                    const shadowPaths = svg.querySelectorAll('#shirt-shadow path');
                    if (programData.primary_color) {
                        shadowPaths.forEach(path => {
                            path.style.fill = `color-mix(in srgb, ${programData.primary_color} 70%, black)`;
                        });
                    }
                    
                    // Shirt highlight (svetlejšia primary)
                    const highlightPaths = svg.querySelectorAll('#shirt-highlight path');
                    if (programData.primary_color) {
                        highlightPaths.forEach(path => {
                            path.style.fill = `color-mix(in srgb, ${programData.primary_color} 70%, white)`;
                        });
                    }
                    
                    // Logo SPA (secondary color)
                    const logoPaths = svg.querySelectorAll('#logoSPA path');
                    if (programData.secondary_color) {
                        logoPaths.forEach(path => {
                            path.style.fill = programData.secondary_color;
                        });
                    }
                }
            }
            
            // Vypni loader AŽ PO aplikácii farieb
            hideLoader();
        }, 100);
    } else {
        // Ak nie sú farby, vypni loader hneď
        hideLoader();
    }
};

    

 /**
 * Zobraz loader
 */
window.showLoader = function() {
        console.log('[SPA LOADER] start');
        const loader = document.getElementById('spa-infobox-loader');
        if (loader) {
            loader.classList.add('active');
        }
    };   

/**
 * Skry loader
 */
window.hideLoader = function() {
        console.log('[SPA LOADER] end');
        const loader = document.getElementById('spa-infobox-loader');
        if (loader) {
            loader.classList.remove('active');
        }
    };    


/**
 * Nájdi sekciu podľa CSS triedy
 * @param {string} cssClass - CSS triedy (napr. 'spa-section-common')
 * @returns {HTMLElement|null}
 */
window.findSectionByClass = function(cssClass) {
    return document.querySelector(`.${cssClass}`);
};