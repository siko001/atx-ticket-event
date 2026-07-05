<?php
/**
 * Plugin Name:       ATX Custom Blocks (example)
 * Description:       Reference example: build your OWN blocks and displays on top
 *                    of the ATX Digital Ticketing Connect data API. Copy this
 *                    folder into wp-content/plugins (or a child theme) and adapt.
 * Version:           1.0.0
 * Requires PHP:      8.1
 * Author:            You
 * License:           GPL-2.0-or-later
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * WHY THIS IS SAFE
 * This is YOUR code, separate from the ticketing plugin, so plugin updates never
 * overwrite it and syncs never touch it. It only uses the ticketing plugin's
 * PUBLIC API:
 *
 *   atx_ticketing_get_event( $post = null ) : array
 *       Full payload for one event — occurrences, ticket_types, speakers,
 *       sponsors, registration_questions, venue, image_url, gallery_urls,
 *       checkout_url, and 'post_id'. Defaults to the current post.
 *
 *   atx_ticketing_get_events( array $args ) : WP_Query
 *       A custom loop. $args: scope (upcoming|past|all), category (slug),
 *       limit, orderby (date|title), order (ASC|DESC).
 *
 *   filter  'atx_ticketing_event_payload' ( $payload, $post_id )
 *   actions 'atx_ticketing_before_single_event' / '_after_single_event' ( $event, $post )
 *           'atx_ticketing_before_events'       / '_after_events'        ( $query, $scope )
 *
 * Structured meta is also readable directly, e.g.:
 *   get_post_meta( $post_id, '_atx_starts_at', true )      // ISO datetime of next date
 *   get_post_meta( $post_id, '_atx_starts_at_ts', true )   // unix timestamp (sortable)
 *   get_post_meta( $post_id, '_atx_venue_name', true )
 *   get_post_meta( $post_id, '_atx_status', true )         // published | cancelled
 *   get_post_meta( $post_id, '_atx_gallery_ids', true )    // attachment IDs (array)
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * @package AtxCustomBlocksExample
 */

defined( 'ABSPATH' ) || exit;

const ATX_EXAMPLE_VER = '1.0.0';

/**
 * Bail with an admin notice if the ticketing plugin (and its API) is not active.
 */
function atx_example_boot(): void {
	if ( ! function_exists( 'atx_ticketing_get_event' ) ) {
		add_action(
			'admin_notices',
			static function (): void {
				echo '<div class="notice notice-warning"><p>'
					. esc_html__( 'ATX Custom Blocks (example) needs the "ATX Digital Ticketing Connect" plugin active.', 'atx-custom-blocks-example' )
					. '</p></div>';
			}
		);

		return;
	}

	add_action( 'init', 'atx_example_register_block' );
	add_action( 'wp_enqueue_scripts', 'atx_example_register_frontend_assets' );
}
add_action( 'plugins_loaded', 'atx_example_boot' );

/**
 * Register the editor script and the server-rendered block. Server rendering
 * (a PHP render_callback) means no build step and the markup logic lives once.
 */
