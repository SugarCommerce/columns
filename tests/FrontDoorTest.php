<?php
/**
 * Front door tests.
 *
 * @package SugarCommerce\Columns
 */

declare( strict_types=1 );

namespace SugarCommerce\Columns\Tests;

use SugarCommerce\Columns\BulkActions;
use SugarCommerce\Columns\Columns;
use SugarCommerce\Columns\Filters;
use SugarCommerce\Columns\RowActions;
use SugarCommerce\Columns\Support\Position;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The four static front doors, the helpers that were global functions, and
 * the fixes that came with the move.
 */
final class FrontDoorTest extends TestCase {

	/**
	 * Forget the last test's registrations.
	 */
	protected function setUp(): void {
		rc_reset_globals();

		$_GET = [];
	}

	/**
	 * Leave no query string behind.
	 */
	protected function tearDown(): void {
		$_GET = [];
	}

	/**
	 * One instance per post type or taxonomy, and a single one for the
	 * tables that have no subtypes, each of the class the old function made.
	 */
	public function test_each_door_builds_the_table_classes(): void {
		$column = [ 'c' => [ 'label' => 'C' ] ];

		$posts = Columns::posts( [ 'post', 'page' ], $column );
		$this->assertCount( 2, $posts );
		$this->assertContainsOnlyInstancesOf( \SugarCommerce\Columns\Tables\Post::class, $posts );
		$this->assertCount( 1, Columns::posts( 'download', $column ) );
		$this->assertContainsOnlyInstancesOf( \SugarCommerce\Columns\Tables\Taxonomy::class, Columns::terms( [ 'category', 'post_tag' ], $column ) );
		$this->assertInstanceOf( \SugarCommerce\Columns\Tables\User::class, Columns::users( $column ) );
		$this->assertInstanceOf( \SugarCommerce\Columns\Tables\Comment::class, Columns::comments( $column ) );
		$this->assertInstanceOf( \SugarCommerce\Columns\Tables\Media::class, Columns::media( $column ) );

		$this->assertContainsOnlyInstancesOf( \SugarCommerce\Columns\Filters\Post::class, Filters::posts( [ 'post', 'page' ], [ 'f' => [ 'label' => 'F' ] ] ) );
		$this->assertInstanceOf( \SugarCommerce\Columns\Filters\User::class, Filters::users( [ 'f' => [ 'label' => 'F' ] ] ) );

		$action = [ 'a' => [ 'label' => 'A' ] ];

		$this->assertCount( 2, BulkActions::posts( [ 'post', 'page' ], $action ) );
		$this->assertCount( 1, BulkActions::terms( 'category', $action ) );
		$this->assertInstanceOf( \SugarCommerce\Columns\BulkActions\User::class, BulkActions::users( $action ) );
		$this->assertInstanceOf( \SugarCommerce\Columns\BulkActions\Comment::class, BulkActions::comments( $action ) );
		$this->assertInstanceOf( \SugarCommerce\Columns\BulkActions\Media::class, BulkActions::media( $action ) );

		$this->assertContainsOnlyInstancesOf( \SugarCommerce\Columns\RowActions\Post::class, RowActions::posts( [ 'post', 'page' ], $action ) );
		$this->assertCount( 1, RowActions::terms( 'category', $action ) );
		$this->assertInstanceOf( \SugarCommerce\Columns\RowActions\User::class, RowActions::users( $action ) );
		$this->assertInstanceOf( \SugarCommerce\Columns\RowActions\Comment::class, RowActions::comments( $action ) );
		$this->assertInstanceOf( \SugarCommerce\Columns\RowActions\Media::class, RowActions::media( $action ) );
	}

