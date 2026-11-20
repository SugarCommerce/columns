<?php
/**
 * Row Action Route
 *
 * @package SugarCommerce\Columns
 */

declare( strict_types=1 );

namespace SugarCommerce\Columns\Rest;

use SugarCommerce\Columns\Abstracts\RowActions;
use SugarCommerce\Columns\Utils\Runtime;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * The REST route a row action with `ajax` runs through.
 *
 * One route for this copy of the library, under its own namespace
 * (Runtime::rest_namespace(), named by sugarcommerce/plugin's Build), so a
 * second plugin bundling a prefixed copy has a route of its own and each
 * answers only for its own actions. The link names the table and the
 * action; the permission check is the action's own capability and its
 * `permission_callback` for that row. The script sends it with
 * @wordpress/api-fetch, which carries the REST nonce and renews it.
 *
 * Actions are looked up in what was registered on this request, so a
 * plugin registers its row actions on `init` -- a REST request runs no
 * `admin_init`.
 *
 * @since 1.0.0
 */
final class RowActionRoute {

	/**
	 * The object types a row action belongs to.
	 *
	 * @var string[]
	 */
	public const array OBJECT_TYPES = [ 'post', 'user', 'term', 'comment' ];

	/**
	 * Whether the route is hooked.
	 *
	 * @var bool
	 */
	private static bool $booted = false;

	/**
	 * The route, without the leading slash: what api-fetch's `path` takes.
	 *
	 * @return string
	 */
	public static function path(): string {
		return Runtime::rest_namespace() . '/row-action';
	}

	/**
	 * Hook the route, once.
	 *
	 * @return void
	 */
	public static function boot(): void {
		if ( self::$booted ) {
			return;
		}

		self::$booted = true;

		add_action( 'rest_api_init', [ self::class, 'register' ] );
	}

	/**
	 * Register the route.
	 *
	 * @return void
	 */
	public static function register(): void {
		register_rest_route(
			Runtime::rest_namespace(),
			'/row-action',
			[
				'methods'             => 'POST',
				'callback'            => [ self::class, 'run' ],
				'permission_callback' => [ self::class, 'permitted' ],
				'args'                => [
					'object_type'    => [
						'type'     => 'string',
						'required' => true,
						'enum'     => self::OBJECT_TYPES,
					],
					'object_subtype' => [
						'type'     => 'string',
						'required' => true,
						'pattern'  => '^[a-z0-9_-]+$',
					],
					'action_key'     => [
						'type'     => 'string',
						'required' => true,
						'pattern'  => '^[a-z0-9_-]+$',
					],
					'object_id'      => [
						'type'     => 'integer',
						'required' => true,
						'minimum'  => 1,
					],
					'options'        => [
						'type'    => 'object',
						'default' => [],
					],
				],
			]
		);
	}

	/**
	 * Whether the signed-in user may run this action on this row.
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @return bool|WP_Error
	 */
	public static function permitted( WP_REST_Request $request ): bool|WP_Error {
		$action = self::action( $request );

		if ( null === $action ) {
			return new WP_Error( 'invalid_action', __( 'Invalid action', 'sugarcommerce' ), [ 'status' => 404 ] );
		}

		if ( ! RowActions::permits( $action, (int) $request->get_param( 'object_id' ) ) ) {
			return new WP_Error( 'rest_forbidden', __( 'Insufficient permissions', 'sugarcommerce' ), [ 'status' => 403 ] );
		}

		return true;
	}

	/**
	 * Run the action, and answer with what its callback said.
	 *
	 * The callback is given the row's id and `options`; it answers with an
	 * array (`message`, `reload` and anything the page wants), a WP_Error,
	 * or anything else for a plain success.
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function run( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$action = self::action( $request );

		if ( null === $action || ! is_callable( $action['callback'] ?? null ) ) {
			return new WP_Error( 'invalid_callback', __( 'Invalid callback function', 'sugarcommerce' ), [ 'status' => 500 ] );
		}

		$options = $request->get_param( 'options' );
		$options = is_array( $options ) ? $options : [];

		try {
			$result = call_user_func( $action['callback'], (int) $request->get_param( 'object_id' ), $options );
		} catch ( Throwable $error ) {
			// The callback's exception is somebody else's -- a query, a path,
			// an API's reply -- so it reaches the browser only where core
			// shows errors.
			return new WP_Error(
				'row_action_failed',
				defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY
					? $error->getMessage()
					: __( 'The action could not be completed.', 'sugarcommerce' ),
				[ 'status' => 500 ]
			);
		}

		if ( $result instanceof WP_Error ) {
			return $result;
		}

		if ( ! is_array( $result ) ) {
			$result = [ 'success' => true ];
		}

		if ( ! isset( $result['message'] ) ) {
			$result['message'] = __( 'Action completed successfully', 'sugarcommerce' );
		}

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * The action a request names, if it is one that runs through here.
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @return array|null
	 */
	private static function action( WP_REST_Request $request ): ?array {
		$actions = RowActions::get_actions(
			(string) $request->get_param( 'object_type' ),
			(string) $request->get_param( 'object_subtype' )
		);
		$action  = $actions[ (string) $request->get_param( 'action_key' ) ] ?? null;

		return is_array( $action ) && ! empty( $action['ajax'] ) ? $action : null;
	}
}
