# SPA – Register Shortcodes

Tento dokument obsahuje kompletný zoznam shortcodov používaných v SPA systéme,
ich účel, parametre, viditeľnosť a stav (CORE / TRIAL).

---

## [spa_trial_info]

**Stav:** 🧪 testovací  
**Modul:** CORE / TRIAL infra  
**Viditeľnosť:** Admin / Owner

### Popis
Zobrazí informačný panel o stave systému:
- CORE verzia
- TRIAL verzia + dátum platnosti

### Použitie


### Poznámka
Dočasný shortcode určený na kontrolu.
V produkcii bude presunutý do dashboardu manažéra.

---

## [spa_registrations_list]

**Stav:** ✅ produkčný  
**Modul:** CORE  
**Viditeľnosť:** Admin, Tréner, Rodič

### Popis
Zobrazí zoznam registrácií podľa roly používateľa:
- Admin: všetky registrácie
- Tréner: len jeho tréningy
- Rodič: jeho deti

### Použitie
[spa_registrations_list]

---

## [spa_attendance]

**Stav:** ✅ produkčný  
**Modul:** CORE  
**Viditeľnosť:** Tréner

### Popis
Umožňuje trénerovi zapísať dochádzku pre konkrétny rozvrh.

### Parametre
- `schedule_id` – ID rozvrhu (povinné)

### Použitie
[spa_attendance schedule_id="898"]

---

## [spa_schedules]

**Stav:** ✅ produkčný  
**Modul:** CORE  
**Viditeľnosť:** Verejné / Rodič

### Popis
Zobrazí zoznam rozvrhov filtrovaných podľa mesta (taxonómia `spa_place`).

### Parametre
- `city` – slug mesta (napr. `malacky`, `kosice`)

### Použitie
[spa_schedules city="malacky"]
