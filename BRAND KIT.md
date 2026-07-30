# Brand Kit

This document defines the graphical styling, typography, and color schemes for the **aS.c AI Boiler Framework** project. These styles are implemented in the stylesheet at [front.css](file:///media/keith/Data/work/asc-ai-boiler/asc-ai-example/assets/front/front.css).

---

## Typography

### Fonts
- **Font Family:** `system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Helvetica Neue', sans-serif` (`--example-font`)

### Hierarchy
- **Body Text:** `18px`
- **Heading 1 (h1):** `36px` (`--example-h1-font-size`)
- **Heading 2 (h2):** `28px` (`--example-h2-font-size`)
- **Heading 3 (h3):** `24px` (`--example-h3-font-size`)
- **Heading 4 (h4):** `20px` (`--example-h4-font-size`)
- **Heading 5 (h5):** `18px` (`--example-h5-font-size`)
- **Heading 6 (h6):** `16px` (`--example-h6-font-size`)

---

## Color Scheme

The site implements a theme system using CSS variables.

### Main Theme Colors

| Variable | Description / Purpose | Hex Value |
| :--- | :--- | :--- |
| `--example-bg` | Main page background | `#ffffff` |
| `--example-surface` | Surfaces (cards, inputs, panels) | `#f2efef` |
| `--example-muted-bg` | Hover states, secondary panels | `#dbdada` |
| `--example-fg` | Main foreground / text | `#212121` |
| `--example-muted` | Muted text, borders, placeholders | `#757575` |
| `--example-post-date` | Post date metadata | `#757575` |

### Accents & Utility Colors

| Variable | Description / Purpose | Hex Value |
| :--- | :--- | :--- |
| `--example-cyan` | Cyan border & highlights | `#0b7285` |
| `--example-green` | Green highlights / success status | `#2b8a3e` |
| `--example-purple` | Purple accent details | `#862e9c` |
| `--example-accent` | Accent pink/red details | `#d6336c` |
| `--example-focus` | Focus outline color | `#e8590c` |
| `--example-brand-accent` | Brand Accent highlights | `#0b7285` |
| `--example-teal` | Deep Teal background panels | `#10738a` |
| `--example-light-teal` | Light sky blue details | `#15aabf` |
| `--example-theme-toggle-bg` | Theme switcher background | `#ced5e0` |
| `--example-cyan-border` | Cyan border style | `2px solid #67d8ef` |

---

## Component Specifications

### Buttons
- **Font Size:** `18px` (`--example-button-font-size`)
- **Font Weight:** `600` (`--example-button-font-weight`)
- **Line Height:** `1.2` (`--example-button-line-height`)
- **Padding (Standard):** `8px` top/bottom, `16px` left/right
- **Padding (Footer):** `8px` top/bottom, `14px` left/right
- **Border Radius:** `16px` (`--example-button-border-radius`)
- **Ghost Button Border:** `2px solid transparent`
- **Standard Button Border (Footer):** `2px solid var(--example-muted)`
- **Hover Border:** `2px solid #495057` (`--example-btn-hover-border`)

### Cards
- **Border Radius:** `10px` (`--example-card-border-radius`)
