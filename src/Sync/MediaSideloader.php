<?php
/**
 * Downloads synced event images into the WordPress media library.
 *
 * @package AtxDigitalTicketing
 */

namespace AtxDigitalTicketing\Sync;

defined( 'ABSPATH' ) || exit;

/**
 * Sideloads the event's main image (as the featured image) and gallery
 * images into wp-content/uploads, idempotently: a map of source URL →
 * attachment ID is kept in _atx_media_map so re-syncs reuse existing
 * attachments instead of downloading duplicates.
 */
final class MediaSideloader {

	/**
	 * @param array<string, mixed> $event Payload "event" object.
	 */
	public function sync( int $post_id, array $event ): void {
		$image_url    = (string) ( $event['image_url'] ?? '' );
		$gallery_urls = is_array( $event['gallery_urls'] ?? null ) ? array_filter( array_map( 'strval', $event['gallery_urls'] ) ) : [];

		$map = get_post_meta( $post_id, '_atx_media_map', true );
		$map = is_array( $map ) ? $map : [];

		// Main media: images become the featured image; videos are stored in
		// _atx_main_media_id and rendered by the single event template.
		if ( '' !== $image_url ) {
			$attachment_id = $this->attachment_for( $image_url, $post_id, $map );

			update_post_meta( $post_id, '_atx_main_media_id', $attachment_id );

			if ( $attachment_id > 0 && wp_attachment_is_image( $attachment_id ) ) {
				if ( (int) get_post_thumbnail_id( $post_id ) !== $attachment_id ) {
					set_post_thumbnail( $post_id, $attachment_id );
				}
			} elseif ( get_post_thumbnail_id( $post_id ) ) {
				delete_post_thumbnail( $post_id );
			}
		} else {
			delete_post_meta( $post_id, '_atx_main_media_id' );

			if ( get_post_thumbnail_id( $post_id ) ) {
				delete_post_thumbnail( $post_id );
			}
		}

		// Gallery (attachment IDs stored in payload order).
		$gallery_ids = [];

		foreach ( $gallery_urls as $url ) {
			$attachment_id = $this->attachment_for( $url, $post_id, $map );

			if ( $attachment_id > 0 ) {
				$gallery_ids[] = $attachment_id;
			}
		}

		update_post_meta( $post_id, '_atx_gallery_ids', $gallery_ids );
		update_post_meta( $post_id, '_atx_media_map', $map );
	}

	/**
	 * Existing attachment for a source URL, or a fresh sideload. Handles any
	 * media type (images and video), not just images.
	 *
	 * @param array<string, int> $map Source URL → attachment ID map (by reference).
	 */
	private function attachment_for( string $url, int $post_id, array &$map ): int {
		$existing = (int) ( $map[ $url ] ?? 0 );

		if ( $existing > 0 && 'attachment' === get_post_type( $existing ) ) {
			return $existing;
		}

		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp_file = download_url( $url, 60 );

		if ( is_wp_error( $tmp_file ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[atx-ticketing] Could not download ' . $url . ': ' . $tmp_file->get_error_message() );

			return 0;
		}

		$filename = wp_basename( (string) wp_parse_url( $url, PHP_URL_PATH ) );

		$attachment_id = media_handle_sideload(
			[
				'name'     => '' !== $filename ? $filename : 'atx-media',
				'tmp_name' => $tmp_file,
			],
			$post_id
		);

		if ( is_wp_error( $attachment_id ) ) {
			@unlink( $tmp_file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[atx-ticketing] Could not sideload ' . $url . ': ' . $attachment_id->get_error_message() );

			return 0;
		}

		$map[ $url ] = (int) $attachment_id;

		return (int) $attachment_id;
	}
}
