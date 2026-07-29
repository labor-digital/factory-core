# RecordPerson — Designer Brief

## What it is

One person: team member, contact or author. Like every record type it **is a
page** (DL #030) at `/person/<slug>`, whose body accepts any active Content Block.

Not to be confused with the **Team content element** (`factory_team`), which is a
flat grid of faces defined inline on a page. Use RecordPerson when each person
needs their own page, or when the same person is referenced from several places.

## Variants

- **card** — 4:5 portrait, then name, role in the primary colour, and a 3-line
  excerpt. The team-grid tile.
- **header** — portrait (max 320px column) beside name, role, a contact list
  (E-Mail / Telefon / Mobil, only the ones that are filled), the bio, and the
  links row. **The profile body renders below this** via `FactoryPageRenderer`.

## Fields

- `title` (required) — the **full display name**, e.g. "Dr. Anna Weber". Not the
  role: a design that puts the role where the name goes will show "Bauleitung"
  as the person's name.
- `slug` (required) — auto from title.
- `role` — job title.
- `excerpt` — one line for the card.
- `email`, `phone`, `mobile` — rendered as `mailto:` / `tel:` links.
- `hero_image` — the portrait. Named `hero_image`, **not** `portrait`, because
  every card and page header reads that key and the SEO head uses it for
  `og:image`.
- `bio` — a paragraph for the profile page.
- `links` — collection of `{ label, url }`, max 6. Short labels ("LinkedIn").
- `content_elements` — the body. Any active block.

## Notes for design

- Name and role are deliberately separate fields, not one line, so a grid can
  align roles and a contact block can print the name alone.
- No first/last name split: `labelField` needs a single field, and display names
  are rarely a clean split (titles, double names). Add a sort field only when a
  client actually needs surname ordering.

## Future work

- A `Relation` from Property/News to a person, for "Ihr Ansprechpartner".
- Department/location grouping once a client's team outgrows one grid.
