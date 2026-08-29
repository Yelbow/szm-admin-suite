# DECISIONS

> Gefaalde aanpakken, gotchas en niet-vanzelfsprekende architectuur-keuzes, gedateerd.

- 2026-08-29: **Module-architectuur = module registry + drop-in thema-folders.** Geen monolithisch single-file plugin zoals `szm-admin-menu-manager` — met 4+ modules en door AI te bouwen thema's schaalt dat niet. Hoofdbestand is een dunne loader; elke module in `inc/modules/<slug>/` zelf-registreert en draait alleen als aan.
- 2026-08-29: **Admin theme module is een thema-systeem, geen instellingenformulier.** De gebruiker laat een AI thema's bouwen (bv. cyberpunk, kawaii) die als aparte folders in `inc/themes/` gedropt worden; de client wisselt ertussen. Dit is bewust anders dan een vast stel kleur-instellingen.
- 2026-08-29: **Theme vs white-label grens.** Theme module = kleur/typografie/layout/presets (het hele admin-look). White-label = logo, footer-tekst, login-achtergrond (de identiteit van de klant). Zonder deze grens vechten de twee modules om dezelfde CSS.
- 2026-08-29: **Menu-restrictie bewust NIET in v1.** De gebruiker wil de restrictie uit `szm-admin-menu-manager` later in Admin Suite — maar pas nadat v1 bewezen is. Twee plugins die hetzelfde doen op een site is verwarrend, dus de standalone plugin blijft voorlopig.
- 2026-08-29: **Per-rol toepassing voor alle modules** vanaf het begin (gekozen in grill), niet "alleen admins zien het" of "alleen niet-admins". Dat is meer werk maar meer flexibel.
- 2026-08-29: **One-click install/activate via admin-ajax** (niet TGM, niet link-only) voor Plugin Recommendations — beste client-demo. Vereist nonce + `manage_options`-gating.
- 2026-08-29: **Bezoekers-statistieken uitgesteld** ("No stats in v1"). WordPress heeft geen ingebouwde visitor-data; de bron (externe analytics API vs eigen counter) is nog onbeslist en blokkeert v1 niet.
- 2026-08-29: **TGM niet gebundeld.** PLAN zei "PUC + TGM", maar Plugin Recommendations gebruikt one-click install/activate via admin-ajax (DECISIONS-regel over ajax), niet TGM. TGM weggelaten als dode code; alleen PUC meegebundeld.
- 2026-08-29: **Yoast Duplicator → plugin-slug `duplicator`.** De gebruiker noemde "yoast duplicator"; Yoast maakt geen Duplicator-plugin, dus de populaire backup-plugin met slug `duplicator` gebruikt. Genoteerd zodat de naam later niet raar overkomt.
- 2026-08-29: **Singleton-bug gevonden en gefixt.** `SZM_Admin_Suite::instance()` riep `load_modules()` aan in de constructor; tijdens de constructie is `self::$instance` nog null, dus `szm_as_register_module()` → `instance()` maakte een tweede instantie aan die de modules kreeg. Fix: modules laden NA de toewijzing, niet in de constructor.
- 2026-08-29: **Settings-save overschreef andere tabs.** Elke module-tab saved alleen zijn eigen sectie; het sanitize-callback liep ook als de sectie niet was meegestuurd en reset hem dan naar defaults. Fix: een sectie alleen sanitizen als hij in de POST staat.
- 2026-08-29: **`remove_meta_box()` markeert boxen als `false`, niet unset.** Bij het testen checkte ik `isset()` → die geeft `true` voor `false`. In het echt werkt remove_meta_box wel (widgets verdwenen van het dashboard, bevestigd via HTTP).

