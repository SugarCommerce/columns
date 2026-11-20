<?php
/**
 * Media Row Actions Class
 *
 * Handles custom row action registration for WordPress media library.
 * Since media are a special post type (attachment), this class integrates with
 * WordPress's media-specific row action filters.
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
use WP_Post;

/**
 * Class Media
 *
 * Manages custom row actions for media in the WordPress admin.
 *
 * @package SugarCommerce\Columns
 */
class Media extends RowActions {

	/**
	 * Object type for media.
	 *
	 * @var string
	 */
	protected const string OBJECT_TYPE = 'post';

	/**
	 * Load the necessary hooks for custom row actions.
	 *
	 * Registers WordPress hooks for adding custom media row actions.
	 *
	 * @return void
	 */
	#[\Override]
	public function load_hooks(): void {
		add_filter( 'media_row_actions', [ $this, 'register_actions_wrapper' ], 10, 2 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Wrapper for registering actions with WordPress filter.
	 *
	 * @param array   $actions Array of existing actions.
	 * @param WP_Post $post    The attachment post object.
	 *
	 * @return array Updated array of actions.
	 */
	public function register_actions_wrapper( array $actions, WP_Post $post ): array {
		return $this->register_actions( $actions, $post, $post->ID );
	}
}
