# Code Style Guide

## Overview

This style guide provides **guidelines** (not strict requirements) for writing code in this project. The primary goals are:

- **Explicit over implicit**: Code should clearly express intent
- **Readable and maintainable**: Easy to understand and modify
- **Consistent patterns**: Similar code should look similar

These guidelines help maintain code quality and make the codebase easier to work with. Use your judgment when applying them—the goal is better code, not rigid adherence to rules.

## General Principles

- **Readability over brevity**: Code should be easy to read and understand
- **Explicit over implicit**: Prefer clear, explicit code over clever shortcuts
- **Logic before presentation**: Separate data preparation from HTML rendering

## Operators and Conditionals

### Use Null Coalescing Operator (`??`)
The null coalescing operator is preferred for simple default value assignment:

```php
// Good:
$value = $settings['key'] ?? $defaults['key'];
$model = $settings['ai_model'] ?? $defaults['ai_model'];

// Avoid:
$value = isset($settings['key']) ? $settings['key'] : $defaults['key'];
```

### Use Switch Statements Where Appropriate
Switch statements are preferred for multiple condition checks:

```php
// Good:
switch ($status) {
	case 'active':
		$class = 'active';
		break;
	case 'pending':
		$class = 'pending';
		break;
	default:
		$class = 'inactive';
		break;
}
```

### Avoid Ternary Operators
Avoid ternary operators (`? :`) in favor of explicit if statements:

```php
// Avoid:
$class = $is_active ? 'active' : 'inactive';
$value = isset($data['key']) ? $data['key'] : 'default';

// Prefer:
$class = 'inactive';
if ( $is_active ) {
	$class = 'active';
}

$value = 'default';
if ( isset( $data['key'] ) ) {
	$value = $data['key'];
}
```

### Avoid Conditional Assignment
Avoid assigning the result of a comparison/conditional expression directly to a variable. Use explicit if statements instead:

```php
// Avoid:
$is_manual = 'none' === $selected_model;
$is_enabled = ! empty( $settings['enabled'] );
$has_permission = current_user_can( 'edit_posts' );

// Prefer:
$is_manual = false;
if ( 'none' === $selected_model ) {
	$is_manual = true;
}

$is_enabled = false;
if ( ! empty( $settings['enabled'] ) ) {
	$is_enabled = true;
}

$has_permission = false;
if ( current_user_can( 'edit_posts' ) ) {
	$has_permission = true;
}
```

## Formatting

### Indentation
- Use **tabs** for indentation, not spaces
- Do not use spaces for column alignment in arrays or other structures

### Arrays
Keep arrays simple without column alignment:

```php
// Good:
$styles = array(
	'block' => __( 'Block', 'asc-core-tools' ),
	'writer' => __( 'Writer', 'asc-core-tools' ),
	'card' => __( 'Card', 'asc-core-tools' ),
	'tab' => __( 'Tab', 'asc-core-tools' ),
);

// Avoid column alignment:
$styles = array(
	'block'  => __( 'Block', 'asc-core-tools' ),
	'writer' => __( 'Writer', 'asc-core-tools' ),
	'card'   => __( 'Card', 'asc-core-tools' ),
	'tab'    => __( 'Tab', 'asc-core-tools' ),
);
```

## HTML and PHP Mixing

### Separate Logic from Presentation
Do all data preparation and logic before HTML blocks. Use plain variables in HTML:

```php
// Good:
$settings = Settings::get_settings();
$defaults = Settings::get_default_settings();
$selected_model = $settings['ai_model'] ?? $defaults['ai_model'];
$model_label = $ai_models[$selected_model] ?? $ai_models[$defaults['ai_model']];

$is_manual = false;
if ( 'none' === $selected_model ) {
	$is_manual = true;
}

?>
<table>
	<tr>
		<td><?php echo esc_html( $model_label ); ?></td>
	</tr>
</table>
```

```php
// Avoid:
<table>
	<tr>
		<td><?php echo esc_html( $ai_models[$settings['ai_model'] ?? $defaults['ai_model']] ?? 'None' ); ?></td>
	</tr>
</table>
```

### Pre-compute Variables
Extract complex expressions into variables before HTML:

