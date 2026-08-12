# ARCH.md

## Repo layout — aS.c AI Boiler Framework

```
asc-ai-boiler/           repo root — docs live here
├── asc-ai-theme/        aS.c AI Boiler Theme (bare minimum WordPress theme stub)
├── asc-ai-plugin/       aS.c AI Boiler Plugin (Content Synchronization Tool)
└── asc-ai-example/      aS.c AI Boiler Example (standalone site layer with Portfolio CPT, Blog, Partials)
```

## asc-ai-theme (aS.c AI Boiler Theme)

Bare WordPress theme required by WP. No site logic lives here.

- `style.css`       Theme header (required by WP)
- `index.php`       Empty fallback template (required by WP)
- `functions.php`   Thin stub: `add_theme_support` only

## asc-ai-plugin (aS.c AI Boiler Plugin — Content Synchronization Tool)

PSR-4 root: `ASC\AI_BOILER\` → `includes/`
Composer autoloader in `vendor/`; loaded from `asc-ai-plugin.php`.
Defines constant `ASC_AI_PLUGIN_FILE` and `ASC_AI_PLUGIN_DOMAIN`.

Pure synchronization tool for importing, exporting, diffing, and backing up content files with the WordPress database.
Operates independently and communicates with active site layer plugins via standard WordPress filter string hooks.
Contains no template files or partial CPT registration classes.

Namespaces:
- `Core\`   Media helpers and lifecycle
- `Admin\`  WP admin: settings page, content sync UI, diffing, import/export logic

### Bootstrap

`asc-ai-plugin.php`
  → `Core\Core::get_instance()`

### Classes

`Core\Core`                  plugin lifecycle singleton
`Admin\Admin`                admin bootstrap
`Admin\SettingsPage`         plugin settings UI
`Admin\ContentSync`          handles content sync AJAX/logic
`Admin\ContentSyncProfile`   base profile builder driven by manifest and filter hooks
`Admin\ContentMediaSync`     syncs media between content/media/ and WP media library
`Admin\PostMetaSync`         syncs custom post meta between WordPress and content-manifest.json
`Admin\SyncConfig`          sync configuration data

### Filter Hooks (String-Based)

- `'asc_ai_boiler_content_dir'` — absolute path to site content directory (trailing slash)
- `'asc_ai_boiler_content_url'` — public URL of site content directory (trailing slash)
- `'asc_ai_boiler_media_dir'` — absolute path to media directory (trailing slash)
- `'asc_ai_boiler_media_url'` — public URL of media directory (trailing slash)
- `'asc_ai_boiler_other_media_dir'` — absolute path to static other-media directory
- `'asc_ai_boiler_other_media_url'` — public URL of static other-media directory
- `'asc_ai_boiler_content_sync_profile'` — content types, cpt shell map, and page body maps
- `'asc_ai_boiler_media_bindings'` — manifest media bindings
- `'asc_ai_boiler_post_meta_sync_keys'` — custom post meta sync keys and types ('raw' / 'slug')

## asc-ai-example (aS.c AI Boiler Example — Standalone Example Site Layer)

PSR-4 root: `ASC\AI_EXAMPLE\` → `includes/`
Composer autoloader in `vendor/`; loaded from `asc-ai-example.php`.
Defines constant `ASC_AI_EXAMPLE_PLUGIN_FILE`.
Runs 100% standalone without requiring `asc-ai-plugin` to be active.

Namespaces:
- `Core\`    site lifecycle, ThemeShell document bypass, Partials CPT registry (`RegisterPartials`, `PartialStore`), Portfolio CPT (`RegisterPortfolio`), media helpers
- `Admin\`   example site settings UI, Portfolio gallery meta box & Featured toggle (`PortfolioAdmin`), blog customizations & Featured toggle (`BlogAdmin`)
- `Front\`   front-end rendering, Portfolio single layout & gallery mosaic (`PortfolioFront`), shortcodes, archive pagination

### Key Core Classes

`Core\Core`               site lifecycle singleton
`Core\RegisterPartials`   registers `asc_boiler_partial` CPT and Partials admin menu (position 56, before aS.c Boiler)
`Core\PartialStore`       queries and caches partial posts by `_asc_ai_boiler_partial_key`
`Core\RegisterPortfolio`  registers `example_portfolio` CPT (`/portfolio/`) with REST API and taxonomy support
`Core\PostMeta`           defines featured flag `_example_featured`, portfolio gallery meta key `_example_portfolio_gallery`, and parsing helpers
`Core\ThemeShell`         front-end ThemeShell bypass, document rendering with cookie-aware `<html>` `color-scheme` inline styling, and layout filter hooks
`Core\Media`              site media path and URL resolution
`Core\BoilerIntegration`  registers sync profile, custom post meta keys, and content path filter callbacks for the sync tool

### Key Front Classes

`Front\Front`               front-end asset enqueuing, cookie-based body class filtering (`filter_body_class`), pill markup, and button helpers
`Front\SiteFront`           site layer layout wrappers, logo rendering, and `[example_theme_toggle]` shortcode renderer
`Front\RegisterShortcodes`  registers shortcodes (`[example_home_url]`, `[example_theme_toggle]`, `[example_all_blogs]`, `[example_portfolio]`, etc.)
`Front\BlogFront`           blog archive grid and single post rendering
`Front\PortfolioFront`      portfolio archive grid, single project layout, and photo mosaic gallery rendering
`Front\SearchFront`         search result templates and custom query handlers

### Theme System & Theme Toggle Architecture

1. **State Persistence**: 1-year cookie named `asc_cookie` storing `asc-dark` (default) or `asc-light`.
2. **Server-Side Rendering (FOUC Prevention)**:
   - `Front::filter_body_class()` evaluates `$_COOKIE['asc_cookie']` and applies `example-site-dark` (default) or `example-site-light` to `<body>`.
   - `ThemeShell::render_document()` injects `style="color-scheme: dark"` or `style="color-scheme: light"` onto `<html>`.
3. **Markup & Shortcode**: `[example_theme_toggle]` renders accessible button group with SVG sun/moon icons.
4. **Client-Side Toggle**: `assets/front/front.js` (`initThemeToggle()`) syncs cookie state, toggles body classes, and manages `aria-pressed`.
5. **Styles**: `assets/front/front.css` uses CSS custom properties defined in `body` (light) and overridden in `body.example-site-dark`.
6. **Server Caching**: Nginx FastCGI cache partitioned using `$asc_theme` cookie map variable.

