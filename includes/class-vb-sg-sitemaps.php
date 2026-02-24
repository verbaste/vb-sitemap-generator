<?php
/**
 * Core sitemap routing and rendering (Stage 1 + Stage 2 Images).
 *
 * Provides:
 * - /sitemap.xml                (index)
 * - /sitemap-main-1.xml ...     (sharded main sitemap)
 * - /sitemap-images-1.xml ...   (sharded images sitemap)
 *
 * @package    VB_Sitemap_Generator
 * @since      1.0.0
 * @author     VerBaste
 * @link       https://verbaste.com
 * @copyright  2026 VerBaste
 * @license    GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

final class VB_SG_Sitemaps {

    private const QV_NAME = 'vb_sg_sitemap';

    private const CACHE_TTL = 6 * HOUR_IN_SECONDS;

    private const TRANSIENT_INDEX_PREFIX  = 'vb_sg_index_v1_';
    private const TRANSIENT_MAIN_PREFIX   = 'vb_sg_main_v1_';
    private const TRANSIENT_IMAGES_PREFIX = 'vb_sg_images_v1_';

    /**
     * Max URLs per sitemap file (protocol limit).
     */
    private const MAX_URLS_PER_FILE = 50000;

    /**
     * Bootstrap hooks.
     *
     * @return void
     */
    public static function init(): void {
        // Avoid duplicates with WP core sitemaps (/wp-sitemap.xml).
        add_filter( 'wp_sitemaps_enabled', '__return_false' );
		add_filter( 'robots_txt', array( __CLASS__, 'add_robots_sitemap' ), 10, 2 );

        add_action( 'init', array( __CLASS__, 'add_rewrites' ) );
        add_action( 'template_redirect', array( __CLASS__, 'maybe_render' ) );

        // Purge caches on content/taxonomy changes.
        add_action( 'save_post', array( __CLASS__, 'purge_all' ), 20 );
        add_action( 'deleted_post', array( __CLASS__, 'purge_all' ), 20 );
        add_action( 'trashed_post', array( __CLASS__, 'purge_all' ), 20 );
        add_action( 'transition_post_status', array( __CLASS__, 'purge_all' ), 20 );

        add_action( 'edited_term', array( __CLASS__, 'purge_all' ), 20 );
        add_action( 'delete_term', array( __CLASS__, 'purge_all' ), 20 );

        // When permalink structure/theme/siteurl changes, purge.
        add_action( 'update_option_permalink_structure', array( __CLASS__, 'purge_all' ), 20 );
        add_action( 'switch_theme', array( __CLASS__, 'purge_all' ), 20 );
        add_action( 'update_option_home', array( __CLASS__, 'purge_all' ), 20 );
        add_action( 'update_option_siteurl', array( __CLASS__, 'purge_all' ), 20 );
    }

    /**
     * Activation hook: add rewrite rules and flush.
     *
     * @return void
     */
    public static function activate(): void {
        self::purge_all();
        self::add_rewrites();
        flush_rewrite_rules();
    }

    /**
     * Deactivation hook: flush rewrite rules.
     *
     * @return void
     */
    public static function deactivate(): void {
        flush_rewrite_rules();
    }

    /**
     * Register rewrite endpoints.
     *
     * @return void
     */
    public static function add_rewrites(): void {
        add_rewrite_tag( '%' . self::QV_NAME . '%', '([^&]+)' );

        add_rewrite_rule( '^sitemap\.xml$', 'index.php?' . self::QV_NAME . '=index', 'top' );

        // Sharded main sitemaps: sitemap-main-1.xml, sitemap-main-2.xml, ...
        add_rewrite_rule( '^sitemap-main-([0-9]+)\.xml$', 'index.php?' . self::QV_NAME . '=main-$matches[1]', 'top' );

        // Sharded images sitemaps: sitemap-images-1.xml, sitemap-images-2.xml, ...
        add_rewrite_rule( '^sitemap-images-([0-9]+)\.xml$', 'index.php?' . self::QV_NAME . '=images-$matches[1]', 'top' );
    }

    /**
     * Output XML if current request is a sitemap endpoint.
     *
     * @return void
     */
    public static function maybe_render(): void {
        $type = get_query_var( self::QV_NAME );
        $type = is_string( $type ) ? $type : '';

        if ( '' === $type ) {
            return;
        }

        nocache_headers();
        header( 'Content-Type: application/xml; charset=UTF-8' );

        if ( 'index' === $type ) {
            echo self::render_index(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- XML is escaped during generation.
            exit;
        }

        if ( 0 === strpos( $type, 'main-' ) ) {
            $n = (int) substr( $type, 5 );
            if ( $n < 1 ) {
                $n = 1;
            }
            echo self::render_main_shard( $n ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- XML is escaped during generation.
            exit;
        }

        if ( 0 === strpos( $type, 'images-' ) ) {
            $n = (int) substr( $type, 7 );
            if ( $n < 1 ) {
                $n = 1;
            }
            echo self::render_images_shard( $n ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- XML is escaped during generation.
            exit;
        }

        status_header( 404 );
        exit;
    }

    /**
     * Purge all sitemap caches by bumping a cache key.
     *
     * @return void
     */
    public static function purge_all(): void {
        $bump = (int) get_option( 'vb_sg_cache_bump', 1 );
        update_option( 'vb_sg_cache_bump', $bump + 1, false );
    }

    /**
     * Render sitemap index.
     *
     * @return string
     */
    private static function render_index(): string {
        $bump  = (int) get_option( 'vb_sg_cache_bump', 1 );
        $t_key = self::TRANSIENT_INDEX_PREFIX . $bump;

        $cached = get_transient( $t_key );
        if ( is_string( $cached ) && '' !== $cached ) {
            return $cached;
        }

        $main_count   = self::estimate_total_url_count();
        $main_shards  = (int) ceil( max( 1, $main_count ) / self::MAX_URLS_PER_FILE );
        $main_shards  = max( 1, $main_shards );

        // Images shard count: based on total publish posts (safe upper bound).
        $img_count   = self::estimate_total_posts_count();
        $img_shards  = (int) ceil( max( 1, $img_count ) / self::MAX_URLS_PER_FILE );
        $img_shards  = max( 1, $img_shards );

        $xml  = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<sitemapindex xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

        for ( $i = 1; $i <= $main_shards; $i++ ) {
            $xml .= self::sitemap_index_item( home_url( '/sitemap-main-' . $i . '.xml' ) );
        }

        for ( $i = 1; $i <= $img_shards; $i++ ) {
            $xml .= self::sitemap_index_item( home_url( '/sitemap-images-' . $i . '.xml' ) );
        }

        $xml .= "</sitemapindex>\n";

        set_transient( $t_key, $xml, self::CACHE_TTL );
        return $xml;
    }

    /**
     * Build a sitemap index entry.
     *
     * @param string $loc Location URL.
     * @return string
     */
    private static function sitemap_index_item( string $loc ): string {
        return "\t<sitemap>\n\t\t<loc>" . esc_url( $loc ) . "</loc>\n\t</sitemap>\n";
    }

    /**
     * Render a single main shard (Stage 1 + image entries).
     *
     * @param int $shard Shard number (1-based).
     * @return string
     */
    private static function render_main_shard( int $shard ): string {
        $bump  = (int) get_option( 'vb_sg_cache_bump', 1 );
        $t_key = self::TRANSIENT_MAIN_PREFIX . $bump . '_' . $shard;

        $cached = get_transient( $t_key );
        if ( is_string( $cached ) && '' !== $cached ) {
            return $cached;
        }

        $offset = ( $shard - 1 ) * self::MAX_URLS_PER_FILE;
        $limit  = self::MAX_URLS_PER_FILE;

        $rows = self::collect_urls_slice( $offset, $limit );

        $xml  = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\" ";
        $xml .= "xmlns:image=\"http://www.google.com/schemas/sitemap-image/1.1\">\n";

        foreach ( $rows as $row ) {
            $loc     = (string) ( $row['loc'] ?? '' );
            $lastmod = (string) ( $row['lastmod'] ?? '' );
            $images  = isset( $row['images'] ) && is_array( $row['images'] ) ? $row['images'] : array();

            if ( '' === $loc ) {
                continue;
            }

            $xml .= "\t<url>\n";
            $xml .= "\t\t<loc>" . esc_url( $loc ) . "</loc>\n";

            if ( '' !== $lastmod ) {
                $xml .= "\t\t<lastmod>" . esc_html( $lastmod ) . "</lastmod>\n";
            }

            if ( ! empty( $images ) ) {
                foreach ( $images as $img_url ) {
                    $img_url = is_string( $img_url ) ? $img_url : '';
                    if ( '' === $img_url ) {
                        continue;
                    }

                    $xml .= "\t\t<image:image>\n";
                    $xml .= "\t\t\t<image:loc>" . esc_url( $img_url ) . "</image:loc>\n";
                    $xml .= "\t\t</image:image>\n";
                }
            }

            $xml .= "\t</url>\n";
        }

        $xml .= "</urlset>\n";

        set_transient( $t_key, $xml, self::CACHE_TTL );
        return $xml;
    }

    /**
     * Render a single images shard (Stage 2).
     *
     * Outputs <url> entries only for posts that have at least one image.
     *
     * @param int $shard Shard number (1-based).
     * @return string
     */
    private static function render_images_shard( int $shard ): string {
        $bump  = (int) get_option( 'vb_sg_cache_bump', 1 );
        $t_key = self::TRANSIENT_IMAGES_PREFIX . $bump . '_' . $shard;

        $cached = get_transient( $t_key );
        if ( is_string( $cached ) && '' !== $cached ) {
            return $cached;
        }

        $offset = ( $shard - 1 ) * self::MAX_URLS_PER_FILE;
        $limit  = self::MAX_URLS_PER_FILE;

        $rows = self::collect_images_urls_slice( $offset, $limit );

        $xml  = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\" ";
        $xml .= "xmlns:image=\"http://www.google.com/schemas/sitemap-image/1.1\">\n";

        foreach ( $rows as $row ) {
            $loc    = (string) ( $row['loc'] ?? '' );
            $images = isset( $row['images'] ) && is_array( $row['images'] ) ? $row['images'] : array();

            if ( '' === $loc || empty( $images ) ) {
                continue;
            }

            $xml .= "\t<url>\n";
            $xml .= "\t\t<loc>" . esc_url( $loc ) . "</loc>\n";

            foreach ( $images as $img_url ) {
                $img_url = is_string( $img_url ) ? $img_url : '';
                if ( '' === $img_url ) {
                    continue;
                }

                $xml .= "\t\t<image:image>\n";
                $xml .= "\t\t\t<image:loc>" . esc_url( $img_url ) . "</image:loc>\n";
                $xml .= "\t\t</image:image>\n";
            }

            $xml .= "\t</url>\n";
        }

        $xml .= "</urlset>\n";

        set_transient( $t_key, $xml, self::CACHE_TTL );
        return $xml;
    }

    /**
     * Estimate total URL count for sharding (main index generation).
     *
     * @return int
     */
    private static function estimate_total_url_count(): int {
        $total = 0;

        // Home.
        $total += 1;

        // Publish posts across public CPT.
        $post_types = self::get_public_content_post_types();
        foreach ( $post_types as $pt ) {
            $counts = wp_count_posts( $pt );
            if ( $counts && isset( $counts->publish ) ) {
                $total += (int) $counts->publish;
            }
        }

        // Post type archives.
        foreach ( $post_types as $pt ) {
            $obj = get_post_type_object( $pt );
            if ( $obj && ! empty( $obj->has_archive ) ) {
                $total += 1;
            }
        }

        // Public taxonomies: count only non-empty terms.
        $taxes = get_taxonomies( array( 'public' => true ), 'names' );
        foreach ( $taxes as $tax ) {
            if ( in_array( $tax, array( 'nav_menu', 'link_category', 'post_format' ), true ) ) {
                continue;
            }

            $c = wp_count_terms(
                array(
                    'taxonomy'   => $tax,
                    'hide_empty' => true,
                )
            );

            if ( ! is_wp_error( $c ) ) {
                $total += (int) $c;
            }
        }

        $total = (int) apply_filters( 'vb_sg_estimated_total_count', $total );
        return max( 1, $total );
    }

    /**
     * Estimate total number of publish posts across included post types (for images sharding).
     *
     * @return int
     */
    private static function estimate_total_posts_count(): int {
        $total      = 0;
        $post_types = self::get_public_content_post_types();

        foreach ( $post_types as $pt ) {
            $counts = wp_count_posts( $pt );
            if ( $counts && isset( $counts->publish ) ) {
                $total += (int) $counts->publish;
            }
        }

        $total = (int) apply_filters( 'vb_sg_estimated_total_posts_count', $total );
        return max( 1, $total );
    }

    /**
     * Collect a slice of URLs (offset/limit) without building the full list.
     *
     * Deterministic order:
     * 1) Home
     * 2) Posts/CPTs in ascending ID
     * 3) CPT archives
     * 4) Terms per taxonomy, ascending term_id
     *
     * Stage 2: for post URLs, attach images[].
     *
     * @param int $offset 0-based offset.
     * @param int $limit  Max items to return.
     * @return array<int, array{loc:string,lastmod:string,images?:array<int,string>}>
     */
    private static function collect_urls_slice( int $offset, int $limit ): array {
        $results = array();

        $skip = max( 0, $offset );
        $take = max( 1, $limit );

        // 1) Home.
        $home = (string) home_url( '/' );
        if ( ! apply_filters( 'vb_sg_exclude_url', false, $home, 'home' ) ) {
            if ( $skip > 0 ) {
                $skip--;
            } else {
                $results[] = array(
                    'loc'     => $home,
                    'lastmod' => (string) apply_filters( 'vb_sg_home_lastmod', gmdate( 'c' ) ),
                );
                $take--;
            }
        }

        if ( $take <= 0 ) {
            return $results;
        }

        // 2) Posts/CPTs (publish).
        $post_types = self::get_public_content_post_types();

        foreach ( $post_types as $pt ) {
            if ( $take <= 0 ) {
                break;
            }

            $batch_size = (int) apply_filters( 'vb_sg_posts_batch_size', 2000, $pt );
            $batch_size = max( 100, min( 5000, $batch_size ) );

            $paged = 1;

            while ( $take > 0 ) {
                $q = new WP_Query(
                    array(
                        'post_type'              => $pt,
                        'post_status'            => 'publish',
                        'posts_per_page'         => $batch_size,
                        'paged'                  => $paged,
                        'orderby'                => 'ID',
                        'order'                  => 'ASC',
                        'fields'                 => 'ids',
                        'no_found_rows'          => true,
                        'update_post_term_cache' => false,
                        'update_post_meta_cache' => false,
                    )
                );

                if ( empty( $q->posts ) ) {
                    break;
                }

                foreach ( $q->posts as $post_id ) {
                    if ( $take <= 0 ) {
                        break 2;
                    }

                    if ( $skip > 0 ) {
                        $skip--;
                        continue;
                    }

                    $post_id = (int) $post_id;

                    if ( true === apply_filters( 'vb_sg_exclude_post', false, $post_id ) ) {
                        continue;
                    }

                    $loc = get_permalink( $post_id );
                    if ( ! is_string( $loc ) || '' === $loc ) {
                        continue;
                    }

                    // Canonical check (core-first).
                    $canonical = wp_get_canonical_url( $post_id );
                    if ( is_string( $canonical ) && '' !== $canonical ) {
                        $a = untrailingslashit( $canonical );
                        $b = untrailingslashit( $loc );
                        if ( $a !== $b ) {
                            continue;
                        }
                    }

                    if ( true === apply_filters( 'vb_sg_is_noindex_post', false, $post_id ) ) {
                        continue;
                    }

                    $lastmod = get_post_modified_time( 'c', true, $post_id ); // GMT ISO8601.
                    $images  = VB_SG_Images::collect_images_for_post( $post_id );

                    $results[] = array(
                        'loc'     => $loc,
                        'lastmod' => is_string( $lastmod ) ? $lastmod : '',
                        'images'  => $images,
                    );

                    $take--;
                }

                $paged++;
            }
        }

        if ( $take <= 0 ) {
            return $results;
        }

        // 3) Post type archives.
        foreach ( $post_types as $pt ) {
            if ( $take <= 0 ) {
                break;
            }

            $obj = get_post_type_object( $pt );
            if ( ! $obj || empty( $obj->has_archive ) ) {
                continue;
            }

            $archive_url = get_post_type_archive_link( $pt );
            if ( ! is_string( $archive_url ) || '' === $archive_url ) {
                continue;
            }

            if ( apply_filters( 'vb_sg_exclude_url', false, $archive_url, 'post_type_archive', $pt ) ) {
                continue;
            }

            if ( $skip > 0 ) {
                $skip--;
                continue;
            }

            $results[] = array(
                'loc'     => $archive_url,
                'lastmod' => '',
            );

            $take--;
        }

        if ( $take <= 0 ) {
            return $results;
        }

        // 4) Public taxonomies (terms with count > 0).
        $taxes = get_taxonomies( array( 'public' => true ), 'names' );
        sort( $taxes );

        foreach ( $taxes as $tax_name ) {
            if ( $take <= 0 ) {
                break;
            }

            if ( in_array( $tax_name, array( 'nav_menu', 'link_category', 'post_format' ), true ) ) {
                continue;
            }

            $term_ids = get_terms(
                array(
                    'taxonomy'   => $tax_name,
                    'hide_empty' => true,
                    'fields'     => 'ids',
                    'orderby'    => 'id',
                    'order'      => 'ASC',
                    'number'     => 0,
                )
            );

            if ( is_wp_error( $term_ids ) || empty( $term_ids ) ) {
                continue;
            }

            foreach ( $term_ids as $term_id ) {
                if ( $take <= 0 ) {
                    break 2;
                }

                if ( $skip > 0 ) {
                    $skip--;
                    continue;
                }

                $term_id = (int) $term_id;

                $term_link = get_term_link( $term_id, $tax_name );
                if ( is_wp_error( $term_link ) || ! is_string( $term_link ) || '' === $term_link ) {
                    continue;
                }

                if ( apply_filters( 'vb_sg_exclude_url', false, $term_link, 'term', $tax_name, $term_id ) ) {
                    continue;
                }

                if ( true === apply_filters( 'vb_sg_is_noindex_term', false, $tax_name, $term_id ) ) {
                    continue;
                }

                $results[] = array(
                    'loc'     => $term_link,
                    'lastmod' => '',
                );

                $take--;
            }
        }

        return $results;
    }

    /**
     * Collect a slice for images sitemap.
     *
     * Deterministic order:
     * - Posts/CPTs in ascending ID
     *
     * Only returns rows that have at least one image.
     *
     * @param int $offset 0-based offset (over POSTS, not over "only those with images").
     * @param int $limit  Max items to scan/return.
     * @return array<int, array{loc:string,images:array<int,string>}>
     */
    private static function collect_images_urls_slice( int $offset, int $limit ): array {
        $results = array();

        $skip = max( 0, $offset );
        $take = max( 1, $limit );

        $post_types = self::get_public_content_post_types();

        foreach ( $post_types as $pt ) {
            if ( $take <= 0 ) {
                break;
            }

            $batch_size = (int) apply_filters( 'vb_sg_posts_batch_size', 2000, $pt );
            $batch_size = max( 100, min( 5000, $batch_size ) );

            $paged = 1;

            while ( $take > 0 ) {
                $q = new WP_Query(
                    array(
                        'post_type'              => $pt,
                        'post_status'            => 'publish',
                        'posts_per_page'         => $batch_size,
                        'paged'                  => $paged,
                        'orderby'                => 'ID',
                        'order'                  => 'ASC',
                        'fields'                 => 'ids',
                        'no_found_rows'          => true,
                        'update_post_term_cache' => false,
                        'update_post_meta_cache' => false,
                    )
                );

                if ( empty( $q->posts ) ) {
                    break;
                }

                foreach ( $q->posts as $post_id ) {
                    if ( $take <= 0 ) {
                        break 2;
                    }

                    if ( $skip > 0 ) {
                        $skip--;
                        continue;
                    }

                    $post_id = (int) $post_id;

                    if ( true === apply_filters( 'vb_sg_exclude_post', false, $post_id ) ) {
                        continue;
                    }

                    $loc = get_permalink( $post_id );
                    if ( ! is_string( $loc ) || '' === $loc ) {
                        continue;
                    }

                    // Canonical check (core-first).
                    $canonical = wp_get_canonical_url( $post_id );
                    if ( is_string( $canonical ) && '' !== $canonical ) {
                        $a = untrailingslashit( $canonical );
                        $b = untrailingslashit( $loc );
                        if ( $a !== $b ) {
                            continue;
                        }
                    }

                    if ( true === apply_filters( 'vb_sg_is_noindex_post', false, $post_id ) ) {
                        continue;
                    }

                    $images = VB_SG_Images::collect_images_for_post( $post_id );
                    if ( empty( $images ) ) {
                        continue;
                    }

                    $results[] = array(
                        'loc'    => $loc,
                        'images' => $images,
                    );

                    $take--;
                }

                $paged++;
            }
        }

        return $results;
    }

    /**
     * Get public content post types for sitemaps (deny system types).
     *
     * @return array<int, string>
     */
    private static function get_public_content_post_types(): array {
        $post_types = get_post_types(
            array(
                'public'             => true,
                'publicly_queryable' => true,
            ),
            'names'
        );

        $deny = array(
            'attachment',
            'revision',
            'nav_menu_item',
            'custom_css',
            'customize_changeset',
            'oembed_cache',
            'user_request',
            'wp_template',
            'wp_template_part',
            'wp_global_styles',
            'wp_navigation',
        );

        foreach ( $deny as $pt ) {
            if ( isset( $post_types[ $pt ] ) ) {
                unset( $post_types[ $pt ] );
            }
        }

        $types = array_values( $post_types );

        $types = (array) apply_filters( 'vb_sg_included_post_types', $types );
        $types = array_values( array_unique( array_filter( $types, 'is_string' ) ) );

        return $types;
    }

	/**
	 * Append sitemap reference to robots.txt.
	 *
	 * @param string $output Existing robots.txt content.
	 * @param bool   $public Whether site is considered "public".
	 * @return string
	 */
	public static function add_robots_sitemap( string $output, bool $public ): string {
		// If blog is set to discourage search engines, do not add sitemap.
		if ( ! $public ) {
			return $output;
		}

		$sitemap_url = home_url( '/sitemap.xml' );
		$line        = 'Sitemap: ' . esc_url_raw( $sitemap_url );

		// Avoid duplication.
		if ( false !== strpos( $output, $sitemap_url ) ) {
			return $output;
		}

		$output = rtrim( $output );
		$output .= "\n\n" . $line . "\n";

		return $output;
	}
}