```php
// Good:
$display_class = 'hidden';
if ( ! empty( $settings['enabled'] ) && current_user_can( 'edit_posts' ) ) {
	$display_class = 'visible';
}

?>
<div class="<?php echo esc_attr( $display_class ); ?>">
```

## Variable Naming

Use descriptive variable names that explain intent:

```php
// Good:
$is_manual = false;
if ( 'none' === $selected_model ) {
	$is_manual = true;
}

$should_sync_excerpt = false;
if ( ! empty( $settings['sync_ai_excerpt_to_post_excerpt'] ) ) {
	$should_sync_excerpt = true;
}

$selected_post_types = $settings['post_types'] ?? $defaults['post_types'];

// Avoid:
$manual = 'none' === $model;
$sync = ! empty( $settings['sync'] );
```

## Code Organization

### Group Related Operations
Keep related operations together:

```php
// Get all data first
$settings = Settings::get_settings();
$defaults = Settings::get_default_settings();
$ai_models = Settings::get_ai_models();

// Process/transform data
$selected_model = $settings['ai_model'] ?? $defaults['ai_model'];
$model_label = $ai_models[$selected_model] ?? $ai_models[$defaults['ai_model']];

// Determine state
$is_manual = false;
if ( 'none' === $selected_model ) {
	$is_manual = true;
}
```

### Complex Conditionals
Extract complex conditionals into variables:

```php
// Good:
$display_class = 'hidden';
if ( ! empty( $settings['enabled'] ) && current_user_can( 'edit_posts' ) ) {
	$display_class = 'visible';
}

// Avoid nested ternaries:
// $class = $is_active ? ( $is_primary ? 'active-primary' : 'active' ) : 'inactive';
```

## HTML, CSS, and JavaScript Markup

These guidelines apply when writing HTML, CSS, and JavaScript for front-end or admin UI.

### Visual Effects

Do not add animations, transitions, drop shadows, or gradients unless explicitly specified.

```css
/* Avoid unless specified: */
.card {
	transition: all 0.3s ease;
	box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
	background: linear-gradient(180deg, #fff, #f5f5f5);
}
```

### Use Project Design Tokens

Do not introduce new fonts, colors, borders, or border-radius values unless explicitly specified. Project colors, buttons, fonts, border radius, and other UI elements will be provided—reuse those instead of inventing new styles.

```css
/* Avoid unless specified: */
.section {
	font-family: 'Inter', sans-serif;
	color: #3b82f6;
	border: 1px solid #e5e7eb;
	border-radius: 12px;
}
```

### Vertical Spacing

Maintain consistent vertical spacing between sections unless otherwise specified. Use the same spacing pattern between paragraph-level items (headings, paragraphs, list items) unless a design calls for a different treatment.

```css
/* Good: reuse established spacing values */
.section + .section {
	margin-top: 40px;
}

.section p + p {
	margin-top: 16px;
}
```

When spacing must change for smaller or larger viewports, adjust it in media queries rather than switching to relative units.

### CSS Sizing

Prefer **pixel-based (`px`) sizing** for layout and spacing to keep designs pixel-perfect. Avoid `em`, `rem`, and other relative units for sizing unless explicitly specified.

Use **CSS media queries** when spacing or layout needs to change for device resolution:

```css
.section {
	margin-bottom: 40px;
	padding: 24px;
}

@media (max-width: 768px) {
	.section {
		margin-bottom: 24px;
		padding: 16px;
	}
}
```

## Summary

These guidelines prioritize explicit, readable, and maintainable code. They are meant to help, not constrain. When in doubt, choose the approach that makes the code easier to understand and maintain.

Key preferences:
- ✅ Use `??` operator for simple defaults
- ✅ Use switch statements where appropriate
- ✅ Use explicit if statements for conditional logic
- ❌ Avoid ternary operators (`? :`)
- ❌ Avoid conditional assignment (e.g., `$var = condition === value;`)
- ✅ Use tabs for indentation
- ✅ No column alignment in arrays
- ✅ Separate logic from HTML presentation
- ✅ Pre-compute variables before HTML blocks
- ✅ Use descriptive variable names
- ❌ No animations, transitions, drop shadows, or gradients unless specified
- ❌ No new fonts, colors, borders, or border-radius unless specified
- ✅ Consistent vertical spacing between sections and paragraph items
- ✅ Use `px` for CSS sizing; use media queries for responsive spacing
