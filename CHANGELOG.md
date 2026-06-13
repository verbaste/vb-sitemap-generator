# Changelog

## 1.0.2 - 2026-06-13

### Fixed
- Fixed public page collection on page-based WordPress sites.
- Removed the overly restrictive `publicly_queryable` requirement from sitemap post type detection.
- Prevented duplicate static front page entries in the main sitemap.
- Added static front page images to the homepage sitemap entry.
- Updated sitemap cache keys to avoid stale output after upgrade.

### Changed
- Homepage `<lastmod>` now uses the static front page modified date when available instead of a generated current timestamp.

## 1.0.0 - 2026-02-24

### Added
- Sitemap index (`/sitemap.xml`)
- Sharded main sitemaps (`/sitemap-main-*.xml`)
- Image sitemaps (`/sitemap-images-*.xml`)
- Image entries inside main sitemap
- Automatic robots.txt integration
- Publish-only URL inclusion
- Respect for `noindex`
- `<lastmod>` based on `post_modified_gmt`
- Dynamic generation with caching

### Notes
- No deprecated `<changefreq>` or `<priority>` tags
- Automatic sharding when limits are reached
