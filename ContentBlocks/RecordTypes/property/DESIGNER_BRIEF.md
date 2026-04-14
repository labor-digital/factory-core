# Property — Designer Brief

## Source

Figma: [node 47:788](https://www.figma.com/design/XSf9bqe4tA6IwvRBYrNlUx?node-id=47-788) — "Teaser Slider – Left – Desktop" (Heckelsmüller).

## Variants

The `Property` record type ships a single Vue component rendered in two variants:

- **card** — the 347×440 card used inside a ReferenceList. Image area 220px with placeholder number overlay, red tag pill, bold title, grey address line, 1px grey divider, 3-column meta row (`FERTIG` / `FLÄCHE` / `EINH.` — small uppercase labels above the values).
- **detail** — the property detail page. Stacks hero image, gallery, teaser, then the free-placeable `content_elements[]` rendered via the normal `T3Ce*` dispatcher.

## Fields

### Common tab
- `title` (required) — appears on the card and detail page.
- `slug` — auto-generated from title.
- `teaser` — 1–3 sentence description used on the detail page; not shown on the card.
- `listing_type` — `buy` | `rent`. Controls whether `price` reads as `€` (total) or `€/Monat`.
- `status` — `new` | `available` | `reserved` | `sold`. Use `sold` for "Vermietet" as well.
- `tag` — one of 7 presets that render as the red pill on the card.
- `tag_custom` — free-text override. When set, the card shows this instead of the `tag` value. Used for multi-category labels like `"NEUBAU & UMBAU"`.
- `address_{street,zip,city,country}` — the card shows `{street}, {zip} {city}` on one line, falling back to just `{city}` when only the city is known.
- `price` — nullable. **When empty, the detail page shows "Preis auf Anfrage".**
- `price_type` — `total` | `monthly`.
- `area_m2`, `rooms`, `units`, `year_built`, `year_completed` — numeric. The card renders:
  - `FERTIG` → `year_completed`
  - `FLÄCHE` → `ca. {area_m2} m²`
  - `EINH.` → `{units} WE` (only shown when `units > 1`)
- `hero_image` — single image used by both card and detail hero.
- `gallery` — detail page only.

### Content tab
- `content_elements` — **inline `tt_content` relation** (IRRE via `foreign_field` / `foreign_table_field`, the EXT:news pattern). Editors drop any active Content Block (PageHero, PageSection, TextSlider, …) into a property's detail and the frontend renders them stacked using the standard `T3Ce*` dispatcher.

## Future work

- Per-property map coordinates + Google Maps / OpenStreetMap embed.
- Energy certificate / Energieausweis fields (Energieeffizienzklasse, Baujahr der Heizung …). Mandatory in DE for rental listings — v2 concern.
- Attachment field for PDFs (Exposé download).
- Multi-broker support: `broker` Relation to a future `Person` record type.
