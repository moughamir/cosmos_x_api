# Convenience developer targets

.PHONY: docs docs-serve test similarity-rebuild

# Generate OpenAPI JSON to public/openapi.json
docs:
	composer docs

# Serve the public/ directory (local dev)
docs-serve:
	composer docs:serve

# Run PHPUnit tests
test:
	composer test

# Rebuild precomputed product similarities (uses DB_FILE if set)
similarity-rebuild:
	composer similarity:rebuild
