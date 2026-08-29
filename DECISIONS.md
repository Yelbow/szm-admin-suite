# DECISIONS

> Gefaalde aanpakken, gotchas en niet-vanzelfsprekende architectuur-keuzes, gedateerd.

- 2026-08-29: **Module-architectuur = module registry + drop-in thema-folders.** Geen monolithisch single-file plugin zoals `szm-admin-menu-manager` — met 4+ modules en door AI te bouwen thema's schaalt dat niet. Hoofdbestand is een dunne loader; elke module in `inc/modules/<slug>/` zelf-registreert en draait alleen als aan.
- 2026-08-29: **Admin theme module is een thema-systeem, geen instellingenformulier.** De gebruiker laat een AI thema's bouwen (bv. cyberpunk, kawaii) die als aparte folders in `inc/themes/` gedropt worden; de client wisselt ertussen. Dit is bewust anders dan een vast stel kleur-instellingen.
- 2026-08-29: **Theme vs white-label grens.** Theme module = kleur/typografie/layout/presets (het hele admin-look). White-label = logo, footer-tekst, login-achtergrond (de identiteit van de klant). Zonder deze grens vechten de twee modules om dezelfde CSS.
- 2026-08-29: **Menu-restrictie bewust NIET in v1.** De gebruiker wil de restrictie uit `szm-admin-menu-manager` later in Admin Suite — maar pas nadat v1 bewezen is. Twee plugins die hetzelfde doen op een site is verwarrend, dus de standalone plugin blijft voorlopig.
- 2026-08-29: **Per-rol toepassing voor alle modules** vanaf het begin (gekozen in grill), niet "alleen admins zien het" of "alleen niet-admins". Dat is meer werk maar meer flexibel.
- 2026-08-29: **One-click install/activate via admin-ajax** (niet TGM, niet link-only) voor Plugin Recommendations — beste client-demo. Vereist nonce + `manage_options`-gating.
- 2026-08-29: **Bezoekers-statistieken uitgesteld** ("No stats in v1"). WordPress heeft geen ingebouwde visitor-data; de bron (externe analytics API vs eigen counter) is nog onbeslist en blokkeert v1 niet.
