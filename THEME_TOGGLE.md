# Theme Toggle and Caching Guide

This document describes the cookie-based light/dark theme system used by the aS.c AI Boiler Framework (`asc-ai-example`), how to use the theme toggle shortcode in templates/partials, and how to configure web server and edge caching solutions to cache pages by theme.

---

## 1. Overview & Mechanics

The framework implements a flash-free, cookie-persisted theme toggle:

1. **Client-Side Toggle (JavaScript)**:
   - Sets a 1-year persistent cookie named `asc_cookie` with values `asc-dark` or `asc-light` (`SameSite=Lax`, `path=/`).
   - Toggles body classes `example-site-dark` and `example-site-light`.
   - Updates `aria-pressed` states on the theme toggle buttons for accessibility.
   - Defaults to `asc-dark` when no cookie is present.

2. **Server-Side Rendering (PHP)**:
   - `Front::filter_body_class()` checks `$_COOKIE['asc_cookie']` (and `$_COOKIE['asc-cookie']` fallback) on every request.
   - Adds `example-site-dark` (default) or `example-site-light` directly to the `<body>` element during WordPress HTML generation.
   - `ThemeShell::render_document()` sets the `style="color-scheme: dark"` or `style="color-scheme: light"` attribute on `<html>`.
   - **Why server-side detection matters**: Setting the correct body class and color-scheme in the initial HTML payload completely eliminates flash-of-wrong-theme (FOUC) when loading cached or uncached pages.

3. **Shortcode and Partial Integration**:
   - Shortcode: `[example_theme_toggle]`
   - Renders a semantic, accessible toggle control containing inline Dashicons SVG paths inside an `aria-label="Theme"` group.
   - Uses the shared `Front::icon_svg()` renderer. The public site does not enqueue the Dashicons stylesheet or font.
   - Can be placed inside header, footer, or mobile navigation partials stored in the Partials CPT (`asc_boiler_partial`).

---

## 2. Shortcode & Markup Reference

### Shortcode

Place this shortcode anywhere in your content, templates, or partials:

```text
[example_theme_toggle]
```

### Generated Markup Structure

```html
<span class="example-theme-toggle" role="group" aria-label="Theme">
    <button type="button" class="example-theme-toggle-btn example-theme-toggle-btn--light" aria-pressed="false" aria-label="Light theme">
        <img class="example-theme-toggle-icon" src=".../content/other-media/sun.svg" alt="" width="20" height="20" aria-hidden="true">
    </button>
    <button type="button" class="example-theme-toggle-btn example-theme-toggle-btn--dark" aria-pressed="false" aria-label="Dark theme">
        <img class="example-theme-toggle-icon" src=".../content/other-media/moon.svg" alt="" width="20" height="20" aria-hidden="true">
    </button>
</span>
```

The theme control loads `sun.svg` and `moon.svg` from `content/other-media/`. `Front::icon_svg()` owns the inline Dashicons paths for search, close, menu, information, performance, and arrow controls.

---

## 3. Web Server and FastCGI Caching Configuration

> [!IMPORTANT]
> When full-page caching is active (such as Nginx FastCGI cache, Varnish, or Redis full-page cache), the cache key **must** incorporate the theme cookie value.
> Without this, the cache would store whatever theme was requested first and serve that cached HTML to all subsequent visitors regardless of their cookie preference.

### Nginx FastCGI Setup

#### Step 1: Global HTTP Map (`nginx.conf`)

Add a map inside the `http { ... }` block to map the theme cookie to a variable:

```nginx
http {
    ...
    # Nginx converts cookie name hyphens to underscores ($cookie_asc_cookie matches asc_cookie and asc-cookie)
    # Default to "dark" when no cookie is present
    map $cookie_asc_cookie $asc_theme {
        "asc-light"  "light";
        default       "dark";
    }
    ...
}
```

#### Step 2: Server Block Cache Key

In your site's `server { ... }` block, include `|$asc_theme` in your `fastcgi_cache_key`:

```nginx
server {
    ...
    # Include theme variable in the cache key so light and dark pages are cached independently
    fastcgi_cache_key "$scheme$request_method$host$request_uri|$asc_theme";
    ...
}
```

#### Step 3: Test and Reload Nginx

```bash
sudo nginx -t
sudo systemctl reload nginx
```

---

## 4. Other Caching Platforms

### Varnish Cache

In `vcl_hash`:

```vcl
sub vcl_hash {
    # Hash by URL and Host as normal
    hash_data(req.url);
    hash_data(req.http.host);

    # Add theme cookie to cache hash
    if (req.http.Cookie ~ "asc_cookie=asc-light") {
        hash_data("theme:light");
    } else {
        hash_data("theme:dark");
    }
}
```

### Cloudflare / CDN Custom Cache Keys

If your CDN plan supports Custom Cache Keys (e.g. Cloudflare Enterprise Cache Rules / Custom Cache Keys):
- Add `asc_cookie` to the cookie list in your cache key definition so the edge caches separate HTML payloads for light and dark visitors.

#### Fallback: Origin Cache-Control Header

If custom cookie-based cache keys are **not** supported or configured at your CDN edge, you should bypass HTML caching at the CDN edge while allowing static assets (CSS, JS, images, fonts) to remain cached. You can instruct CDNs not to store HTML pages by sending `Cache-Control` headers from your origin PHP handler:

