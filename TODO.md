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
- [x] Modules-tab: master aan/uit + per-rol toepassing per module.
- [x] Admin theme module: thema-registry + picker + 1 demo thema (`midnight`).
- [x] Dashboard module: Welcome + Plugin Recommendations (Yoast SEO, Duplicator, Sucuri Security), Site Health behouden.
- [x] Declutter module (default dashboard-widgets uit, Site Health blijft).
- [x] White-label module: logo, footer-tekst, login-achtergrond.
- [x] PUC self-updater naar `Yelbow/szm-admin-suite` (main branch).
- [x] README.md.

## Getest (op echte WP-testsite, mhh docker)
- [x] Modules registreren + defaults correct (aan/uit zoals gepland).
- [x] Dashboard: Welcome + Recommendations renderen, Site Health blijft, defaults weg (echte HTTP check).
- [x] Admin theme: `midnight` CSS wordt ge-enqueued zodra module aan + thema actief.
- [x] White-label: footer-tekst, login-logo en login-achtergrond renderen (echte HTTP check).
- [x] Plugin Recommendations: one-click install+activate (Sucuri via ajax) werkt.
- [x] Settings-save via options.php: modules-tab saved, andere tabs worden niet gewist.
- [x] Per-rol gating live: white-label scoped op `editor` → niet zichtbaar voor admin; theme (alle rollen) wel.

## Nog te doen / niet gepland
- [ ] Menu-restrictie-module overnemen uit `szm-admin-menu-manager` (pas na v1 bewezen).
- [ ] Bezoekers-statistieken (bewust uitgesteld, "No stats in v1").
- [ ] Zelfde PUC/TGM-patronen + echte publieke repo `Yelbow/szm-admin-suite` aanmaken (repo bestaat nog niet).
- [ ] Tweede demo thema (bv. kawaii) bouwen — los via AI, per SPEC.
