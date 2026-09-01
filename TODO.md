# TODO

> Huidige status. v1 is gebouwd en functioneel getest op een echte WP-testsite (Docker, WP 7.1).

## Klaar
- [x] Project voorbereid: folder, git init (branch `main`), `.gitignore`.
- [x] Grill-me sessie: naam, modules, architectuur, delivery vastgelegd.
- [x] SPEC.md, PLAN.md geschreven.
- [x] Scaffold: hoofdbestand `szm-admin-suite.php` + module registry loader + opties-framework.
- [x] Module registry: detectie + registratie van `inc/modules/*/`.
- [x] Opties-framework per site (defaults gemerged, nooit overschreven bij update).
- [x] Bundel PUC in `inc/libs/plugin-update-checker/` (vanuit bestaande plugin).
- [x] Modules-tab: master aan/uit + minimale-rol-select per module.
- [x] Admin theme module: thema-registry + picker + 3 thema's (`midnight`, `cyberpunk`, `kawaii`).
- [x] Dashboard module: Welcome + Plugin Recommendations (Yoast SEO, Duplicator, Sucuri Security), Site Health behouden.
- [x] Declutter module (default dashboard-widgets uit, Site Health blijft; lege lijst = niets verbergen).
- [x] White-label module: logo, footer-tekst, login-achtergrond + werkende media-picker.
- [x] PUC self-updater naar `Yelbow/szm-admin-suite` (main branch).
- [x] README.md.

## Bugfix-sessie 2026-08-29 (na gebruikersfeedback)
- [x] White-label "Choose" (media) knoppen werkten niet → `wp_enqueue_media()` + `picker.js` met `media-editor`-dependency (inline script draaide te vroeg).
- [x] Declutter: "Hide all defaults below" + uitvinken kon niet bewaard worden → semantiek omgedraaid (lege lijst = toon alles); deep_merge lege-array-bug gefixt.
- [x] Rollen-select: checkbox-per-rol → één "minimale rol"-dropdown per module.
- [x] Site Health verdween stiekem op WP 7.1 → dashboard-module verwijderde en re-addde hem, maar WP 7.1 weigert re-add van verwijderde core-widgets; nu nooit meer verwijderd.
- [x] Login-pagina kreeg thema/white-label niet → rol-gate slaat niet-ingelogde users nu over.
- [x] Cyberpunk + kawaii thema's gebouwd en geverifieerd (computed styles via Playwright).
- [x] WCAG-contrast-audit van alle thema's: `kawaii` (15 fails) en `cyberpunk` (1 fail) gefixt; `midnight` ok.
- [x] Color-scheme robuustheid: selectors `body.wp-admin/body.login`, links, menu-iconen en login-links werken nu op élk admin color scheme (niet alleen "Fresh").
- [x] `goth-baddie` thema gebouwd (donker pruimen-zwart, karmijn + mauve).
- [x] Alle 4 thema's halen alle 13 live contrast-checks (menu, adminbar, buttons, links, postbox, login).

