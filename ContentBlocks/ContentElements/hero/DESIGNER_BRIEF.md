# PageHero — Designer Brief

This document tells designers what is **configurable** in the Page Hero content block and what is **off-limits**. Match your Figma variants to these axes so the implementation can be a straight mapping.

## What the hero is

A full-bleed section placed at the top of a page (or anywhere). It supports **multiple slides** with a built-in slider (Splide.js). Each slide has its own background + content + buttons. Use one slide for a static hero; use multiple for a rotating banner.

## Hero-level options (apply to the whole slider)

| Option | Values | Default | Notes |
|---|---|---|---|
| **Content position (X)** | `left` / `center` / `right` | `center` | Horizontal alignment of text + buttons |
| **Content position (Y)** | `top` / `center` / `bottom` | `center` | Vertical alignment within the hero |
| **Height** | `small` / `medium` / `large` / `fullscreen` | `medium` | small ≈ 360–420px · medium ≈ 480–600px · large ≈ 560–720px · fullscreen = 100vh |
| **Arrows** | on / off | off | Prev/next buttons (only visible with >1 slide) |
| **Pagination** | on / off | on | Dots indicator (only visible with >1 slide) |
| **Autoplay** | on / off | off | |
| **Loop** | on / off | on | Restart after last slide |

## Per-slide options

| Option | Values | Notes |
|---|---|---|
| **Eyebrow** | text, optional | Small uppercase label above the headline |
| **Headline** | text | Main H1/heading |
| **Text** | text, optional | Supporting paragraph |
| **Text color** | `auto` / `light` / `dark` | `auto` picks white on color/image/video backgrounds; override per slide |
| **Background type** | `color` / `image` / `video` | Mutually exclusive |
| **Background color** | one of `primary / secondary / neutral / success / info / warning / error` | Theme color — not a free hex |
| **Background image** | jpg / png / webp / svg | Full-bleed, `object-fit: cover` |
| **Background video** | mp4 / webm (upload) **or** URL | Auto-plays, muted, loop |
| **Overlay** | `none` / `dark` / `light` | A scrim over image/video for readability |
| **Buttons** | 0..n | See Button spec below |

## Button spec (shared across the whole Factory library)

| Option | Values | Notes |
|---|---|---|
| **Label** | text | |
| **Link** | URL / page ref | |
| **Target** | `_self` / `_blank` / `_parent` / `_top` | |
| **Color** | `primary / secondary / neutral / success / info / warning / error` | Theme colors only |
| **Variant** | `solid / outline / soft / subtle / ghost / link` | 6 variants |
| **Size** | `xs / sm / md / lg / xl` | |
| **Icon** | Iconify name (e.g. `i-lucide-arrow-right`) | Optional |
| **Leading / Trailing** | boolean | Icon position |
| **Block** | boolean | Full-width |

## Rules designers must follow

1. **Use theme colors, not hex codes.** If the design needs a new color, update `factory.json > settings.colors` first; don't sneak hex values into components.
2. **Per-slide background is one-of.** Don't design slides with both image + color; pick one and use `overlay` for tint.
3. **Images must be full-bleed.** Don't design bordered / padded hero images — the component crops to cover.
4. **Text color is light or dark only.** No custom slide text colors. If the background needs a different contrast, change the background or add an overlay.
5. **Buttons are the shared Button component.** Don't design one-off button styles inside a hero that don't match the library's 6 variants.
6. **Minimum one slide, maximum is unlimited** but keep it ≤ 5 for UX reasons.
7. **Content sits inside a max-width container (~80rem / 7xl)** with 1.5rem horizontal padding; the background extends to the viewport edges.

## Figma variants you can design

For Figma, create frames for the combinations your client needs. You don't need 9×4 = 36 variants — make:
- 1 per **content position** you expect to use (usually `left-center`, `center-center`, `right-center`)
- 1 per **height preset** if the client cares
- 1 per **background type** (color / image / video with overlay)
- 1 multi-slide example with pagination dots visible

## What is NOT configurable (yet)

Ask before designing these in — they'd require a schema change:

- Fancy transitions (fade/slide/zoom animations between slides) — Splide default slide only
- Per-slide height override — height is hero-level
- Ken-burns / parallax effects
- Scroll-down indicator
- Video poster image
- Slide duration override
- Mute/unmute control for video
- Aspect ratio lock per slide
- Deep-link anchor id per slide
- Theme mode override (light/dark `--ui-*` scope)

If any of these appear in a design, please flag it so we can scope a follow-up.
