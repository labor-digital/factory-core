# TextSlider — Designer Brief

A section with a **text header** (eyebrow / title / description / optional CTAs) and an **image or video slider** below, with dot pagination. Use TextSlider for project showcases, client galleries, or "Latest news" teasers — anywhere a headline block should introduce a swipeable set of visuals.

For an in-section image next to a paragraph, use **PageSection**. For a full-bleed hero, use **PageHero**.

Based on Figma node `47:1219` (Heckelsmüller · "Image Slider – Left – Desktop").

## Header block

| Option | Values | Notes |
|---|---|---|
| **Eyebrow** | short text (optional) | Small uppercase label |
| **Eyebrow decoration** | `none` / `bar` / `dot` | Visual marker next to the eyebrow |
| **Eyebrow / accent color** | 7 semantic colors | Also drives the `<strong>` accent inside the title |
| **Title** | text (supports inline `<strong>…</strong>`) | `<strong>` renders in the accent color |
| **Description** | paragraph text | Preserves line breaks |
| **Header alignment** | `left` / `center` / `right` | Default `left` (Figma variant) |
| **Buttons** | 0..n | Shared Button component. When header is `left`, buttons render top-right opposite the title. When `center`/`right`, they wrap below the text. |

## Slider

| Option | Values | Default | Notes |
|---|---|---|---|
| **Slide aspect ratio** | `auto / 16:9 / 4:3 / 21:9 / 1:1` | `16/9` | Section-wide ratio |
| **Show arrows** | on / off | off | Only when >1 slide |
| **Show pagination** | on / off | on | Track-style dots below the slider |
| **Autoplay** | on / off | off | 6s interval, pauses on hover |
| **Loop** | on / off | on | Continues from first slide after last |

## Slides

Each slide holds one image or video. **Minimum 1 slide required.**

| Option | Values | Notes |
|---|---|---|
| **Type** | `image` / `video` (upload) / `video_url` | |
| **Image** | jpg/png/webp/svg | When type = image |
| **Video (upload)** | mp4/webm | When type = video |
| **Video URL** | text | When type = video_url |
| **Poster** | jpg/png/webp (optional) | Still image for videos |
| **Alt** | text | Accessibility label |
| **Caption** | text | Rendered below the slide |

## Rules for designers

1. **Title accent** via `<strong>` — keep emphasis to one phrase.
2. **Header alignment drives button placement.** Left-aligned pulls buttons to the right of the header row; center/right stacks them under the text.
3. **All slides share the same aspect ratio.** Design each image to crop cleanly into the chosen ratio.
4. **Captions are always below the slide** (no overlay in v1) — keep them to one line.
5. **All colors come from the theme.** 7 semantic tokens: primary, secondary, neutral, success, info, warning, error.

## Figma variants to prepare

Per client, usually enough:
- 1 per **header alignment** you expect (at minimum: `left`)
- 1 per **aspect ratio** if you rely on a non-default one
- 1 example with the trailing-arrow "See all" CTA, 1 without

## What is NOT configurable (yet)

Ask before designing these in — each requires a schema change:

- Per-slide overlay text / headline
- Caption overlay on image
- Thumbnail pagination (dots only for v1)
- Lightbox (use PageSection if lightbox is required)
- Separate slide heights / custom aspect per slide
- Mixed image + video in a single slider with shared controls (works today, but video slides don't autoplay)
