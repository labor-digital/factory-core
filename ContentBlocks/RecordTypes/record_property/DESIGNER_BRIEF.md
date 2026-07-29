# Property — Designer Brief

## Source

Figma: [node 47:788](https://www.figma.com/design/XSf9bqe4tA6IwvRBYrNlUx?node-id=47-788) — "Teaser Slider – Left – Desktop" (Heckelsmüller).

## Variants

The `RecordProperty` record type ships a single Vue component rendered in two variants. It renders the record's own page at `/property/<slug>` — the component supplies the header, `FactoryPageRenderer` supplies the body:

- **card** — the 347×440 card used inside a ReferenceList. Image area 220px with placeholder number overlay, red tag pill, bold title, grey address line, 1px grey divider, 3-column meta row (`FERTIG` / `FLÄCHE` / `EINH.` — small uppercase labels above the values).
- **header** — the top of the property's page. Stacks hero image, meta row, teaser and gallery. It does **not** render `content_elements[]`: a record is a page, so `FactoryPageRenderer` dispatches the body exactly as it does a normal page's (DL #030).

## Fields

### Common tab
- `title` (required) — appears on the card and the page header.
- `slug` — auto-generated from title.
- `teaser` — 1–3 sentence description used on the page header; not shown on the card.
- `listing_type` — `buy` | `rent`. Controls whether `price` reads as `€` (total) or `€/Monat`.
- `status` — `new` | `available` | `reserved` | `sold`. Use `sold` for "Vermietet" as well.
- `tag` — one of 7 presets that render as the red pill on the card.
- `tag_custom` — free-text override. When set, the card shows this instead of the `tag` value. Used for multi-category labels like `"NEUBAU & UMBAU"`.
- `address_{street,zip,city,country}` — the card shows `{street}, {zip} {city}` on one line, falling back to just `{city}` when only the city is known.
- `price` — nullable. **When empty, the page header shows "Preis auf Anfrage".**
- `price_type` — `total` | `monthly`.
- `area_m2`, `rooms`, `units`, `year_built`, `year_completed` — numeric. The card renders:
  - `FERTIG` → `year_completed`
  - `FLÄCHE` → `ca. {area_m2} m²`
  - `EINH.` → `{units} WE` (only shown when `units > 1`)
- `hero_image` — single image used by both card and page hero.
- `gallery` — page header only, not the card.

### Content tab
- `content_elements` (required on every record type) — the record's **page body**: an inline `tt_content` relation (IRRE via `foreign_field` / `foreign_table_field`, the EXT:news pattern). Editors drop any active Content Block into it and `FactoryPageRenderer` renders them stacked, the same dispatch a page uses.

  The IRRE override is applied generically by `Configuration/TCA/Overrides/factory_record_types.php` to every `tx_factorycore_record_*` table, and the pointer columns are **one shared pair** (`tx_factorycore_record_parent` + `_parent_table`) for all record types — `foreign_table_field` stores the owning table, which is what disambiguates. So a new record type needs no TCA file and no schema change.

## Future work

- Per-property map coordinates + Google Maps / OpenStreetMap embed.
- Energy certificate / Energieausweis fields (Energieeffizienzklasse, Baujahr der Heizung …). Mandatory in DE for rental listings — v2 concern.
- Attachment field for PDFs (Exposé download).
- Multi-broker support: `broker` Relation to a future `Person` record type.
