# labor-digital/factory-core

TYPO3 extension for the [LABOR.digital Factory](https://github.com/labor-digital/lab-factory) headless CMS boilerplate.

Ships Content Blocks, record types, tenant-scope enforcement, and provisioning CLIs for single-tenant and shared-tenant TYPO3 installations.

## Install

```bash
composer require labor-digital/factory-core
```

Then activate in TYPO3:

```bash
vendor/bin/typo3 extension:setup
```

## Features

- **Content Blocks** — Curated headless-friendly CTypes (PageHero, PageSection, TextSlider, etc.) consumed by the [`@labor-digital/factory-nuxt-layer`](https://www.npmjs.com/package/@labor-digital/factory-nuxt-layer) frontend.
- **Record types** — Polymorphic ReferenceList wrapper for arbitrary records.
- **Multi-tenant** — `factory:tenant:provision` and `factory:tenant:audit` CLIs, plus a DataHandler `TenantScopeEnforcer` that blocks cross-tenant writes in shared-DB setups.
- **Component activation contract** — `factory.json` declares which components are active per project (or per site, in shared-tenant mode).

## Requirements

- TYPO3 13.4
- PHP 8.4
- `friendsoftypo3/headless`, `friendsoftypo3/content-blocks`, `netzbewegung/nb-headless-content-blocks`

## License

GPL-2.0-or-later — see [LICENSE](./LICENSE).
