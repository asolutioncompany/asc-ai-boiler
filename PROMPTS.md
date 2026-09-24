# aS.c AI Boiler Prompts

Replace every bracketed value before using a prompt.

## Create a New Site Layer Plugin

```text
Create a new standalone WordPress site layer plugin using asc-ai-example as the reference implementation.

Project values:
- Site name: [SITE NAME]
- Plugin name: [PLUGIN NAME]
- Plugin directory slug: [PLUGIN-SLUG]
- PHP namespace: [VENDOR\\PROJECT]
- PHP symbol prefix: [project_prefix]
- WordPress option and metadata prefix: [_project_prefix]
- Text domain: [plugin-slug]
- Custom post types and rewrite slugs: [LIST EACH POST TYPE AND SLUG]

Copy the required site-layer architecture into a new plugin directory. Replace every example-specific identifier, including "Example", "example", "ASC_AI_EXAMPLE", "ASC\\AI_EXAMPLE", option names, metadata keys, CSS classes, JavaScript selectors, shortcode names, hook names, asset handles, cookies, post type names, rewrite slugs, and translation domains. Do not rename identifiers owned by WordPress or asc-ai-plugin.

Keep the new site layer fully functional when asc-ai-plugin is inactive. Register content-sync filters only as optional integration points. Update Composer autoloading, the plugin header, constants, namespaces, documentation, and content paths. Search the finished plugin for remaining example-specific identifiers and report any intentional matches. Run PHP syntax checks on every PHP file.
```

## Generate Complete Content Manifest Metadata

```text
Review the site layer's registered content types and every file under its content directory. Generate or update content/content-manifest.json without removing unrelated valid data.

For each page, post, and registered custom post type, include the correct post_type, title, slug, filename, and date_gmt. Include excerpt, categories, tags, featured media bindings, registered custom post metadata, and all applicable provider-neutral SEO fields: meta_description, social_description, x_description, social_title, x_title, focus_keyphrase, and primary_category. Add top-level category and tag taxonomy rows with a slug, name, and concise archive description for every used term.

Store description text in the matching companion directories and reference each .txt filename from the manifest. Use category and tag slugs with names. Store primary_category as the slug of a category assigned to that post. Use portable slugs or filenames instead of WordPress database IDs.

Preserve the sync contract. Omit a field when import must leave its current WordPress value unchanged. Use an empty inline value or an empty referenced companion file when import must clear an existing value. Validate JSON, confirm every referenced file exists, and report missing source information instead of inventing facts.
```

## Remove Light and Dark Mode Switching

The maintained removal prompt and cache cleanup checklist live in [THEME_TOGGLE.md](THEME_TOGGLE.md#6-how-to-remove-light-and-dark-mode-switching). The prompt preserves the shared inline icon renderer because search, close, menu, and other controls also use it.