	/**
	 * The media and user tables keep the subtypes the old functions gave
	 * them, which is what their registries are keyed by.
	 */
	public function test_the_fixed_subtypes_are_kept(): void {
		Columns::media( [ 'credit' => [ 'label' => 'Credit' ] ] );
		Columns::users( [ 'orders' => [ 'label' => 'Orders' ] ] );

		$this->assertArrayHasKey( 'credit', \SugarCommerce\Columns\Abstracts\Columns::get_columns( 'post', 'attachment' ) );
		$this->assertArrayHasKey( 'orders', \SugarCommerce\Columns\Abstracts\Columns::get_columns( 'user', 'user' ) );

		BulkActions::media( [ 'credit' => [ 'label' => 'Credit' ] ] );
		RowActions::media( [ 'credit' => [ 'label' => 'Credit' ] ] );
		Filters::users( [ 'contacted' => [ 'label' => 'Contacted' ] ] );

		$this->assertArrayHasKey( 'credit', \SugarCommerce\Columns\Abstracts\BulkActions::get_actions( 'media', 'attachment' ) );
		$this->assertArrayHasKey( 'credit', \SugarCommerce\Columns\Abstracts\RowActions::get_actions( 'post', 'attachment' ) );
		$this->assertArrayHasKey( 'contacted', \SugarCommerce\Columns\Abstracts\ListFilters::get_filters( 'user', 'user' ) );
	}

	/**
	 * A key that is not a string is refused for that table and logged; the
	 * screen goes on without it rather than with a fatal error.
	 *
	 * @param callable $register Registers a bad array.
	 * @param mixed    $refused  What the door answers.
	 */
	#[DataProvider( 'refusals' )]
	public function test_a_bad_key_is_refused_not_fatal( callable $register, mixed $refused ): void {
		$this->expectErrorLog();

		$this->assertSame( $refused, $register() );
	}

	/**
	 * A bad array at every door.
	 *
	 * @return array<string, array{0: callable, 1: mixed}>
	 */
	public static function refusals(): array {
		$bad = [ 0 => [ 'label' => 'No key' ] ];

		return [
			'post columns'     => [ static fn() => Columns::posts( 'post', $bad ), [] ],
			'user columns'     => [ static fn() => Columns::users( $bad ), null ],
			'term columns'     => [ static fn() => Columns::terms( 'category', $bad ), [] ],
			'post filters'     => [ static fn() => Filters::posts( 'post', $bad ), [] ],
			'user filters'     => [ static fn() => Filters::users( $bad ), null ],
			'post bulk'        => [ static fn() => BulkActions::posts( 'post', $bad ), [] ],
			'media bulk'       => [ static fn() => BulkActions::media( $bad ), null ],
			'post row actions' => [ static fn() => RowActions::posts( 'post', $bad ), [] ],
			'user row actions' => [ static fn() => RowActions::users( $bad ), null ],
		];
	}

	/**
	 * `after:` and `before:` a key, either end when the key is missing, the
	 * end with no position at all, and an integer-looking key found as one.
	 */
	public function test_position(): void {
		$items = [
			'cb'    => 1,
			'title' => 2,
			'date'  => 3,
		];

		$this->assertSame( [ 'cb', 'title', 'new', 'date' ], array_keys( Position::insert( $items, 'after:title', 'new', 0 ) ) );
		$this->assertSame( [ 'cb', 'new', 'title', 'date' ], array_keys( Position::insert( $items, 'before:title', 'new', 0 ) ) );
		$this->assertSame( [ 'cb', 'title', 'date', 'new' ], array_keys( Position::insert( $items, 'after:gone', 'new', 0 ) ) );
		$this->assertSame( [ 'new', 'cb', 'title', 'date' ], array_keys( Position::insert( $items, 'before:gone', 'new', 0 ) ) );
		$this->assertSame( [ 'cb', 'title', 'date', 'new' ], array_keys( Position::insert( $items, '', 'new', 0 ) ) );
		$this->assertSame( [ 'cb', 'title', 'date', 'new' ], array_keys( Position::insert( $items, 'title', 'new', 0 ) ) );
		$this->assertSame( [ 'cb', 'title', 'date', 'new' ], array_keys( Position::insert( $items, 'after:date', 'new', 0 ) ) );
		$this->assertSame( [ 7, 'new', 8 ], array_keys( Position::insert( [ 7 => 'a', 8 => 'b' ], 'after:7', 'new', 0 ) ) );
		$this->assertSame( 'value', Position::insert( $items, 'after:title', 'new', 'value' )['new'] );

		// An entry already there stays where it was.
		$this->assertSame( [ 'cb', 'title', 'date' ], array_keys( Position::insert( $items, 'after:date', 'cb', 0 ) ) );
	}

