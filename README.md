# VB Sitemap Generator

Lightweight, standards-compliant XML sitemap generator for WordPress (dynamic + cache).

## Endpoints
- `/sitemap.xml` (index)
- `/sitemap-main-1.xml`, `/sitemap-main-2.xml`, ... (URL sitemaps, sharded)

> Images and video support will be added in later stages.

## Filters (developer)
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
