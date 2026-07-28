# Project Rules

- **Layout constraint:** Do not use CSS Grid layouts. Always use CSS Flexbox layouts instead for structural alignments and columns.
- **Deployment constraint:** Do not run deployment commands or deploy files to external directories (e.g. `deploy-plugin`). The user handles all deployments.
- **Code style constraint:** All code MUST strictly adhere to [STYLE.md](file:///media/keith/Data/work/asc-ai-boiler/STYLE.md). Never use column alignment padding before `=` or `=>` in variable declarations or arrays. Never use ternary operators (`? :`). Never use consecutive blank lines. Always run `php -l` verification after editing.


