<?php
/**
 * Row action tests.
 *
 * @package SugarCommerce\Columns
 */

declare( strict_types=1 );

namespace SugarCommerce\Columns\Tests;

use SugarCommerce\Columns\RowActions\Post;
use SugarCommerce\Columns\Utils\Runtime;
use PHPUnit\Framework\TestCase;
use SugarCommerce\Columns\Rest\RowActionRoute;
use WP_REST_Request;

/**
 * A row action is a link in a list table that does something to one row, and
 * the something runs through this copy's REST route.
 *
 * The route is named for the copy: two plugins bundling this library each
 * answer for their own actions, where one shared admin-ajax action used to
 * run both handlers and the first to find no match exited.
 */
final class RowActionsTest extends TestCase {

	/**
	 * Empty the stubbed WordPress and the static registry.
	 */
	protected function setUp(): void {
		rc_reset_globals();

		// Hooks attach when a table is registered, whether or not admin_init
		// has run; FrontDoorTest says so for both cases.
		$GLOBALS['rc_did']['admin_init'] = 1;

		$reflection = new \ReflectionClass( \SugarCommerce\Columns\Abstracts\RowActions::class );
		$actions    = $reflection->getProperty( 'actions' );
		$actions->setValue( null, array() );

		$enqueued = $reflection->getProperty( 'assets_enqueued' );
		$enqueued->setValue( null, false );

		( new \ReflectionProperty( RowActionRoute::class, 'booted' ) )->setValue( null, false );

		$_POST = array();
	}

	/**
	 * And again.
	 */
	protected function tearDown(): void {
		$_POST = array();
	}

	/**
	 * Register one AJAX action on the product post type.
	 *
	 * @param callable|null $callback What the action does.
	 *
	 * @return Post
	 */
	private function actions( ?callable $callback = null ): Post {
		return new Post(
			array(
				'resend' => array(
					'label'      => 'Resend',
					'ajax'       => true,
					'capability' => 'edit_posts',
					'callback'   => $callback ?? static fn( $id, $options ) => array( 'message' => 'done' ),
				),
			),
			'product'
		);
	}

	/**
	 * A request to the route for the registered action on row 7.
	 *
	 * @param array<string, mixed> $params What differs.
	 *
	 * @return WP_REST_Request
	 */
	private function request( array $params = array() ): WP_REST_Request {
		$request = new WP_REST_Request( 'POST', '/' );

		foreach ( array_merge(
			array(
				'object_type'    => 'post',
				'object_subtype' => 'product',
				'action_key'     => 'resend',
				'object_id'      => 7,
				'options'        => array(),
			),
			$params
		) as $key => $value ) {
			$request->set_param( $key, $value );
		}

		return $request;
	}

	/**
	 * An action runs through this build's own REST route, its arguments
	 * typed, and no admin-ajax action is hooked.
	 */
	public function test_the_action_runs_through_this_builds_route(): void {
		$this->actions();
		RowActionRoute::register();

		$route = $GLOBALS['rc_routes'][ Runtime::rest_namespace() . '/row-action' ] ?? null;

		$this->assertSame( 'sugarcommerce-columns/v1', Runtime::rest_namespace() );
		$this->assertNotNull( $route );
		$this->assertSame( 'POST', $route['methods'] );
		$this->assertSame( array( 'post', 'user', 'term', 'comment' ), $route['args']['object_type']['enum'] );
		$this->assertSame( 'integer', $route['args']['object_id']['type'] );
		$this->assertSame( 1, $route['args']['object_id']['minimum'] );
		$this->assertSame( '^[a-z0-9_-]+$', $route['args']['action_key']['pattern'] );
		$this->assertSame( 'object', $route['args']['options']['type'] );
		$this->assertSame( array(), preg_grep( '/^wp_ajax_/', array_keys( $GLOBALS['rc_hooks'] ?? array() ) ) );
		$this->assertContains( array( RowActionRoute::class, 'register' ), $GLOBALS['rc_hooks']['rest_api_init'] ?? array() );
	}

