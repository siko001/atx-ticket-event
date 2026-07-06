<?php
/**
 * WP-CLI commands for ATX Digital Ticketing Connect.
 *
 * @package AtxDigitalTicketing
 */

namespace AtxDigitalTicketing\Cli;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the `wp atx-ticketing` command family.
 *
 * Only loaded under WP-CLI. Mirrors the Plugins-screen prompt for the command
 * line: deactivating never removes data (that is core WP-CLI behaviour), so the
 * keep-or-delete choice lives on the uninstall command, which is what actually
 * runs uninstall.php.
 */
final class Commands {

	public static function register(): void {
		if ( defined( 'WP_CLI' ) && \WP_CLI ) {
			\WP_CLI::add_command( 'atx-ticketing', self::class );
		}
	}

	/**
	 * Deactivates and deletes the plugin, choosing what happens to stored data.
	 *
	 * This is the command-line equivalent of the prompt shown when you click
	 * "Deactivate" on the Plugins screen. `wp plugin deactivate` on its own
	 * never removes data — only deleting the plugin (which runs uninstall.php)
	 * can, and only when you ask it to here.
	 *
	 * ## OPTIONS
	 *
	 * [--data=<mode>]
	 * : What should happen to the mirrored events, media, categories and settings.
	 * ---
	 * options:
	 *   - keep
	 *   - delete
	 * ---
	 *
	 * [--yes]
	 * : Answer yes to the confirmation prompt (required for --data=delete in scripts).
	 *
	 * ## EXAMPLES
	 *
	 *     # Ask interactively what to do with the data, then remove the plugin.
	 *     $ wp atx-ticketing uninstall
	 *
	 *     # Remove the plugin but keep every event for a later reinstall (no re-sync).
	 *     $ wp atx-ticketing uninstall --data=keep
	 *
	 *     # Remove the plugin AND permanently delete all events, media and settings.
	 *     $ wp atx-ticketing uninstall --data=delete --yes
	 *
	 * @when after_wp_load
	 *
	 * @param array<int, string>    $args       Positional arguments (unused).
	 * @param array<string, string> $assoc_args Associative arguments.
	 */
	public function uninstall( array $args, array $assoc_args ): void {
		$mode = isset( $assoc_args['data'] ) ? strtolower( (string) $assoc_args['data'] ) : '';

		if ( ! in_array( $mode, [ 'keep', 'delete' ], true ) ) {
			\WP_CLI::log( 'What should happen to the events and data this site has stored?' );
			\WP_CLI::log( '  keep   — remove the plugin but keep events, media and settings (reinstall later, no re-sync)' );
			\WP_CLI::log( '  delete — remove the plugin AND permanently delete all events, media, categories and settings' );

			$mode = strtolower( trim( self::read_line( 'Type keep or delete: ' ) ) );
		}

		if ( ! in_array( $mode, [ 'keep', 'delete' ], true ) ) {
			\WP_CLI::error( 'Please choose "keep" or "delete".' );
		}

		$delete = 'delete' === $mode;

		if ( $delete ) {
			\WP_CLI::confirm( 'This permanently deletes ALL mirrored events, media and settings. Continue?', $assoc_args );
		}

		update_option( 'atx_ticketing_delete_data_on_uninstall', $delete ? 1 : 0 );

		$basename = plugin_basename( ATX_TICKETING_FILE );

		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		deactivate_plugins( $basename );

		// Delegate the file removal to core WP-CLI (runs uninstall.php, which
		// reads the option set above). A fresh subprocess is used so deleting
		// this plugin's own files mid-command is safe.
		$result = \WP_CLI::runcommand(
			'plugin delete ' . escapeshellarg( dirname( $basename ) ),
			[
				'exit_error' => false,
				'return'     => 'return_code',
			]
		);

		if ( 0 !== $result ) {
			\WP_CLI::error( 'The plugin was deactivated and your data preference was saved, but the files could not be removed. Run: wp plugin delete ' . dirname( $basename ) );
		}

		\WP_CLI::success(
			$delete
				? 'Plugin removed and all ATX Ticketing data permanently deleted.'
				: 'Plugin removed. Your ATX Ticketing events, media and settings were kept for a future reinstall.'
		);
	}

	/**
	 * Reads a single line from STDIN behind a prompt.
	 */
	private static function read_line( string $prompt ): string {
		fwrite( STDOUT, $prompt ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CLI prompt output.

		$line = fgets( STDIN );

		return false === $line ? '' : (string) $line;
	}
}
