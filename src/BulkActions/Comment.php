<?php
/**
 * Comment Bulk Actions
 *
 * @package     SugarCommerce\Columns
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Columns\BulkActions;

use SugarCommerce\Columns\Abstracts\BulkActions;

/**
 * Custom bulk actions on the comments list table.
 */
class Comment extends BulkActions {

	/**
	 * The object type these actions are for.
	 *
	 * @var string
	 */
	protected const OBJECT_TYPE = 'comment';
}
