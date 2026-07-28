# STYLE.md

PHP, HTML, CSS, and JS code style for asc-ai-boiler. Guidelines, not strict rules — goal is readable, consistent code.

## Indentation and formatting

- Tabs, not spaces
- No column alignment in arrays, array key-value pairs (=>), or variable assignments (=)
- WordPress coding standards spacing: spaces inside parens for control structures and function calls
- Maximum of one consecutive blank line (no double or multiple blank lines)

## Operators and conditionals

- Use ?? for simple default assignment: $val = $settings['key'] ?? $default
- No ternary operators (? :) — use explicit if statements
- No conditional assignment: avoid $flag = condition === value
  Use: $flag = false; if (condition) { $flag = true; }
- Use switch for multiple condition checks

## Variables

- Descriptive names: $is_manual, $should_sync_excerpt — not $manual, $sync
- Pre-compute all variables before HTML blocks

## PHP/HTML mixing

- All logic and data preparation above the ?> boundary
- Plain variables only inside HTML — no expressions or function calls inline

## Example

```php
// Good
$selected = $settings['model'] ?? $defaults['model'];
$is_manual = false;
if ( 'none' === $selected ) {
    $is_manual = true;
}
?>
<td><?php echo esc_html( $selected ); ?></td>

// Avoid
<td><?php echo esc_html( $settings['model'] ?? $defaults['model'] ); ?></td>
```

## HTML, CSS, and JavaScript

- No animations, transitions, drop shadows, or gradients unless explicitly specified
- Do not introduce new fonts, colors, borders, or border-radius values unless explicitly specified — reuse project design tokens
- Consistent vertical spacing between sections; adjust in media queries for smaller/larger viewports
- Prefer px for layout and spacing; avoid em/rem unless explicitly specified
