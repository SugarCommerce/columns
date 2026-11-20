<?php
/**
 * Drawing Cells
 *
 * @package SugarCommerce\Columns
 */

declare( strict_types=1 );

namespace SugarCommerce\Columns\Traits\Abstracts\Columns;

use SugarCommerce\Columns\Support\Image;

/**
 * Drawing a custom column's cell: a callback's markup, a meta value, an
 * image, or the placeholder for nothing.
 */
trait Cells {

	/**
	 * What one cell of a custom column contains.
	 *
	 * A display callback is given the meta value first when the column names
	 * a meta_key, and the object id alone when it does not — so a column that
	 * is about a stored value does not have to fetch it, and one that is
	 * about the object can still do anything.
	 *
	 * @param int|string           $object_id The object.
	 * @param array<string, mixed> $column    The column's configuration.
	 *
	 * @return string
	 */
	protected function render_custom_column_content( $object_id, array $column ): string {
		$meta_key = (string) ( $column['meta_key'] ?? '' );

		if ( is_callable( $column['display_callback'] ) ) {
			return '' === $meta_key
				? (string) call_user_func( $column['display_callback'], $object_id )
				: (string) call_user_func( $column['display_callback'], $this->get_meta( $object_id, $meta_key ), $object_id );
		}

		if ( false !== $column['image'] && null !== $column['image'] ) {
			return $this->render_image_cell( $object_id, $column );
		}

		$value = '' === $meta_key ? '' : $this->get_meta( $object_id, $meta_key );

		// Not empty(): a stored nought is a value, and a column of counts
		// showing a dash where it should show 0 is a column that looks
		// broken rather than empty.
		if ( null === $value || '' === $value || false === $value || [] === $value ) {
			return self::placeholder();
		}

		return esc_html( (string) $value );
	}

	/**
	 * What one cell of an image column contains.
	 *
	 * With a meta_key the stored value is the image -- an attachment id or a
	 * URL. Without one the table says what its own natural image is: the
	 * featured image on a post, the avatar on a user, the file itself on a
	 * media item. That is the same relationship core has between the users
	 * table and the avatar it puts in the username column, which is what
	 * this is for.
	 *
	 * @param int|string           $object_id The object.
	 * @param array<string, mixed> $column    The column's configuration.
	 *
	 * @return string
	 */
	protected function render_image_cell( $object_id, array $column ): string {
		$meta_key = (string) ( $column['meta_key'] ?? '' );
		$size     = $column['image_size'] ?? Image::DEFAULT_SIZE;

		$html = '' === $meta_key
			? $this->render_default_image( $object_id, $size )
			: Image::render( $this->get_meta( $object_id, $meta_key ), $size );

		// A post with no featured image, or an attachment id whose file has
		// been deleted. Either way the cell shows what every other empty
		// cell shows rather than a broken image.
		return '' === $html ? self::placeholder() : $html;
	}

	/**
	 * The image this table shows when the column names no meta_key.
	 *
	 * Empty by default: a table with no natural image of its own -- terms --
	 * should ask for one with a meta_key rather than invent it.
	 *
	 * @param int|string       $object_id The object.
	 * @param int|string|array $size      Configured size.
	 *
	 * @return string
	 */
	protected function render_default_image( $object_id, $size ): string {
		return '';
	}

	/**
	 * What an empty cell shows.
	 *
	 * A dash for the eye and a word for anything reading the page aloud: an
	 * em dash announces as "em dash", or as nothing at all, and a blank cell
	 * in a list table reads as a column that failed to load.
	 *
	 * @return string
	 */
	protected static function placeholder(): string {
		return '<span aria-hidden="true">&#8212;</span><span class="screen-reader-text">'
			. esc_html__( 'None', 'sugarcommerce' ) . '</span>';
	}
}
