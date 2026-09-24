# PageSpeed Review

## September 23, 2026 Results

The latest homepage reports show these scores:

| Device | Performance | Accessibility | Best Practices | SEO | Agentic Browsing |
|---|---:|---:|---:|---:|---:|
| Mobile | 85 | 100 | 100 | 100 | 3/3 |
| Desktop | 100 | 100 | 100 | 100 | 3/3 |

| Device | First Contentful Paint | Largest Contentful Paint | Total Blocking Time | Cumulative Layout Shift | Speed Index |
|---|---:|---:|---:|---:|---:|
| Mobile | 1.5 s | 4.0 s | 80 ms | 0 | 4.0 s |
| Desktop | 0.4 s | 0.8 s | 20 ms | 0 | 0.4 s |

The reports were captured on September 23, 2026, at 9:31 PM EDT. PageSpeed Insights had no real-user field data for this site. These figures come from simulated lab runs.

- [Mobile report screenshot](<2026-09-23 at 21-38-09 PageSpeed Insights Mobile.png>)
- [Desktop report screenshot](<2026-09-23 at 21-32-42 PageSpeed Insights.png>)

## Repository Changes

- Removed the front-end jQuery dependency and unused AJAX localization.
- Removed the public Dashicons font and stylesheet dependency. Required Dashicons paths now render as inline SVG through one shared helper.
- Added WordPress responsive image output for the hero so browsers can select a smaller source.
- Kept home-page card titles at `h3` below the Latest Blog Posts `h2`.
- Changed blog, portfolio, search, tag, and category listing card titles to `h2` below each page `h1`.
- Added post titles to the accessible names of repeated Read More links.
- Added a dedicated button background color with sufficient contrast against white text.
- Increased dark-mode cyan contrast for links and featured badges.
- Underlined inline text links in page copy and the footer copyright line so they remain distinct without color.

## External Follow-Up

The latest reports still show these opportunities and related work in the plugin or hosting setup:

- Mobile render-blocking requests have an estimated 740 ms saving. Review the Autoptimize request chain and font loading in the separate font plugin.
- Unused JavaScript has an estimated 73 KiB saving on both devices. Review third-party scripts, including Google Tag Manager.
- Cache lifetime findings have an estimated 1 KiB saving. Review the hosting and CDN cache settings for versioned static assets.
- Desktop image delivery has an estimated 97 KiB saving. Inspect the specific images and delivery settings in the expanded audit.
- The hosting plugin should remove its own jQuery dependency in its repository.
