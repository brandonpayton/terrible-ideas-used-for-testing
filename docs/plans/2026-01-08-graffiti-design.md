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

### Dashboard

- View all graffiti across the site
- Delete individual drawings
- Metadata visible: timestamp, IP address, associated page/post, paragraph location

### Configuration

- Global enable/disable toggle (site-wide)
- No per-page settings

### Notifications

- Dashboard only (no email notifications)

## Technical Decisions

- **Canvas size**: ~400x200 pixels (medium, card-sized)
- **Drawing tools**: Minimal - single brush, few colors, eraser
- **Persistence**: Drawings remain forever until manually deleted
- **Storage**: TBD (likely WordPress database with drawings as base64 or uploaded to media library)

## Status

This design is a work in progress. Technical architecture and implementation details to follow.
