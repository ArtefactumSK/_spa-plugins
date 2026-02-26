/**
 * SPA Register GF – Age Preview & Warning
 *
 * Načíta birthdate z GF poľa spa_member_birthdate,
 * vypočíta vek a zobrazí ho v #spa-age-preview.
 * Ak vek nezodpovedá rozsahu programu, zobrazí hlášku.
 *
 * Zdroj rozsahu: data-age-min / data-age-max na .spa-age-warning
 * (server vložil tieto atribúty cez PreRenderHooks::buildPriceSummary)
 *
 * Tento súbor NEOBSAHUJE žiadnu business logiku.
 * SESSION nie je tu čítaná. Iba UI upozornenie.
 */
(function () {
    'use strict';

    /**
     * Vypočítaj vek z reťazca vo formáte dd.mm.rrrr
     * @param {string} dateStr
     * @returns {number|null}
     */
    function calcAge(dateStr) {
        if (!dateStr) return null;
        const parts = dateStr.trim().split('.');
        if (parts.length !== 3) return null;
        const day   = parseInt(parts[0], 10);
        const month = parseInt(parts[1], 10) - 1;
        const year  = parseInt(parts[2], 10);
        if (isNaN(day) || isNaN(month) || isNaN(year)) return null;
        const birth = new Date(year, month, day);
        const today = new Date();
        if (birth > today) return null;
        let age = today.getFullYear() - birth.getFullYear();
        const m = today.getMonth() - birth.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) {
            age--;
        }
        return age;
    }

    /**
     * Slovenská gramatika pre vek
     * @param {number} age
     * @returns {string}
     */
    function calcAgeLabel(age) {
        if (age === 1) return '1 rok';
        if (age >= 2 && age <= 4) return age + ' roky';
        return age + ' rokov';
    }

    /**
     * Nájdi birthdate input:
     * 1. cez spaRegisterFields['spa_member_birthdate'] (name atribút)
     * 2. fallback: placeholder="dd.mm.rrrr"
     * @returns {HTMLInputElement|null}
     */
    function findBirthdateInput() {
        const fields  = window.spaRegisterFields || {};
        const inputId = fields['spa_member_birthdate'];
        if (inputId) {
            const el = document.querySelector('[name="' + inputId + '"]');
            if (el) return el;
        }
        return document.querySelector('.gfield input[placeholder="dd.mm.rrrr"]');
    }

    /**
     * Nájdi age warning element – PHP renderuje .spa-age-warning
     * @returns {Element|null}
     */
    function findWarningEl() {
        return document.querySelector('.spa-age-warning');
    }

    /**
     * Hlavná funkcia – vypočíta vek, zapíše do #spa-age-preview,
     * skryje server-renderovanú hlášku .spa-age-warning
     */
    function checkAge() {
        const warningEl = findWarningEl();
        const previewEl = document.getElementById('spa-age-preview');

        // Vždy skry server-renderovanú hlášku – preview preberá jej úlohu
        if (warningEl) {
            warningEl.style.display = 'none';
        }

        const inputEl = findBirthdateInput();
        if (!inputEl) return;

        const age = calcAge(inputEl.value);

        if (!previewEl) return;

        if (age === null) {
            previewEl.innerHTML    = '';
            previewEl.style.display = 'none';
            return;
        }

        // Načítaj rozsah – primárne z window.spaAgeMin/spaAgeMax (PHP inline script)
        // fallback: data atribúty na .spa-age-warning (ak existuje v DOM)
        const ageMin = (window.spaAgeMin !== undefined && window.spaAgeMin !== null)
            ? window.spaAgeMin
            : (warningEl ? parseFloat(warningEl.dataset.ageMin) : NaN);
        const ageMax = (window.spaAgeMax !== undefined && window.spaAgeMax !== null)
            ? window.spaAgeMax
            : (warningEl && warningEl.dataset.ageMax !== undefined ? parseFloat(warningEl.dataset.ageMax) : null);

        const tooYoung = !isNaN(ageMin) && age < ageMin;
        const tooOld   = ageMax !== null && !isNaN(ageMax) && age > ageMax;

        if (tooYoung || tooOld) {
            previewEl.innerHTML = calcAgeLabel(age)
                + ' <span class="gfield_required"> – nezodpovedá vybranému programu</span>';
        } else {
            previewEl.textContent = calcAgeLabel(age);
        }
        previewEl.style.display = '';
    }

    // ── Event listeners ──────────────────────────────────────────────────────

    document.addEventListener('input', function (e) {
        const fields    = window.spaRegisterFields || {};
        const bdInputId = fields['spa_member_birthdate'];
        if (
            (bdInputId && e.target.name === bdInputId) ||
            (!bdInputId && e.target.placeholder === 'dd.mm.rrrr')
        ) {
            checkAge();
        }
    });

    document.addEventListener('change', function (e) {
        const fields    = window.spaRegisterFields || {};
        const bdInputId = fields['spa_member_birthdate'];
        if (
            (bdInputId && e.target.name === bdInputId) ||
            (!bdInputId && e.target.placeholder === 'dd.mm.rrrr')
        ) {
            checkAge();
        }
    });

    // Initial check po načítaní (pre prípad predvyplneného poľa)
    document.addEventListener('DOMContentLoaded', function () {
        setTimeout(checkAge, 400);
    });

    // GF AJAX re-render (pagebreak späť/vpred)
    document.addEventListener('gform_post_render', function () {
        setTimeout(checkAge, 200);
    });

    // Expose pre prípadné externé volanie
    window.spaCheckAge = checkAge;

})();