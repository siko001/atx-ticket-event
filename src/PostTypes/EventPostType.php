<?php
/**
 * The mirrored event custom post type and taxonomy.
 *
 * @package AtxDigitalTicketing
 */

namespace AtxDigitalTicketing\PostTypes;

use AtxDigitalTicketing\Plugin;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the atx_event CPT, its structured meta and the mirrored
 * category taxonomy. Laravel owns the data: the CPT is not creatable or
 * content-editable in wp-admin.
 */
final class EventPostType {

	public const POST_TYPE = 'atx_event';
	public const TAXONOMY  = 'atx_event_category';

	public static function register(): void {
		register_post_type(
			self::POST_TYPE,
			[
				'labels'       => [
					'name'          => __( 'Events', 'atx-digital-ticketing-connect' ),
					'singular_name' => __( 'Event', 'atx-digital-ticketing-connect' ),
				],
				'public'       => true,
				'has_archive'  => 'events',
				'rewrite'      => [ 'slug' => 'events' ],
				'menu_icon'    => 'dashicons-tickets-alt',
				'show_in_rest' => true,
				// Content is owned by Laravel; the editor is intentionally absent.
				'supports'     => [ 'title', 'thumbnail' ],
				'capabilities' => [
					// Events can only be created by the incoming webhook.
					'create_posts' => 'do_not_allow',
				],
				'map_meta_cap' => true,
			]
		);

		register_taxonomy(
			self::TAXONOMY,
			self::POST_TYPE,
			[
				'labels'       => [
					'name'          => __( 'Event categories', 'atx-digital-ticketing-connect' ),
					'singular_name' => __( 'Event category', 'atx-digital-ticketing-connect' ),
				],
				'public'       => true,
				'hierarchical' => true,
				'show_in_rest' => true,
				'rewrite'      => [ 'slug' => 'event-category' ],
			]
		);

		foreach ( self::structured_meta_keys() as $key => $type ) {
			register_post_meta(
				self::POST_TYPE,
				$key,
				[
					'type'          => $type,
					'single'        => true,
					'show_in_rest'  => false,
					'auth_callback' => '__return_false',
				]
			);
		}
	}

	/**
	 * Structured meta for querying/sorting; nested display data (speakers,
	 * sponsors, ticket types, questions, occurrences) lives in _atx_payload.
	 *
	 * @return array<string, string>
	 */
	public static function structured_meta_keys(): array {
		return [
			'_atx_event_id'      => 'integer',
			'_atx_starts_at'     => 'string',
			'_atx_ends_at'       => 'string',
			'_atx_timezone'      => 'string',
			'_atx_max_capacity'  => 'integer',
			'_atx_is_recurring'  => 'boolean',
			'_atx_published_at'  => 'string',
			'_atx_venue_name'    => 'string',
			'_atx_venue_address' => 'string',
			'_atx_venue_lat'     => 'string',
			'_atx_venue_lng'     => 'string',
			'_atx_checkout_url'  => 'string',
			'_atx_status'        => 'string',
			'_atx_payload'       => 'string',
		];
	}

	public static function register_meta_boxes(): void {
		add_meta_box(
			'atx-ticketing-source',
			__( 'ATX Ticketing — event details', 'atx-digital-ticketing-connect' ),
			[ self::class, 'render_source_meta_box' ],
			self::POST_TYPE,
			'normal',
			'high'
		);
	}

