# ARCH.md

## Repo layout

```
asc-ai-boiler/           repo root — docs live here
├── asc-ai-theme/        WordPress theme (bare minimum)
├── asc-ai-plugin/       WordPress plugin — boiler framework (Core + Admin)
└── asc-ai-example/      WordPress plugin — example site layer (Example*)
```

## asc-ai-theme

Bare WordPress theme required by WP. No site logic lives here.

- `style.css`       Theme header (required by WP)
- `index.php`       Empty fallback template (required by WP)
- `functions.php`   Thin stub: `add_theme_support` only

## asc-ai-plugin

PSR-4 root: `ASC\AI_BOILER\` → `includes/`
Composer autoloader in `vendor/`; loaded from `asc-ai-plugin.php`.
Defines constant `ASC_AI_PLUGIN_FILE` and `ASC_AI_BOILER_TEXT_DOMAIN`.

Namespaces:
- `Core\`   partial system, content sync helpers, plugin lifecycle
- `Admin\`  WP admin: settings page, content sync UI

### Bootstrap

`asc-ai-plugin.php`
  → `Core\Core::get_instance()`

Activation/deactivation hooks run `Core` lifecycle methods.

### Classes

`Core\Core`              plugin lifecycle singleton
`Core\ThemeShell`        base class for partial-based theme output
`Core\PartialStore`      loads and caches partial HTML from the content store
`Core\RegisterPartials`  registers partial CPT on init
`Core\ContentMediaSync`  syncs media between content/media/ and WP media library
`Core\ClassicEditor`     disables block editor unless opted in via filter
`Admin\Admin`            admin bootstrap
`Admin\SettingsPage`     plugin settings UI
`Admin\ContentSync`      handles content sync AJAX/logic
`Admin\ContentSyncProfile`  base class for sync profiles
`Admin\SyncConfig`       sync configuration data

### Path and URL filters

`ContentSync::FILTER_CONTENT_DIR`    absolute path to `content/` (trailing slash)
`ContentSync::FILTER_CONTENT_URL`    public URL of `content/` (trailing slash)
`ContentMediaSync::FILTER_MEDIA_DIR` absolute path to `content/media/` (trailing slash)
`ContentMediaSync::FILTER_MEDIA_URL` public URL of `content/media/` (trailing slash)

These filters let `asc-ai-example` redirect all content paths to its own directory.

### Other-media directory

`content/other-media/` holds static assets (SVGs, fonts, icon sets) that are served directly via URL and **never imported into the WordPress media library**. WordPress blocks SVG uploads by default; files that don't need to be WP attachments belong here.

- `ContentMediaSync::get_other_media_url( string $relative_path ): string` — public URL helper
- `ContentMediaSync::FILTER_OTHER_MEDIA_DIR` — override the absolute path (trailing slash)
- `ContentMediaSync::FILTER_OTHER_MEDIA_URL` — override the public base URL (trailing slash)
- `BoilerIntegration` wires both filters to point to the example plugin's own `content/other-media/`

### SEO meta description filter

`ContentSync::FILTER_META_DESCRIPTION_META_KEY`  override the post meta key used to read/write SEO meta descriptions during backup/restore.
Default: `_yoast_wpseo_metadesc` (Yoast SEO). Other common values: `_aioseo_description`, `rank_math_description`, `_seopress_titles_desc`.

### Companion content directories

`content/excerpts/`          one `<slug>.txt` per post — stores `post_excerpt` for backup/restore
`content/meta-descriptions/` one `<slug>.txt` per post — stores SEO meta description for backup/restore

Companion files are written during backup, applied during restore, and checked during detect-differences. Partials are excluded. An empty companion file signals "clear the field on restore." Missing companion file means "skip the field."

### Assets

`assets/admin/admin.css`   admin styles
`assets/admin/admin.js`    admin JS

### Templates

`templates/boiler-theme-shell.php`   base PHP template included by ThemeShell

## asc-ai-example

PSR-4 root: `ASC\AI_BOILER\` → `includes/`
Composer autoloader in `vendor/`; loaded from `asc-ai-example.php`.
Defines constant `ASC_AI_EXAMPLE_PLUGIN_FILE`.
Requires `asc-ai-plugin` to be active.

Namespaces:
- `ExampleCore\`   example site: post types, settings, content sync profile
- `ExampleAdmin\`  example site: admin screens for services, projects, blog
- `ExampleFront\`  example site: front-end rendering, shortcodes, archive pagination

### Bootstrap

`asc-ai-example.php`
  → `ExampleCore\Core::get_instance()`
  → `ExampleCore\BoilerIntegration::register()` (hooks content path filters)

Activation/deactivation hooks run `ExampleCore` lifecycle methods.

### Classes

`ExampleCore\Core`                     example site lifecycle singleton
`ExampleCore\CoreSettings`             settings data for the example site
`ExampleCore\BoilerIntegration`        wires ExampleCore into Core's APIs and content path filters
`ExampleCore\ExampleThemeShell`        concrete ThemeShell for the example site
`ExampleCore\ExamplePartialCatalog`    maps partial names to content/ files
`ExampleCore\ExampleContentSyncProfile`  defines content sync mapping
`ExampleCore\ExampleMediaBindings`     maps media files to posts
`ExampleCore\RegisterProjects`         registers Projects custom post type
`ExampleCore\RegisterServices`         registers Services custom post type
`ExampleCore\PostMeta`                 post meta registration
`ExampleCore\ArchiveConfig`            archive query config
`ExampleAdmin\Admin`                   example admin bootstrap
`ExampleAdmin\SettingsPage`            example settings UI
`ExampleAdmin\BlogAdmin`               blog admin customizations
`ExampleAdmin\ProjectsAdmin`           projects post type admin columns/meta boxes
`ExampleAdmin\ServicesAdmin`           services post type admin columns/meta boxes
`ExampleFront\Front`                   front bootstrap
`ExampleFront\SiteFront`               site-wide front hooks
`ExampleFront\BlogFront`               blog archive/single rendering
`ExampleFront\ProjectsFront`           projects archive/single rendering
`ExampleFront\ServicesFront`           services archive/single rendering
`ExampleFront\CallToAction`            CTA partial rendering
`ExampleFront\ArchivePagination`       pagination for archive pages
`ExampleFront\RegisterShortcodes`      shortcode registration

### Content system

Partials live in `content/partials/` as HTML files.
Pages, posts, services, and projects live in `content/pages/`, `content/posts/`, `content/services/`, `content/projects/`.
Media lives in `content/media/`.
`content/content-manifest.json` describes the full content set for sync.

ContentSync imports content from the `content/` directory into WordPress on demand.
ExampleContentSyncProfile defines how content files map to post types and meta.
ExampleMediaBindings maps media files to posts for attachment during sync.

### Constants (defined in asc-ai-example.php)

`ASC_AI_EXAMPLE_TEST_PAGING`          bool    forces paginated archive for testing
`ASC_AI_EXAMPLE_TEST_VIEW_ALL`        bool    forces "view all" archive for testing
`ASC_AI_EXAMPLE_TEST_PAGING_POST_NUM` int     posts per page in test paging mode (default: 3)
`ASC_AI_EXAMPLE_TEST_VIEW_ALL_NUM`    int     posts shown in view-all mode (default: 2)
`ASC_AI_EXAMPLE_CARD_EXCERPT_SOURCE`  string  card grid excerpt source: 'none' (default) | 'excerpt' | 'meta_description' | 'content'
`ASC_AI_EXAMPLE_CARD_WORD_LIMIT`      int     word limit for 'content' source (0 = no limit, default; takes priority over char limit)
`ASC_AI_EXAMPLE_CARD_CHAR_LIMIT`      int     character limit for 'content' source when word limit is 0 (0 = no limit, default)

### Assets

`assets/example-admin/admin.css`   example admin styles
`assets/example-admin/admin.js`    example admin JS
`assets/example-front/front.css`   example front-end styles
`assets/example-front/front.js`    example front-end JS
