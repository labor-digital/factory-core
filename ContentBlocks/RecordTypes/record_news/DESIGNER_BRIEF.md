# RecordNews — Designer Brief

## What it is

A news post. Like every record type it **is a page** (DL #030): it has a slug,
lives at `/news/<slug>`, and its body accepts any active Content Block. The
component here supplies only the *typed* parts — the card and the page header.

## Variants

- **card** — 347×220 image area, then a meta row (category in the primary colour,
  then the formatted date), bold title, 3-line excerpt. Used by RecordList and
  Teaser listings.
- **header** — the top of the post's page: category + date, large light title,
  byline, 16:9 hero, lead-in excerpt, then the gallery. **The post body renders
  below this**, dispatched by `FactoryPageRenderer` — do not design body sections
  here, design them as blocks.

## Fields

- `title` (required) — headline style, no trailing period.
- `slug` (required) — auto from title; the URL segment.
- `excerpt` — 1–2 sentences. Appears on the card **and** is the SEO description
  fallback, so it has to make sense out of context.
- `date` (required) — the display date **and** the sort key for news lists.
- `author` — optional byline, rendered as "von {author}".
- `category` — `company` | `projects` | `press` | `events`. Rendered as a German
  label (Unternehmen / Projekte / Presse / Termine).
- `hero_image` — card image and page hero. One image.
- `gallery` — page only, max 30.
- `content_elements` — the body. Any active block.

## Notes for design

- The date is formatted with `Intl.DateTimeFormat('de-DE')`, so it follows the
  locale rather than a hardcoded pattern — a design that assumes "14. Juni 2026"
  will read differently under another locale.
- Cards fall back to a seeded placeholder image, deterministic per record, so a
  post without a hero still fills its grid cell instead of collapsing.

## Future work

- Related posts (a Relation to other news records).
- Tags in addition to the single category, once a client needs filtering.
