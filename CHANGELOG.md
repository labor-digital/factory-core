# Change Log

All notable changes to this project will be documented in this file. See [standard-version](https://github.com/conventional-changelog/standard-version) for commit guidelines.

#  (2026-07-24)


### Bug Fixes

* **ci:** correct deploy step script path → /opt/deploy-docker-to-ecs.sh ([f9b3168](https://bitbucket.org/labor-digital/labor-factory-app/commits/f9b316891a41d01c8557a0971c061f86a703fa0e))
* **ci:** quote ext_emconf sync command (colon-space broke YAML parse) ([5ddbaf6](https://bitbucket.org/labor-digital/labor-factory-app/commits/5ddbaf6f954d0e8b25756d507d9f26f326f120e0))
* **ci:** sync release steps to master tip before bump ([dff8ed2](https://bitbucket.org/labor-digital/labor-factory-app/commits/dff8ed2398ccb1164d816eb7d7d19a15cb12e0fc))
* **ci:** write npm auth to $HOME/.npmrc (workspace ignores member .npmrc) ([7f6bace](https://bitbucket.org/labor-digital/labor-factory-app/commits/7f6bace8ad612f4e0301b10dd00e782d669bc7a0))
* correct nuxt-layer install snippet to match actual typo3 module config ([e804f8d](https://bitbucket.org/labor-digital/labor-factory-app/commits/e804f8d7ea53a7b91946e14466faa5da74809472))
* correct nuxt-layer repo link and add typo3-extension README ([b54310a](https://bitbucket.org/labor-digital/labor-factory-app/commits/b54310a8b268a9cf28048f86866e01e2e33bf445))
* **factory-components:** resolve the nuxt-layer path in npm mode ([8e963cd](https://bitbucket.org/labor-digital/labor-factory-app/commits/8e963cd19fdde9f3dff937616e87793e1e3029a1))
* **factory-core:** invalidate TYPO3 site cache on tenant create/retire ([fbe6872](https://bitbucket.org/labor-digital/labor-factory-app/commits/fbe68722eb22d0dafc1a2aaf3086f825359cb35a))
* **factory-core:** make fly.io wake-on-request reliable in frontend template ([213302f](https://bitbucket.org/labor-digital/labor-factory-app/commits/213302f3a876cf638472b634f6f4224053f7a4e7))
* **factory-core:** normalise frontendBase to always end with "/" ([963a37d](https://bitbucket.org/labor-digital/labor-factory-app/commits/963a37db85e2ea267cc64d0a7183db5db9417734))
* **factory-core:** read site config.yaml directly instead of SiteFinder ([1e4977a](https://bitbucket.org/labor-digital/labor-factory-app/commits/1e4977a25ff534d23d85122f77c1b138c0716198))
* **factory-core:** rename content-block templates Frontend.html → frontend.html ([ef6027f](https://bitbucket.org/labor-digital/labor-factory-app/commits/ef6027f7b6c1f2d0c358cd192e262c04214690a0))
* **factory-core:** TenantScopeEnforcer must be a public DI service ([951f0b7](https://bitbucket.org/labor-digital/labor-factory-app/commits/951f0b7571b2e8df90e0f2116a395abd7013201c))
* **factory-core:** use Doctrine\DBAL\ParameterType::INTEGER, not \PDO::PARAM_INT ([d01abb8](https://bitbucket.org/labor-digital/labor-factory-app/commits/d01abb89e42f0b7de9c8e3201697aaf9c9fd5a65)), closes [#2](https://bitbucket.org/labor-digital/labor-factory-app/issue/2)
* **factory-core:** use sys_filemounts (TYPO3 13 schema) in TenantProvisionCommand ([9aba963](https://bitbucket.org/labor-digital/labor-factory-app/commits/9aba96340fd8bff0d9b840dcd76b47940f7e6acb))
* **factory-multitenant-api:** disable autowire on SubpathAwareUrlUtility ([96be227](https://bitbucket.org/labor-digital/labor-factory-app/commits/96be227ef7f35426566114f3a2528f6802fb0272))
* **factory-multitenant-api:** fall back to REDIRECT_HTTP_AUTHORIZATION + apache_request_headers ([928dccd](https://bitbucket.org/labor-digital/labor-factory-app/commits/928dccde0a202f13a7d5c4b4aeb1c152de5caa07))
* **factory-multitenant-api:** GET /tenants/{slug} includes status field ([18f2a7c](https://bitbucket.org/labor-digital/labor-factory-app/commits/18f2a7cfddca968332d55bc8fae1968f3d907c18))
* **factory-multitenant-api:** require factory-core ^0.2 for invalidate hook ([da64e9a](https://bitbucket.org/labor-digital/labor-factory-app/commits/da64e9a6aac38f3f1e9c22b751c9e989909c77b1))
* **factory-multitenant-api:** require factory-core ^0.3 (TenantScopeEnforcer public-service fix) ([4326a55](https://bitbucket.org/labor-digital/labor-factory-app/commits/4326a553e2b61589c92c179100bfd67b3c40bf1b))
* **factory-multitenant-api:** require factory-core ^0.4 ([66edb56](https://bitbucket.org/labor-digital/labor-factory-app/commits/66edb5685e0f209d9a32ab55d229010d889f1307))
* **factory-multitenant-api:** require factory-core ^0.5 ([0fba476](https://bitbucket.org/labor-digital/labor-factory-app/commits/0fba4761bebfb404fa0daed567c995538afa564f))
* **factory-multitenant-api:** require factory-core ^0.6 ([8b849d2](https://bitbucket.org/labor-digital/labor-factory-app/commits/8b849d210a2a160497c98ddd1c025834b56889f8))
* **factory-multitenant-api:** require factory-core ^0.7 ([f782e68](https://bitbucket.org/labor-digital/labor-factory-app/commits/f782e68500cd9e0f8069313de38d8cc2ff0b7fd3))
* **factory-multitenant-api:** require factory-core ^0.8 ([df0d26e](https://bitbucket.org/labor-digital/labor-factory-app/commits/df0d26e7fa1bb9f079fa20c2da4f1f6fae31fec7))
* **factory-multitenant-api:** require factory-core ^0.9 ([dbbfc6f](https://bitbucket.org/labor-digital/labor-factory-app/commits/dbbfc6fb5dada431dde98621327a9cb63856d0ef))
* **factory-multitenant-api:** use correct TYPO3 13 middleware identifier ([bd77eb1](https://bitbucket.org/labor-digital/labor-factory-app/commits/bd77eb1abda6de2ffdcd098997f59b4ec9bf32f9))
* **nuxt-layer:** include assets/ in published files whitelist ([3c7eda2](https://bitbucket.org/labor-digital/labor-factory-app/commits/3c7eda22a9ce35e6b83849f91bc4134f721257b8)), closes [#018](https://bitbucket.org/labor-digital/labor-factory-app/issue/018)
* **nuxt-layer:** rename heading classes and drop @nuxt/ui from layout primitives ([534526a](https://bitbucket.org/labor-digital/labor-factory-app/commits/534526a5f9bc0921d57b9691bac9da07d42b5800))
* **pipeline-app:** always create factory-core symlink + bump composer constraint to ^0.2 ([a71ffe7](https://bitbucket.org/labor-digital/labor-factory-app/commits/a71ffe7f7467ba176f1d10c43be49bc4977abf8f))
* **pipeline-app:** apply seed.settings to factory.json before staging build ([732b349](https://bitbucket.org/labor-digital/labor-factory-app/commits/732b349cf7e5de0be25df88a0b7aba3b59caece8))
* **pipeline-app:** default flyIoOrgSlug to "personal" (LABOR's actual Fly org) ([7074211](https://bitbucket.org/labor-digital/labor-factory-app/commits/7074211013ef79960a0d8f7b4d9e10f1f5fa81b0))
* **pipeline-app:** derive frontendBase from slug for Fly staging deploys ([eddab5d](https://bitbucket.org/labor-digital/labor-factory-app/commits/eddab5d2edf57dbc0deb4462135f587d7e035359))
* **pipeline-app:** don't append to base .bashrc; set flyctl PATH via ENV ([763cecc](https://bitbucket.org/labor-digital/labor-factory-app/commits/763cecc0ef89348ec1fe955248e7c94779856d29))
* **pipeline-app:** main form fetches /api/seeds (unified list) not /api/templates ([252cb3e](https://bitbucket.org/labor-digital/labor-factory-app/commits/252cb3eb5a1ac001a55c4f4733921840ce7bb1ab))
* **pipeline-app:** read runtime secrets via $env/dynamic/private ([861a502](https://bitbucket.org/labor-digital/labor-factory-app/commits/861a502802cdb2fc3734fc0f7d187c025703c86d))
* **pipeline-app:** rewrite fly.toml primary_region after scaffold ([7c7301a](https://bitbucket.org/labor-digital/labor-factory-app/commits/7c7301a4e6654ce4250c9b0ae60a7bb9ae921725))
* **pipeline-app:** send frontendBase to POST /tenants ([00b18aa](https://bitbucket.org/labor-digital/labor-factory-app/commits/00b18aa223c55ee9fd5959469bca62ce41644364))
* **pipeline-app:** serve on PORT 8000 to match the node22 base EXPOSE ([14d928c](https://bitbucket.org/labor-digital/labor-factory-app/commits/14d928c1610d59cc3fb60e61a241d49c3d181de6))
* **pipeline-app:** stagingPhase reads STAGING_API_TOKEN via $env/dynamic/private, not process.env ([0775ed9](https://bitbucket.org/labor-digital/labor-factory-app/commits/0775ed91ccf7eaca06315530831ec07a4a9cb39c))
* **pipeline-app:** strip comments from .env.template for ECR build ([be3dc43](https://bitbucket.org/labor-digital/labor-factory-app/commits/be3dc43df9df9edd1da500b27f49aa2b4d4597d0))
* **pipeline-app:** target=staging takes precedence over deploymentMode + correct token gate ([024a4d7](https://bitbucket.org/labor-digital/labor-factory-app/commits/024a4d77441b9c7b157ba0cdf90db888e8075681))
* **pipeline-app:** treat seed.json.core_version as authoritative ([1493d31](https://bitbucket.org/labor-digital/labor-factory-app/commits/1493d314a870d249342a02fc1117b6d63aea2dae))
* **pipeline-app:** use per-tenant subpath base for staging deploys ([3d547a1](https://bitbucket.org/labor-digital/labor-factory-app/commits/3d547a14e21a8bb9a1039a42c9d15aef84b574a2))
* **pipeline:** correct npm/composer constraints for the published layers ([557d6a3](https://bitbucket.org/labor-digital/labor-factory-app/commits/557d6a3928a24dda87023a05eb0fd731e45ede12))
* **responsive:** working mobile menu + breakout/grid overflow fixes ([7ec2125](https://bitbucket.org/labor-digital/labor-factory-app/commits/7ec21255a9fa6c6b4da90e14b9eac30e34bf52ee))
* **seeds:** align aurum-bau demo hero with reworked hero schema ([42594d8](https://bitbucket.org/labor-digital/labor-factory-app/commits/42594d81b33626b82388df83a1557e6596d1e0f9))
* **staging:** pass TYPO3_API_BASE_URL to local nuxt build env ([4ee268f](https://bitbucket.org/labor-digital/labor-factory-app/commits/4ee268ff8bf198c91ec3204179c167d70c82183e))
* **typo3-extension:** bulletproof child-table self-heal via try-ALTER pattern ([7791194](https://bitbucket.org/labor-digital/labor-factory-app/commits/77911947c392d41b5512b533d83be795d44293cd))
* **typo3-extension:** cta buttons collection matches the standard button schema ([bb8db85](https://bitbucket.org/labor-digital/labor-factory-app/commits/bb8db85431328a8c3b6f5354e9660c90efb3d2a0))
* **typo3-extension:** handle backticked reserved-word columns in self-heal ([6acfed8](https://bitbucket.org/labor-digital/labor-factory-app/commits/6acfed85c6e9717a77aad64fd335a4693b7d9d75))
* **typo3-extension:** introspect columns via information_schema, not ADD COLUMN IF NOT EXISTS ([56bdcad](https://bitbucket.org/labor-digital/labor-factory-app/commits/56bdcadf9d46ed2dc0331ce8212f2d02b5344f1a))
* **typo3-extension:** quote Footer config label containing colons ([d56be0e](https://bitbucket.org/labor-digital/labor-factory-app/commits/d56be0ebe11234d2a4c0a4526823d32a4f8c3310))
* **typo3-extension:** quote variant labels containing commas ([34caacc](https://bitbucket.org/labor-digital/labor-factory-app/commits/34caacc53f1484add993f35634c859bc516005e0)), closes [#1146](https://bitbucket.org/labor-digital/labor-factory-app/issue/1146)
* **typo3-extension:** rename `size` identifier to avoid content-blocks reservation ([f34ea73](https://bitbucket.org/labor-digital/labor-factory-app/commits/f34ea7307b1317ab69f0ad7faff15d0bb09fb1a7))
* **typo3-extension:** rename `size` to `button_size` in SeedData.yaml defaults ([c622849](https://bitbucket.org/labor-digital/labor-factory-app/commits/c622849bbe9373af0dc660fa3a5e47461d46f4a0))
* **typo3-extension:** self-heal child-table columns in TenantContentSeeder ([1d05970](https://bitbucket.org/labor-digital/labor-factory-app/commits/1d05970b0ef6c4e0425d8ba6ae411e70ff865978))
* **typo3-multitenant-api:** allow factory-core ^0.10 ([4e75b03](https://bitbucket.org/labor-digital/labor-factory-app/commits/4e75b039a0c3711df34c9ae62d21e23c0e701cfc)), closes [#018](https://bitbucket.org/labor-digital/labor-factory-app/issue/018)


### Features

* **ai-layer:** agent-drivable dev harness (DL [#024](https://bitbucket.org/labor-digital/labor-factory-app/issues/024)) ([6135070](https://bitbucket.org/labor-digital/labor-factory-app/commits/61350707c5926d0f3dce8f32e445f159d0d97d56))
* **ai-layer:** guided news creation + usable records surface (DL [#023](https://bitbucket.org/labor-digital/labor-factory-app/issues/023)) ([ed9bb03](https://bitbucket.org/labor-digital/labor-factory-app/commits/ed9bb03baf1f81e90ef6dad30d24bc44d9e2c38c))
* **ai-layer:** wire local frontend + fix cross-origin broker calls ([0e4cca8](https://bitbucket.org/labor-digital/labor-factory-app/commits/0e4cca809e5b5c5d19f5fe604407428cd04ca2f7))
* apply seed records via multitenant API path ([f853af4](https://bitbucket.org/labor-digital/labor-factory-app/commits/f853af4678d43d2bde32d58c7b58e374f2faf8d4))
* **cms-free:** AI editor layer — records, SEO, pages panel, nav ([6362fdb](https://bitbucket.org/labor-digital/labor-factory-app/commits/6362fdb06bb753840926bcba5b7df164781c7856)), closes [#020](https://bitbucket.org/labor-digital/labor-factory-app/issue/020) [#021](https://bitbucket.org/labor-digital/labor-factory-app/issue/021)
* **content-blocks:** adopt Claude-design content blocks, retire NuxtUI ones (DL [#018](https://bitbucket.org/labor-digital/labor-factory-app/issues/018)) ([93ccfc5](https://bitbucket.org/labor-digital/labor-factory-app/commits/93ccfc5e8b1df8fda2d6ae82cd62650ad74b8b97))
* **factory-ai-layer:** publish as a private npm package ([09800c7](https://bitbucket.org/labor-digital/labor-factory-app/commits/09800c70a894d5fc4fa894176e2a962f004859c1))
* **factory-core:** --language option on factory:tenant:provision ([7054355](https://bitbucket.org/labor-digital/labor-factory-app/commits/7054355bc4b00d9dd15e9e18c6ca84c1559db2fc))
* **factory-core,factory-multitenant-api:** tenant lifecycle — content seed + retire (DL [#016](https://bitbucket.org/labor-digital/labor-factory-app/issues/016)) ([cc1d33b](https://bitbucket.org/labor-digital/labor-factory-app/commits/cc1d33bdbd8571e5fa553fbc5045ad83f7adb37c))
* **factory-core:** add --base option to factory:tenant:provision ([01a7828](https://bitbucket.org/labor-digital/labor-factory-app/commits/01a78281ee49c970ba8d23d2f2deb70954e7c57f))
* **factory-core:** add FactoryComponentRegistry::invalidate and factory:seed:reset ([f453e6a](https://bitbucket.org/labor-digital/labor-factory-app/commits/f453e6ac7302f24ff86bef31add24bb50ccb7906)), closes [#013](https://bitbucket.org/labor-digital/labor-factory-app/issue/013) [#014](https://bitbucket.org/labor-digital/labor-factory-app/issue/014)
* **factory-core:** CORS middleware allowing the site's frontendBase ([69148ef](https://bitbucket.org/labor-digital/labor-factory-app/commits/69148ef283fd59efaaa8240af8e2911d660ba7d6))
* **factory-core:** TenantContentSeeder supports subpages ([25c0315](https://bitbucket.org/labor-digital/labor-factory-app/commits/25c0315b37c51eefcae97b70ea0113648e9fcce4))
* **factory-core:** write frontendBase into tenant site config ([3d0eec2](https://bitbucket.org/labor-digital/labor-factory-app/commits/3d0eec2aeef9abb0f4dc185835d2d9445252ff14))
* **factory-multitenant-api:** accept optional base in POST /tenants ([373e8e2](https://bitbucket.org/labor-digital/labor-factory-app/commits/373e8e21e501ab6c5794daf9f7931b25e1d249c5))
* **factory-multitenant-api:** accept optional frontendBase in POST /tenants ([8d0526d](https://bitbucket.org/labor-digital/labor-factory-app/commits/8d0526db57650e8b74143e10eddeabd926217447))
* **factory-multitenant-api:** accept pages payload on POST /tenants/{slug}/content ([2aef937](https://bitbucket.org/labor-digital/labor-factory-app/commits/2aef937a7009dc17d5b3e8be2726feca8814cc94))
* **factory-multitenant-api:** forward optional language to provision ([2487c3e](https://bitbucket.org/labor-digital/labor-factory-app/commits/2487c3e392b894b8b0293ea3f688bfded6dfaf62))
* **factory-multitenant-api:** scaffold extension + release wiring ([26704c1](https://bitbucket.org/labor-digital/labor-factory-app/commits/26704c195aba1c7ed99fff082748d8bb72e60311)), closes [#013](https://bitbucket.org/labor-digital/labor-factory-app/issue/013)
* **factory-multitenant-api:** strip backend base path from generated links ([b600890](https://bitbucket.org/labor-digital/labor-factory-app/commits/b6008907963a3f1f9c9e14ac48ab4a0858802417))
* **factory-nuxt-layer:** export types/ for consumers and prerender records ([ab1e87c](https://bitbucket.org/labor-digital/labor-factory-app/commits/ab1e87cbfcf09893fd00fb34f9d9145a131e35a4)), closes [#048](https://bitbucket.org/labor-digital/labor-factory-app/issue/048) [#021](https://bitbucket.org/labor-digital/labor-factory-app/issue/021)
* **header:** subpage dropdowns for classic/transparent + mobile arrow accordion ([8f50820](https://bitbucket.org/labor-digital/labor-factory-app/commits/8f50820d4d7f3e55ad67440ec649566607ecda80))
* initial public release of factory-core 0.1.0 ([f3c1d53](https://bitbucket.org/labor-digital/labor-factory-app/commits/f3c1d53c57c00f1761d08f2a6ac2794839477cb8))
* **nuxt-layer:** add Vue components for 11 new content blocks ([92752bb](https://bitbucket.org/labor-digital/labor-factory-app/commits/92752bb91b14d84d3e79a5d3904b55ff67431170))
* **pipeline-app:** auth flow + dashboard IA (DL [#017](https://bitbucket.org/labor-digital/labor-factory-app/issues/017) phase C) ([eb2aa53](https://bitbucket.org/labor-digital/labor-factory-app/commits/eb2aa537179c78e5ab274b7903e245cdc47b110b))
* **pipeline-app:** bake factory-core into the image (drop runtime repo token) ([2503c3b](https://bitbucket.org/labor-digital/labor-factory-app/commits/2503c3b64873ec8a18d420e93be1a31481b0b56e)), closes [#022](https://bitbucket.org/labor-digital/labor-factory-app/issue/022)
* **pipeline-app:** create tenant admin directly (no invite email) ([49af27b](https://bitbucket.org/labor-digital/labor-factory-app/commits/49af27b7773c9a6cb174cc38e47c96ad1eb9ea00))
* **pipeline-app:** default client Supabase URL/anon from app env; require admin email ([979dc85](https://bitbucket.org/labor-digital/labor-factory-app/commits/979dc8525894cfe1936d016a0829a909a4b098e3))
* **pipeline-app:** default stagingApiBaseUrl to lab-fac-mul.labor.show ([6641018](https://bitbucket.org/labor-digital/labor-factory-app/commits/6641018bd8369671ab5f1f7734b5d9fbdfe4f51e)), closes [#013](https://bitbucket.org/labor-digital/labor-factory-app/issue/013)
* **pipeline-app:** dependency-light /healthz endpoint for ECS/ALB ([20687a5](https://bitbucket.org/labor-digital/labor-factory-app/commits/20687a5e373a7d7499fdbcafa734184c8b5466d8))
* **pipeline-app:** email one-time-code login (replace magic link) ([0e2b413](https://bitbucket.org/labor-digital/labor-factory-app/commits/0e2b4130102030fbc08c6861ffd4cd58bc2596e9))
* **pipeline-app:** environment selector + staging deploy + version compat (DL [#015](https://bitbucket.org/labor-digital/labor-factory-app/issues/015)) ([ed6a36b](https://bitbucket.org/labor-digital/labor-factory-app/commits/ed6a36b984e207cda49d8494b5e5422f506a209b))
* **pipeline-app:** extend TenantSpec with identifier, frontendBase, websiteTitle ([da057cc](https://bitbucket.org/labor-digital/labor-factory-app/commits/da057ccac298e3833e5d4acaa747291f6b791fe9))
* **pipeline-app:** finalize seed translator for page-manifest + brand tokens ([199e12e](https://bitbucket.org/labor-digital/labor-factory-app/commits/199e12eab23672aa3b7a6314188c76dab11fab53))
* **pipeline-app:** forward seed.language to POST /tenants ([65af3f2](https://bitbucket.org/labor-digital/labor-factory-app/commits/65af3f2610cea9511e10479c7c7cc8738b38c301))
* **pipeline-app:** generate record rows from record-listing sections ([d8b66a4](https://bitbucket.org/labor-digital/labor-factory-app/commits/d8b66a42d97549ec4b06380b7b044f61744777fc))
* **pipeline-app:** generate seeds from JSON via Mistral ([8f1f2a0](https://bitbucket.org/labor-digital/labor-factory-app/commits/8f1f2a01e6e7824dccf4aeea72c10778f53e6d58))
* **pipeline-app:** high-contrast theme with pastel section accents ([0d0b466](https://bitbucket.org/labor-digital/labor-factory-app/commits/0d0b4664f976a5b8f9bd0160c91df985ca46ad46))
* **pipeline-app:** monorepo runtime access for full provisioning (DL [#022](https://bitbucket.org/labor-digital/labor-factory-app/issues/022) Q4) ([a901ab7](https://bitbucket.org/labor-digital/labor-factory-app/commits/a901ab79fed94f43e3d201c01a6d67be0a0ca903))
* **pipeline-app:** port factory:* commands in-process, drop labCliBin ([bd245bf](https://bitbucket.org/labor-digital/labor-factory-app/commits/bd245bfe262ef11377f863ce1730a6a5e4e9489c))
* **pipeline-app:** run locally via `lab up` (compose + Doppler + HTTPS) ([7a86800](https://bitbucket.org/labor-digital/labor-factory-app/commits/7a86800f8d66b237dfd0eaa59b467aacc2f68cc1))
* **pipeline-app:** seed edit page at /seeds/[slug] ([aba6c3a](https://bitbucket.org/labor-digital/labor-factory-app/commits/aba6c3a6d79e04fbadfc9c65c477fb069de46b87))
* **pipeline-app:** seed library + fast local reseed (DL [#014](https://bitbucket.org/labor-digital/labor-factory-app/issues/014)) ([54b4947](https://bitbucket.org/labor-digital/labor-factory-app/commits/54b4947db1ca30e7e7e0701dddc9245ef6dfaa18))
* **pipeline-app:** seed pre-fills tenants[] + Fly.io UI in staging mode ([1890b20](https://bitbucket.org/labor-digital/labor-factory-app/commits/1890b2072698eacd09593ccdbb66dd8551cfcf93))
* **pipeline-app:** seed subpages + heckelsmueller-test-1 default tenant ([766b750](https://bitbucket.org/labor-digital/labor-factory-app/commits/766b75036181afced5c0426f61f693569c97ca46))
* **pipeline-app:** serve HTTPS in-container via adapter handler + LABOR certs ([504e0e6](https://bitbucket.org/labor-digital/labor-factory-app/commits/504e0e6757882ec3a3196ef0803bcd517ba469c5))
* **pipeline-app:** show phase 4 + relabel as "Staging Deploy" in staging mode ([2118087](https://bitbucket.org/labor-digital/labor-factory-app/commits/211808722a3012dbb92813415acf987bb215cf8a))
* **pipeline-app:** slim staging deploy wizard with preflight checks ([696472e](https://bitbucket.org/labor-digital/labor-factory-app/commits/696472e48d291d7dc5898d229bd31ec75d082f0b))
* **pipeline-app:** stagingPhase creates Fly app + sets TYPO3_API_BASE_URL secret ([22c0a09](https://bitbucket.org/labor-digital/labor-factory-app/commits/22c0a0914b3dd58b04f159df7a2967eb32e466b6))
* **pipeline-app:** supabase backend + schema (DL [#017](https://bitbucket.org/labor-digital/labor-factory-app/issues/017) phase A-B) ([de243bf](https://bitbucket.org/labor-digital/labor-factory-app/commits/de243bf748e46b76fd1e6ca7ebd4ba04e1fffbef))
* **pipeline-app:** update-mode for staging + automatic content seeding (DL [#016](https://bitbucket.org/labor-digital/labor-factory-app/issues/016)) ([60fa006](https://bitbucket.org/labor-digital/labor-factory-app/commits/60fa006501b062c38394bf229b22623f7d3ac877))
* **pipeline:** wire private-npm consume mode for the two layers ([580480c](https://bitbucket.org/labor-digital/labor-factory-app/commits/580480c71080b437a611cff22c5178ea2b205038))
* **playground:** add theme settings page and dark-preview toggle ([f21c3dc](https://bitbucket.org/labor-digital/labor-factory-app/commits/f21c3dc4b08e9577a05c76390ff0fa82b158564e))
* **playground:** desktop/tablet/mobile viewport switcher in the preview ([8ae140b](https://bitbucket.org/labor-digital/labor-factory-app/commits/8ae140b412a84ecf46606d5a603d7f6417f8cd35))
* rework component library from Figma spec + per-component AI manifests ([00a910f](https://bitbucket.org/labor-digital/labor-factory-app/commits/00a910f71a486970c266de2f5f9c083501e58752)), closes [#023](https://bitbucket.org/labor-digital/labor-factory-app/issue/023) [#024](https://bitbucket.org/labor-digital/labor-factory-app/issue/024)
* **seed:** heckelsmüller declares core_version ^0.2 + read seed file directly ([d992c1c](https://bitbucket.org/labor-digital/labor-factory-app/commits/d992c1c722cf8c1b9ded7e6fa12c5d634cf082f8))
* **shared-tenant:** wire local E2E test bed for factory-multitenant-api ([8c81834](https://bitbucket.org/labor-digital/labor-factory-app/commits/8c81834416588bd810e6518a13a3c4358f5c40d2)), closes [#013](https://bitbucket.org/labor-digital/labor-factory-app/issue/013) [#013](https://bitbucket.org/labor-digital/labor-factory-app/issue/013)
* **staging:** build Nuxt locally + deploy thin image to Fly + default region fra ([461ab52](https://bitbucket.org/labor-digital/labor-factory-app/commits/461ab52598140f20fed8e49398a404ab7ee6993b))
* **typo3-extension:** add 11 content elements from claude-design expansion ([12f2ea1](https://bitbucket.org/labor-digital/labor-factory-app/commits/12f2ea1eb1889bdab14dfe725ec7d400888e69cb))



## [0.12.0](https://github.com/labor-digital/lab-factory/compare/factory-core-v0.11.3...factory-core-v0.12.0) (2026-05-20)


### Features

* claude-design 11-block expansion + staging wizard polish ([3203dd8](https://github.com/labor-digital/lab-factory/commit/3203dd88f84ec1b06ab42a92f877409f04849ed2))
* **typo3-extension:** add 11 content elements from claude-design expansion ([12f2ea1](https://github.com/labor-digital/lab-factory/commit/12f2ea1eb1889bdab14dfe725ec7d400888e69cb))

## [0.11.3](https://github.com/labor-digital/lab-factory/compare/factory-core-v0.11.2...factory-core-v0.11.3) (2026-05-15)


### Bug Fixes

* **typo3-extension:** rename `size` to `button_size` in SeedData.yaml defaults ([c622849](https://github.com/labor-digital/lab-factory/commit/c622849bbe9373af0dc660fa3a5e47461d46f4a0))

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
