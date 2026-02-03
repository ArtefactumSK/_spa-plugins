# SPA Infobox – State & Render Audit (ARCH-REF-01)

## Kontext

Tento dokument sumarizuje **architektonický audit infobox logiky** (stavový automat + DOM render) v projekte SPA. Slúži ako **referenčný podklad pre budúci refaktor**, nie ako okamžitý patch.

Aktuálny stav systému je **funkčný, ale architektonicky nekonzistentný**. Cieľom dokumentu je:

* pomenovať skutočný root cause problému,
* oddeliť symptómy od príčiny,
* definovať bezpečný plán refaktoru v budúcnosti.

---

## 1. Základný problém (Executive Summary)

Infobox trpí **race condition + ghost DOM efektom**, pretože:

* `window.currentState` sa zapisuje na **viacerých miestach**
* DOM cleanup (`.spa-infobox-program`) sa vykonáva **na viacerých miestach**
* render logika nie je centralizovaná
* cleanup je niekedy viazaný na AJAX callback (nie deterministický)

Výsledok:

* po návrate z CASE2 → CASE1 ostáva v DOM **program infobox**, ktorý tam nemá byť
* opravy typu „pridaj if“ alebo „odstráň element“ sú nestabilné

---

## 2. Kde sa číta / zapisuje `window.currentState`

### Zápisy (PROBLÉM)

| Súbor                | Funkcia / kontext      | Hodnoty | Poznámka             |
| -------------------- | ---------------------- | ------- | -------------------- |
| spa-infobox-state.js | updateInfoboxState()   | 0/1/2   | legitímne            |
| spa-infobox-state.js | restoreWizardData()    | 1, 2    | **duplicitný zápis** |
| spa-infobox-state.js | city change handler    | 0, 1    | **duplicitný zápis** |
| spa-infobox-state.js | program change handler | 1, 2    | **duplicitný zápis** |
| spa-infobox-state.js | applyGetParams()       | 1, 2    | **duplicitný zápis** |

➡️ `window.currentState` má **5+ entry pointov** → žiadny single source of truth.

---

## 3. Kde sa čistí / renderuje DOM

### DOM CLEANUP (DUPLIKÁTY)

| Súbor                | Funkcia              | Akcia                         | Problém       |
| -------------------- | -------------------- | ----------------------------- | ------------- |
| spa-infobox-state.js | loadInfoboxContent() | cleanup pri state < 2         | pred AJAX     |
| spa-infobox-state.js | city change handler  | sync cleanup                  | duplicitný    |
| spa-infobox.js       | renderInfobox()      | hard cleanup container        | duplicitný    |
| spa-infobox.js       | CASE 0/1             | remove `.spa-infobox-program` | tretí cleanup |

➡️ Cleanup prebieha **3× rôznymi cestami**.

---

## 4. Prečo vzniká bug (Root Cause)

Kritický scenár:

1. Používateľ je v CASE2 (program zobrazený)
2. Zmení mesto / program → JS nastaví `currentState = 1`
3. Zavolá sa `loadInfoboxContent(1)`
4. **AJAX ešte nedorazil**
5. Cleanup sa nevykonal alebo bol preskočený
6. `.spa-infobox-program` ostáva v DOM

➡️ Stav UI ≠ stav aplikácie.

Toto **nie je chyba jedného if-u**, ale **porušený render kontrakt**.

---

## 5. Správny architektonický princíp (cieľový stav)

### A) Jediný zdroj pravdy pre STATE

```js
window.setSpaState(newState, reason)
```

* jediné miesto, kde sa zapisuje `window.currentState`
* všetky ostatné miesta ho iba volajú
* voliteľné logovanie stack trace

---

### B) Jediný render kontrakt

`renderInfobox()`:

* **vždy** najprv kompletne vyčistí `#spa-infobox-container` (okrem loadera)
* **potom** renderuje podľa `window.currentState`

| State | Render                    |
| ----- | ------------------------- |
| 0     | WP obsah                  |
| 1     | WP obsah + mesto summary  |
| 2     | Program infobox + summary |

➡️ Je to **jediné miesto**, kde existuje `.spa-infobox-program`.

---

### C) loadInfoboxContent()

* nerobí žiadny DOM cleanup
* iba:

  * showLoader()
  * AJAX
  * deleguje na `renderInfobox()`

---

## 6. Prečo sa to NEMÁ robiť hneď

* ide o **refaktor**, nie hotfix
* dotýka sa minimálne **3 JS súborov**
* mení tok udalostí
* vyžaduje testovací checklist

➡️ Správne rozhodnutie bolo:

> ponechať stav ako „prípustný“ a pokračovať na errorbox / validáciu

---

## 7. Odporúčaný postup do budúcna

### Fáza 1 – Logging only

* zaviesť `setSpaState()`
* používať ho paralelne s existujúcou logikou
* len sledovať prechody

### Fáza 2 – Zrušenie duplicitných zápisov

* nahradiť priame zápisy setterom

### Fáza 3 – Render kontrakt

* odstrániť cleanup mimo `renderInfobox()`

Každá fáza = samostatný commit.

---

## 8. Stav dokumentu

* ID: `INFBOX-ARCH-REF-01`
* Stav: **diagnóza potvrdená**
* Implementácia: **odložená vedome**

---

*Dokument slúži ako stabilný referenčný bod. Nie je určený na okamžitú implementáciu.*
