# AGENT.md

This file provides guidance to AI assistants/agents when working with code in this repository.

Three-package WordPress repo. PHP 8.1+, GPL v3.
- `asc-ai-theme/`   bare WordPress theme (no logic)
- `asc-ai-plugin/`  boiler framework: partial system, content sync, admin settings, bundled assets
- `asc-ai-example/` example site layer: post types, shortcodes, front-end rendering, content files

## Rules

Consult STYLE.md for all code generation and reviews.
Consult ARCH.md when working on new features or asking about structure.
Consult LOG.md before suggesting architectural changes.
Consult BRAND KIT.md for CSS styling, color schemes, and fonts.
Do not rewrite working code without being asked.
Do not add comments unless asked.

## Commands

php -l <file>                          syntax check
rg 'pattern' asc-ai-plugin/includes/  search plugin
rg 'pattern' asc-ai-example/includes/ search example
composer install (in asc-ai-plugin/)   install/update plugin autoloader
composer install (in asc-ai-example/)  install/update example autoloader

No build step, no test suite. php -l is primary verification.
