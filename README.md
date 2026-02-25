# VB Sitemap Generator

Lightweight, standards-compliant XML sitemap generator for WordPress (dynamic generation with caching).

## Features

- `/sitemap.xml` — sitemap index
- `/sitemap-main-1.xml`, `/sitemap-main-2.xml`, ... — sharded URL sitemaps
- `/sitemap-images-1.xml`, `/sitemap-images-2.xml`, ... — image sitemaps
- Images included in main sitemap entries
- Publish-only URLs
- Respects `noindex`
- Uses `post_modified_gmt` for `<lastmod>`
- Automatic `robots.txt` integration
- No deprecated `<changefreq>` or `<priority>` tags

## Architecture Notes

- Dynamic output (no file writing by default)
- Cached responses for performance
- Automatic sharding when limits are reached
- Designed to follow modern XML sitemap standards (2026-ready)

## Filters (Developer)

- `vb_sg_included_post_types`
- `vb_sg_exclude_post`
- `vb_sg_exclude_url`
- `vb_sg_is_noindex_post`
- `vb_sg_is_noindex_term`
- `vb_sg_estimated_total_count`
- `vb_sg_posts_batch_size`
- `vb_sg_home_lastmod`

## License

GPL-2.0-or-later
