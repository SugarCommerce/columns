<?php
/**
 * Base Columns Class
 *
 * A lightweight class designed to simplify the registration of custom columns in WordPress.
 * This class focuses on display-only functionality, allowing you to easily add custom columns
 * to WordPress admin tables without the complexity of inline editing or custom UI components.
 *
 * @package     SugarCommerce\Columns
 * @copyright   Copyright (c) 2024, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

namespace SugarCommerce\Columns\Abstracts;

use Exception;
use SugarCommerce\Columns\Support\Image;
use SugarCommerce\Columns\Support\Position;
use SugarCommerce\Columns\Traits\Abstracts\Columns\Cells;
use SugarCommerce\Columns\Traits\Abstracts\Columns\Request;
use SugarCommerce\Columns\Traits\Abstracts\Columns\Styles;

/**
 * Class Columns
 *
 * Base class for registering custom columns in WordPress.
 *
 * @package SugarCommerce\Columns
 */
abstract class Columns {

	use Cells;
	use Styles;

	use Request;

	/**
	 * Object type constant that must be defined by child classes.
	 *
	 * Examples: 'post', 'user', 'term', 'comment'
	 *
	 * @var string
	 */
	protected const string OBJECT_TYPE = '';

	/**
	 * Array of custom column configurations.
	 *
	 * @var array
	 */
	protected static array $columns = [];

	/**
	 * The tables whose hooks are already attached.
	 *
	 * Keyed by object type and subtype, because that is what the column
	 * registry above is keyed by — one set of hooks renders everything
	 * registered for a table, whoever registered it.
	 *
	 * Without this, registering twice for the same
	 * table attaches a second copy of every hook, and each copy renders
	 * every column: two registrations for `page`, one shared with `post` and
	 * one for pages alone, printed every cell on the Pages screen twice.
	 *
	 * @var array<string, array<string, bool>>
	 */
	protected static array $hooked = [];

	/**
	 * Object type for the current instance.
	 *
	 * @var string
	 */
	protected string $object_type;

	/**
	 * Object subtype for the current instance (e.g., post type, taxonomy).
	 *
	 * @var string
	 */
	protected string $object_subtype;

	/**
	 * Core column keys to take off each table, from every registration.
	 *
	 * Per table rather than per instance: the hooks attach once per table,
	 * to the first instance, so a second registration's removals kept on its
	 * own instance were never read.
	 *
	 * @var array<string, array<string, string[]>>
	 */
	protected static array $removed = [];

	/**
	 * Columns constructor.
	 *
	 * @param array  $columns        Custom columns configuration.
	 * @param string $object_subtype Object subtype (e.g., 'post', 'page', 'category').
	 * @param array  $keys_to_remove Optional. Array of column keys to remove. Default empty array.
	 *
	 * @throws Exception If a column key is invalid or OBJECT_TYPE is not defined.
	 */
	public function __construct( array $columns, string $object_subtype, array $keys_to_remove = [] ) {
		// Validate that child class defined OBJECT_TYPE
		if ( empty( static::OBJECT_TYPE ) ) {
			throw new Exception( 'Child class must define OBJECT_TYPE constant.' );
		}

		$this->object_type    = static::OBJECT_TYPE;
		$this->object_subtype = $object_subtype;
		$this->set_keys_to_remove( $keys_to_remove );
		$this->add_columns( $columns );

		if ( ! isset( self::$hooked[ $this->object_type ][ $this->object_subtype ] ) ) {
			self::$hooked[ $this->object_type ][ $this->object_subtype ] = true;

			$this->load_hooks();
			$this->add_column_filters();
		}
	}

	/**
	 * Add core column keys to take off this table.
	 *
	 * @param string[] $keys Column keys to remove.
	 *
	 * @return void
	 */
	public function set_keys_to_remove( array $keys ): void {
		$current = self::$removed[ $this->object_type ][ $this->object_subtype ] ?? [];

		self::$removed[ $this->object_type ][ $this->object_subtype ] = array_values( array_unique( array_merge( $current, array_map( 'strval', $keys ) ) ) );
	}

	/**
	 * Add new columns to the existing configuration.
	 *
	 * @param array $columns Custom columns configuration.
	 *
	 * @return void
	 * @throws Exception If a column key is invalid.
	 */
	public function add_columns( array $columns ): void {
		$default_column = [
			'label'               => '',
			'meta_key'            => '',
			'position'            => '',
			'sortable'            => false,
			'numeric'             => false,
			'desc_first'          => false,
			'sortby'              => '',
			'display_callback'    => null,
			'permission_callback' => null,
			'width'               => null,
			'image'               => false,
			'image_size'          => Image::DEFAULT_SIZE,
		];

		foreach ( $columns as $key => $column ) {
			if ( ! is_string( $key ) || empty( $key ) ) {
				throw new Exception( 'Invalid column key provided. It must be a non-empty string.' );
			}

			self::$columns[ $this->object_type ][ $this->object_subtype ][ $key ] = wp_parse_args( $column, $default_column );
		}
	}