	public static function render_source_meta_box( WP_Post $post ): void {
		$settings  = Plugin::settings();
		$event_id  = (int) get_post_meta( $post->ID, '_atx_event_id', true );
		$status    = (string) get_post_meta( $post->ID, '_atx_status', true );
		$lat       = (string) get_post_meta( $post->ID, '_atx_venue_lat', true );
		$lng       = (string) get_post_meta( $post->ID, '_atx_venue_lng', true );
		$payload   = self::payload( $post->ID );
		$admin_url = $settings['admin_url'];

		$rows = [
			__( 'Status', 'atx-digital-ticketing-connect' ) => '' !== $status ? $status : 'unknown',
			__( 'Event ID', 'atx-digital-ticketing-connect' ) => $event_id > 0 ? (string) $event_id : '',
			__( 'Timezone', 'atx-digital-ticketing-connect' ) => (string) get_post_meta( $post->ID, '_atx_timezone', true ),
			__( 'Capacity', 'atx-digital-ticketing-connect' ) => (string) get_post_meta( $post->ID, '_atx_max_capacity', true ),
			__( 'Recurring', 'atx-digital-ticketing-connect' ) => get_post_meta( $post->ID, '_atx_is_recurring', true ) ? __( 'Yes', 'atx-digital-ticketing-connect' ) : __( 'No', 'atx-digital-ticketing-connect' ),
			__( 'Next date', 'atx-digital-ticketing-connect' ) => (string) get_post_meta( $post->ID, '_atx_starts_at', true ),
			__( 'Venue', 'atx-digital-ticketing-connect' ) => (string) get_post_meta( $post->ID, '_atx_venue_name', true ),
			__( 'Address', 'atx-digital-ticketing-connect' ) => (string) get_post_meta( $post->ID, '_atx_venue_address', true ),
			__( 'Latitude', 'atx-digital-ticketing-connect' ) => $lat,
			__( 'Longitude', 'atx-digital-ticketing-connect' ) => $lng,
			__( 'Dates synced', 'atx-digital-ticketing-connect' ) => (string) count( is_array( $payload['occurrences'] ?? null ) ? $payload['occurrences'] : [] ),
			__( 'Ticket types', 'atx-digital-ticketing-connect' ) => (string) count( is_array( $payload['ticket_types'] ?? null ) ? $payload['ticket_types'] : [] ),
			__( 'Speakers', 'atx-digital-ticketing-connect' ) => (string) count( is_array( $payload['speakers'] ?? null ) ? $payload['speakers'] : [] ),
			__( 'Sponsors', 'atx-digital-ticketing-connect' ) => (string) count( is_array( $payload['sponsors'] ?? null ) ? $payload['sponsors'] : [] ),
		];

		echo '<p>' . esc_html__( 'This event is managed in the ATX Digital admin platform. Changes made here will be overwritten by the next sync.', 'atx-digital-ticketing-connect' ) . '</p>';

		echo '<style>
			.atx-meta-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 0; border: 1px solid #dcdcde; border-radius: 4px; overflow: hidden; }
			.atx-meta-grid__cell { padding: 10px 14px; border-bottom: 1px solid #f0f0f1; border-right: 1px solid #f0f0f1; }
			.atx-meta-grid__label { display: block; font-size: 11px; text-transform: uppercase; letter-spacing: 0.04em; color: #646970; margin-bottom: 2px; }
			.atx-meta-grid__value { font-size: 14px; font-weight: 500; word-break: break-word; }
			.atx-meta-actions { margin-top: 12px; display: flex; gap: 8px; flex-wrap: wrap; }
		</style>';

		echo '<div class="atx-meta-grid">';

		foreach ( $rows as $label => $value ) {
			if ( '' === $value ) {
				continue;
			}

			echo '<div class="atx-meta-grid__cell"><span class="atx-meta-grid__label">' . esc_html( $label ) . '</span><span class="atx-meta-grid__value">' . esc_html( $value ) . '</span></div>';
		}

		echo '</div>';

		echo '<div class="atx-meta-actions">';

		if ( '' !== $admin_url && $event_id > 0 ) {
			$url = trailingslashit( $admin_url ) . 'events/' . $event_id . '/edit';
			echo '<a class="button button-primary" href="' . esc_url( $url ) . '" target="_blank" rel="noopener">'
				. esc_html__( 'Edit in ATX admin ↗', 'atx-digital-ticketing-connect' ) . '</a>';
		}

		if ( '' !== $lat && '' !== $lng ) {
			$map_url = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $lat . ',' . $lng );
			echo '<a class="button" href="' . esc_url( $map_url ) . '" target="_blank" rel="noopener">'
				. esc_html__( 'View on Google Maps ↗', 'atx-digital-ticketing-connect' ) . '</a>';
		}

		echo '</div>';
	}

	/**
	 * Find a mirrored post by its Laravel-side event id.
	 */
	public static function find_by_event_id( int $event_id ): ?WP_Post {
		$posts = get_posts(
			[
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'meta_key'       => '_atx_event_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $event_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			]
		);

		return $posts[0] ?? null;
	}

	/**
	 * Decoded sync payload for a mirrored event post.
	 *
	 * @return array<string, mixed>
	 */
	public static function payload( int $post_id ): array {
		$raw     = (string) get_post_meta( $post_id, '_atx_payload', true );
		$decoded = json_decode( $raw, true );

		return is_array( $decoded ) ? $decoded : [];
	}
}
