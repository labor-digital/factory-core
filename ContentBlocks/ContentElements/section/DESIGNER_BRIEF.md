# PageSection — Designer Brief

A flexible mid-page content section: **eyebrow / heading / text / buttons** paired with a **media gallery** (images or videos). Supports multiple media per section, selectable aspect ratio, optional lightbox, and a section background.

Use PageSection for narrative blocks like "Our vision", "About us", or a feature showcase with imagery.

For full-bleed heroes, use **PageHero** instead. For a large card layout with many small cards, use **PageGrid**.

## Text block

| Option | Values | Notes |
|---|---|---|
| **Icon** | Iconify name (optional) | e.g. `i-lucide-home`. Rendered above the eyebrow. |
| **Eyebrow** | short text (optional) | Small uppercase label |
| **Eyebrow decoration** | `none` / `bar` / `dot` | Visual marker next to the eyebrow |
| **Eyebrow / accent color** | 7 semantic colors | Also drives the `<strong>` accent inside the title |
| **Title** | text (supports inline `<strong>…</strong>`) | `<strong>` renders in the accent color |
| **Description** | paragraph text | Preserves line breaks |
| **Buttons** | 0..n | Shared Button component (see PageHero brief) |

## Layout

| Option | Values | Default | Notes |
|---|---|---|---|
| **Media position** | `left` / `right` / `bottom` / `top` | `right` | Relative to the text column |
| **Media width** | `narrow` / `normal` / `wide` | `normal` | Column split when media is left/right: narrow = 1/3 media · 2/3 text · wide = 2/3 media · 1/3 text |
| **Text alignment** | `left` / `center` / `right` | `left` | Alignment of eyebrow/title/text/buttons |
| **Text max width** | `narrow` / `normal` / `wide` / `full` | `normal` | Applied when media is above/below text |

On mobile, the layout stacks vertically regardless of `media_position`.

## Section background

| Option | Values | Default | Notes |
|---|---|---|---|
| **Background** | `none` / `color` / `image` | `none` | Full-bleed section background |
| **Background color** | 7 semantic colors | `neutral` | Shown only when type = color |
| **Background image** | jpg/png/webp/svg | — | Shown only when type = image |
| **Background overlay** | `none` / `dark` / `light` | `none` | Scrim for readability |

Video backgrounds are **not** supported at the section level (use PageHero for that). If an editor needs moving imagery in-section, add it as a **video media item** inside the gallery.

## Media gallery

Each media item is either an **image** or a **video**.

| Option | Values | Notes |
|---|---|---|
| **Type** | `image` / `video` | |
| **Image** | jpg/png/webp/svg | When type = image |
| **Video (upload)** | mp4/webm | When type = video |
| **Video URL** | text (optional) | Alternative to upload |
| **Poster** | jpg/png/webp (optional) | Still image for a video |
| **Alt** | text | Accessibility label |
| **Caption** | text | Rendered below or overlaid |

Gallery-level options (applies to all items):

| Option | Values | Default | Notes |
|---|---|---|---|
| **Aspect ratio** | `auto / 1:1 / 16:9 / 4:3 / 3:2 / 21:9 / 3:4 / 2:3` | `1:1` | The frame the image crops into |
| **Caption position** | `below` / `overlay` / `none` | `below` | |
| **Show arrows** | on / off | off | Only when >1 item |
| **Show pagination** | on / off | on | Only when >1 item |
| **Enable lightbox** | on / off | on | Click any media to open full-screen modal |

## Rules for designers

1. **Title accent** is set via `<strong>` in the title field (editor wraps the part that should be emphasised). Use it sparingly — at most one line of emphasis per title.
2. **Don't design per-image aspect ratios** — the ratio is section-wide. If the client needs mixed ratios, they need multiple PageSections.
3. **Lightbox is on by default.** Assume every image in a section can be enlarged. Design captions short enough to overlay or sit below comfortably.
4. **Media width only applies to left/right positions.** For `top`/`bottom`, media is always full-width of the content container.
5. **Don't stack two PageSections with the same background** — it looks like one section. Alternate or insert a neutral section in between.
6. **All colors come from the theme.** 7 semantic tokens: primary, secondary, neutral, success, info, warning, error.

## Figma variants to prepare

For each client, usually enough:
- 1 per **media position** you expect (at minimum: `right`, `left`, `bottom`)
- 1 per **aspect ratio** if you rely on a non-default one
- 1 background variant if the section will have a color or image background
- 1 multi-image example with lightbox indicator (cursor/zoom affordance)

## What is NOT configurable (yet)

Ask before designing these in — each requires a schema change:

- Per-image aspect ratio overrides
- Independent decoration color (separate from accent/eyebrow color)
- Icon size override (currently fixed at `size-10`)
- Video as the section background
- Gallery layout variations (grid, mosaic, masonry)
- Per-section `padding` override (currently fixed `py-16 sm:py-20 lg:py-24`)
- Auto-play slider for gallery (currently manual only)
