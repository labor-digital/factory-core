# ReferenceList — Designer Brief

## Source

Figma: [node 47:788](https://www.figma.com/design/XSf9bqe4tA6IwvRBYrNlUx?node-id=47-788) — "Teaser Slider – Left – Desktop" (Heckelsmüller).

## Layout variants

- **Grid** (default): 2/3/4 column responsive grid. On `md` breakpoint collapses to 2 columns regardless of setting. Matches the Figma "Referenzen" row.
- **Slider**: Splide carousel showing the same cards. Arrows render as 32px white pills with subtle drop-shadow, positioned bottom-right (Figma exact). Pagination dots off by default (the header's "Alle anzeigen" button is the primary navigation affordance).

## Fields

### Header (mirrors TextSlider)
- `eyebrow`, `eyebrow_decoration` (bar / dot / none), `eyebrow_color`
- `title` (HTML `<strong>` allowed — renders in accent color)
- `description`
- `text_align` (left / center / right) — when `left`, buttons snap to the top-right on `lg:` in a `justify-between` row; other alignments stack buttons under text
- `buttons` (Collection — full Button schema, typically one "Alle anzeigen" link button)

### Layout
- `layout` (grid / slider)
- `columns` (2 / 3 / 4) — applies to both grid and slider (slider shows N cards at a time on lg)
- `aspect_ratio` — applies to the card image area (default 4/3)
- `show_arrows` / `show_pagination` / `autoplay` / `loop` — only rendered when layout=slider

### Record source
- `record_type` — dropdown populated dynamically from `active_record_types` in `factory.json` via `RecordTypeItemsProc`. New record types added to `factory.json` appear here without editing this config.
- `selection_mode` — `manual` | `auto`:
  - **manual** shows `records_<slug>` Relation pickers (one per active record type; the correct one is revealed via displayCond on the matching `record_type` value).
  - **auto** shows `auto_storage_pid` (folder) + `auto_filter_listing_type` (any / buy / rent) + `auto_limit` (default 6) + `auto_order` (newest / oldest / title).

## Backend rendering path

A TypoScript DataProcessor (`ReferenceListProcessor`) resolves the selected UIDs — either reading the relation field (manual) or running a query (auto) — and serialises each record to the JSON wire shape consumed by `BaseProperty.vue` (identical keys to the raw property table, plus a `_record_type` discriminator). The Nuxt frontend then dispatches each record to `T3RecordFactory<Type>` via `resolveDynamicComponent`.

## Future work

- Category / tag filter on auto mode (beyond listing_type).
- Per-card aspect override.
- Masonry layout option.
- "Load more" progressive disclosure for auto mode with limit > N.
