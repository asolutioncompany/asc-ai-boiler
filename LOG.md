# LOG.md

Running log of architectural and design decisions. Newest entries at top.

---

## 2026-06-15 — Restructured as theme + two plugins

Moved from a single plugin (`asc-ai-boiler`) to three packages in the repo:

- `asc-ai-theme/` — bare WordPress theme (style.css, index.php, functions.php stub only)
- `asc-ai-plugin/` — boiler framework plugin (Core\ + Admin\)
- `asc-ai-example/` — example site plugin (ExampleCore\ + ExampleAdmin\ + ExampleFront\ + content/)

**Why:** Separates the reusable framework from the site-specific layer and keeps the theme
as a minimal WP requirement rather than a logic host.

**Path/URL wiring:** `asc-ai-plugin` defines four filters (`asc_ai_boiler_content_dir`,
`asc_ai_boiler_content_url`, `asc_ai_boiler_media_dir`, `asc_ai_boiler_media_url`) so
`asc-ai-example` can redirect all content paths to its own directory via
`BoilerIntegration::register()`.

**Asset URLs:** `ExampleAdmin\Admin` and `ExampleFront\Front` now use
`plugin_dir_url( ASC_AI_EXAMPLE_PLUGIN_FILE )` directly instead of going through
`Core::get_plugin_url()`, since their assets live in the example plugin.

**Namespaces:** Both plugins share the `ASC\AI_BOILER\` root namespace. Each has its own
Composer autoloader mapping `ASC\AI_BOILER\` to its own `includes/`. PHP's autoloader
chain handles both without conflict.

**Constants:** `ASC_AI_BOILER_PLUGIN_FILE` replaced by `ASC_AI_PLUGIN_FILE` (framework)
and `ASC_AI_EXAMPLE_PLUGIN_FILE` (example). `ASC_AI_BOILER_ENABLE_PRODUCT` removed — the
example plugin being active is the signal.

---
