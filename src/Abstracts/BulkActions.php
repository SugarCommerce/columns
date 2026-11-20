<?php
/**
 * Bulk Actions Abstract Class
 *
 * @package     SugarCommerce\Columns
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

namespace SugarCommerce\Columns\Abstracts;

use SugarCommerce\Columns\Support\Screen;
use SugarCommerce\Columns\Utils\Runtime;
use Exception;
use Throwable;

abstract class BulkActions {

	/**
	 * Object type constant (e.g., 'post', 'user', 'term', 'comment', 'attachment')
	 */
	protected const string OBJECT_TYPE = '';

	/**
	 * Object type
	 *
	 * @var string
	 */
	protected string $object_type;

	/**
	 * Object subtype (e.g., post type, taxonomy)
	 *
	 * @var string
	 */
	protected string $object_subtype;

	/**
	 * Registered bulk actions storage
	 *
	 * @var array<string, array<string, array<string, mixed>>>
	 */
	protected static array $actions = [];

	/**
	 * The tables whose hooks are already attached.
	 *
	 * One set of hooks handles everything registered for a table. Two
	 * registrations for one table attached two `handle_bulk_actions-`
	 * filters, and each ran the chosen action's callback -- every selected
	 * row was processed twice.
	 *
	 * @var array<string, array<string, bool>>
	 */
	protected static array $hooked = [];

	/**
	 * BulkActions constructor.
	 *
	 * @param array  $actions        Bulk actions configuration.
	 * @param string $object_subtype Object subtype (e.g., 'post', 'page', 'category').
	 *
	 * @throws Exception If OBJECT_TYPE is not defined.
	 */
	public function __construct( array $actions, string $object_subtype ) {
		if ( empty( static::OBJECT_TYPE ) ) {
			throw new Exception( 'Child class must define OBJECT_TYPE constant.' );
		}

		$this->object_type    = static::OBJECT_TYPE;
		$this->object_subtype = $object_subtype;
		$this->add_actions( $actions );

		// Registered now rather than deferred to admin_init. add_filter() only
		// has to happen before the hook fires, and deferring introduced a
		// case that then had to be guarded against — something registering
		// *during* admin_init would attach to a hook that had already run and
		// never load at all. The columns half never deferred, and now that
		// these are one library they should not differ on something this
		// basic.
		if ( ! isset( self::$hooked[ $this->object_type ][ $this->object_subtype ] ) ) {
			self::$hooked[ $this->object_type ][ $this->object_subtype ] = true;

			$this->load_hooks();
		}
	}

	/**
	 * Add bulk actions to the registry.
	 *
	 * @param array $actions Bulk actions configuration.
	 *
	 * @return void
	 * @throws Exception If an action key is invalid.
	 */
	public function add_actions( array $actions ): void {
		$default_action = [
			'label'      => '',
			'callback'   => null,
			'capability' => 'manage_options',
		];

		foreach ( $actions as $key => $action ) {
			if ( ! is_string( $key ) || empty( $key ) ) {
				throw new Exception( 'Invalid action key. It must be a non-empty string.' );
			}

			self::$actions[ $this->object_type ][ $this->object_subtype ][ $key ] = wp_parse_args( $action, $default_action );
		}
	}

	/**
	 * Get all registered actions for a specific object type and subtype.
	 *
	 * @param string $object_type    Object type.
	 * @param string $object_subtype Object subtype.
	 *
	 * @return array<string, mixed> Registered actions.
	 */
	public static function get_actions( string $object_type, string $object_subtype ): array {
		return self::$actions[ $object_type ][ $object_subtype ] ?? [];
	}

	/**
	 * Attach to the table's own hooks.
	 *
	 * Core names them from the screen id -- `bulk_actions-edit-post`,
	 * `bulk_actions-users`, `bulk_actions-upload` -- which is the one thing
	 * that differs between the tables.
	 *
	 * @return void
	 */
	public function load_hooks(): void {
		$screen = Screen::id( $this->object_type, $this->object_subtype );

		add_filter( "bulk_actions-{$screen}", [ $this, 'register_bulk_actions' ] );
		add_filter( "handle_bulk_actions-{$screen}", [ $this, 'handle_bulk_action' ], 10, 3 );
		add_action( 'admin_notices', [ $this, 'display_admin_notice' ] );
	}

	/**
	 * Register bulk actions in the dropdown.
	 *
	 * @param array $bulk_actions Existing bulk actions.
	 *
	 * @return array Modified bulk actions.
	 */
	public function register_bulk_actions( array $bulk_actions ): array {
		$actions = self::get_actions( $this->object_type, $this->object_subtype );

		foreach ( $actions as $key => $action ) {
			if ( ! empty( $action['capability'] ) && ! current_user_can( $action['capability'] ) ) {
				continue;
			}

			// Escaped here, because core does not. WP_List_Table::bulk_actions()
			// escapes the option's value and echoes the label raw
			// (class-wp-list-table.php:47), so a label built from anything a
			// user typed lands in the page as markup. The columns half has
			// always escaped its labels; this one did not.
			$bulk_actions[ $key ] = esc_html( (string) $action['label'] );
		}

		return $bulk_actions;
	}

	/**
	 * Handle bulk action execution.
	 *
	 * @param string $redirect_to Redirect URL.
	 * @param string $doaction    Current action.
	 * @param array  $object_ids  Selected object IDs.
	 *
	 * @return string Modified redirect URL.
	 */
	public function handle_bulk_action( string $redirect_to, string $doaction, array $object_ids ): string {
		$actions = self::get_actions( $this->object_type, $this->object_subtype );

		// Check if this is our action
		if ( ! isset( $actions[ $doaction ] ) ) {
			return $redirect_to;
		}

		$action = $actions[ $doaction ];

		// Check capability
		if ( ! empty( $action['capability'] ) && ! current_user_can( $action['capability'] ) ) {
			return $redirect_to;
		}

		// Check for callback
		if ( empty( $action['callback'] ) || ! is_callable( $action['callback'] ) ) {
			return $redirect_to;
		}

		$object_ids = array_map( 'intval', $object_ids );

		try {
			// Execute callback
			$result = call_user_func( $action['callback'], $object_ids );

			// Handle response
			if ( is_array( $result ) ) {
				if ( isset( $result['message'] ) ) {
					$redirect_to = add_query_arg( self::arg( 'notice' ), self::keep_message( (string) $result['message'] ), $redirect_to );
				}
				if ( isset( $result['success'] ) && ! $result['success'] ) {
					$redirect_to = add_query_arg( self::arg( 'error' ), '1', $redirect_to );
				}
			}

			// Add count
			$redirect_to = add_query_arg( self::arg( 'count' ), count( $object_ids ), $redirect_to );
			$redirect_to = add_query_arg( self::arg( 'done' ), $doaction, $redirect_to );

		} catch ( Throwable $e ) {
			// The callback's exception is somebody else's -- a query, a path --
			// so its words are shown only where core shows errors.
			$redirect_to = add_query_arg(
				[
					self::arg( 'error' )  => '1',
					self::arg( 'notice' ) => self::keep_message(
						defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY
							? $e->getMessage()
							: __( 'The action could not be completed.', 'sugarcommerce' )
					),
				],
				$redirect_to
			);
		}

		return $redirect_to;
	}

	/**
	 * Keep an action's message for the page it sends the user back to, for
	 * this user only, and hand back the token the address carries.
	 *
	 * The message used to travel in the address itself, so a link anyone
	 * could write -- `bulk_action_message=Your account is locked, call…` --
	 * put their words in an admin notice on the list.
	 *
	 * @param string $message What to say.
	 *
	 * @return string The token.
	 */
	protected static function keep_message( string $message ): string {
		$token = bin2hex( random_bytes( 8 ) );

		set_transient( self::message_key( $token ), $message, 5 * MINUTE_IN_SECONDS );

		return $token;
	}

	/**
	 * The transient a token names, for the signed-in user only.
	 *
	 * @param string $token The token.
	 *
	 * @return string
	 */
	protected static function message_key( string $token ): string {
		return Runtime::key( 'bulk_' . get_current_user_id() . '_' . sanitize_key( $token ) );
	}

	/**
	 * A query argument the redirect after an action carries, named for this
	 * build: `sugarcommerce_columns_bulk_done` unprefixed.
	 *
	 * Core's list screens are shared. Two plugins each bundling a copy and
	 * both adding a bulk action to the posts list would otherwise read each
	 * other's `bulk_action_done` -- and one copy's token, looked up in its
	 * own transients, told the other nothing.
	 *
	 * @param string $name done, count, error or notice.
	 *
	 * @return string
	 */
	public static function arg( string $name ): string {
		return Runtime::key( 'bulk_' . $name );
	}

	/**
	 * Display admin notice after bulk action.
	 *
	 * @return void
	 */
	public function display_admin_notice(): void {
		if ( empty( $_GET[ self::arg( 'done' ) ] ) ) {
			return;
		}

		// Only on this table. Every registered table hooks admin_notices, and
		// an action key registered on two of them -- one array for `post`
		// and `page` -- printed the notice once for each.
		if ( ! Screen::is( $this->object_type, $this->object_subtype ) ) {
			return;
		}

		$action_key = sanitize_key( $_GET[ self::arg( 'done' ) ] );
		$count      = isset( $_GET[ self::arg( 'count' ) ] ) ? absint( $_GET[ self::arg( 'count' ) ] ) : 0;
		$is_error   = isset( $_GET[ self::arg( 'error' ) ] );
		// The message is the one this user's action kept, named by its
		// token; words in the address are never shown.
		$token   = isset( $_GET[ self::arg( 'notice' ) ] ) ? sanitize_key( wp_unslash( $_GET[ self::arg( 'notice' ) ] ) ) : '';
		$message = '' === $token ? '' : sanitize_text_field( (string) get_transient( self::message_key( $token ) ) );

		$actions = self::get_actions( $this->object_type, $this->object_subtype );

		if ( ! isset( $actions[ $action_key ] ) ) {
			return;
		}

		// Default message if none provided
		if ( '' === $message ) {
			$message = sprintf(
				/* translators: %d: how many items the bulk action processed */
				_n( '%d item processed.', '%d items processed.', $count, 'sugarcommerce' ),
				$count
			);
		}

		// Core's markup, so its wp_admin_notice_args and _markup filters
		// apply. Core does not escape the message; it is escaped here.
		wp_admin_notice(
			esc_html( $message ),
			[
				'type'        => $is_error ? 'error' : 'success',
				'dismissible' => true,
			]
		);
	}
}