```nginx
location ~ \.php$ {
    ...
    # Prevent edge CDNs without cookie-aware caching from caching HTML responses
    add_header Cache-Control "no-cache, no-store, must-revalidate" always;
    add_header Pragma "no-cache" always;
}
```

With this configuration, static assets are delivered via CDN with long expiry, while HTML is served from origin Nginx FastCGI cache (which correctly partitions cache keys by `$asc_theme`).

---

## 5. WordPress Caching Plugins

If you use WordPress-level page caching plugins instead of (or in addition to) server-level FastCGI cache, configure them to vary or separate cache files based on the `asc_cookie` cookie:

- **WP Rocket**: Use the `rocket_cache_dynamic_cookies` filter hook (or helper plugin) to add `asc_cookie` to the list of dynamic cookies that trigger separate cache files per cookie value.
- **LiteSpeed Cache**: Under *Cache → Cache Settings*, specify `asc_cookie` in the *Vary Cookies* / *Cookie Groups* settings to maintain distinct cache files for each theme state.
- **W3 Total Cache**: Under *Page Cache → Advanced*, configure *Cookie Groups* or add `asc_cookie` to generate separate cache instances based on the cookie value.
- **WP Super Cache / Cache Enabler**: Ensure cookie-based caching / cookie-vary rules include `asc_cookie` so light and dark page renders are not mixed.

---

## 6. How to Remove Light and Dark Mode Switching

Use this process when a site needs one fixed theme. Choose light or dark before making the changes. Remove the switcher, cookie handling, unused theme variables, and cache variation together.

### Step-by-Step Manual Removal Checklist

1. **Remove Shortcode from Templates & Partials**:
   - In `content/partials/header.html`, remove the `[example_theme_toggle]` shortcode from the desktop navigation list (`<li>[example_theme_toggle]</li>`) and remove the `<div class="example-header-drawer-footer">` container from the mobile navigation drawer.

2. **Remove PHP Shortcode Handler & Registration**:
   - In `includes/Front/SiteFront.php`, remove the `render_theme_toggle_shortcode()` method.
   - In `includes/Front/RegisterShortcodes.php`, remove `add_shortcode( 'example_theme_toggle', array( $site_front, 'render_theme_toggle_shortcode' ) );`.
   - Keep `Front::icon_svg()` and `[example_icon]`. Search, close, menu, and other controls still use them.

3. **Simplify Server-Side Body Class & HTML Attributes**:
   - In `includes/Front/Front.php`, simplify `filter_body_class()` to return your fixed theme class (e.g., `example-site-light` or `example-site-dark`) without reading `$_COOKIE['asc_cookie']`.
   - In `includes/Core/ThemeShell.php`, hardcode the `color-scheme` attribute (e.g., `style="color-scheme: dark"` or `style="color-scheme: light"`) on `<html>`.

4. **Remove Client-Side JavaScript**:
   - In `assets/front/front.js`, delete the `initThemeToggle()` function and remove the `initThemeToggle();` call from `init()`.

5. **Clean Up CSS**:
   - In `assets/front/front.css`, delete the `.example-theme-toggle`, `.example-theme-toggle-btn`, and `.example-header-drawer-footer` rule blocks.
   - If consolidating to a single theme, you can merge the desired variables directly into `body` and remove the `body.example-site-dark` override block.

6. **Simplify Server Caching**:
   - In your Nginx site configuration, remove `|$asc_theme` from `fastcgi_cache_key`.
   - If no other sites on the server use theme switching, remove the `$asc_theme` map from `/etc/nginx/nginx.conf`.

7. **Verify the Fixed Theme**:
   - Search for `example_theme_toggle`, `initThemeToggle`, `asc_cookie`, `example-site-light`, and `example-site-dark`.
   - Keep only the fixed theme class and the CSS variables required by that theme.
   - Test uncached and cached pages at mobile and desktop widths.

---

### Copy-Paste AI Prompt for Automated Removal

Replace `[light|dark]` with the fixed theme before using this prompt:

```text
Please remove the theme toggle functionality completely from this site layer plugin and set the site to a fixed [light|dark] theme:

1. Partials: Remove [example_theme_toggle] from content/partials/header.html (both desktop nav and mobile drawer).
2. Shortcodes: Remove render_theme_toggle_shortcode() from includes/Front/SiteFront.php and unregister [example_theme_toggle] in includes/Front/RegisterShortcodes.php.
3. PHP Classes: In includes/Front/Front.php (filter_body_class) and includes/Core/ThemeShell.php, remove the $_COOKIE['asc_cookie'] checks and set the body class and html color-scheme to fixed [light|dark].
4. JavaScript: In assets/front/front.js, remove initThemeToggle() and its call from init().
5. CSS: In assets/front/front.css, remove the .example-theme-toggle, .example-theme-toggle-btn, and .example-header-drawer-footer CSS rules.
6. Icons: Keep Front::icon_svg() and [example_icon]. Other interface controls use the shared inline Dashicons renderer. Remove content/other-media/sun.svg and content/other-media/moon.svg with the theme selector.
7. Caching: Remove the theme cookie from page-cache variation and cache keys after the application no longer reads it.
8. Verification: Search for remaining theme-toggle and cookie references. Run php -l on all modified PHP files and check assets/front/front.js syntax.
```
