# RecordJob — Designer Brief

## What it is

An open position. Like every record type it **is a page** (DL #030) at
`/job/<slug>`. The actual posting — Aufgaben, Profil, Wir bieten — lives in the
record's **body** as ordinary blocks, not in fields: those sections differ per
client and per role, which is exactly what blocks are for.

## Variants

- **card** — a **text row**, not an image tile: title, a facts line (Umfang ·
  Arbeitsort · Ort · Start), then a 2-line excerpt. Deliberately imageless — a
  stock photo per vacancy reads as a bug.
- **header** — "Offene Stelle" eyebrow, large light title, a fact row (Umfang /
  Arbeitsort / Ort / Start / Gehalt — only what is filled), lead-in excerpt, then
  the apply button. **The posting body renders below this** via
  `FactoryPageRenderer`.

## Fields

- `title` (required) — the position. Follow the client's existing `(m/w/d)`
  convention rather than introducing one.
- `slug` (required) — auto from title.
- `excerpt` — 1–2 sentences for the card.
- `employment_type` — `full` | `part` | `apprentice` | `intern` | `freelance`,
  rendered as Vollzeit / Teilzeit / Ausbildung / Praktikum / Freelance.
- `work_mode` — `onsite` | `hybrid` | `remote` → Vor Ort / Hybrid / Remote.
- `location` — city or site.
- `starts` — **free text**: "ab sofort" is as common as a date.
- `salary_range` — optional. Never invent one.
- `apply_email`, `apply_link` — the **link wins** when both are set. Resolved once
  in the parser so the card and the header always point at the same place.
- `hero_image` — available but unused by the card by design.
- `content_elements` — the posting body. Any active block.

## Notes for design

- The facts line and the fact row come from one helper, so adding a fact changes
  both surfaces at once.
- Close a position by hiding or deleting the record, so its URL stops resolving.
  Editing an old record into a new role leaves the old URL pointing at the new
  job.

## Future work

- `JobPosting` JSON-LD, which needs the body text as `description` — a schema
  builder that can read the record's blocks, not just its fields.
- Application deadline, once a client's process has one.
