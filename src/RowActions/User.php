<?php
/**
 * User Row Actions Class
 *
 * Handles custom row action registration for WordPress users.
 * Integrates with WordPress user row action filters.
 *
 * @package     SugarCommerce\Columns
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

namespace SugarCommerce\Columns\RowActions;

use SugarCommerce\Columns\Abstracts\RowActions;
use SugarCommerce\Columns\Utils\Runtime;
use WP_User;

/**
 * Class User
 *
 * Manages custom row actions for users in the WordPress admin.
 *
 * @package SugarCommerce\Columns
 */
class User extends RowActions {

	/**
	 * Object type for users.
	 *
	 * @var string
	 */
	protected const OBJECT_TYPE = 'user';

	/**
	 * Load the necessary hooks for custom row actions.
	 *
	 * Registers WordPress hooks for adding custom user row actions.
	 *
	 * @return void
	 */
	public function load_hooks(): void {
		add_filter( 'user_row_actions', [ $this, 'register_actions_wrapper' ], 10, 2 );
		add_action( 'wp_ajax_' . Runtime::ajax_action( $this->object_type . '_' . $this->object_subtype ), [ $this, 'handle_ajax' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Wrapper for registering actions with WordPress filter.
	 *
	 * @param array   $actions Array of existing actions.
	 * @param WP_User $user    The user object.
	 *
	 * @return array Updated array of actions.
	 */
	public function register_actions_wrapper( array $actions, WP_User $user ): array {
		return $this->register_actions( $actions, $user, $user->ID );
	}
}
