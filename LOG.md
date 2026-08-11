# LOG.md

Running log of architectural and design decisions. Newest entries at top.

---

### Version 1.2.0
- **[asc-ai-plugin] Feature**: Added custom post meta sync support to the content synchronization tool. Site layers can register custom post meta to sync via the `asc_ai_boiler_post_meta_sync_keys` filter hook, supporting raw values (`raw`) and portable comma-delimited media/post slug lists (`slug`).
- **[asc-ai-plugin] Improvement**: Normalization messages during import and export are now summarized (e.g., "Normalized 7 plugin files") instead of outputting an alert for every single file.
- **[asc-ai-plugin] Improvement**: Noise reduction in Detect Differences: file modification timestamp differences and whitespace/formatting normalizations are now summarized with a count breakdown by post type rather than generating individual difference alerts, keeping focus only on actual content changes.
- **[asc-ai-example] Feature**: Added Featured post settings toggle for Portfolio and Blog post types with mutually exclusive selection per post type.
- **[asc-ai-example] Content**: Updated content manifest with custom post meta entries for featured posts and portfolio project photo galleries.
- **[asc-ai-theme] Version Bump**: Bumped theme version to 1.2.0 in `style.css`.

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