	/**
	 * The link carries its copy's settings and what the route needs, and no
	 * nonce: api-fetch sends the REST one.
	 *
	 * Each prefixed copy has a route of its own and loads the same script
	 * under its own handle, so the link names its copy's settings rather
	 * than one shared object owned by whichever printed last.
	 */
	public function test_the_link_carries_its_own_copy(): void {
		$instance = $this->actions();

		$link = implode( ' ', $instance->register_actions( array(), (object) array( 'ID' => 7 ), 7 ) );

		$this->assertStringContainsString( 'data-config="' . Runtime::handle() . '"', $link );
		$this->assertStringContainsString( 'data-object-type="post"', $link );
		$this->assertStringContainsString( 'data-object-subtype="product"', $link );
		$this->assertStringContainsString( 'data-action-key="resend"', $link );
		$this->assertStringContainsString( 'data-object-id="7"', $link );
		$this->assertStringNotContainsString( 'data-nonce', $link );
		$this->assertStringNotContainsString( 'data-ajax-action', $link );
		$this->assertSame( Runtime::rest_namespace() . '/row-action', \SugarCommerce\Columns\Abstracts\RowActions::script_config()['path'] );
	}

	/**
	 * The route runs the callback with the row and its options, and answers
	 * with what it said.
	 */
	public function test_the_route_runs_the_action(): void {
		$seen = null;

		$this->actions(
			function ( $id, $options ) use ( &$seen ) {
				$seen = array( $id, $options );

				return array( 'reload' => false );
			}
		);

		$this->assertTrue( RowActionRoute::permitted( $this->request( array( 'options' => array( 'reason' => 'expired' ) ) ) ) );

		$answer = RowActionRoute::run( $this->request( array( 'options' => array( 'reason' => 'expired' ) ) ) );

		$this->assertSame( array( 7, array( 'reason' => 'expired' ) ), $seen );
		$this->assertFalse( $answer->get_data()['reload'] );
		$this->assertSame( 'Action completed successfully', $answer->get_data()['message'] );
	}

	/**
	 * Without the capability, or past the action's own permission callback
	 * for that row, the route refuses before anything runs.
	 */
	public function test_the_capability_is_required(): void {
		$this->actions();

		$GLOBALS['rc_caps'] = array();

		$refused = RowActionRoute::permitted( $this->request() );

		$this->assertInstanceOf( \WP_Error::class, $refused );
		$this->assertSame( 403, $refused->get_error_data()['status'] );

		new Post(
			array(
				'only_eight' => array(
					'ajax'                => true,
					'capability'          => 'read',
					'permission_callback' => static fn( int $id ): bool => 8 === $id,
					'callback'            => static fn() => array(),
				),
			),
			'product'
		);

		$GLOBALS['rc_caps'] = array( 'read' );

		$this->assertInstanceOf( \WP_Error::class, RowActionRoute::permitted( $this->request( array( 'action_key' => 'only_eight' ) ) ) );
		$this->assertTrue( RowActionRoute::permitted( $this->request( array( 'action_key' => 'only_eight', 'object_id' => 8 ) ) ) );
	}

	/**
	 * An action that is not one, on a table it was not registered for, or
	 * that does not run through the route, is refused.
	 */
	public function test_an_unknown_action_is_refused(): void {
		$this->actions();

		new Post( array( 'link' => array( 'url' => 'https://example.test/', 'capability' => 'read' ) ), 'product' );

		foreach ( array(
			array( 'action_key' => 'nope' ),
			array( 'object_subtype' => 'page' ),
			array( 'object_type' => 'user' ),
			array( 'action_key' => 'link' ),
		) as $bad ) {
			$refused = RowActionRoute::permitted( $this->request( $bad ) );

			$this->assertInstanceOf( \WP_Error::class, $refused, (string) wp_json_encode( $bad ) );
			$this->assertSame( 404, $refused->get_error_data()['status'] );
		}
	}

