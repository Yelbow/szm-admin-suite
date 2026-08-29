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

## Getest (op echte WP-testsite, mhh docker)
- [x] Modules registreren + defaults correct (aan/uit zoals gepland).
- [x] Dashboard: Welcome + Recommendations renderen, Site Health blijft, defaults weg (echte HTTP check).
- [x] Admin theme: `midnight`/`cyberpunk`/`kawaii` CSS wordt ge-enqueued zodra module aan + thema actief (ook op login).
- [x] White-label: footer-tekst, login-logo en login-achtergrond renderen (echte HTTP check); media-modal opent in browser.
- [x] Plugin Recommendations: one-click install+activate (Sucuri via ajax) werkt.
- [x] Settings-save via options.php: modules-tab saved (incl. min_role), andere tabs worden niet gewist.
- [x] Minimale-rol gating live: editor wel / subscriber niet bij `editor`; admin altijd; lege rol = iedereen; login-pagina altijd.

## Nog te doen / niet gepland
- [ ] Menu-restrictie-module overnemen uit `szm-admin-menu-manager` (pas na v1 bewezen).
- [ ] Bezoekers-statistieken (bewust uitgesteld, "No stats in v1").
- [ ] Zelfde PUC/TGM-patronen + echte publieke repo `Yelbow/szm-admin-suite` aanmaken (repo bestaat nog niet).
- [ ] Admin-password op mhh-testsite is tijdelijk gewijzigd voor verificatie (origineel onbekend, terugzetten na overleg).
