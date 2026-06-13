# VB Sitemap Generator

Lightweight, standards-compliant XML sitemap generator for WordPress (dynamic generation with caching).

## Features

- `/vb-sitemap.xml` — sitemap index
- `/vb-sitemap-main-1.xml`, `/sitemap-main-2.xml`, ... — sharded URL sitemaps
- `/vb-sitemap-images-1.xml`, `/sitemap-images-2.xml`, ... — image sitemaps
- Includes public posts, pages, and public custom post types
- Images included in main sitemap entries
- Static front page handled as the homepage entry to avoid duplicate URLs
- Publish-only URLs
- Respects `noindex`
- Uses `post_modified_gmt` for `<lastmod>`
- Automatic `robots.txt` integration
- No deprecated `<changefreq>` or `<priority>` tags

## Architecture Notes

- Dynamic output (no file writing by default)
- Cached responses for performance
- Cache keys are versioned to avoid stale sitemap output after bugfix upgrades
- Automatic sharding when limits are reached
- Designed to follow modern XML sitemap standards

## Filters (Developer)

- `vb_sg_included_post_types`
- `vb_sg_exclude_post`
- `vb_sg_exclude_url`
- `vb_sg_is_noindex_post`
- `vb_sg_is_noindex_term`
- `vb_sg_estimated_total_count`
- `vb_sg_posts_batch_size`
- `vb_sg_home_lastmod`

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## License

GPL-2.0-or-later
