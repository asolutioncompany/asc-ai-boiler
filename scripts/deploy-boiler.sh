#!/usr/bin/env bash
# Deploy asc-ai-boiler to local boiler/ape and always restore content from plugin files.
set -euo pipefail

usage() {
	cat <<EOF
Deploy asc-ai-boiler to boiler/ape and run ContentSync restore (www-data).

Usage:
  $(basename "$0")

Always deploys plugin files, then restores from files. No deploy-only mode.

Environment (optional overrides):
  DEPLOY_PLUGIN   Path to deploy-plugin script (default: ~/utility-scripts/deploy-plugin)
  WP_PATH         WordPress install path (default: /var/www/boiler/ape)
EOF
}

if [[ "${1:-}" == "-h" || "${1:-}" == "--help" ]]; then
	usage
	exit 0
fi

if [[ -n "${1:-}" ]]; then
	echo "Unknown argument: $1 (this script always deploys and restores)" >&2
	usage >&2
	exit 1
fi

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DEPLOY_PLUGIN="${DEPLOY_PLUGIN:-$HOME/utility-scripts/deploy-plugin}"
WP_PATH="${WP_PATH:-/var/www/boiler/ape}"
PLUGINS_DIR="${WP_PATH}/wp-content/plugins"

if [[ ! -x "$DEPLOY_PLUGIN" ]]; then
	echo "deploy-plugin not found or not executable: $DEPLOY_PLUGIN" >&2
	exit 1
fi

"$DEPLOY_PLUGIN" "$REPO_ROOT" "$PLUGINS_DIR"

sudo -u www-data wp eval "$(cat <<'PHP'
$result = \ASC\AI_BOILER\Admin\ContentSync::restore_from_files( true );
echo 'ok: ' . ( $result['ok'] ? 'yes' : 'no' ) . PHP_EOL;
echo 'updated: ' . (int) $result['updated'] . PHP_EOL;
foreach ( $result['messages'] as $msg ) {
	echo '- ' . $msg . PHP_EOL;
}
PHP
)" --path="$WP_PATH"