function atx_example_register_block(): void {
	wp_register_script(
		'atx-example-block',
		plugins_url( 'assets/block-editor.js', __FILE__ ),
		[ 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-server-side-render', 'wp-api-fetch', 'wp-i18n' ],
		ATX_EXAMPLE_VER,
		true
	);

	register_block_type(
		'atx-example/featured-parallax',
		[
			'api_version'     => 3,
			'editor_script'   => 'atx-example-block',
			'render_callback' => 'atx_example_render_featured_parallax',
			'attributes'      => [
				'postId'  => [
					'type'    => 'number',
					'default' => 0,
				],
				'height'  => [
					'type'    => 'number',
					'default' => 460,
				],
				'overlay' => [
					'type'    => 'boolean',
					'default' => true,
				],
			],
		]
	);
}

/**
 * Register the frontend CSS/JS. Enqueued only when the block actually renders.
 */
function atx_example_register_frontend_assets(): void {
	wp_register_style(
		'atx-example-parallax',
		plugins_url( 'assets/parallax.css', __FILE__ ),
		[],
		ATX_EXAMPLE_VER
	);

	wp_register_script(
		'atx-example-parallax',
		plugins_url( 'assets/parallax.js', __FILE__ ),
		[],
		ATX_EXAMPLE_VER,
		true
	);
}

/**
 * Block render callback: a featured event as a parallax hero.
 *
 * @param array<string, mixed> $attributes Block attributes.
 */
function atx_example_render_featured_parallax( array $attributes ): string {
	$post_id = (int) ( $attributes['postId'] ?? 0 );

	// Pull the event payload — an explicit pick, or the next upcoming event.
	if ( $post_id > 0 ) {
		$event = atx_ticketing_get_event( $post_id );
	} else {
		$query = atx_ticketing_get_events( [ 'scope' => 'upcoming', 'limit' => 1 ] );
		$event = $query->have_posts() ? atx_ticketing_get_event( $query->posts[0] ) : [];
		wp_reset_postdata();
	}

	if ( ! $event ) {
		return '<p>' . esc_html__( 'No event to feature yet.', 'atx-custom-blocks-example' ) . '</p>';
	}

	// The synced main image, falling back to the WordPress featured image.
	$image = (string) ( $event['image_url'] ?? '' );
	if ( '' === $image ) {
		$image = (string) get_the_post_thumbnail_url( (int) $event['post_id'], 'large' );
	}

	$height    = max( 200, min( 900, (int) ( $attributes['height'] ?? 460 ) ) );
	$overlay   = ! empty( $attributes['overlay'] );
	$title     = get_the_title( (int) $event['post_id'] );
	$permalink = (string) get_permalink( (int) $event['post_id'] );
	$starts_at = (string) get_post_meta( (int) $event['post_id'], '_atx_starts_at', true );
	$venue     = is_array( $event['venue'] ?? null ) ? (string) ( $event['venue']['name'] ?? '' ) : '';
	$timestamp = $starts_at ? strtotime( $starts_at ) : false;

	wp_enqueue_style( 'atx-example-parallax' );
	wp_enqueue_script( 'atx-example-parallax' );

	ob_start();
	?>
	<section class="atx-ex-parallax<?php echo $overlay ? ' has-overlay' : ''; ?>"
		data-atx-parallax
		style="--atx-ex-height:<?php echo esc_attr( (string) $height ); ?>px;">
		<?php if ( '' !== $image ) : ?>
			<div class="atx-ex-parallax__bg" data-atx-parallax-layer
				style="background-image:url('<?php echo esc_url( $image ); ?>');"></div>
		<?php endif; ?>

		<div class="atx-ex-parallax__inner">
			<h2 class="atx-ex-parallax__title">
				<a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $title ); ?></a>
			</h2>

			<?php if ( false !== $timestamp ) : ?>
				<p class="atx-ex-parallax__meta">
					<?php echo esc_html( wp_date( get_option( 'date_format' ) . ' — ' . get_option( 'time_format' ), $timestamp ) ); ?>
					<?php if ( '' !== $venue ) : ?>
						· <?php echo esc_html( $venue ); ?>
					<?php endif; ?>
				</p>
			<?php endif; ?>

			<a class="atx-ex-parallax__cta" href="<?php echo esc_url( $permalink ); ?>">
				<?php esc_html_e( 'Get tickets', 'atx-custom-blocks-example' ); ?>
			</a>
		</div>
	</section>
	<?php
	return (string) ob_get_clean();
}

/*
|------------------------------------------------------------------------------
| MORE EXAMPLES (uncomment to try) — the same primitives, other placements.
|------------------------------------------------------------------------------
*/

/**
 * Example A — a shortcode built from a custom loop: [atx_example_next_three].
 * Shows how to query events yourself and read structured meta.
 */
// add_shortcode( 'atx_example_next_three', function (): string {
// 	$q = atx_ticketing_get_events( [ 'scope' => 'upcoming', 'limit' => 3 ] );
// 	if ( ! $q->have_posts() ) {
// 		return '<p>' . esc_html__( 'Nothing coming up.', 'atx-custom-blocks-example' ) . '</p>';
// 	}
// 	$out = '<ul class="atx-ex-list">';
// 	while ( $q->have_posts() ) {
// 		$q->the_post();
// 		$when = (string) get_post_meta( get_the_ID(), '_atx_starts_at', true );
// 		$out .= '<li><a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a>'
// 			. ( $when ? ' — ' . esc_html( wp_date( get_option( 'date_format' ), strtotime( $when ) ) ) : '' )
// 			. '</li>';
// 	}
// 	wp_reset_postdata();
// 	return $out . '</ul>';
// } );

/**
 * Example B — inject markup on every single event page WITHOUT copying the
 * template, using the action hook the plugin fires.
 */
// add_action( 'atx_ticketing_before_single_event', function ( array $event, WP_Post $post ): void {
// 	echo '<p class="atx-ex-ribbon">' . esc_html__( 'Presented by Your Brand', 'atx-custom-blocks-example' ) . '</p>';
// }, 10, 2 );

/**
 * Example C — add a derived field to every payload via the filter, so all
 * displays (built-in and custom) can use it.
 */
// add_filter( 'atx_ticketing_event_payload', function ( array $payload, int $post_id ): array {
// 	$ts = (int) get_post_meta( $post_id, '_atx_starts_at_ts', true );
// 	$payload['is_this_week'] = $ts > 0 && $ts <= ( time() + WEEK_IN_SECONDS );
// 	return $payload;
// }, 10, 2 );