	/**
	 * Add the hooks that are the same whatever the table is.
	 *
	 * Attached once per table, beside load_hooks(), rather than once per
	 * call to add_columns() — which printed the width stylesheet once for
	 * every registration.
	 *
	 * @return void
	 */
	protected function add_column_filters(): void {
		add_action( 'admin_head', [ $this, 'add_custom_column_styles' ] );
	}

	/**
	 * Get columns array for the given object type and subtype.
	 *
	 * @param string $object_type    Object type.
	 * @param string $object_subtype Object subtype.
	 *
	 * @return array
	 */
	public static function get_columns( string $object_type, string $object_subtype ): array {
		return self::$columns[ $object_type ][ $object_subtype ] ?? [];
	}

	/**
	 * Get the configuration for a specific column by name.
	 *
	 * Both types default to the ones this instance was built for, which is
	 * what every caller wants and what every caller was passing by hand.
	 * Requiring them was an argument count waiting to go wrong: ten call
	 * sites in edd-registrars passed the object type and stopped, and each
	 * one was an ArgumentCountError the moment its column drew a cell.
	 *
	 * @param string      $column_name    The name of the column.
	 * @param string|null $object_type    Object type, or null for this one.
	 * @param string|null $object_subtype Object subtype, or null for this one.
	 *
	 * @return array|null The column configuration if exists, null otherwise.
	 */
	public function get_column_by_name( string $column_name, ?string $object_type = null, ?string $object_subtype = null ): ?array {
		$columns = self::get_columns(
			$object_type ?? $this->object_type,
			$object_subtype ?? $this->object_subtype
		);

		return $columns[ $column_name ] ?? null;
	}

	/**
	 * Register custom columns with their labels.
	 *
	 * @param array $columns Array of existing columns.
	 *
	 * @return array Updated array of columns with custom columns.
	 */
	public function register_columns( array $columns ): array {
		$custom_columns = self::get_columns( $this->object_type, $this->object_subtype );

		// Remove specified keys from existing columns
		$columns = $this->remove_keys_from_columns( $columns );

		foreach ( $custom_columns as $key => $column ) {
			if ( ! $this->check_column_permission( $column ) ) {
				continue;
			}

			$columns = Position::insert( $columns, (string) $column['position'], (string) $key, esc_html( $column['label'] ) );
		}

		return $columns;
	}

	/**
	 * Remove specified keys from the columns array.
	 *
	 * This method verifies if the keys exist before attempting to remove them.
	 *
	 * @param array $columns Array of existing columns.
	 *
	 * @return array The columns array with specified keys removed.
	 */
	protected function remove_keys_from_columns( array $columns ): array {
		foreach ( self::$removed[ $this->object_type ][ $this->object_subtype ] ?? [] as $key ) {
			if ( array_key_exists( $key, $columns ) ) {
				unset( $columns[ $key ] );
			}
		}

		return $columns;
	}

	/**
	 * Register custom columns as sortable.
	 *
	 * @param array $columns Array of existing sortable columns.
	 *
	 * @return array Updated array of sortable columns with custom columns.
	 */
	public function register_sortable_columns( array $columns ): array {
		$custom_columns = self::get_columns( $this->object_type, $this->object_subtype );

		foreach ( $custom_columns as $key => $column ) {
			if ( $column['sortable'] ) {
				// The second element is core's "sort descending on the first
				// click", not a type. `numeric` was being passed here, so
				// every numeric column silently started descending — while
				// `numeric` also, correctly, drives meta_value_num in the
				// query. Two different jobs, one config key, one of them
				// wrong.
				$columns[ $key ] = [ $key, (bool) ( $column['desc_first'] ?? false ) ];
			}
		}

		return $columns;
	}

	/**
	 * Check if we are on the correct screen for custom columns.
	 *
	 * @return bool True if on the correct screen, false otherwise.
	 */
	abstract protected function is_screen(): bool;

	/**
	 * One meta value for an object of this type.
	 *
	 * The only thing that differs between a post column and a term column
	 * once the configuration has been read — which is why the rendering
	 * below is here and not copied into each table class, as it was, five
	 * times, identically.
	 *
	 * @param int|string $object_id The object.
	 * @param string     $meta_key  The key.
	 *
	 * @return mixed
	 */
	abstract protected function get_meta( $object_id, string $meta_key );

	/**
	 * Render the custom column content.
	 *
	 * @param string $value       The current value of the column.
	 * @param string $column_name The name/key of the current column.
	 * @param mixed  $object_id   The object ID.
	 *
	 * @return string The rendered column content.
	 */
	abstract public function render_column_content( string $value, string $column_name, $object_id ): string;

	/**
	 * Check column permission.
	 *
	 * @param array $column The column configuration.
	 *
	 * @return bool True if permission is granted, false otherwise.
	 */
	protected function check_column_permission( array $column ): bool {
		if ( isset( $column['permission_callback'] ) && is_callable( $column['permission_callback'] ) ) {
			return call_user_func( $column['permission_callback'] );
		}

		return current_user_can( 'manage_options' );
	}

	/**
	 * Load the necessary hooks for custom columns.
	 *
	 * Registers WordPress hooks for adding, sorting, and displaying custom columns.
	 *
	 * @return void
	 */
	abstract protected function load_hooks(): void;
}
