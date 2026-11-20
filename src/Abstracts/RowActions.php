<?php
/**
 * Base Row Actions Class
 *
 * A lightweight class designed to simplify the registration of custom row actions in WordPress.
 * Supports static URLs, dynamic URL generation via callbacks, and AJAX-powered actions with
 * automatic security handling.
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
use SugarCommerce\Columns\Rest\RowActionRoute;
use SugarCommerce\Columns\Support\Position;
use SugarCommerce\Columns\Traits\Abstracts\RowActions\Links;
use SugarCommerce\Columns\Traits\Abstracts\RowActions\Scripts;
use SugarCommerce\Fields\Icons\Icons;

/**
 * Class RowActions
 *
 * Base class for registering custom row actions in WordPress.
 *
 * @package SugarCommerce\Columns
 */
abstract class RowActions {

	use Links;
	use Scripts;

	/**
	 * Object type constant that must be defined by child classes.
	 *
	 * Examples: 'post', 'user', 'term', 'comment'
	 *
	 * @var string
	 */
	protected const string OBJECT_TYPE = '';

	/**
	 * The class every icon carries, SVG or Dashicon.
	 *
	 * The same as sugarcommerce/fields' own (`Icons::ICON_CLASS`), so the
	 * stylesheet sizes either kind with one rule and a Dashicon drawn here
	 * without the kit looks like one drawn by it.
	 */
	public const string ICON_CLASS = 'sugarcommerce-icon';

	/**
	 * Array of custom action configurations.
	 *
	 * @var array
	 */
	protected static array $actions = [];

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
	 * Core action keys to take off each table, from every registration.
	 *
	 * Per table rather than per instance: the hooks attach once per table, so
	 * a second registration's removals kept on its own instance were never
	 * read.
	 *
	 * @var array<string, array<string, string[]>>
	 */
	protected static array $removed = [];

	/**
	 * The tables whose hooks are already attached.
	 *
	 * Registering twice for one table attached a second admin-ajax handler
	 * and a second filter that each did the whole table's work.
	 *
	 * @var array<string, array<string, bool>>
	 */
	protected static array $hooked = [];

	/**
	 * Whether assets have been enqueued.
	 *
	 * @var bool
	 */
	protected static bool $assets_enqueued = false;

	/**
	 * RowActions constructor.
	 *
	 * @param array  $actions        Custom actions configuration.
	 * @param string $object_subtype Object subtype (e.g., 'post', 'page', 'category').
	 * @param array  $keys_to_remove Optional. Array of action keys to remove. Default empty array.
	 *
	 * @throws Exception If an action key is invalid or OBJECT_TYPE is not defined.
	 */
	public function __construct( array $actions, string $object_subtype, array $keys_to_remove = [] ) {
		// Validate that child class defined OBJECT_TYPE
		if ( empty( static::OBJECT_TYPE ) ) {
			throw new Exception( 'Child class must define OBJECT_TYPE constant.' );
		}

		$this->object_type    = static::OBJECT_TYPE;
		$this->object_subtype = $object_subtype;
		$this->set_keys_to_remove( $keys_to_remove );
		$this->add_actions( $actions );

		// Attached now, as the columns, filters and bulk actions are, rather
		// than deferred to admin_init: add_filter() only has to happen before
		// the hook fires.
		if ( ! isset( self::$hooked[ $this->object_type ][ $this->object_subtype ] ) ) {
			self::$hooked[ $this->object_type ][ $this->object_subtype ] = true;

			$this->load_hooks();
		}

		// The REST route an `ajax` action runs through, on every request:
		// the script's request is not the screen that drew the link.
		RowActionRoute::boot();
	}

	/**
	 * Add core action keys to take off this table.
	 *
	 * @param string[] $keys Action keys to remove.
	 *
	 * @return void
	 */
	public function set_keys_to_remove( array $keys ): void {
		$current = self::$removed[ $this->object_type ][ $this->object_subtype ] ?? [];

		self::$removed[ $this->object_type ][ $this->object_subtype ] = array_values( array_unique( array_merge( $current, array_map( 'strval', $keys ) ) ) );
	}

