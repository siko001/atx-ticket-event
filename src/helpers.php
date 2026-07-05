<?php
/**
 * Public helper functions for theme and block developers who want to build
 * custom event displays (sliders, parallax, bespoke layouts) on top of the
 * synced data — without editing plugin files or querying meta by hand.
 *
 * @package AtxDigitalTicketing
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'atx_ticketing_get_event' ) ) {
	/**
	 * The full synced payload for one event: occurrences, ticket_types,
	 * speakers, sponsors, registration_questions, venue, image_url,
	 * gallery_urls, checkout_url, etc. — plus the WordPress post id under
	 * 'post_id'. Defaults to the current post in the loop.
	 *
	 * @param int|WP_Post|null $post Post, post id, or null for the current post.
	 * @return array<string, mixed> Empty array when the post is not an event.
	 */
	function atx_ticketing_get_event( $post = null ): array {
		$post = get_post( $post );

		if ( ! $post instanceof WP_Post || \AtxDigitalTicketing\PostTypes\EventPostType::POST_TYPE !== $post->post_type ) {
			return array();
		}

		$payload            = \AtxDigitalTicketing\PostTypes\EventPostType::payload( $post->ID );
		$payload['post_id'] = $post->ID;

		return $payload;
	}
}

if ( ! function_exists( 'atx_ticketing_get_events' ) ) {
	/**
	 * Query synced events for a custom loop. Accepts: scope (upcoming|past|all),
	 * category (slug), limit, orderby (date|title), order (ASC|DESC).
	 * Remember to call wp_reset_postdata() after your loop.
	 *
	 * @param array<string, mixed> $args Query options.
	 * @return WP_Query
	 */
	function atx_ticketing_get_events( array $args = array() ): WP_Query {
		return \AtxDigitalTicketing\Frontend\Shortcodes::query( $args );
	}
}
