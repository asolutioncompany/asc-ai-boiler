# ARCH.md

## Repo layout — aS.c AI Boiler Framework

```
asc-ai-boiler/           repo root — docs live here
├── asc-ai-theme/        aS.c AI Boiler Theme (bare minimum WordPress theme stub)
├── asc-ai-plugin/       aS.c AI Boiler Plugin (Content Synchronization Tool)
├── asc-ai-example/      aS.c AI Boiler Example (standalone site layer with Portfolio CPT, Blog, Partials)
├── tests/
│   └── asc-ai-plugin/   repository-level WordPress integration tests for the sync plugin
└── phpunit.xml.dist     PHPUnit configuration for repository-level tests
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
`Admin\SeoSync`              maps provider-neutral SEO manifest fields to provider metadata and companion files
`Admin\TaxonomySync`         syncs category and tag names and descriptions from the top-level manifest taxonomy contract
`Admin\SyncConfig`           sync configuration data

### SEO manifest contract

Pages, posts, and registered custom post types can declare provider-neutral SEO fields in their `types` manifest rows. `meta_description`, `social_description`, and `x_description` contain `.txt` filenames under `content/meta-descriptions/`, `content/social-descriptions/`, and `content/x-descriptions/`. `social_title`, `x_title`, `focus_keyphrase`, and `primary_category` contain inline strings. `primary_category` stores a category slug instead of a database term ID so the manifest remains portable.

A missing field leaves the WordPress value unchanged. An inline empty string or an empty referenced companion file clears the value. A populated inline field or companion file sets the value. A declared companion file that is missing or unreadable leaves the WordPress value unchanged. A populated `primary_category` must match a category assigned to the post. Import resolves its slug after applying the manifest category list.

`Admin\SeoSync` owns the provider adapter. The default Yoast adapter maps the seven logical fields to Yoast post metadata. The `asc_ai_boiler_seo_provider` and `asc_ai_boiler_seo_field_map` filters can select or alter the adapter without adding site-specific logic to the sync classes. The adapter uses `WPSEO_Meta` when Yoast provides it and WordPress post metadata functions when Yoast is inactive. Restore writes SEO metadata before it issues the post update that lets Yoast rebuild indexables.

| Manifest field | Yoast post metadata |
|---|---|
| `meta_description` | `_yoast_wpseo_metadesc` |
| `social_description` | `_yoast_wpseo_opengraph-description` |
| `x_description` | `_yoast_wpseo_twitter-description` |
| `social_title` | `_yoast_wpseo_opengraph-title` |
| `x_title` | `_yoast_wpseo_twitter-title` |
| `focus_keyphrase` | `_yoast_wpseo_focuskw` |
| `primary_category` | `_yoast_wpseo_primary_category` |

### Taxonomy manifest contract

The top-level `taxonomies` object contains taxonomy keys such as `category` and `post_tag`. Each term row uses a portable `slug` and can declare `name` and `description`. Import creates missing declared terms and updates only declared fields. A missing taxonomy, term row, or field leaves the WordPress value unchanged. An empty declared description clears the WordPress term description.

Export writes names and descriptions for terms assigned to synchronized published content. Difference detection compares each declared term and field with WordPress. The `asc_ai_boiler_manifest_taxonomies` filter controls the supported taxonomy list.

### Tests

The WordPress integration test configuration lives at the repository root. Plugin tests live under `tests/asc-ai-plugin/`, outside the deployable `asc-ai-plugin/` directory. The suite requires PHPUnit and the WordPress test library. Manual WordPress and Yoast checks remain part of release verification because the automated suite does not exercise the full admin UI or a production Yoast indexable rebuild.

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
- `'asc_ai_boiler_manifest_taxonomies'` — taxonomy slugs supported by the top-level taxonomy manifest contract
- `'asc_ai_boiler_seo_provider'` — active SEO provider identifier (`yoast` by default)
- `'asc_ai_boiler_seo_field_map'` — logical SEO field definitions for the active provider

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

`Front\Front`               front-end asset enqueuing, cookie-based body class filtering (`filter_body_class`), tag-first and primary-category-first pill markup, and button helpers
`Front\SiteFront`           site layer layout wrappers, logo rendering, and `[example_theme_toggle]` shortcode renderer
`Front\RegisterShortcodes`  registers shortcodes (`[example_home_url]`, `[example_theme_toggle]`, `[example_taxonomy_description]`, `[example_all_blogs]`, `[example_portfolio]`, etc.)
`Front\BlogFront`           blog archive grid and single post rendering
`Front\PortfolioFront`      portfolio archive grid, single project layout, and photo mosaic gallery rendering
`Front\SearchFront`         search results, combined post/Portfolio category archives, two-column category headers, taxonomy description shortcode output, and legacy Blog/Portfolio tag redirects

### Theme System & Theme Toggle Architecture

1. **State Persistence**: 1-year cookie named `asc_cookie` storing `asc-dark` (default) or `asc-light`.
2. **Server-Side Rendering (FOUC Prevention)**:
   - `Front::filter_body_class()` evaluates `$_COOKIE['asc_cookie']` and applies `example-site-dark` (default) or `example-site-light` to `<body>`.
   - `ThemeShell::render_document()` injects `style="color-scheme: dark"` or `style="color-scheme: light"` onto `<html>`.
3. **Markup and Shortcode**: `[example_theme_toggle]` renders an accessible button group with the sun and moon SVG files from `content/other-media/`.
4. **Client-Side Toggle**: Dependency-free vanilla JavaScript in `assets/front/front.js` (`initThemeToggle()`) syncs cookie state, toggles body classes, and manages `aria-pressed`.
5. **Styles**: `assets/front/front.css` uses CSS custom properties defined in `body` (light) and overridden in `body.example-site-dark`.
6. **Server Caching**: Nginx FastCGI cache partitioned using `$asc_theme` cookie map variable.
7. **Front-End Icons**: `Front::icon_svg()` and `[example_icon]` render [WordPress Dashicons](https://github.com/WordPress/dashicons) paths as inline SVG for search, close, menu, information, performance, and arrow controls. The public site does not enqueue the Dashicons stylesheet or font. The theme selector uses the dedicated sun and moon files in `content/other-media/`.
