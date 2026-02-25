# WP Washer

> **Advanced WordPress Media Metadata Management & Database Cleanup Plugin**  
> Owner: [Yellowhoster SMC Limited](https://yellowhoster.com)

---

## Overview

WP Washer is a comprehensive, production-ready WordPress plugin that keeps your media library and database clean, safe, and efficient.

**Never slows your server. Never deletes anything unsafe. Always asks before destructive actions.**

---

## Features

### 🧹 Auto-Delete on Post/Product Removal
- Detects all attached media when a post, page, or WooCommerce product is deleted
- Removes attachment metadata, generated sizes, EXIF data, and custom meta fields
- Optionally: permanently deletes attachment files from server
- Trigger on: Trash, Permanent Delete, or both
- Applies to: Posts, Pages, WooCommerce Products

### 🔒 Safe Deletion System
Before deleting anything, WP Washer checks if the file is still used in:
- Featured images on any post
- Post content HTML (direct `src=` references)
- WooCommerce product galleries
- Elementor, WPBakery, Gutenberg, and ACF custom fields
- Any serialised page-builder meta

**If still referenced → deletion is blocked and reported. Never loses data.**

### 🖱️ Enhanced Media Library Selection
- **Drag-to-select** across the thumbnail grid
- **Shift+Click** range selection
- **Ctrl+Click** individual toggle
- **Ctrl+A** — select all visible items
- **Select All in Library** — loads every attachment ID via AJAX
- **Live counter** showing selected file count

### ⚡ Bulk Metadata Operations
Select any group of files, then choose:
| Action | Description |
|--------|-------------|
| Delete Metadata Only | Removes postmeta, keeps the file |
| Delete Metadata + File | Permanently removes file and metadata |
| Remove EXIF Data | Strips GPS/camera info from JPEG/TIFF |
| Remove Unused Thumbnails | Deletes size files no longer registered |
| Clean Orphaned Post Meta | Removes orphan `wp_postmeta` rows |

### 🗄️ Database Cleanup Engine
- Scans for orphaned attachments (unattached, unused)
- Scans for orphaned postmeta rows
- Estimates disk space recoverable
- Preview report before confirming cleanup

### ↩️ Undo / Restore
- Metadata snapshot saved before every bulk operation
- One-click restore on the Reports page
- Snapshots auto-expire after 7 days

### 📋 Operation Logging
- Every action logged to a dedicated database table
- Filterable by action type and status
- Paginated log viewer in the admin
- Export logs as CSV

---

## Admin Pages

| Page | Purpose |
|------|---------|
| **Dashboard** | Stats overview + quick action links |
| **Settings** | All toggles, post-type selection, batch size |
| **Bulk Cleaner** | Custom media grid with selection + actions |
| **Cleanup Reports** | Orphan scan + DB cleanup + undo last operation |
| **Logs** | Full operation history with filters + CSV export |

---

## Performance & Safety

- ✅ Batch processing: **30 files/chunk** (configurable 5–100)
- ✅ Memory guard: auto-pauses at **80% of PHP memory limit**
- ✅ No cron jobs required — browser-side AJAX polling continues processing
- ✅ Tested safe on **shared hosting (1 GB RAM)**
- ✅ No page builder conflicts — checks before touching any file

---

## Security

| Threat | Mitigation |
|--------|-----------|
| SQL Injection | `$wpdb->prepare()` on all queries |
| XSS | `esc_html()`, `esc_attr()`, `esc_url()` everywhere |
| CSRF | WordPress nonces on all forms and AJAX |
| Privilege Escalation | `current_user_can('manage_options')` on every endpoint |
| Path Traversal | `realpath()` + uploads-directory whitelist |
| Arbitrary File Delete | Only registered WP attachments inside `/uploads/` |
| Direct File Execution | `defined('ABSPATH') \|\| die` in every PHP file |

---

## Requirements

| | Minimum |
|-|---------|
| WordPress | 5.8 |
| PHP | 7.4 |
| WooCommerce | Optional (auto-detected) |

---

## Installation

1. Upload the `wp-washer` folder to `/wp-content/plugins/`
2. Activate through **WP Admin → Plugins**
3. Navigate to **WP Washer** in the sidebar

On activation, two database tables are created automatically:
- `{prefix}wpwasher_log` — operation history
- `{prefix}wpwasher_restore` — undo snapshots

---

## Compatibility

- ✅ WordPress (latest)
- ✅ WooCommerce
- ✅ Elementor
- ✅ Gutenberg
- ✅ ACF (Advanced Custom Fields)
- ✅ Wordfence, iThemes Security, caching plugins (no conflicts)

---

## Changelog

### 1.0.0 — 2026-02-25
- Initial release

---

## License

GPL v2 or later — https://www.gnu.org/licenses/gpl-2.0.html

---

*Built with ❤️ by [Yellowhoster SMC Limited](https://yellowhoster.com)*
