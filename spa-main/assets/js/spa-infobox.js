/**
 * SPA Infobox Wizard – Frontend logika
 */

(function() {
    'use strict';

    if (typeof spaConfig === 'undefined') {
        console.error('[SPA Infobox] spaConfig nie je definovaný.');
        return;
    }
    if (typeof spaConfig === 'undefined') {
        console.error('[SPA Infobox] spaConfig nie je definovaný.');
        return; // ← ZASTAV VYKONÁVANIE
    }
    let lastCapacityFree = null;
    let currentState = 0;
    let wizardData = {
        program_id: null,
        city_name: '',
        program_name: '',
        program_age: ''
    };

    document.addEventListener('DOMContentLoaded', function() {
        initInfobox();
        watchFormChanges();
    });

    // Gravity Forms AJAX callback
    if (typeof jQuery !== 'undefined') {
        jQuery(document).on('gform_post_render', function() {
            initInfobox();
            watchFormChanges();
        });
    }

    /**
     * Inicializácia infoboxu
     */
    function initInfobox() {
        const infoboxContainer = document.getElementById('spa-infobox-container');
        
        if (!infoboxContainer) {
            console.warn('[SPA Infobox] Container nenájdený v DOM.');
            return;
        }

        // Načítaj úvodný stav
        loadInfoboxContent(0);
        
        console.log('[SPA Infobox] Inicializovaný.');
    }

    /**
     * Sledovanie zmien vo formulári
     */
    function watchFormChanges() {
        // Sleduj zmenu mesta
        const cityField = document.querySelector(`[name="${spaConfig.fields.spa_city}"]`);
        if (cityField) {
            cityField.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                
                if (this.value && this.value !== '0') {
                    wizardData.city_name = selectedOption.text;
                    currentState = 1;
                } else {
                    // Reset - vyčisti všetko
                    wizardData.city_name = '';
                    wizardData.program_name = '';
                    wizardData.program_id = null;
                    wizardData.program_age = '';
                    currentState = 0;
                }
                
                loadInfoboxContent(currentState);
            });
        }
        
        // Sleduj zmenu programu
        const programField = document.querySelector(`[name="${spaConfig.fields.spa_program}"]`);

        console.log('[SPA Infobox] Program field selector:', `[name="${spaConfig.fields.spa_program}"]`);
        console.log('[SPA Infobox] Program field element:', programField);

        if (programField) {
            programField.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                
                console.log('[SPA Infobox] Program changed - value:', this.value);
                console.log('[SPA Infobox] Program changed - text:', selectedOption.text);
                
                if (this.value) {
                    wizardData.program_name = selectedOption.text;
                    wizardData.program_id = selectedOption.getAttribute('data-program-id') || this.value;
                    
                    console.log('[SPA Infobox] Program ID:', wizardData.program_id);
                    
                    // Parsuj vek z názvu programu (podporuje desatinné čísla s čiarkou)
                    const ageMatch = selectedOption.text.match(/(\d+(?:,\d+)?)\s*[–-]\s*(\d+(?:,\d+)?)/);
                    if (ageMatch) {
                        wizardData.program_age = ageMatch[1] + ' - ' + ageMatch[2];
                    } else {
                        const agePlusMatch = selectedOption.text.match(/(\d+(?:,\d+)?)\+/);
                        if (agePlusMatch) {
                            wizardData.program_age = agePlusMatch[1] + '+';
                        }
                    }

                    console.log('[SPA Infobox] Parsed program_age:', wizardData.program_age); // DEBUG
                    
                    currentState = 2;
                    console.log('[SPA Infobox] State changed to 2, wizardData:', wizardData);
                } else {
                    // Reset programu - vráť sa do stavu 1 (mesto) alebo 0
                    wizardData.program_name = '';
                    wizardData.program_id = null;
                    wizardData.program_age = '';
                    currentState = wizardData.city_name ? 1 : 0;
                }
                
                loadInfoboxContent(currentState);
            });
        } else {
            console.error('[SPA Infobox] Program field NOT FOUND!');
        }
    }

    /**
     * Načítanie obsahu infoboxu cez AJAX
     */
    function loadInfoboxContent(state) {
        console.log('[SPA Infobox] Loading state:', state, wizardData); // 🔍 DEBUG

        const formData = new FormData();
        formData.append('action', 'spa_get_infobox_content');
        formData.append('program_id', wizardData.program_id);
        formData.append('state', state);
        formData.append('city_name', wizardData.city_name);
        formData.append('program_name', wizardData.program_name);
        formData.append('program_age', wizardData.program_age);

        // Uložíme si ikonu location pre inline použitie v summary
        let locationIconSvg = null;

        fetch(spaConfig.ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            console.log('[SPA Infobox] AJAX Response:', data); // DEBUG
            
            if (data.success) {
                renderInfobox(data.data, data.data.icons, data.data.capacity_free, data.data.price);
            } else {
                console.error('[SPA Infobox] Chyba:', data.data?.message);
            }
        })
        .catch(error => {
            console.error('[SPA Infobox] AJAX error:', error);
        });
    }

    /**
     * Vykreslenie infoboxu
     */
    function renderInfobox(data, icons, capacityFree, price) {
        console.log('[renderInfobox] Full data:', data);
        console.log('[renderInfobox] State:', currentState, 'wizardData:', wizardData);
        
        const content = data.content;
        const programData = data.program;
        
        const container = document.getElementById('spa-infobox-container');
        if (!container) return;
    
        // 0. Vyčisti kontajner (JEDINÝ render bod)
        container.innerHTML = '';

        /* ==================================================
        1. OBSAH – WP stránka (SPA Infobox Wizard)
        ================================================== */
        if (!wizardData.program_name) {
            const contentDiv = document.createElement('div');
            contentDiv.className = 'spa-infobox-content';
            contentDiv.innerHTML = content;
            container.appendChild(contentDiv);
        }
        
        /* ==================================================
        1.3 ÚDAJE PROGRAMU (ikona, názov, obsah)
        ================================================== */
        if (currentState === 2 && wizardData.program_name && programData) {
            console.log('[renderInfobox] Rendering program data:', programData);
            
            const programDiv = document.createElement('div');
            programDiv.className = 'spa-infobox-program';
            
            let programHtml = '';
            
            // Ikona programu (zväčšená) + aplikácia CSS premenných
            if (programData.icon) {
                const colorStyle = [
                    programData.primary_color ? `--program-primary-color: ${programData.primary_color};` : '',
                    programData.secondary_color ? `--program-secondary-color: ${programData.secondary_color};` : ''
                ].filter(Boolean).join(' ');
                
                programHtml += `<div class="spa-program-icon-large" style="${colorStyle}">${programData.icon}</div>`;
            }
            
            // VEĽKÝ TEXT VEKU POD SVG
            if (wizardData.program_age) {
                const primaryColor = programData.primary_color || '#6d71b2';
                programHtml += `<div class="spa-age-range-text" style="color: ${primaryColor};">${wizardData.program_age} r.</div>`;
            }
            
            // Názov programu s SPA logom
            if (programData.title) {
                const spaLogoSvg = icons && icons.spa_logo ? icons.spa_logo : '';
                programHtml += `<h4 class="spa-program-title">${spaLogoSvg}${programData.title}</h4>`;
            }
            
            // Obsah CPT (čistý WordPress content)
            if (programData.content) {
                programHtml += `<div class="spa-program-content">${programData.content}</div>`;
            }
            
            programDiv.innerHTML = programHtml;
            container.appendChild(programDiv);
        }
        
        /* ==================================================
        1.5 DYNAMICKÝ SUMMARY (mesto, vek, kapacita)
        ================================================== */
        if (wizardData.city_name || wizardData.program_age) {

            const summaryDiv = document.createElement('div');
            summaryDiv.className = 'spa-infobox-summary';

            let summaryHtml = '<hr><ul class="spa-summary-list">';

            // MESTO s inline ikonou
            if (wizardData.city_name) {
                // Získaj location ikonu z už renderovaných ikon
                const locationIcon = icons && icons.location ? icons.location : '';
                
                summaryHtml += `
                    <li class="spa-summary-item spa-summary-city">
                        <span class="spa-summary-icon">${locationIcon}</span>
                        ${wizardData.city_name}
                    </li>`;
            }
            // VEK s ikonou (načítanou z témy)
            if (wizardData.program_age) {
                // Gramatika: 8+ = "rokov", 6-8 = "roky"
                const ageLabel = wizardData.program_age.includes('+') ? 'rokov' : 'roky';
                
                // Ikona age (z témy)
                const ageIconSvg = icons && icons.age ? icons.age : '<span class="spa-icon-placeholder">👶</span>';
                
                summaryHtml += `
                    <li class="spa-summary-item spa-summary-age">
                        <span class="spa-summary-icon">${ageIconSvg}</span>
                        <strong style="color: var(--program-primary-color, inherit);">${wizardData.program_age}</strong> ${ageLabel}
                    </li>`;
            }

            // KAPACITA (len v stave 2)
            if (currentState === 2 && wizardData.program_name && capacityFree !== null && capacityFree !== undefined) {            

                const capacityIconSvg = icons && icons.capacity
                    ? icons.capacity
                    : '';
                const capacityLabel = getCapacityLabel(capacityFree);
            
                summaryHtml += `
                    <li class="spa-summary-item spa-summary-capacity">
                        <span class="spa-summary-icon">${capacityIconSvg}</span>
                        <strong>${capacityFree}</strong> ${capacityLabel}
                    </li>`;
            }            
           
            // CENA (len ak je vybraný program)
            // Problém: price.svg sa nezobrazuje lebo v PHP kóde nie je vrátená v poli $icons, teda neprenáša sa do JS
            // RIEŠENIE: fallback na <span>€</span> ak svg neprišlo, ale HLAVNÝ problém treba opraviť v PHP (pozri nižšie JS komentáre)

            if (price && wizardData.program_name) {
                let priceIconSvg = '€';
                // icons.price je undefined ak PHP ho negeneruje do pola $icons. Ostatné (location/capacity) tam sú, price nie.
                // Odporúčaná oprava (PHP): do funkcie spa_get_infobox_icons() pridať riadok pre 'price' v stave 2:
                // $icons['price'] = spa_icon('price', 'spa-icon-price', $options);
                // Pokiaľ to v JS nie je, fallbackuj na €
                if (icons && icons.price && typeof icons.price === 'string' && icons.price.trim().startsWith('<svg')) {
                    priceIconSvg = icons.price;
                } else {
                    priceIconSvg = '<span class="spa-icon-placeholder">€</span>';
                }
                
                // Rozdeľ cenu a kontext (napr. "130 € / 2× týždenne")
                const priceFormatted = price.replace(/(\d+\s*€)/g, '<strong>$1</strong>');

                summaryHtml += `
                    <li class="spa-summary-item spa-summary-price">
                        <span class="spa-summary-icon">${priceIconSvg}</span>
                        ${priceFormatted}
                    </li>`;
            }
            // VEKOVÝ ROZSAH (len v stave 2)
            if (currentState === 2 && wizardData.program_name && data.program) {
                const ageFrom = data.program.age_min;
                const ageTo = data.program.age_max;
                
                let ageText = '';
                
                if (ageFrom && ageTo) {
                    // Formát: 1,8–3 r.
                    ageText = ageFrom.toString().replace('.', ',') + ' - ' + ageTo.toString().replace('.', ',') + ' r.';
                } else if (ageFrom) {
                    // Formát: 10+ r.
                    ageText = ageFrom.toString().replace('.', ',') + '+ r.';
                }
                
                if (ageText) {
                    // Po vykreslení summary boxu (po .innerHTML = summaryHtml), presúvať pomocou setTimeout(…, 0)
                    setTimeout(function() {
                        const iconLarge = container.querySelector('.spa-program-icon-large');
                        if (iconLarge) {
                            // Najprv zisti, či tam už .spa-age-range-text je
                            if (!iconLarge.querySelector('.spa-age-range-text')) {
                                // Najprv over, či niekde v SPA Infoboxe je už .spa-age-range-text (napr. v summary)
                                let ageRangeText = container.querySelector('.spa-age-range-text');
                                
                                // Ak existuje v summary (li), vyber ju odtiaľ a použijeme existujúci element
                                if (ageRangeText) {
                                    ageRangeText.parentElement.removeChild(ageRangeText);
                                } else {
                                    // Ak nie, vytvor nový element (pre bezpečnosť)
                                    ageRangeText = document.createElement('div');
                                    ageRangeText.className = 'spa-age-range-text';
                                    ageRangeText.textContent = ageText;
                                }
                                // Presuň/vlož do správneho miesta - hneď za SVG
                                // Nájdeme SVG vo vnútri .spa-program-icon-large
                                const svg = iconLarge.querySelector('svg');
                                if (svg) {
                                    if (svg.nextSibling) {
                                        iconLarge.insertBefore(ageRangeText, svg.nextSibling);
                                    } else {
                                        iconLarge.appendChild(ageRangeText);
                                    }
                                } else {
                                    // fallback: vlož na koniec, ak SVG neexistuje
                                    iconLarge.appendChild(ageRangeText);
                                }
                            }
                        }
                    }, 0);
                }
            }
            /* sumarizacia kontajneru - koniec */
            summaryHtml += '</ul>';

            summaryDiv.innerHTML = summaryHtml;
            container.appendChild(summaryDiv);
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
        if (programData.primary_color || programData.secondary_color) {
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
            }, 100);
        }
    }

})();