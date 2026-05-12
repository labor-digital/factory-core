# SeedTemplates — synthetic-only builtins

This directory is for **synthetic, generic seed templates** that ship with
`labor-digital/factory-core` and are safe to live in a public Composer
package. Real, customer-derived seeds (with client names, real copy, or
brand-specific design intent) belong in the private
`labor-factory-seeds` Bitbucket repo — see DL #014 for the convention.

Pipeline-app reads both sources and tags each entry's `source` as
`builtin` (from here) or `library` (from the private repo). The picker
shows them in one combined list.

## Seed file shape

```json
{
	"core_version": "^0.2",
	"name": "Display name",
	"description": "What this seed produces",
	"active_components": ["PageHero", "Text"],
	"active_record_types": [],
	"settings": { "colors": { "primary": "..." }, "radius": "...", "..." },
	"elements": [
		{ "component": "page-hero", "data": { ... } }
	]
}
```

See `../_schema.json` in `labor-factory-seeds` for the formal JSON Schema.

## Why this dir is empty right now

The previous `heckelsmueller.json` was migrated to the private library
repo on 2026-05-12 — its design intent reflected a specific client and
didn't belong in a public Composer package. We expect to land at least
one truly synthetic seed here over time (e.g. a generic "blog" or
"corporate" template) so demo installs of factory-core have something
to seed against.