	/**
	 * Core has no `{post_type}_row_actions` filter: a custom post type's
	 * actions go on `post_row_actions` or `page_row_actions`, and only on
	 * that post type's rows.
	 */
	public function test_post_row_actions_reach_a_custom_type_and_only_it(): void {
		RowActions::posts( 'download', [ 'resend' => [ 'label' => 'Resend', 'url' => 'https://example.test/' ] ] );

		$this->assertArrayHasKey( 'post_row_actions', $GLOBALS['rc_hooks'] );
		$this->assertArrayHasKey( 'page_row_actions', $GLOBALS['rc_hooks'] );
		$this->assertArrayNotHasKey( 'download_row_actions', $GLOBALS['rc_hooks'] );

		$filter = $GLOBALS['rc_hooks']['post_row_actions'][0];

		$this->assertArrayHasKey( 'resend', $filter( [ 'edit' => 'Edit' ], $this->post( 3, 'download' ) ) );
		$this->assertSame( [ 'edit' => 'Edit' ], $filter( [ 'edit' => 'Edit' ], $this->post( 4, 'post' ) ) );
	}

	/**
	 * Registering one table twice attaches its hooks once, whatever the
	 * half -- the row actions used to attach a second admin-ajax handler.
	 */
	public function test_each_half_hooks_a_table_once(): void {
		RowActions::posts( 'download', [ 'a' => [ 'label' => 'A' ] ] );
		RowActions::posts( 'download', [ 'b' => [ 'label' => 'B' ] ] );
		BulkActions::posts( 'download', [ 'a' => [ 'label' => 'A' ] ] );
		BulkActions::posts( 'download', [ 'b' => [ 'label' => 'B' ] ] );

		$this->assertCount( 1, $GLOBALS['rc_hooks']['post_row_actions'] );
		$this->assertCount( 1, $GLOBALS['rc_hooks']['handle_bulk_actions-edit-download'] );
	}

	/**
	 * Hooks attach at registration, not on admin_init: registering during
	 * admin_init, or later, still works.
	 */
	public function test_row_actions_attach_before_admin_init(): void {
		$this->assertSame( 0, did_action( 'admin_init' ) );

		RowActions::posts( 'download', [ 'a' => [ 'label' => 'A' ] ] );

		$this->assertArrayHasKey( 'post_row_actions', $GLOBALS['rc_hooks'] );
		$this->assertArrayNotHasKey( 'admin_init', $GLOBALS['rc_hooks'] );
	}

	/**
	 * A bulk action registered twice for a table runs once.
	 */
	public function test_a_bulk_action_runs_once(): void {
		$runs = 0;

		$action = [
			'tag' => [
				'label'    => 'Tag',
				'callback' => static function () use ( &$runs ): void {
					++$runs;
				},
			],
		];

		BulkActions::posts( 'download', $action );
		BulkActions::posts( 'download', [ 'other' => [ 'label' => 'Other' ] ] );

		$url = 'https://example.test/wp-admin/edit.php';

		foreach ( $GLOBALS['rc_hooks']['handle_bulk_actions-edit-download'] as $handler ) {
			$url = $handler( $url, 'tag', [ 1, 2 ] );
		}

		$this->assertSame( 1, $runs );
	}

	/**
	 * Somebody without an action's capability cannot run it by posting its
	 * key, though the dropdown never offered it to them.
	 */
	public function test_a_bulk_action_checks_its_capability_when_run(): void {
		$runs = 0;

		BulkActions::posts(
			'download',
			[
				'purge' => [
					'label'      => 'Purge',
					'capability' => 'delete_others_posts',
					'callback'   => static function () use ( &$runs ): void {
						++$runs;
					},
				],
			]
		);

		$GLOBALS['rc_caps'] = [ 'edit_posts' ];

		$handler = $GLOBALS['rc_hooks']['handle_bulk_actions-edit-download'][0];

		$this->assertSame( 'https://example.test/', $handler( 'https://example.test/', 'purge', [ 1 ] ) );
		$this->assertSame( 0, $runs );

		$GLOBALS['rc_caps'] = [ 'delete_others_posts' ];

		$handler( 'https://example.test/', 'purge', [ 1 ] );
		$this->assertSame( 1, $runs );
	}