	/**
	 * Add new actions to the existing configuration.
	 *
	 * @param array $actions Custom actions configuration.
	 *
	 * @return void
	 * @throws Exception If an action key is invalid.
	 */
	public function add_actions( array $actions ): void {
		$default_action = [
			'label'               => '',
			'label_callback'      => null,
			'url'                 => '',
			'url_callback'        => null,
			'ajax'                => false,
			'callback'            => null,
			'position'            => '',
			'permission_callback' => null,
			'capability'          => 'manage_options',
			'confirm'             => '',
			'class'               => '',
			'target'              => '',
			'icon'                => '',
		];

		foreach ( $actions as $key => $action ) {
			if ( ! is_string( $key ) || empty( $key ) ) {
				throw new Exception( 'Invalid action key provided. It must be a non-empty string.' );
			}

			self::$actions[ $this->object_type ][ $this->object_subtype ][ $key ] = wp_parse_args( $action, $default_action );
		}
	}

	/**
	 * Get actions array for the given object type and subtype.
	 *
	 * @param string $object_type    Object type.
	 * @param string $object_subtype Object subtype.
	 *
	 * @return array
	 */
	public static function get_actions( string $object_type, string $object_subtype ): array {
		return self::$actions[ $object_type ][ $object_subtype ] ?? [];
	}

	/**
	 * Get the configuration for a specific action by name.
	 *
	 * @param string $action_name    The name of the action.
	 * @param string $object_type    Object type.
	 * @param string $object_subtype Object subtype.
	 *
	 * @return array|null The action configuration if exists, null otherwise.
	 */
	public function get_action_by_name( string $action_name, string $object_type, string $object_subtype ): ?array {
		$actions = self::get_actions( $object_type, $object_subtype );

		return $actions[ $action_name ] ?? null;
	}

	/**
	 * Register custom row actions.
	 *
	 * @param array $actions   Array of existing actions.
	 * @param mixed $item    The object (post, user, term, comment).
	 * @param int   $object_id The object ID.
	 *
	 * @return array Updated array of actions with custom actions.
	 */
	public function register_actions( array $actions, $item, int $object_id ): array {
		$custom_actions = self::get_actions( $this->object_type, $this->object_subtype );

		// Remove specified keys from existing actions
		$actions = $this->remove_keys_from_actions( $actions );

		foreach ( $custom_actions as $key => $action ) {
			if ( ! $this->check_action_permission( $action, $object_id ) ) {
				continue;
			}

			$actions = Position::insert( $actions, (string) $action['position'], (string) $key, $this->get_action_link( $action, $object_id, (string) $key ) );
		}

		return $actions;
	}

	/**
	 * Remove specified keys from the actions array.
	 *
	 * @param array $actions Array of existing actions.
	 *
	 * @return array The actions array with specified keys removed.
	 */
	protected function remove_keys_from_actions( array $actions ): array {
		foreach ( self::$removed[ $this->object_type ][ $this->object_subtype ] ?? [] as $key ) {
			if ( array_key_exists( $key, $actions ) ) {
				unset( $actions[ $key ] );
			}
		}

		return $actions;
	}

	/**
	 * Check action permission.
	 *
	 * @param array $action    The action configuration.
	 * @param int   $object_id The object ID.
	 *
	 * @return bool True if permission is granted, false otherwise.
	 */
	protected function check_action_permission( array $action, int $object_id ): bool {
		return self::permits( $action, $object_id );
	}

	/**
	 * Whether the signed-in user may see and run an action on a row: its
	 * capability, then its `permission_callback` for that row.
	 *
	 * @param array $action    The action.
	 * @param int   $object_id The row.
	 *
	 * @return bool
	 */
	public static function permits( array $action, int $object_id ): bool {
		if ( ! current_user_can( (string) ( $action['capability'] ?? 'manage_options' ) ) ) {
			return false;
		}

		if ( isset( $action['permission_callback'] ) && is_callable( $action['permission_callback'] ) ) {
			return (bool) call_user_func( $action['permission_callback'], $object_id );
		}

		return true;
	}

	/**
	 * Load the necessary hooks for custom row actions.
	 *
	 * Registers WordPress hooks for adding and handling custom row actions.
	 *
	 * @return void
	 */
	abstract public function load_hooks(): void;
}