## Redesign-sessie 2026-08-29 (moderne look + visuele verificatie)
- [x] Alle 4 thema's herontworpen naar moderne floating-card look (zwevende pill-sidebar, afgeronde kaarten 16px, gradient pill-buttons, pill settings-tabs, radial-gradient login) — één gedeeld skelet per palet.
- [x] `kawaii` opgewarmd naar crème-roze (#fbeef3, niet stralend wit) op verzoek "this is too white"; `goth-baddie` matte pruim/oxbloed, duidelijk anders dan cyberpunk; `midnight` strak leisteen-indigo; `cyberpunk` neon behouden.
- [x] Echte visuele verificatie via Playwright-screenshots tegen mhh Docker-site (niet alleen computed styles) — alle 4 bevestigd.
- [x] Button/pill-tekst-contrast aangescherpt naar ≥4.5:1 (donkerder gradient-eindstops op alleen de wit-op-gradient regels).

## Getest (op echte WP-testsite, mhh docker)
- [x] Modules registreren + defaults correct (aan/uit zoals gepland).
- [x] Dashboard: Welcome + Recommendations renderen, Site Health blijft, defaults weg (echte HTTP check).
- [x] Admin theme: `midnight`/`cyberpunk`/`kawaii` CSS wordt ge-enqueued zodra module aan + thema actief (ook op login).
- [x] White-label: footer-tekst, login-logo en login-achtergrond renderen (echte HTTP check); media-modal opent in browser.
- [x] Plugin Recommendations: one-click install+activate (Sucuri via ajax) werkt.
- [x] Settings-save via options.php: modules-tab saved (incl. min_role), andere tabs worden niet gewist.
- [x] Minimale-rol gating live: editor wel / subscriber niet bij `editor`; admin altijd; lege rol = iedereen; login-pagina altijd.

## Release (2026-08-29/31)
- [x] Publieke repo `Yelbow/szm-admin-suite` aangemaakt, `origin` gezet, main gepusht.
- [x] Tags `1.0.0` en `1.0.1` gezet; GitHub release `1.0.1` gepubliceerd (PUC self-update nu werkend, zie DECISIONS.md).

## Declutter herontwerp (2026-08-31, na gebruikersfeedback "nieuwe plugins geven rommel")
- [x] Declutter omgebouwd naar allowlist-model: elke dashboard-widget die niet op `always_show` staat (default Welcome + Plugin Recommendations; Site Health hardcoded uitgezonderd) start uitgevinkt in Screen Options, inclusief widgets van later geïnstalleerde plugins.
- [x] Per-gebruiker "seen"-tracking zodat een handmatige keuze van de gebruiker nooit wordt overschreven.
- [x] Settings-tab toont bekende widgets (opgebouwd via een site-wide "known widgets"-optie) met checkboxes voor de allowlist.
- [x] Geverifieerd op mhh-testsite via directe aanroep van de actieve plugin-functie (`wp eval --user=1`) met een gesimuleerde Sucuri-widget: nieuwe widget → verborgen; Welcome/Recommendations/Site Health → zichtbaar; gebruiker-aanvinken blijft bewaard.
- [x] Versie gebumpt naar 1.0.2, tag `1.0.2` + GitHub release gepubliceerd.

## Declutter bugfix 2026-08-31 (na "op mijn live site verborgen ze niet")
- [x] Bug bevestigd lokaal (mhh-testsite, echte Playwright browser-login + dashboardload): Yoast-widgets bleven zichtbaar ondanks 1.0.2.
- [x] Root cause gevonden via `error_log`-trace: `wp_dashboard_setup()` wordt in `wp-admin/index.php` **direct** aangeroepen, niet via de `load-index.php`-hook — onze `load-index.php` prio-20 callback draaide dus vóórdat er ook maar één widget geregistreerd was (`$wp_meta_boxes['dashboard']` was leeg op dat moment).
- [x] Fix: hook nu op de `wp_dashboard_setup` **action** zelf (die Yoast en andere plugins gebruiken om hun eigen widget te registreren) op prioriteit `PHP_INT_MAX`, zodat we altijd na iedereen draaien.
- [x] Geverifieerd met een echte browser-login + ruwe HTML-inspectie (niet alleen function-level `wp eval`): Yoast-widgets krijgen `hide-if-js` op de postbox-div en hun Screen-Options-checkbox staat uit; Welcome/Recommendations/Site Health blijven aan.
- [x] Versie gebumpt naar 1.0.3, tag + release.

## Midnight menu-QA-pass (2026-09-01, na gebruikersfeedback "hovers/states kloppen niet, witte achtergronden, verkeerde contrasten")
- [x] Herbruikbaar audit-script gebouwd (`menu-audit.js`, Playwright): 6 schermen × 5 states (rust, hover×3, submenu-flyout, keyboard-focus, ingeklapte-sidebar-flyout) = 38 screenshots + computed-style-checks.
- [x] Ontbrekende `:focus`-state op submenu-links toegevoegd (was alleen `:hover`).
- [x] Ontbrekende `:focus-visible`-ring toegevoegd op top-level én submenu-links (WCAG 2.4.7), apart van hover.
- [x] Witte achtergrond op plugin-update-notices (`.notice-warning` e.d.) gefixt — WP-core compound-selector-specificiteit versloeg het thema; override toegevoegd die core's varianten matcht/overtreft.
- [x] Volledige pass op verse, tijdstempel-geverifieerde screenshot-batch: geen overige issues gevonden.
- [x] Versie gebumpt naar 1.0.4, tag + release.
- [ ] Zelfde audit + fixes herhalen voor `cyberpunk`, `kawaii`, `goth-baddie` (bewust uitgesteld tot na Midnight, user's expliciete scope-keuze).

## Midnight contrast-naronde (2026-09-01, na gebruikersfeedback "contrasten bijblang niet alles goed")
- [x] Automatische WCAG-contrastscanner gebouwd (`contrast-scan.js`, Playwright): 12 admin-schermen, elk tekst/achtergrond-paar op de pagina, effectieve (geërfde) achtergrond via DOM-parent-walk, echte WCAG-ratio-berekening.
- [x] Root cause gevonden en gefixt: CSS-overerving vs. WP-core's expliciete `color`-regels op chrome-elementen (~130 treffers in één keer opgelost i.p.v. los gepatcht).
- [x] Third-party tekstkleur-lek gefixt: eigen notice-tekst (Novamira e.d.), Yoast's lichte React-paneel, Gutenberg's lichte top-toolbar — laatste twee met correct-gescopede `color: initial !important` (na een tussentijdse regressie met een te brede selector, gevonden en gecorrigeerd).
- [x] Badge-contrast (`.awaiting-mod`/`.update-plugins`/tellers) verbeterd naar een donkerdere achtergrondvariabele (was net onder 4.5:1).
- [x] Volledige herscan: 128 → 8 resterende treffers, alle 8 bevestigde false positives (screen-reader-only tekst, Gutenberg's eigen Save-draft-knop, kleurenschema-preview-label) — geen echte contrastbugs meer.
- [x] Visueel geverifieerd met screenshots (dashboard, plugins, post-new, profiel) naast de computed-style-scan.
- [x] Versie gebumpt naar 1.0.5, tag + release.
- [x] Gebruiker gaf direct tegengas op 1.0.5 ("full white" post-edit-scherm) — Gutenberg's eigen editor-chrome (toolbar, meta-box-wrapper, document-sidebar) bleek nooit aangepakt, ten onrechte als "buiten scope" bestempeld. Gefixt inclusief tweede-laags bug (donkere achtergrond legde zwarte Gutenberg-knoptekst bloot), Yoast's eigen postbox bewust licht gelaten. Versie 1.0.6, tag + release.
- [x] Gebruiker gaf nogmaals tegengas: Yoast's keyphrase-invoerveld was donker (onze globale form-styling reikt overal in wp-admin) terwijl de rest van het Yoast-paneel licht bleef — inconsistent. Gebruiker gevraagd: volledig donker of volledig licht? Antwoord: consistent maken. Yoast's eigen inputs uitgesloten van onze donkere form-styling (`revert`) zodat het hele paneel weer licht-en-consistent oogt, i.p.v. het risico op Yoast's interne tekstkleuren overschrijven. Versie 1.0.7, tag + release.

## Nog te doen / niet gepland
- [ ] Menu-restrictie-module overnemen uit `szm-admin-menu-manager` (pas na v1 bewezen).
- [ ] Bezoekers-statistieken (bewust uitgesteld, "No stats in v1").
- [ ] Tijdelijke wijzigingen op de mhh-testsite (voor screenshot-verificatie) terugzetten na overleg:
  - admin-password tijdelijk gewijzigd (origineel onbekend; nieuwe waarde staat in lokale sessienotitie, niet in de repo).
  - `DISABLE_WP_CRON true` toegevoegd aan wp-config (was traag door overdue cron → externe API's).
  - `home`/`siteurl` gezet naar `http://localhost:8096` (waren de tailscale HTTPS-URL; veroorzaakte redirect-vertraging).
