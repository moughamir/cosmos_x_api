#!/usr/bin/env sh
set -e

DB_FILE="${DB_FILE:-/var/www/html/config/data/sqlite/products.sqlite}"

# Generate OpenAPI JSON (ignore non-fatal issues)
php /var/www/html/bin/generate_openapi.php || true

# Precompute related products if DB exists
if [ -f "$DB_FILE" ]; then
  echo "Computing product similarities using DB: $DB_FILE"
  DB_FILE="$DB_FILE" php /var/www/html/bin/compute_similarities.php || true
else
  echo "DB file not found at $DB_FILE, skipping similarities."
fi

# Start Apache in foreground
exec apache2-foreground
