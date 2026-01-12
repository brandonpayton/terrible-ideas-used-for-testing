# Anybody Editing Plugin - Design Document

A WordPress plugin that allows any public visitor to edit post content using inline Gutenberg block editing.

## Overview

The plugin enables wiki-style editing for WordPress posts. Site admins opt-in specific posts for public editing. Visitors see edit buttons on hoverable/focusable elements and can edit content inline using the Gutenberg block editor. Changes publish immediately with WordPress revisions providing rollback capability.

## Core Decisions

| Aspect | Decision |
|--------|----------|
| Publish model | Immediate (no moderation queue) |
| Editor | Gutenberg block editor, inline per block |
| Editable scope | Title, content, excerpt, featured image, categories, tags |
| Post selection | Opt-in per post via admin checkbox |
| Abuse prevention | WordPress revisions only |
| Image uploads | Custom upload UI (not Media Library) |

## Architecture

### Three Main Components

**1. Admin Settings**
- Meta box on post edit screen with checkbox: "Allow anyone to edit this post"
- Stored as post meta: `_anybody_editing_enabled`
- Optional settings page for global config (upload limits, allowed image types)

**2. Frontend Display**
- On editable posts, enqueue block editor scripts
- Render edit buttons on each block (visible on hover/focus)
- Mount inline Gutenberg editor when visitor clicks edit
- Save/Cancel buttons appear below active editor

**3. REST API Extension**
- Custom endpoints for unauthenticated post updates
- Permission check: post must be published and have `_anybody_editing_enabled = true`
- Custom upload endpoint for images

## Frontend Editing Experience

### Block Detection & Edit Buttons

1. Scan post content for block boundaries
2. Wrap each block in a container with `position: relative`
3. Inject edit button (pencil icon) at top-right corner
4. Button hidden by default, shown on `:hover` or `:focus-within`

### Inline Editor Flow

1. Visitor clicks edit button
2. Rendered block content is hidden
3. Gutenberg `BlockEdit` component mounts in place
4. Block attributes parsed from saved content
5. Save/Cancel buttons appear below

### Saving

1. Serialize updated block to HTML/comments format
2. Replace block in full post content
3. PATCH request to REST API
4. On success: unmount editor, show updated content
5. On failure: show error, keep editor open

### Non-Content Fields

**Title**: Edit button next to title, transforms to text input

**Featured Image**: Edit button on image, opens custom upload dropzone

**Excerpt**: Edit button next to excerpt, opens textarea

**Categories/Tags**: Edit buttons next to terms, opens checkbox/tag input panel

## REST API

### Post Update Endpoint

`POST /wp-json/anybody-editing/v1/posts/{id}`

Accepts: `title`, `content`, `excerpt`, `featured_media`, `categories`, `tags`

Permission logic:
1. Post exists and is published?
2. `_anybody_editing_enabled` meta is true?
3. If both yes, allow update without authentication

### File Upload Endpoint

`POST /wp-json/anybody-editing/v1/upload`

- Accepts multipart form data with image file
- Validates file type (jpg, png, gif, webp) and size (default 2MB limit)
- Creates attachment in media library
- Adds meta to attachment:
  - `_anybody_editing_upload` = `true`
  - `_anybody_editing_source_post` = post ID
  - `_anybody_editing_upload_ip` = visitor IP
- Returns attachment ID and URL

## Error Handling

### Concurrent Edits
- Last save wins
- Revisions preserve all versions

### Network Failures
- Editor stays open with error message
- Visitor can retry or cancel
- No data loss until dismissed

### Invalid Content
- Server sanitizes via `wp_kses_post()`
- Malformed blocks rejected

### Missing Blocks
- Blocks without registered editors show no edit button
- Core blocks always available
- Third-party blocks may not be editable

### Post Status Changes
- If post becomes non-editable mid-edit, save fails with message
- "This post is no longer editable"

### Upload Errors
- File too large: "Image exceeds the 2MB limit"
- Wrong type: "Only images (JPG, PNG, GIF, WebP) are allowed"
- Server error: "Upload failed. Please try again."

## Admin Experience

### Post Edit Screen
- Meta box: "Public Editing"
- Checkbox: "Allow anyone to edit this post"
- Note when enabled: "Visitors can edit this post without logging in"

### Settings Page (Settings → Anybody Editing)
- Max upload size (default 2MB)
- Allowed image types (jpg, png, gif, webp)
- Default state for new posts

### Media Library
- Filter dropdown: "Uploaded by: All / Visitors / Admins"
- Visitor uploads show source post link in attachment details

### Revisions
- Standard WordPress revision UI
- Anonymous saves attributed to "Anonymous" or "Public Visitor"

## Frontend Assets

### Scripts (loaded only on editable posts)
- `@wordpress/block-editor`
- `@wordpress/blocks`
- `@wordpress/components`
- `@wordpress/api-fetch`
- `anybody-editing-frontend.js`

### Performance
- Scripts load only when `_anybody_editing_enabled = true`
- Static content on initial load
- Block editor components lazy-loaded on first edit click
- Estimated payload: ~200-300KB gzipped

### Build Setup
- `@wordpress/scripts` for building
- JSX components
- Output to `build/` directory

## File Structure

```
anybody-editing-plugin/
├── anybody-editing.php          # Main plugin file, hooks, enqueueing
├── includes/
│   ├── class-admin.php          # Meta box, settings page
│   ├── class-rest-api.php       # Custom endpoints
│   └── class-frontend.php       # Frontend detection & setup
├── src/
│   ├── index.js                 # Entry point
│   ├── components/
│   │   ├── EditButton.js        # Hover/focus edit button
│   │   ├── BlockEditor.js       # Inline block editor wrapper
│   │   ├── FieldEditor.js       # Title/excerpt editor
│   │   └── ImageUploader.js     # Custom upload dropzone
│   └── api/
│       └── index.js             # REST API helpers
├── build/                       # Compiled assets
├── package.json
└── readme.txt
```
