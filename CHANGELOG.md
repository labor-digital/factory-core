# Changelog

## [0.11.2](https://github.com/labor-digital/lab-factory/compare/factory-core-v0.11.1...factory-core-v0.11.2) (2026-05-15)


### Bug Fixes

* **typo3-extension:** cta buttons collection matches the standard button schema ([bb8db85](https://github.com/labor-digital/lab-factory/commit/bb8db85431328a8c3b6f5354e9660c90efb3d2a0))

## [0.11.1](https://github.com/labor-digital/lab-factory/compare/factory-core-v0.11.0...factory-core-v0.11.1) (2026-05-15)


### Bug Fixes

* **typo3-extension:** introspect columns via information_schema, not ADD COLUMN IF NOT EXISTS ([56bdcad](https://github.com/labor-digital/lab-factory/commit/56bdcadf9d46ed2dc0331ce8212f2d02b5344f1a))

## [0.11.0](https://github.com/labor-digital/lab-factory/compare/factory-core-v0.10.6...factory-core-v0.11.0) (2026-05-15)


### ⚠ BREAKING CHANGES

* **typo3-extension:** native ADD COLUMN IF NOT EXISTS + drop legacy fallbacks

### Bug Fixes

* **typo3-extension:** native ADD COLUMN IF NOT EXISTS + drop legacy fallbacks ([e3b9a61](https://github.com/labor-digital/lab-factory/commit/e3b9a6162858ef6221c2de0f9032a7c3ea06bdab))

## [0.10.6](https://github.com/labor-digital/lab-factory/compare/factory-core-v0.10.5...factory-core-v0.10.6) (2026-05-15)


### Bug Fixes

* **typo3-extension:** bulletproof child-table self-heal via try-ALTER pattern ([7791194](https://github.com/labor-digital/lab-factory/commit/77911947c392d41b5512b533d83be795d44293cd))

## [0.10.5](https://github.com/labor-digital/lab-factory/compare/factory-core-v0.10.4...factory-core-v0.10.5) (2026-05-15)


### Bug Fixes

* **typo3-extension:** handle backticked reserved-word columns in self-heal ([6acfed8](https://github.com/labor-digital/lab-factory/commit/6acfed85c6e9717a77aad64fd335a4693b7d9d75))

## [0.10.4](https://github.com/labor-digital/lab-factory/compare/factory-core-v0.10.3...factory-core-v0.10.4) (2026-05-15)


### Bug Fixes

* **typo3-extension:** self-heal child-table columns in TenantContentSeeder ([1d05970](https://github.com/labor-digital/lab-factory/commit/1d05970b0ef6c4e0425d8ba6ae411e70ff865978))

## [0.10.3](https://github.com/labor-digital/lab-factory/compare/factory-core-v0.10.2...factory-core-v0.10.3) (2026-05-15)


### Bug Fixes

* **typo3-extension:** rename `size` identifier to avoid content-blocks reservation ([f34ea73](https://github.com/labor-digital/lab-factory/commit/f34ea7307b1317ab69f0ad7faff15d0bb09fb1a7))

## [0.10.2](https://github.com/labor-digital/lab-factory/compare/factory-core-v0.10.1...factory-core-v0.10.2) (2026-05-15)


### Bug Fixes

* **typo3-extension:** quote variant labels containing commas ([34caacc](https://github.com/labor-digital/lab-factory/commit/34caacc53f1484add993f35634c859bc516005e0))

## [0.10.1](https://github.com/labor-digital/lab-factory/compare/factory-core-v0.10.0...factory-core-v0.10.1) (2026-05-15)


### Bug Fixes

* **typo3-extension:** quote Footer config label containing colons ([d56be0e](https://github.com/labor-digital/lab-factory/commit/d56be0ebe11234d2a4c0a4526823d32a4f8c3310))

## [0.10.0](https://github.com/labor-digital/lab-factory/compare/factory-core-v0.9.0...factory-core-v0.10.0) (2026-05-14)


### Features

* **content-blocks:** adopt Claude-design content blocks, retire NuxtUI ones (DL [#018](https://github.com/labor-digital/lab-factory/issues/018)) ([93ccfc5](https://github.com/labor-digital/lab-factory/commit/93ccfc5e8b1df8fda2d6ae82cd62650ad74b8b97))

## [0.9.0](https://github.com/labor-digital/lab-factory/compare/factory-core-v0.8.0...factory-core-v0.9.0) (2026-05-13)


### Features

* **factory-core:** CORS middleware allowing the site's frontendBase ([69148ef](https://github.com/labor-digital/lab-factory/commit/69148ef283fd59efaaa8240af8e2911d660ba7d6))

## [0.8.0](https://github.com/labor-digital/lab-factory/compare/factory-core-v0.7.1...factory-core-v0.8.0) (2026-05-13)


### Features

* **factory-core:** --language option on factory:tenant:provision ([7054355](https://github.com/labor-digital/lab-factory/commit/7054355bc4b00d9dd15e9e18c6ca84c1559db2fc))

## [0.7.1](https://github.com/labor-digital/lab-factory/compare/factory-core-v0.7.0...factory-core-v0.7.1) (2026-05-13)


### Bug Fixes

* **factory-core:** normalise frontendBase to always end with "/" ([963a37d](https://github.com/labor-digital/lab-factory/commit/963a37db85e2ea267cc64d0a7183db5db9417734))

## [0.7.0](https://github.com/labor-digital/lab-factory/compare/factory-core-v0.6.0...factory-core-v0.7.0) (2026-05-13)


### Features

* **factory-core:** write frontendBase into tenant site config ([3d0eec2](https://github.com/labor-digital/lab-factory/commit/3d0eec2aeef9abb0f4dc185835d2d9445252ff14))

## [0.6.0](https://github.com/labor-digital/lab-factory/compare/factory-core-v0.5.2...factory-core-v0.6.0) (2026-05-13)


### Features

* **factory-core:** TenantContentSeeder supports subpages ([25c0315](https://github.com/labor-digital/lab-factory/commit/25c0315b37c51eefcae97b70ea0113648e9fcce4))

## [0.5.2](https://github.com/labor-digital/lab-factory/compare/factory-core-v0.5.1...factory-core-v0.5.2) (2026-05-13)


### Bug Fixes

* **factory-core:** rename content-block templates Frontend.html → frontend.html ([ef6027f](https://github.com/labor-digital/lab-factory/commit/ef6027f7b6c1f2d0c358cd192e262c04214690a0))

## [0.5.1](https://github.com/labor-digital/lab-factory/compare/factory-core-v0.5.0...factory-core-v0.5.1) (2026-05-13)


### Bug Fixes

* **factory-core:** invalidate TYPO3 site cache on tenant create/retire ([fbe6872](https://github.com/labor-digital/lab-factory/commit/fbe68722eb22d0dafc1a2aaf3086f825359cb35a))

## [0.5.0](https://github.com/labor-digital/lab-factory/compare/factory-core-v0.4.2...factory-core-v0.5.0) (2026-05-13)


### Features

* **factory-core:** add --base option to factory:tenant:provision ([01a7828](https://github.com/labor-digital/lab-factory/commit/01a78281ee49c970ba8d23d2f2deb70954e7c57f))

## [0.4.2](https://github.com/labor-digital/lab-factory/compare/factory-core-v0.4.1...factory-core-v0.4.2) (2026-05-12)


### Bug Fixes

* **factory-core:** read site config.yaml directly instead of SiteFinder ([1e4977a](https://github.com/labor-digital/lab-factory/commit/1e4977a25ff534d23d85122f77c1b138c0716198))

## [0.4.1](https://github.com/labor-digital/lab-factory/compare/factory-core-v0.4.0...factory-core-v0.4.1) (2026-05-12)


### Bug Fixes

* **factory-core:** use Doctrine\DBAL\ParameterType::INTEGER, not \PDO::PARAM_INT ([d01abb8](https://github.com/labor-digital/lab-factory/commit/d01abb89e42f0b7de9c8e3201697aaf9c9fd5a65))

## [0.4.0](https://github.com/labor-digital/lab-factory/compare/factory-core-v0.3.2...factory-core-v0.4.0) (2026-05-12)


### Features

* **factory-core,factory-multitenant-api:** tenant lifecycle — content seed + retire (DL [#016](https://github.com/labor-digital/lab-factory/issues/016)) ([cc1d33b](https://github.com/labor-digital/lab-factory/commit/cc1d33bdbd8571e5fa553fbc5045ad83f7adb37c))

## [0.3.2](https://github.com/labor-digital/lab-factory/compare/factory-core-v0.3.1...factory-core-v0.3.2) (2026-05-12)


### Bug Fixes

* **factory-core:** use sys_filemounts (TYPO3 13 schema) in TenantProvisionCommand ([9aba963](https://github.com/labor-digital/lab-factory/commit/9aba96340fd8bff0d9b840dcd76b47940f7e6acb))

## [0.3.1](https://github.com/labor-digital/lab-factory/compare/factory-core-v0.3.0...factory-core-v0.3.1) (2026-05-12)


### Bug Fixes

* **factory-core:** TenantScopeEnforcer must be a public DI service ([951f0b7](https://github.com/labor-digital/lab-factory/commit/951f0b7571b2e8df90e0f2116a395abd7013201c))

## [0.3.0](https://github.com/labor-digital/lab-factory/compare/factory-core-v0.2.0...factory-core-v0.3.0) (2026-05-12)


### Features

* **seed:** heckelsmüller declares core_version ^0.2 + read seed file directly ([d992c1c](https://github.com/labor-digital/lab-factory/commit/d992c1c722cf8c1b9ded7e6fa12c5d634cf082f8))

## [0.2.0](https://github.com/labor-digital/lab-factory/compare/factory-core-v0.1.1...factory-core-v0.2.0) (2026-05-08)


### Features

* **factory-core:** add FactoryComponentRegistry::invalidate and factory:seed:reset ([f453e6a](https://github.com/labor-digital/lab-factory/commit/f453e6ac7302f24ff86bef31add24bb50ccb7906))

## [0.1.1](https://github.com/labor-digital/lab-factory/compare/factory-core-v0.1.0...factory-core-v0.1.1) (2026-04-30)


### Bug Fixes

* correct nuxt-layer repo link and add typo3-extension README ([b54310a](https://github.com/labor-digital/lab-factory/commit/b54310a8b268a9cf28048f86866e01e2e33bf445))

## 0.1.0 (2026-04-30)


### Features

* initial public release of factory-core 0.1.0 ([f3c1d53](https://github.com/labor-digital/lab-factory/commit/f3c1d53c57c00f1761d08f2a6ac2794839477cb8))

## Changelog
