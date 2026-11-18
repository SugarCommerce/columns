<?php
/**
 * What a list filter does to the query, and to everybody else's part of it.
 *
 * @package SugarCommerce\Columns
 */

declare( strict_types=1 );

namespace SugarCommerce\Columns\Tests;

use PHPUnit\Framework\TestCase;
use SugarCommerce\Columns\Abstracts\ListFilters;
use SugarCommerce\Columns\Filters\Post;
use SugarCommerce\Columns\Filters\User;

/**
 * Another plugin may already have narrowed the list with a meta or tax
 * query. A filter here must add to that, never replace it.
 */
final class FilterQueryTest extends TestCase {

	/**
	 * Forget the last test's registrations and request.
	 */
	protected function setUp(): void {
		rc_reset_globals();

		foreach ( [ 'filters', 'instances' ] as $property ) {
			( new \ReflectionProperty( ListFilters::class, $property ) )->setValue( null, [] );
		}

		$_GET               = [];
		$_REQUEST           = [];
		$GLOBALS['pagenow'] = 'edit.php';
	}

	/**
	 * Put the request back.
	 */
	protected function tearDown(): void {
		$_GET     = [];
		$_REQUEST = [];
		unset( $GLOBALS['pagenow'] );
	}

	/**
	 * Filters on the download list: one by meta, one by taxonomy.
	 *
	 * @return Post
	 */
	private function post_filters(): Post {
		return new Post(
			[
				'state' => [
					'label'   => 'All states',
					'options' => [ 'live' => 'Live' ],
				],
				'genre' => [
					'label'    => 'All genres',
					'taxonomy' => 'download_category',
				],
			],
			'download'
		);
	}

	/**
	 * The main admin query for the download list.
	 *
	 * @param array<string, mixed> $vars Query vars already set.
	 *
	 * @return object
	 */
	private static function query( array $vars = [] ): object {
		return new class( $vars ) {
			/** @var array<string, mixed> */
			public array $query = [ 'post_type' => 'download' ];

			/**
			 * @param array<string, mixed> $vars Query vars.
			 */
			public function __construct( public array $vars ) {}

			public function is_main_query(): bool {
				return true;
			}

			public function get( string $key ): mixed {
				return $this->vars[ $key ] ?? '';
			}

			public function set( string $key, mixed $value ): void {
				$this->vars[ $key ] = $value;
			}
		};
	}

	private const THEIRS_META = [
		'relation' => 'OR',
		[ 'key' => '_members_only', 'compare' => 'NOT EXISTS' ],
		[ 'key' => '_members_only', 'value' => '0' ],
	];

	private const THEIRS_TAX = [
		[ 'taxonomy' => 'region', 'field' => 'slug', 'terms' => 'eu' ],
	];

	/**
	 * With nothing there before, the clauses are set as they always were.
	 */
	public function test_a_post_filter_sets_its_clauses_on_an_empty_query(): void {
		$_GET  = [ 'state' => 'live', 'genre' => 'house' ];
		$query = self::query();

		$this->post_filters()->modify_query( $query );

		$this->assertSame( [ [ 'key' => 'state', 'value' => 'live', 'compare' => '=' ] ], $query->vars['meta_query'] );
		$this->assertSame( [ [ 'taxonomy' => 'download_category', 'field' => 'slug', 'terms' => 'house' ] ], $query->vars['tax_query'] );
	}

	/**
	 * Another plugin's meta and tax queries survive, ANDed with ours.
	 */
	public function test_a_post_filter_keeps_another_plugins_queries(): void {
		$_GET  = [ 'state' => 'live', 'genre' => 'house' ];
		$query = self::query( [ 'meta_query' => self::THEIRS_META, 'tax_query' => self::THEIRS_TAX ] );

		$this->post_filters()->modify_query( $query );

		$this->assertSame(
			[
				'relation' => 'AND',
				self::THEIRS_META,
				[ [ 'key' => 'state', 'value' => 'live', 'compare' => '=' ] ],
			],
			$query->vars['meta_query']
		);
		$this->assertSame(
			[
				'relation' => 'AND',
				self::THEIRS_TAX,
				[ [ 'taxonomy' => 'download_category', 'field' => 'slug', 'terms' => 'house' ] ],
			],
			$query->vars['tax_query']
		);
	}

	/**
	 * Two of ours are ANDed together inside their own group.
	 */
	public function test_several_filters_are_anded_inside_their_group(): void {
		new Post( [ 'colour' => [ 'label' => 'Any', 'options' => [ 'red' => 'Red' ] ] ], 'download' );
		$filters = $this->post_filters();

		$_GET  = [ 'state' => 'live', 'colour' => 'red' ];
		$query = self::query( [ 'meta_query' => self::THEIRS_META ] );

		$filters->modify_query( $query );

		$this->assertSame( 'AND', $query->vars['meta_query']['relation'] );
		$this->assertSame( self::THEIRS_META, $query->vars['meta_query'][0] );
		$this->assertSame( 'AND', $query->vars['meta_query'][1]['relation'] );
		$this->assertCount( 3, $query->vars['meta_query'][1] );
	}

	/**
	 * Nothing chosen, nothing touched.
	 */
	public function test_an_unused_filter_leaves_the_query_alone(): void {
		$query = self::query( [ 'meta_query' => self::THEIRS_META ] );

		$this->post_filters()->modify_query( $query );

		$this->assertSame( self::THEIRS_META, $query->vars['meta_query'] );
		$this->assertArrayNotHasKey( 'tax_query', $query->vars );
	}

	/**
	 * The users list: the same, on the query arguments.
	 */
	public function test_a_user_filter_keeps_another_plugins_queries(): void {
		$user = new User(
			[
				'tier'    => [ 'label' => 'Any tier', 'options' => [ 'gold' => 'Gold' ] ],
				'country' => [ 'label' => 'Anywhere', 'taxonomy' => 'user_country' ],
			],
			'user'
		);

		$_REQUEST = [ 'tier' => 'gold', 'country' => 'ie' ];

		$args = $user->modify_list_table_query( [ 'meta_query' => self::THEIRS_META, 'tax_query' => self::THEIRS_TAX ] );

		$this->assertSame(
			[
				'relation' => 'AND',
				self::THEIRS_META,
				[ [ 'key' => 'tier', 'value' => 'gold', 'compare' => '=' ] ],
			],
			$args['meta_query']
		);
		$this->assertSame(
			[
				'relation' => 'AND',
				self::THEIRS_TAX,
				[ [ 'taxonomy' => 'user_country', 'field' => 'slug', 'terms' => 'ie' ] ],
			],
			$args['tax_query']
		);
	}

	/**
	 * And on an empty one, the clauses as they always were.
	 */
	public function test_a_user_filter_sets_its_clauses_on_empty_arguments(): void {
		$user     = new User( [ 'tier' => [ 'label' => 'Any tier', 'options' => [ 'gold' => 'Gold' ] ] ], 'user' );
		$_REQUEST = [ 'tier' => 'gold' ];

		$this->assertSame(
			[ [ 'key' => 'tier', 'value' => 'gold', 'compare' => '=' ] ],
			$user->modify_list_table_query( [] )['meta_query']
		);
	}
}
