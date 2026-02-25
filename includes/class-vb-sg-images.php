<?php
/**
 * Images collection utilities (Stage 2).
 *
 * Collects images for a post (featured + Gutenberg + classic "wp-image-ID").
 * Supports WebP preference (if local file exists) and excludes SVG.
 *
 * @package    VB_Sitemap_Generator
 * @since      1.0.0
 * @author     VerBaste
 * @link       https://verbaste.com
 * @copyright  2026 VerBaste
 * @license    GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

final class VB_SG_Images {

	/**
	 * Collect image URLs for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array<int, string> Unique list of image URLs.
	 */
	public static function collect_images_for_post( int $post_id ): array {
		static $cache = array();

		if ( isset( $cache[ $post_id ] ) && is_array( $cache[ $post_id ] ) ) {
			return $cache[ $post_id ];
		}

		$urls = array();

		// 1) Featured image.
		$thumb_id = (int) get_post_thumbnail_id( $post_id );
		if ( $thumb_id > 0 ) {
			$url = self::get_attachment_url_preferred( $thumb_id );
			if ( '' !== $url ) {
				$urls[] = $url;
			}
		}

		// 2) Gutenberg blocks (core/image + core/gallery).
		$content = get_post_field( 'post_content', $post_id );
		$content = is_string( $content ) ? $content : '';

		if ( function_exists( 'parse_blocks' ) && '' !== $content ) {
			$blocks = parse_blocks( $content );
			$ids    = self::extract_attachment_ids_from_blocks( $blocks );

			foreach ( $ids as $attachment_id ) {
				$attachment_id = (int) $attachment_id;
				if ( $attachment_id <= 0 ) {
					continue;
				}
				$url = self::get_attachment_url_preferred( $attachment_id );
				if ( '' !== $url ) {
					$urls[] = $url;
				}
			}
		}

		// 3) Classic editor fallback: wp-image-123.
		if ( '' !== $content ) {
			if ( preg_match_all( '/wp-image-([0-9]+)/', $content, $m ) ) {
				foreach ( $m[1] as $id ) {
					$attachment_id = (int) $id;
					if ( $attachment_id <= 0 ) {
						continue;
					}
					$url = self::get_attachment_url_preferred( $attachment_id );
					if ( '' !== $url ) {
						$urls[] = $url;
					}
				}
			}
		}

		// Deduplicate + filter.
		$unique = array();
		foreach ( $urls as $url ) {
			$url = is_string( $url ) ? trim( $url ) : '';
			if ( '' === $url ) {
				continue;
			}

			if ( self::is_svg_url( $url ) ) {
				continue;
			}

			$exclude = (bool) apply_filters( 'vb_sg_exclude_image', false, $url, $post_id );
			if ( $exclude ) {
				continue;
			}

			$unique[ $url ] = true;
		}

		$urls = array_keys( $unique );

		$max = (int) apply_filters( 'vb_sg_max_images_per_url', 20, $post_id );
		if ( $max > 0 && count( $urls ) > $max ) {
			$urls = array_slice( $urls, 0, $max );
		}

		$cache[ $post_id ] = $urls;
		return $urls;
	}

	/**
	 * Extract attachment IDs from Gutenberg blocks (recursive).
	 *
	 * Supports:
	 * - core/image (attrs[id])
	 * - core/gallery (attrs[ids] or innerBlocks)
	 *
	 * @param array<int, mixed> $blocks Parsed blocks.
	 * @return array<int, int>
	 */
	private static function extract_attachment_ids_from_blocks( array $blocks ): array {
		$ids = array();

		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$block_name = isset( $block['blockName'] ) && is_string( $block['blockName'] ) ? $block['blockName'] : '';

			$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();

			if ( 'core/image' === $block_name ) {
				if ( isset( $attrs['id'] ) ) {
					$ids[] = (int) $attrs['id'];
				}
			}

			if ( 'core/gallery' === $block_name ) {
				if ( isset( $attrs['ids'] ) && is_array( $attrs['ids'] ) ) {
					foreach ( $attrs['ids'] as $id ) {
						$ids[] = (int) $id;
					}
				}
			}

			if ( isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) && ! empty( $block['innerBlocks'] ) ) {
				$child_ids = self::extract_attachment_ids_from_blocks( $block['innerBlocks'] );
				foreach ( $child_ids as $cid ) {
					$ids[] = (int) $cid;
				}
			}
		}

		// Unique ints > 0.
		$out = array();
		foreach ( $ids as $id ) {
			$id = (int) $id;
			if ( $id > 0 ) {
				$out[ $id ] = true;
			}
		}

		return array_keys( $out );
	}

	/**
	 * Get attachment URL with WebP preference (if enabled and local .webp exists).
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string
	 */
	private static function get_attachment_url_preferred( int $attachment_id ): string {
		$url = wp_get_attachment_url( $attachment_id );
		if ( ! is_string( $url ) || '' === $url ) {
			return '';
		}

		// Exclude SVG by mime if available.
		$mime = get_post_mime_type( $attachment_id );
		if ( is_string( $mime ) && 'image/svg+xml' === $mime ) {
			return '';
		}

		$prefer = (bool) apply_filters( 'vb_sg_prefer_webp', true, $url, $attachment_id );
		if ( ! $prefer ) {
			return $url;
		}

		$webp = self::prefer_webp_if_exists( $url );
		if ( '' !== $webp ) {
			return $webp;
		}

		return $url;
	}

	/**
	 * If URL points to a local upload file, check if a same-path .webp exists and return it.
	 *
	 * @param string $url Original URL.
	 * @return string WebP URL or empty string.
	 */
	private static function prefer_webp_if_exists( string $url ): string {
		$uploads = wp_get_upload_dir();
		$baseurl = isset( $uploads['baseurl'] ) && is_string( $uploads['baseurl'] ) ? $uploads['baseurl'] : '';
		$basedir = isset( $uploads['basedir'] ) && is_string( $uploads['basedir'] ) ? $uploads['basedir'] : '';

		if ( '' === $baseurl || '' === $basedir ) {
			return '';
		}

		// Only for URLs inside uploads baseurl.
		if ( 0 !== strpos( $url, $baseurl ) ) {
			return '';
		}

		$rel = substr( $url, strlen( $baseurl ) );
		$rel = ltrim( $rel, '/' );

		$path = trailingslashit( $basedir ) . $rel;
		$path = wp_normalize_path( $path );

		// Replace extension with .webp.
		$webp_path = preg_replace( '/\.[a-zA-Z0-9]+$/', '.webp', $path );
		if ( ! is_string( $webp_path ) || '' === $webp_path ) {
			return '';
		}

		if ( file_exists( $webp_path ) ) {
			$webp_rel = substr( $webp_path, strlen( wp_normalize_path( trailingslashit( $basedir ) ) ) );
			$webp_rel = ltrim( $webp_rel, '/' );

			return trailingslashit( $baseurl ) . str_replace( '\\', '/', $webp_rel );
		}

		return '';
	}

	/**
	 * Quick SVG URL check.
	 *
	 * @param string $url Image URL.
	 * @return bool
	 */
	private static function is_svg_url( string $url ): bool {
		$p = wp_parse_url( $url );
		if ( ! is_array( $p ) || empty( $p['path'] ) || ! is_string( $p['path'] ) ) {
			return false;
		}

		return (bool) preg_match( '/\.svg$/i', $p['path'] );
	}
}
