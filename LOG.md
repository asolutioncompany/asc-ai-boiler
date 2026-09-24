# LOG.md

Running log of architectural and design decisions. Newest entries at top.

### Version 1.4.0
- **[asc-ai-plugin] Feature**: Added provider-aware SEO manifest synchronization for meta descriptions, social descriptions, X descriptions, social titles, X titles, focus keyphrases, and primary categories across import, export, and difference detection.
- **[asc-ai-plugin] Architecture**: Moved Yoast metadata names behind the `SeoSync` adapter and filters. Restore now persists SEO metadata before the post update used for Yoast indexable rebuilding.
- **[asc-ai-example] Content**: Completed the example manifest SEO data for pages, posts, and Portfolio entries, including social and X descriptions, social and X titles, focus keyphrases, and applicable primary categories.
- **[asc-ai-plugin] Taxonomies**: Added import, export, and difference detection for category and tag names and descriptions in the top-level content manifest taxonomy contract.
- **[asc-ai-example] Taxonomies**: Moved specific blog topics to categories, assigned Small Business as a secondary Portfolio category, and added short descriptions for every demonstrated category.
- **[asc-ai-example] Archives**: Added centered two-column category headers and the reusable `[example_taxonomy_description]` shortcode for synced term copy.
- **[asc-ai-example] Navigation**: Kept Blog and Portfolio as leading pills linked to their main listing pages, cleared their taxonomy assignments, and redirected their legacy tag archive URLs.
- **[asc-ai-example] Archives**: Added full-width, borderless category header surfaces with compact Blog Topic or Portfolio Category archive labels and a larger category title and description column.
- **[repo] Tests**: Added the first WordPress integration tests under the repository-level `tests/` directory so development files are not included in the deployable plugin directory.
- **[repo] Documentation**: Added reusable prompts for creating project-specific site layers and generating complete content manifests.
- **[repo] Compatibility**: Renamed `AGENT.md` to `AGENTS.md` for broader coding-agent discovery.
- **[asc-ai-example] Performance**: Removed jQuery, unused script localization, and the front-end Dashicons stylesheet. Added a shared inline SVG renderer, responsive hero image output, and accurate responsive size hints for archive card images.
- **[asc-ai-example] Assets**: Routed search, close, menu, information, performance, and arrow icons through the shared inline renderer. Kept the dedicated sun and moon SVG files for the theme selector.
- **[asc-ai-example] Accessibility**: Made card title heading levels context-aware, gave repeated Read More links unique accessible names, and increased dark-mode button, link, and badge contrast.
- **[asc-ai-example] Interface**: Docked the compact scroll-to-top control to the right viewport edge, raised it above the bottom edge, and increased the footer's horizontal padding.
- **[asc-ai-example] Interface**: Unified hover, focus, and active borders with a near-black light-mode color and near-white dark-mode color.
- **[asc-ai-example] Archives**: Centered one- and two-card archive result rows while preserving the standard card width.
- **[repo] Cleanup**: Removed superseded SEO helpers, duplicate manifest loading, redundant resize handling, and unrequested front-end transitions and shadows. Mixed archives now use post-type-aware fallback images and card classes.

### Version 1.3.0
- **[asc-ai-example] Feature**: Added light/dark theme toggle with cookie-based body classes (`example-site-dark` / `example-site-light`), defaulting to dark theme. The theme cookie (`asc_cookie`) is evaluated server-side in PHP during `body_class` and `ThemeShell::render_document()` rendering to eliminate flash-of-wrong-theme on page load.
- **[asc-ai-example] Feature**: Added `[example_theme_toggle]` shortcode rendering accessible inline theme controls for placement inside header, footer, or navigation drawer partials.
- **[asc-ai-example] Assets**: Added dark mode CSS custom property overrides and theme toggle styles in `front.css`, and client-side cookie toggle handler in `front.js`.
- **[asc-ai-example] Docs**: Updated `BRAND KIT.md` with dark mode palette specifications and created `THEME_TOGGLE.md` with full caching guidance and Nginx FastCGI configuration examples.
- **[asc-ai-example] Content**: Updated home page hero summary message to align with agency starter branding and added an FAQ entry highlighting the framework's built-in theme toggle, server-side caching mechanics, and AI-assisted removal prompt.
- **[asc-ai-plugin] Improvement**: Minor Formatting & Date Normalization sync suggestions in Detect Differences now dynamically align with major sync suggestions. If all major syncs suggest Import, minor sync suggests Import; if all suggest Export, it suggests Export; if both Import and Export are suggested, it suggests Export and merging with new plugin files.

---

### Version 1.2.1
- **[asc-ai-plugin] Fix**: Resolved an issue where newly created media attachments were not attached to posts during the same import batch due to stale in-memory media path cache. Cache is now populated immediately upon attachment creation, fallback search by `_wp_attached_file` is supported, and `apply_featured_binding()` uses `query_post_by_slug()` to reliably locate standard blog posts and custom post types.

---

### Version 1.2.0
- **[asc-ai-plugin] Feature**: Added custom post meta sync support to the content synchronization tool. Site layers can register custom post meta to sync via the `asc_ai_boiler_post_meta_sync_keys` filter hook, supporting raw values (`raw`) and portable comma-delimited media/post slug lists (`slug`).
- **[asc-ai-plugin] Improvement**: Normalization messages during import and export are now summarized (e.g., "Normalized 7 plugin files") instead of outputting an alert for every single file.
- **[asc-ai-plugin] Improvement**: Noise reduction in Detect Differences: file modification timestamp differences and whitespace/formatting normalizations are now summarized with a count breakdown by post type rather than generating individual difference alerts, keeping focus only on actual content changes.
- **[asc-ai-plugin] Improvement**: Updated media bindings difference detector to dynamically suggest "Import" when `content-manifest.json` on disk is newer than WordPress.
- **[asc-ai-plugin] Fix**: Resolved a false-positive calculation where on-disk HTML file normalizations were reported as WordPress post database updates.
- **[asc-ai-example] Feature**: Added Featured post settings toggle for Portfolio and Blog post types with mutually exclusive selection per post type.
- **[asc-ai-example] Content**: Updated content manifest with custom post meta entries for featured posts and portfolio project photo galleries.
- **[asc-ai-example] Content**: Added new blog post and FAQ on WordPress content and metadata portability with matching featured image (`blog-portability.jpg`).

---

### Version 1.1.2
- **Feature**: Added automatic support for featured images as social media (Open Graph & Twitter / X) images for pages, posts, and custom post types at the expected image size of 1200x627 or at matching aspect ratio (~1.91:1).

---

### Version 1.1.1
- **Fix**: Adjusted search bar CSS (`min-width: 0`, `appearance: none`) to prevent search buttons from overflowing the container and touching the screen edge on Android mobile browsers.

---

### Version 1.1.0
- **Feature**: Added content manifest support for exporting and synchronizing social sharing descriptions (`social-descriptions/`, `x-descriptions/`).
- **Assets**: Added SVG favicons, PNG performance icons, and default Open Graph preview images to assets and content manifest.
- **Content**: Refined Home, About Us, and Contact Us pages with expanded documentation and updated layouts.

---
