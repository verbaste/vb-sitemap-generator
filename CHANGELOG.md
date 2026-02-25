# Changelog

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
