<?php
/**
 * Lightweight activity log stored in an option (capped ring buffer).
 *
 * @package AtxDigitalTicketing
 */

namespace AtxDigitalTicketing\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Sync ("Laravel traffic") and buy (checkout) activity, shown on the
 * Events → Settings → Logs tab. Mirrors the System → Logs screen in the
 * ATX admin. Capped at 200 entries, newest first.
 */
final class Logger {

	private const OPTION      = 'atx_ticketing_logs';
	private const MAX_ENTRIES = 200;

	public static function log( string $channel, string $message, string $level = 'info' ): void {
		$entries = self::entries();

		array_unshift(
			$entries,
			[
				'time'    => time(),
				'channel' => sanitize_key( $channel ),
				'level'   => sanitize_key( $level ),
				'message' => mb_substr( sanitize_text_field( $message ), 0, 500 ),
			]
		);

		update_option( self::OPTION, array_slice( $entries, 0, self::MAX_ENTRIES ), false );
	}

	/**
	 * @return array<int, array{time: int, channel: string, level: string, message: string}>
	 */
	public static function entries(): array {
		$stored = get_option( self::OPTION, [] );

		return is_array( $stored ) ? $stored : [];
	}

	public static function clear(): void {
		delete_option( self::OPTION );
	}
}
