<?php
/**
 * Core sitemap routing and rendering (Stage 1).
 *
 * Provides:
 * - /sitemap.xml              (index)
 * - /sitemap-main-1.xml ...   (sharded URL sitemaps)
 *
 * Notes:
 * - Images and video are added in later stages.
 * - Dynamic output + transients cache.
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

    private const TRANSIENT_INDEX_PREFIX = 'vb_sg_index_v1_';
    private const TRANSIENT_MAIN_PREFIX  = 'vb_sg_main_v1_';

    /**
     * Max URLs per sitemap file (protocol limit).
     * @link https://www.sitemaps.org/protocol.html
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
            echo self::render_index();
            exit;
        }

        if ( 0 === strpos( $type, 'main-' ) ) {
            $n = (int) substr( $type, 5 );
            if ( $n < 1 ) {
                $n = 1;
            }
            echo self::render_main_shard( $n );
            exit;
        }

        status_header( 404 );
        exit;
    }

    /**
     * Purge all sitemap caches.
     *
     * @return void
     */
    public static function purge_all(): void {
        // We don’t know shard count cheaply without recomputing; purge by versioned prefix is not possible with transients.
        // So we use a bumping key.
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

        $count = self::estimate_total_url_count();
        $shards = (int) ceil( max( 1, $count ) / self::MAX_URLS_PER_FILE );
        $shards = max( 1, $shards );

        $xml  = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<sitemapindex xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

        for ( $i = 1; $i <= $shards; $i++ ) {
            $loc = home_url( '/sitemap-main-' . $i . '.xml' );
            $xml .= self::sitemap_index_item( $loc );
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
        $loc = esc_url( $loc );
        return "\t<sitemap>\n\t\t<loc>{$loc}</loc>\n\t</sitemap>\n";
    }

    /**
     * Render a single main shard.
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
        $xml .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

        foreach ( $rows as $row ) {
            $loc     = esc_url( (string) $row['loc'] );
            $lastmod = (string) $row['lastmod'];

            if ( '' === $loc ) {
                continue;
            }

            $xml .= "\t<url>\n";
            $xml .= "\t\t<loc>{$loc}</loc>\n";

            if ( '' !== $lastmod ) {
                $xml .= "\t\t<lastmod>" . esc_html( $lastmod ) . "</lastmod>\n";
            }

            $xml .= "\t</url>\n";
        }

        $xml .= "</urlset>\n";

        set_transient( $t_key, $xml, self::CACHE_TTL );
        return $xml;
    }

    /**
     * Estimate total URL count for sharding.
     *
     * This is a fast estimate for index generation (counts publish posts + term counts + archives + home).
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

        /**
         * Filter: adjust estimated total URL count (for custom URLs, etc.).
         *
         * @param int $total
         */
        $total = (int) apply_filters( 'vb_sg_estimated_total_count', $total );

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
     * @param int $offset 0-based offset.
     * @param int $limit  Max items to return.
     * @return array<int, array{loc:string,lastmod:string}>
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
                    // Home lastmod: conservative "now" (stage 1 minimal). Can be filtered.
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

                    // noindex: WordPress core does not provide a per-post noindex flag by default.
                    // So we keep it filter-driven, core-first via your integrations later.
                    if ( true === apply_filters( 'vb_sg_is_noindex_post', false, $post_id ) ) {
                        continue;
                    }

                    $lastmod = get_post_modified_time( 'c', true, $post_id ); // GMT ISO8601.

                    $results[] = array(
                        'loc'     => $loc,
                        'lastmod' => is_string( $lastmod ) ? $lastmod : '',
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

        // 4) Public taxonomies (terms with count > 0), deterministic order by taxonomy name + term_id.
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

                // noindex: filter-driven (core has no simple per-term noindex flag).
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

        // Known non-content/system post types to exclude.
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

        /**
         * Filter: customize which post types are included in sitemaps.
         *
         * @param array<int,string> $types
         */
        $types = (array) apply_filters( 'vb_sg_included_post_types', $types );

        // Normalize to strings, unique.
        $types = array_values( array_unique( array_filter( $types, 'is_string' ) ) );

        return $types;
    }
}
