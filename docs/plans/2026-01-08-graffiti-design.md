# Graffiti Plugin Design

A WordPress plugin that allows unauthenticated site visitors to leave drawing-based notes between paragraphs of posts and pages.

## Core Concept

Visitors can leave small drawings (graffiti) between paragraphs of WordPress content - like leaving marks on a public wall. All graffiti is public and visible to everyone.

## User Experience

### Visitor Flow

1. Visitor reads a post or page
2. Subtle "add graffiti" indicators appear between paragraphs (faint line or "+" icon on hover)
3. Clicking opens a drawing canvas (~400x200px) with minimal tools:
   - Single brush (fixed size)
   - 5-6 color swatches (black, red, blue, green, yellow, white)
   - Eraser
   - Clear/Cancel/Submit buttons
4. On submit, the drawing immediately appears in that gap
5. Other visitors see all graffiti inline as they scroll through content

### Display

- Drawings appear as images between paragraphs
- Multiple drawings in the same gap stack vertically
- Each drawing shows as a simple card - just the image, no attribution (anonymous)

### Friction-Free

- No login required
- No CAPTCHA
- No email verification
- Just draw and post

## Moderation

- **Post-moderation**: Notes appear immediately, admins delete inappropriate content as needed
- **No spam prevention**: Keep the experience frictionless

## Admin Features

### Custom Post Type Registration

- Post type slug: `graffiti`
- Label: "Graffiti" (plural: "Graffiti")
- Menu icon: dashicons-art or similar
- Appears in admin sidebar
- `public` = false (not directly viewable as standalone pages)
- `show_ui` = true (visible in admin)

### Admin List Table Columns

- Thumbnail preview of the drawing
- Parent post/page (linked)
- Paragraph position
- IP address
- Date submitted
- Quick "Delete" action

### Filtering/Sorting

- Filter by parent post/page
- Sort by date (default: newest first)
- Search by IP address

### Single Graffiti Edit Screen

- Minimal - just displays:
  - The drawing (full size)
  - Parent post link
  - Metadata (IP, timestamp, paragraph index)
  - Delete button
- No editing of drawings (just view or delete)

### Settings Page

- Under Settings > Graffiti (or as submenu under the Graffiti CPT)
- Single toggle: Enable/Disable graffiti site-wide

### Configuration

- Global enable/disable toggle (site-wide)
- No per-page settings

### Notifications

- Dashboard only (no email notifications)

## Technical Architecture

### Data Storage - Custom Post Type

- Post type: `graffiti`
- Post meta:
  - `_graffiti_post_id` - which post/page the graffiti belongs to
  - `_graffiti_paragraph_index` - which gap (0 = before first paragraph, 1 = after first, etc.)
  - `_graffiti_ip_address` - visitor IP for moderation reference
- The drawing stored as the post's featured image (uploaded to media library)

### Benefits of CPT Approach

- Native WordPress admin UI for viewing/managing graffiti
- Built-in bulk actions (delete, etc.)
- Searchable, sortable, filterable out of the box
- Standard WordPress hooks for extensibility
- Easier backup/export via standard WordPress tools

### Backend

- REST API endpoint: `POST /wp-json/graffiti/v1/drawings` (creates the CPT entry)
- Uses `wp_insert_post()` under the hood
- Uploads drawing to media library and attaches as featured image

### Rendering

- Filter `the_content` to query and inject graffiti posts between paragraphs

## Frontend Interaction

### Paragraph Detection

- On page load, JavaScript scans `.entry-content` (or configurable container) for block-level elements (p, h1-h6, ul, ol, blockquote, figure, etc.)
- Creates invisible "drop zones" between each element

### Hover Triggers

- On hover between paragraphs, a subtle indicator appears (thin line with centered "+" icon)
- Clicking the trigger opens the drawing modal
- Trigger fades out when not hovered (unobtrusive)

### Drawing Modal

- Centered overlay with semi-transparent backdrop
- Canvas element (~400x200px) with white background
- Toolbar below canvas:
  - Color swatches (6 circles: black, red, blue, green, yellow, white)
  - Eraser toggle
  - Clear button (resets canvas)
  - Cancel button (closes modal)
  - Submit button (saves drawing)
- Touch-friendly for mobile (pointer events)

### Drawing Mechanics

- Mouse/touch down begins stroke
- Mouse/touch move draws line
- Mouse/touch up ends stroke
- Single brush size (~4px)
- Eraser uses destination-out composite or paints white

### Submission

- Canvas converted to PNG data URL
- POST to REST endpoint with: image data, post ID, paragraph index
- On success: modal closes, drawing appears in the gap immediately
- On error: simple "Something went wrong" message

## Rendering Existing Graffiti

### Server-Side Injection

- Hook into `the_content` filter (priority ~20, after shortcodes/blocks)
- Query graffiti CPT posts where `_graffiti_post_id` matches current post
- Group by `_graffiti_paragraph_index`
- Split content by block-level elements, inject graffiti HTML between them
- Return modified content

### Graffiti Display HTML

```html
<div class="graffiti-cluster" data-paragraph="3">
  <div class="graffiti-item">
    <img src="[media URL]" alt="Visitor graffiti" loading="lazy" />
  </div>
  <div class="graffiti-item">
    <img src="..." alt="Visitor graffiti" loading="lazy" />
  </div>
</div>
```

### Styling

- Graffiti items styled as simple cards (subtle border or shadow)
- Centered within content column
- Small margin above/below
- Multiple items in same gap stack vertically with small gap between
- Responsive: scale down on mobile if needed

### Performance Considerations

- Single query per page (get all graffiti for this post)
- Images lazy-loaded (`loading="lazy"`)
- Consider caching graffiti query results with transients (optional optimization)

## Technical Summary

| Aspect | Decision |
|--------|----------|
| Canvas size | ~400x200 pixels |
| Drawing tools | Single brush, 6 colors, eraser |
| Persistence | Forever until manually deleted |
| Storage | Custom post type + media library |
| Moderation | Post-moderation (immediate publish, admin cleanup) |
| Spam prevention | None (frictionless) |
| Configuration | Global enable/disable only |