	/**
	 * Options that are not an object reach the callback as an array.
	 *
	 * @param mixed $options What was sent.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider( 'malformedProvider' )]
	public function test_malformed_options_are_still_an_array( mixed $options ): void {
		$seen = 'not called';

		$this->actions(
			function ( $id, $given ) use ( &$seen ) {
				$seen = $given;

				return array();
			}
		);

		RowActionRoute::run( $this->request( array( 'options' => $options ) ) );

		$this->assertSame( array(), $seen );
	}

	/**
	 * @return array<string, array{0: mixed}>
	 */
	public static function malformedProvider(): array {
		return array(
			'false'   => array( false ),
			'null'    => array( null ),
			'number'  => array( 42 ),
			'string'  => array( 'hello' ),
		);
	}

	/**
	 * A callback that throws answers that it failed, and keeps its message
	 * -- a query, a path -- on the server.
	 */
	public function test_a_failing_callback_keeps_its_message(): void {
		$this->actions(
			static function (): never {
				throw new \RuntimeException( 'SQLSTATE wp_secret' );
			}
		);

		$answer = RowActionRoute::run( $this->request() );

		$this->assertInstanceOf( \WP_Error::class, $answer );
		$this->assertSame( 500, $answer->get_error_data()['status'] );
		$this->assertStringNotContainsString( 'wp_secret', $answer->get_error_message() );
	}

	/**
	 * Two builds share no route, handle or key.
	 */
	public function test_two_builds_share_nothing(): void {
		$one = Runtime::prefix_for( 'PluginOne\\SugarCommerce\\Columns\\Utils' );
		$two = Runtime::prefix_for( 'PluginTwo\\SugarCommerce\\Columns\\Utils' );

		$this->assertNotSame( $one, $two );
		$this->assertSame( 'sugarcommerce-columns', Runtime::prefix_for( 'SugarCommerce\\Columns\\Utils' ) );
		$this->assertSame( 'pluginone-sugarcommerce-columns', $one );
	}

	/**
	 * No runtime key is written as a literal.
	 *
	 * In an unprefixed build the derived name equals the old hardcoded one,
	 * so a value-based test cannot tell them apart.
	 */
	public function test_no_runtime_key_is_hardcoded(): void {
		$files = glob( dirname( __DIR__ ) . '/src/{,*/,*/*/,*/*/*/}*.php', GLOB_BRACE );

		$this->assertNotEmpty( $files );

		foreach ( $files as $file ) {
			if ( 'Runtime.php' === basename( $file ) ) {
				continue;
			}

			$source = (string) file_get_contents( $file );

			$this->assertStringNotContainsString(
				'wp_ajax_',
				$source,
				sprintf( '%s hooks an admin-ajax action.', basename( $file ) )
			);

			$this->assertStringNotContainsString(
				"'row-actions-ajax'",
				$source,
				sprintf( '%s hardcodes the asset handle.', basename( $file ) )
			);
		}
	}

	/**
	 * A plugin of the family prefixes with a namespace that also starts
	 * "SugarCommerce", and its build is still its own; a plugin outside the
	 * family keeps the name its first segment always gave it.
	 */
	public function test_a_family_plugin_build_is_not_taken_for_the_library(): void {
		$this->assertSame(
			'sugarcommerce-playground-vendor-sugarcommerce-columns',
			Runtime::prefix_for( 'SugarCommerce\\Playground\\Vendor\\SugarCommerce\\Columns\\Utils' )
		);
		$this->assertNotSame(
			Runtime::prefix_for( 'SugarCommerce\\Playground\\Vendor\\SugarCommerce\\Columns\\Utils' ),
			Runtime::prefix_for( 'SugarCommerce\\Members\\Vendor\\SugarCommerce\\Columns\\Utils' ),
			'Two family plugins share a name.'
		);
		$this->assertSame( 'sugarcart-sugarcommerce-columns', Runtime::prefix_for( 'SugarCart\\Vendor\\SugarCommerce\\Columns\\Utils' ) );
		$this->assertSame( 'sugarcommerce-columns', Runtime::prefix_for( '\\SugarCommerce\\Columns\\Utils\\' ) );
	}
}
