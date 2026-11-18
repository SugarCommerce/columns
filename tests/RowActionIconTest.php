<?php
/**
 * Row action icon tests.
 *
 * @package SugarCommerce\Columns
 */

declare( strict_types=1 );

namespace SugarCommerce\Columns\Tests;

use SugarCommerce\Columns\Abstracts\RowActions;
use SugarCommerce\Fields\Icons\Icons;
use SugarCommerce\Columns\RowActions\Post;
use SugarCommerce\Columns\Utils\Runtime;
use PHPUnit\Framework\TestCase;

/**
 * A row action's `icon` takes two forms: a bare Dashicons name, which is what
 * it always took and must draw exactly as it did, and a `set:name` value
 * (`heroicons-outline:star`), drawn as inline SVG by sugarcommerce/fields.
 *
 * The icons the script shows while an AJAX action runs -- working, done,
 * failed -- are the library's own, and are Heroicons outline now.
 */
final class RowActionIconTest extends TestCase {

	/**
	 * Empty the stubbed WordPress and the static registry.
	 */
	protected function setUp(): void {
		rc_reset_globals();

		$GLOBALS['rc_did']['admin_init'] = 1;
	}

	/**
	 * Register one action on posts and render the row's links.
	 *
	 * @param array $action The action.
	 *
	 * @return string The links, joined.
	 */
	private function links( array $action ): string {
		$instance = new Post( array( 'act' => $action + array( 'label' => 'Act', 'capability' => 'edit_posts' ) ), 'post' );

		return implode( ' ', $instance->register_actions( array(), (object) array( 'ID' => 7 ), 7 ) );
	}

	/**
	 * Both kinds of link: a URL and an AJAX one.
	 *
	 * @return array<string, array{0: array}>
	 */
	public static function link_kinds(): array {
		return array(
			'url'  => array( array( 'url' => 'https://example.test/x' ) ),
			'ajax' => array( array( 'ajax' => true, 'callback' => static fn() => array() ) ),
		);
	}

	/**
	 * A `set:name` icon is inline SVG, before the label.
	 *
	 * @param array $kind The link's own keys.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider( 'link_kinds' )]
	public function test_a_set_icon_draws_svg( array $kind ): void {
		$link = $this->links( $kind + array( 'icon' => 'heroicons-outline:star' ) );

		$this->assertMatchesRegularExpression( '/<svg [^>]*class="sugarcommerce-icon"[^>]*>.*<\/svg> Act<\/a>/s', $link );
		$this->assertStringContainsString( 'stroke="currentColor"', $link );
		$this->assertStringNotContainsString( 'dashicons', $link );
	}

	/**
	 * A bare name is the Dashicons span it always was, in either spelling.
	 *
	 * @param array $kind The link's own keys.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider( 'link_kinds' )]
	public function test_a_bare_name_draws_the_dashicon( array $kind ): void {
		$span = '<span class="dashicons dashicons-star-filled sugarcommerce-icon" aria-hidden="true"></span> Act</a>';

		$this->assertStringContainsString( $span, $this->links( $kind + array( 'icon' => 'star-filled' ) ) );

		rc_reset_globals();
		$GLOBALS['rc_did']['admin_init'] = 1;

		$this->assertStringContainsString( $span, $this->links( $kind + array( 'icon' => 'dashicons-star-filled' ) ) );
	}

	/**
	 * No icon, or one that names nothing, prints nothing -- not an empty span.
	 *
	 * @param array $kind The link's own keys.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider( 'link_kinds' )]
	public function test_no_icon_draws_nothing( array $kind ): void {
		$this->assertStringNotContainsString( 'sugarcommerce-icon', $this->links( $kind ) );

		rc_reset_globals();
		$GLOBALS['rc_did']['admin_init'] = 1;

		$link = $this->links( $kind + array( 'icon' => 'heroicons-outline:no-such-icon' ) );

		$this->assertStringNotContainsString( 'sugarcommerce-icon', $link );
		$this->assertMatchesRegularExpression( '/>Act<\/a>/', $link );
	}

	/**
	 * The states the script swaps in are Heroicons, each with the class the
	 * stylesheet spins or colours it by.
	 */
	public function test_the_state_icons_are_heroicons(): void {
		$instance = new Post( array( 'act' => array( 'label' => 'Act', 'ajax' => true, 'callback' => static fn() => array() ) ), 'post' );

		$instance->enqueue_assets();

		$inline = $GLOBALS['ra_inline'][ Runtime::handle() ][0] ?? array();

		$this->assertSame( 'before', $inline['position'] ?? '' );
		$this->assertMatchesRegularExpression( '/^window\.SugarCommerceColumns=window\.SugarCommerceColumns\|\|\{\};window\.SugarCommerceColumns\["' . preg_quote( Runtime::handle(), '/' ) . '"\]=\{/', $inline['data'] ?? '' );

		$icons = RowActions::script_config()['icons'];

		$expected = array(
			'processing' => 'row-action-spin',
			'success'    => 'row-action-success',
			'error'      => 'row-action-error',
		);

		$this->assertSame( array_keys( $expected ), array_keys( $icons ) );

		$names = array(
			'processing' => 'arrow-path',
			'success'    => 'check',
			'error'      => 'x-mark',
		);

		foreach ( $expected as $state => $class ) {
			$this->assertMatchesRegularExpression( '/^<svg [^>]*class="sugarcommerce-icon ' . $class . '"/', $icons[ $state ], $state );
			$this->assertSame( Icons::render( 'heroicons-outline:' . $names[ $state ], array( 'class' => $class ) ), $icons[ $state ], $state );
		}
	}

	/**
	 * An icon on a URL action brings the stylesheet that lines it up, but
	 * not the script, and does not stop a later AJAX table getting it.
	 */
	public function test_an_icon_alone_enqueues_only_the_stylesheet(): void {
		$icons = new Post( array( 'go' => array( 'label' => 'Go', 'url' => 'https://example.test/', 'icon' => 'heroicons-outline:star' ) ), 'page' );

		$icons->enqueue_assets();

		$this->assertSame( array( Runtime::handle() => true ), $GLOBALS['ra_styles'] );
		$this->assertSame( array(), $GLOBALS['ra_scripts'] );
		$this->assertSame( array(), $GLOBALS['ra_inline'] );

		$ajax = new Post( array( 'act' => array( 'label' => 'Act', 'ajax' => true, 'callback' => static fn() => array() ) ), 'post' );

		$ajax->enqueue_assets();

		$this->assertArrayHasKey( Runtime::handle(), $GLOBALS['ra_scripts'] );
		$this->assertArrayHasKey( Runtime::handle(), $GLOBALS['ra_inline'] );
	}

	/**
	 * No icon and no AJAX: nothing to enqueue.
	 */
	public function test_a_plain_action_enqueues_nothing(): void {
		$plain = new Post( array( 'go' => array( 'label' => 'Go', 'url' => 'https://example.test/' ) ), 'page' );

		$plain->enqueue_assets();

		$this->assertSame( array(), $GLOBALS['ra_scripts'] );
		$this->assertSame( array(), $GLOBALS['ra_styles'] );
	}
}
