# RecordEvent — Designer Brief

## What it is

A dated event: open day, info evening, trade fair. Like every record type it **is
a page** (DL #030) at `/event/<slug>`, whose body accepts any active Content Block.

## Variants

- **card** — 347×220 image, then the "when" line in the primary colour, bold
  title, place line, 3-line excerpt.
- **header** — when + large light title + place, 16:9 hero, a fact row (Ort /
  Eintritt), lead-in excerpt, then the registration button. **The event body
  renders below this** via `FactoryPageRenderer`.

## The "when" line

Composed in one place (`whenLine`) and shared by both variants, so the card and
the page can never disagree. It covers four real cases:

| Case | Renders as |
| --- | --- |
| all-day, single day | `12. September 2026` |
| timed, with an end | `12. September 2026, 14:00 – 18:00 Uhr` |
| timed, no end | `12. September 2026, 14:00 Uhr` |
| spans several days | `07. November 2026 – 09. November 2026` |

Multi-day is **derived** from start/end, not a stored flag — one less field for an
editor to contradict.

## Fields

- `title` (required) — names the event, not the date.
- `slug` (required) — auto from title.
- `excerpt` — 1–2 sentences for the card.
- `start_date` (required) — datetime, and the sort key for event lists.
- `end_date` — empty for a single-moment event.
- `all_day` — checkbox; hides the times and shows the date alone.
- `location_name`, `address_street`, `address_zip`, `address_city` — joined into
  the place line, degrading gracefully when only the city is known.
- `registration_link` — renders the "Anmelden" button. No link, no button.
- `price` — **free text**, because "kostenlos", "ab 12 €" and "12 € / 8 €
  reduziert" are all real answers.
- `hero_image` — card image and page hero.
- `content_elements` — the body. Any active block.

## Notes for design

- Dates are formatted via `Intl.DateTimeFormat('de-DE')`, so the pattern follows
  the locale rather than the design.
- TYPO3 emits `YYYY-MM-DD HH:MM:SS` while the AI editor writes ISO — the parser
  normalises both, so a design can assume a real date is always available.

## Future work

- Past/upcoming split in listings (needs a query filter on `start_date`).
- Recurring events. Today a series is several records, deliberately: each
  occurrence needs its own registration and its own URL.