	/**
	 * One action on two tables tells only the table it ran on.
	 */
	public function test_the_bulk_notice_is_printed_once_on_its_own_table(): void {
		BulkActions::posts( [ 'post', 'page' ], [ 'touch' => [ 'label' => 'Touch' ] ] );

		$_GET = [
			'bulk_action_done'  => 'touch',
			'bulk_action_count' => '2',
		];

		$GLOBALS['rc_screen'] = (object) [
			'id'        => 'edit-page',
			'base'      => 'edit',
			'post_type' => 'page',
			'taxonomy'  => '',
		];

		ob_start();

		foreach ( $GLOBALS['rc_hooks']['admin_notices'] as $notice ) {
			$notice();
		}

		$printed = (string) ob_get_clean();

		$this->assertSame( 1, substr_count( $printed, 'class="notice' ) );
		$this->assertSame( '<div class="notice notice-success is-dismissible"><p>2 items processed.</p></div>', $printed );
	}

	/**
	 * A failed action says so, in core's error notice, with its message as
	 * text -- the message kept for the user who ran it, the address carrying
	 * only its token.
	 */
	public function test_a_failed_bulk_action_prints_an_error_notice_as_text(): void {
		BulkActions::posts(
			'post',
			[
				'touch' => [
					'label'    => 'Touch',
					'callback' => static fn(): array => [
						'success' => false,
						'message' => 'Fish & chips',
					],
				],
			]
		);

		$handler = $GLOBALS['rc_hooks']['handle_bulk_actions-edit-post'][0];

		parse_str( (string) parse_url( $handler( 'https://example.test/', 'touch', [ 1 ] ), PHP_URL_QUERY ), $_GET );

		$this->assertArrayNotHasKey( 'bulk_action_message', $_GET );
		$this->assertSame( '1', $_GET['bulk_action_error'] );

		$printed = $this->notices();

		$this->assertStringStartsWith( '<div class="notice notice-error is-dismissible"><p>', $printed );
		$this->assertStringContainsString( 'Fish &amp; chips', $printed );
	}

	/**
	 * Words in the address are never a notice: a link anyone could write
	 * used to put its own sentence on the list.
	 */
	public function test_a_message_in_the_address_is_not_shown(): void {
		BulkActions::posts( 'post', [ 'touch' => [ 'label' => 'Touch' ] ] );

		$_GET = [
			'bulk_action_done'    => 'touch',
			'bulk_action_count'   => '1',
			'bulk_action_message' => 'Your account is locked. Call 555-0100.',
			'bulk_action_notice'  => 'madeup',
		];

		$printed = $this->notices();

		$this->assertStringNotContainsString( 'locked', $printed );
		$this->assertStringContainsString( '1 item processed.', $printed );
	}

	/**
	 * Another user's token says nothing to this one.
	 */
	public function test_another_users_message_is_not_shown(): void {
		BulkActions::posts(
			'post',
			[
				'touch' => [
					'label'    => 'Touch',
					'callback' => static fn(): array => [ 'message' => 'Mine.' ],
				],
			]
		);

		$handler = $GLOBALS['rc_hooks']['handle_bulk_actions-edit-post'][0];

		parse_str( (string) parse_url( $handler( 'https://example.test/', 'touch', [ 1 ] ), PHP_URL_QUERY ), $_GET );

		$GLOBALS['rc_user_id'] = 2;

		$this->assertStringNotContainsString( 'Mine.', $this->notices() );

		$GLOBALS['rc_user_id'] = 1;

		$this->assertStringContainsString( 'Mine.', $this->notices() );
	}

	/**
	 * What the post list's notices print.
	 *
	 * @return string
	 */
	private function notices(): string {
		$GLOBALS['rc_screen'] = (object) [
			'id'        => 'edit-post',
			'base'      => 'edit',
			'post_type' => 'post',
			'taxonomy'  => '',
		];

		ob_start();

		foreach ( $GLOBALS['rc_hooks']['admin_notices'] as $notice ) {
			$notice();
		}

		return (string) ob_get_clean();
	}

