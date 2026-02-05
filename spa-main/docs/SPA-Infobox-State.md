# SPA Infobox – State & Wizard Logika

Tento dokument popisuje finálny stav logiky SPA Infobox wizardu
po refaktore (GET, errorbox, CHILD/ADULT).

---

## 1. Stav projektu

- GET parametre: ✅ funkčné
- Errorbox:
  - zobrazí sa pri chybe GET
  - po manuálnej zmene mesta / programu sa skryje
- CHILD / ADULT vetvy: ✅ korektné
- Wizard flow: stabilný

Stav overený manuálnym testom (02/2026).

---

## 2. Zodpovednosti súborov

### spa-infobox-core-state.js
- globálny state wizardu
- `currentState`
- `wizardData`
- helper funkcie (napr. spa_remove_diacritics)
- **žiadny DOM rendering**

---

### spa-infobox-errorbox.js
- jediný bod pre:
  - zobrazovanie
  - skrývanie
  - reset errorboxu
- reaguje na:
  - `spaErrorState`
  - zmenu `currentState`

---

### spa-infobox-state.js
- hlavný orchestrátor
- `renderInfobox()`
- render UI podľa:
  - programu
  - veku
  - typu registrácie
- obsahuje ~700 riadkov → **vedomé rozhodnutie**
  (funkcia je monolitická, ale stabilná)

---

### spa-infobox-restore.js
- obnova wizardu po reload / GET
- oddelené kvôli:
  - čitateľnosti
  - budúcim úpravám

---

## 3. Pravidlá, ktoré NESMÚ byť porušené

- ❌ žiadne nové state premenné mimo core
- ❌ žiadne DOM manipulácie v core-state
- ❌ errorbox sa NESMIE ovládať mimo errorbox súboru
- ❌ renderInfobox sa nerozdeľuje bez jasného dôvodu

---

## 4. Testovacie scenáre (POVINNÉ)

- GET:
  - valid city + program
  - invalid city
  - invalid program
- manuálna zmena selectov po chybe
- CHILD vs ADULT programy
- reload stránky po chybe

---

## 5. Status

- Stav: STABILNÝ
- Pripravené na ďalšie fázy SPA
