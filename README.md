# SZM Admin Suite

One WordPress plugin, many modules — a Jetpack-style plugin for client sites that cleans up and spruces up the wp-admin. Each module toggles on/off and applies per role.

## Modules (v1)

| Module | Slug | What it does | Default |
|---|---|---|---|
| **Admin Theme** | `admin-theme` | Drop-in admin themes (colors/typography/layout). Themes are folders in `inc/themes/` — drop one in and it appears in the picker. | Off (none active until one is placed) |
| **Custom Dashboard** | `dashboard` | Replaces the default dashboard with a Welcome card + Plugin Recommendations card. Site Health stays. | On |
| **Declutter** | `declutter` | Hides the default dashboard widgets (At a Glance, Activity, Quick Draft, WordPress news). Site Health always stays. | On |
| **White-label** | `white-label` | Client identity: logo, admin footer text, login background. | Off (nothing set) |

Menu-restriction and visitor stats are explicitly **not** in v1 (see `SPEC.md`).

## Install

Copy the `szm-admin-suite` folder into `wp-content/plugins/` (repo root is the plugin) and activate. The plugin self-updates from the public GitHub repo `Yelbow/szm-admin-suite` through the normal Plugins → Updates screen.

## Usage

Manage everything under the top-level **Admin Suite** menu:

- **Modules** tab — master on/off per module, plus which roles each module applies to (leave roles empty for everyone, including administrators).
- One tab per module with its own settings:
  - **Admin Theme** — pick the active theme.
  - **Declutter** — choose which default dashboard widgets to hide.
  - **White-label** — set logo, footer text, login background (with media picker).

## Plugin Recommendations

The dashboard's **Plugin Recommendations** card ships with a curated starter list (Yoast SEO, Duplicator, Sucuri Security). Each shows its state and offers one-click **Install / Activate** via admin-ajax, gated by nonce + `manage_options`. Installed plugins are skipped; the list is easily extended in `szm_as_dashboard_get_recommendations()`.

## Adding an admin theme

1. Create a folder `inc/themes/<name>/`.
2. Add a `theme.json` descriptor:
   ```json
   {
     "slug": "my-theme",
     "name": "My Theme",
     "description": "What it looks like",
     "version": "1.0.0"
   }
   ```
3. Add an `admin.css` (and optionally a `login.css`) targeting the wp-admin.
4. It shows up in the Admin Theme picker automatically — no code changes.

## Adding a module

1. Create `inc/modules/<slug>/module.php`.
2. Call `szm_as_register_module()` with the module descriptor (slug, name, description, icon, default_enabled, boot callback, optional settings/render/sanitize for its own tab).
3. The module only runs when enabled and when it applies to the current user.

## Requirements

- PHP 7.4+
- WordPress 5.9+

## License

GPL-2.0-or-later
