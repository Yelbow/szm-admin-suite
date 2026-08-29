# PLAN

> Huidige aanpak. Gebaseerd op de grill-me sessie (2026-08-29). SPEC.md = wat de gebruiker wil; dit = hoe we het bouwen.

## Product

**SZM Admin Suite** — een multi-module WordPress-plugin (Jetpack-achtig) voor client-sites, die het wp-admin visueel en functioneel opruimt en verfraait. Eén plugin, veel modules die per site aan/uit kunnen.

## Modules (v1)

| Module | Slug | Doel | Aan/uit default |
|---|---|---|---|
| Admin theme | `admin-theme` | Drop-in admin-thema's (sidebar/kleur/typografie/layout). | UIT (geen thema actief tot er een geplaatst is) |
| Custom dashboard | `dashboard` | Vervangt default dashboard door Welcome + Plugin Recommendations (Site Health widget blijft). | AAN |
| Declutter | `declutter` | Verbergt default dashboard-widgets / rommel. | AAN |
| White-label | `white-label` | Eigen logo, footer-tekst, login-achtergrond. | UIT (geen logo ingesteld) |

- Menu-restrictie-module: **niet in v1**. Komt later over uit `szm-admin-menu-manager` zodra v1 bewezen is.
- Bezoekers-statistieken: **niet in v1** (afgesproken "No stats in v1").

## Architectuur

- **Module registry**: hoofdbestand (`szm-admin-suite.php`) is een dunne loader. Elke module in `inc/modules/<slug>/` zelf-registreert (naam, omschrijving, icoon, default on/off) en draait alleen wanneer ingeschakeld.
- **Themes**: drop-in thema-folders in `inc/themes/<naam>/` — elk met eigen CSS + klein descriptor-bestand (JSON) voor de picker. Nieuw thema = folder droppen, verschijnt vanzelf. Thema's individueel met AI te bouwen en te wisselen.
- **Libraries** in `inc/libs/`: Plugin Update Checker + TGM Plugin Activation (hergebruikt uit bestaande plugin).
- **Settings**: één top-level "Admin Suite"-menu met een Modules-tab (master aan/uit) plus een tab per module voor eigen instellingen (Jetpack-stijl).
- **Per-role toepassing**: elke module is per-rol configureerbaar vanaf het begin.
- **Opties per site**, los opgeslagen, nooit overschreven bij update (zelfde patroon als `szm_amm_settings`).

## Delivery

- Self-update via Plugin Update Checker vanaf publieke GitHub repo `Yelbow/szm-admin-suite` (main branch), zelfde patroon als bestaande plugin.
- Minimale versies: PHP 7.4+, WP 5.9+ (zelfde als bestaande plugin).
- Repo-root is de plugin (nodig voor PUC).
- Public repo, geen client-data/secrets, geen ingebedde tokens.

## Installatie-plugins (Plugin Recommendations)

- Vaste, gecureerde starterlijst — v1: **Yoast SEO**, **Yoast Duplicator**, **Sucuri Security**. Uitbreidbaar later.
- Slaat reeds geïnstalleerde over; biedt one-click install/activate.
- Install/activate via admin-ajax met nonce + `manage_options`-gating (best practice voor het client-demo-geval).

## TODO-stappen (overzicht, detail in TODO.md)

1. Scaffold: hoofdbestand + module registry loader + opties-framework.
2. Bundel libs (PUC + TGM) in `inc/libs/`.
3. Modules-tab (master aan/uit, per-rol).
4. Admin theme module + 1 demo thema + thema-picker.
5. Dashboard module (Welcome + Recommendations + Site Health bewaren).
6. Declutter module.
7. White-label module.
8. PUC self-updater + publieke repo.
9. Testen op echte WP-testsite; README.