	/**
	 * A second registration's removals apply, for columns and row actions.
	 */
	public function test_every_registrations_removals_apply(): void {
		$first  = Columns::posts( 'download', [ 'a' => [ 'label' => 'A' ] ], [ 'author' ] )[0];
		$second = Columns::posts( 'download', [ 'b' => [ 'label' => 'B' ] ], [ 'date' ] )[0];

		$columns = $first->register_columns(
			[
				'title'  => 'Title',
				'author' => 'Author',
				'date'   => 'Date',
			]
		);

		$this->assertSame( [ 'title', 'a', 'b' ], array_keys( $columns ) );
		$this->assertSame( $columns, $second->register_columns( [ 'title' => 'Title', 'author' => 'Author', 'date' => 'Date' ] ) );

		$actions = RowActions::posts( 'download', [], [ 'trash' ] )[0];
		RowActions::posts( 'download', [], [ 'view' ] );

		$this->assertSame(
			[ 'edit' => 'Edit' ],
			$actions->register_actions( [ 'edit' => 'Edit', 'trash' => 'Bin', 'view' => 'View' ], $this->post( 1, 'download' ), 1 )
		);
	}

	/**
	 * Two identically configured AJAX actions each post their own key.
	 */
	public function test_identical_ajax_actions_keep_their_own_keys(): void {
		$same = [
			'label'    => 'Same',
			'ajax'     => true,
			'callback' => 'strlen',
		];

		$actions = RowActions::posts( 'download', [ 'one' => $same, 'two' => $same ] )[0];
		$links   = $actions->register_actions( [], $this->post( 5, 'download' ), 5 );

		$this->assertStringContainsString( 'data-action-key="one"', $links['one'] );
		$this->assertStringContainsString( 'data-action-key="two"', $links['two'] );
		$this->assertStringContainsString( 'data-config="sugarcommerce-columns"', $links['two'] );
	}

	/**
	 * The toggle helpers read and flip a flag through each type's own meta.
	 *
	 * @param string $type  The object type.
	 * @param string $store Where its meta lives.
	 */
	#[DataProvider( 'metaTypes' )]
	public function test_toggle_meta_and_label( string $type, string $store ): void {
		$this->assertSame( 'Feature', RowActions::toggle_label( $type, 9, '_featured', 'Unfeature', 'Feature' ) );

		$this->assertTrue( RowActions::toggle_meta( $type, 9, '_featured' ) );
		$this->assertTrue( $GLOBALS['rc_meta'][ $store ][9]['_featured'] );
		$this->assertSame( 'Unfeature', RowActions::toggle_label( $type, 9, '_featured', 'Unfeature', 'Feature' ) );

		$this->assertFalse( RowActions::toggle_meta( $type, 9, '_featured' ) );
		$this->assertFalse( $GLOBALS['rc_meta'][ $store ][9]['_featured'] );
	}

	/**
	 * Every type the helpers know, and where its meta is kept.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function metaTypes(): array {
		return [
			'post'       => [ 'post', 'post' ],
			'attachment' => [ 'attachment', 'post' ],
			'comment'    => [ 'comment', 'comment' ],
			'user'       => [ 'user', 'user' ],
			'term'       => [ 'term', 'term' ],
		];
	}

	/**
	 * A type the helpers do not know writes nothing, answers true, and
	 * reads as inactive.
	 */
	public function test_toggle_on_an_unknown_type(): void {
		$this->assertTrue( RowActions::toggle_meta( 'order', 9, '_featured' ) );
		$this->assertSame( [], $GLOBALS['rc_meta'] );
		$this->assertSame( 'off', RowActions::toggle_label( 'order', 9, '_featured', 'on', 'off' ) );
	}

	/**
	 * The admin-ajax URL carries the action, the id and a nonce for both,
	 * and extra arguments add to or replace them.
	 */
	public function test_ajax_url(): void {
		$url = RowActions::ajax_url( 'my_toggle', 12, [ 'flag' => 'on' ] );

		$this->assertSame( 'https://example.test/wp-admin/admin-ajax.php?action=my_toggle&id=12&nonce=nonce%3Amy_toggle_12&flag=on', $url );
		$this->assertStringContainsString( 'nonce=mine', RowActions::ajax_url( 'my_toggle', 12, [ 'nonce' => 'mine' ] ) );
	}

	/**
	 * A post, as core passes one to the row action filters.
	 *
	 * @param int    $id   Its id.
	 * @param string $type Its post type.
	 *
	 * @return \WP_Post
	 */
	private function post( int $id, string $type ): \WP_Post {
		$post            = ( new \ReflectionClass( \WP_Post::class ) )->newInstanceWithoutConstructor();
		$post->ID        = $id;
		$post->post_type = $type;

		return $post;
	}
}
