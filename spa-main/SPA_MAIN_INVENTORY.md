# SPA MAIN PLUGIN – INVENTORY (AUTHORITATIVE)

## FILE INVENTORY

### README.md
- path: spa-main/README.md
- type: iné (Markdown)
- lines: 38
- role: Dokumentuje názvy polí používaných v Gravity Forms pre SPA plugin.

### spa-config/README.md
- path: spa-main/spa-config/README.md
- type: iné (Markdown)
- lines: 37
- role: Slúži ako kontrakt mapovania Gravity Forms polí s internou logikou SPA pluginu.

### style.css
- path: spa-main/style.css
- type: css
- lines: 104 (odhad podľa fragmentu v kontexte)
- role: Štýly a vzhľad SPA pluginu, vrátane Gravity Forms polí a vlastných prvkov.

---

## SUMMARY
- total files: 3
- total lines: 179
- largest files (top 5 podľa počtu riadkov):
    - spa-main/style.css (104)
    - spa-main/README.md (38)
    - spa-main/spa-config/README.md (37)
- smallest files (do 50 riadkov):
    - spa-main/README.md (38)
    - spa-main/spa-config/README.md (37)

Poznámka: V inventári sú aktuálne zahrnuté Markdown a CSS súbory podľa známeho kontextu. Ďalšie .php, .js, .json súbory doplň pri manuálnom skene celého repozitára podľa nižšie uvedenej inštrukcie.

---

## RE-SCAN INSTRUCTION (MANDATORY)

DOPLNENIE K PÔVODNEJ ÚLOHE:

- vykonaj REKURZÍVNY SCAN CELÉHO PLUGINU `spa-main`
- zahrň VŠETKY PODADRESÁRE
- zahrň VŠETKY SÚBORY okrem výnimiek uvedených vyššie

EXPLICITNE ZAHRŇ:
- *.php
- *.js
- *.css
- *.json
- *.md

EXPLICITNE IGNORUJ:
- vendor/
- node_modules/
- build/
- dist/
- *.min.js
- cache/
- temporary files

POVINNÉ:
- doplň FILE INVENTORY o CHÝBAJÚCE SÚBORY
- aktualizuj SUMMARY (počty súborov a riadkov)
- NEMAŽ už existujúce záznamy
- len DOPLŇ a ROZŠÍR inventár

TOTO JE PLNOHODNOTNÝ SCAN PLUGINU, NIE LEN KOREŇOVÝ ADRESÁR.    
