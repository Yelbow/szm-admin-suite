# TODO

> Huidige status. Nog niks gebouwd — alleen project voorbereid en design uitgegrild.

## Klaar
- [x] Project voorbereid: folder `szm-admin-suite`, git init (branch `main`), `.gitignore`.
- [x] Grill-me sessie: naam, modules, architectuur, delivery vastgelegd.
- [x] SPEC.md, PLAN.md geschreven.

## Te doen (bouwvolgorde)
- [ ] Openstaande vraag vóór Plugin Recommendations: welke plugins raadt Jelle standaard aan op client-sites? (de gecureerde lijst is nog niet opgesteld)
- [ ] Scaffold: hoofdbestand `szm-admin-suite.php` (plugin-header, dunne loader).
- [ ] Module registry: detectie + registratie van `inc/modules/*/`.
- [ ] Opties-framework per site (niet overschreven bij update).
- [ ] Bundel libs in `inc/libs/` (PUC + TGM) vanuit bestaande plugin.
- [ ] Modules-tab: master aan/uit + per-rol toepassing per module.
- [ ] Admin theme module: thema-registry + picker + 1 demo thema.
- [ ] Dashboard module: Welcome + Plugin Recommendations, Site Health behouden.
- [ ] Declutter module (default dashboard-widgets uit).
- [ ] White-label module: logo, footer-tekst, login-achtergrond.
- [ ] PUC self-updater + publieke repo `Yelbow/szm-admin-suite`.
- [ ] Testen op echte WP-testsite.
- [ ] README.md.

## Nog niet gepland
- Menu-restrictie-module overnemen uit `szm-admin-menu-manager` (pas na v1).
- Bezoekers-statistieken (bewust uitgesteld, "No stats in v1").
