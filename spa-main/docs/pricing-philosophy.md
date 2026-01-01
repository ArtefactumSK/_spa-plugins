# Filozofia cien – SPA systém

## Základné princípy
- Cena je viazaná na tréningový program (spa_group)
- Program môže mať viac paralelných cien
- Neexistuje povinnosť mať všetky ceny vyplnené

## Typy cien (post_meta)
- spa_price_1x_weekly – mesačná cena pri 1 tréningu týždenne
- spa_price_2x_weekly – mesačná cena pri 2 tréningoch týždenne
- spa_price_monthly – fixná mesačná cena (override)
- spa_price_semester – cena za polrok / sezónu
- spa_external_surcharge – príplatok za externé priestory

## Pravidlá zobrazovania
- Zobrazujú sa len ceny s hodnotou > 0
- 0 alebo prázdna hodnota = nezobrazuje sa
- Poradie: 1x → 2x → mesačne → semester → príplatok

## Frontend
- Infobox zobrazuje agregovanú cenu
- Výber konkrétnej ceny prebieha v ďalších krokoch registrácie
