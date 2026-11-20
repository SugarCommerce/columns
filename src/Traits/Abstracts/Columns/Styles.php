<?php
/**
 * Column Styles
 *
 * @package SugarCommerce\Columns
 */

declare( strict_types=1 );

namespace SugarCommerce\Columns\Traits\Abstracts\Columns;

use SugarCommerce\Columns\Support\Image;

/**
 * The widths and alignment a screen's custom columns ask for, as one
 * inline stylesheet.
 */
trait Styles {

	/**
	 * Add custom column styles for width control.
	 *
	 * @return void
	 */
	public function add_custom_column_styles(): void {
		if ( ! $this->is_screen() ) {
			return;
		}

		$rules = '';

		foreach ( self::get_columns( $this->object_type, $this->object_subtype ) as $key => $column ) {
			$class    = sanitize_html_class( (string) $key );
			$width    = self::sanitize_width( (string) ( $column['width'] ?? '' ) );
			$is_image = false !== ( $column['image'] ?? false ) && null !== ( $column['image'] ?? false );

			if ( $is_image ) {
				// An image column with no width set stretches like any other,
				// which puts a 32px thumbnail in the middle of a third of the
				// table. Fall back to the image's own width plus core's cell
				// padding rather than making every caller say it.
				if ( '' === $width ) {
					$width = ( Image::pixels( $column['image_size'] ?? Image::DEFAULT_SIZE )[0] + 20 ) . 'px';
				}

				// Killing the margin core puts on list-table images: that rule
				// exists to sit an avatar beside a username, and here there is
				// nothing beside it.
				$rules .= sprintf(
					'.column-%1$s img{display:block;margin:0;float:none;max-width:100%%;height:auto}',
					$class
				);

				// On a phone core labels every cell but the title's with its
				// column's name, laid over the cell's left side -- which a
				// thumbnail column 52px wide, placed before the title as it
				// usually is, cannot hold: "Image" was drawn over the dash
				// and the picture. A thumbnail needs no label. The selector
				// carries core's own specificity and one more, to win.
				$rules .= sprintf(
					'@media screen and (max-width:782px){.wp-list-table tr:not(.inline-edit-row):not(.no-items) td.column-%1$s:not(.column-primary)::before{display:none}}',
					$class
				);
			}

			if ( '' === $width ) {
				continue;
			}

			$rules .= sprintf( '.column-%s{width:%s}', $class, $width );
		}

		// Nothing to say, so nothing said. An empty <style> element in every
		// admin head is not harmful and is not defensible either.
		if ( '' === $rules ) {
			return;
		}

		printf( '<style>%s</style>', $rules ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * A width, if it is one.
	 *
	 * A CSS length, a percentage, or `auto` — which is a real width and the
	 * way to say "leave this column alone".
	 *
	 * This goes into a stylesheet, and esc_attr() does nothing useful there:
	 * it escapes for an HTML attribute, so a brace and a semicolon pass
	 * straight through and `10px;} body{display:none` closes the rule and
	 * writes another. A CSS length is a number and a unit, so that is all
	 * that is allowed through.
	 *
	 * @param string $width The configured width.
	 *
	 * @return string The width, or an empty string if it is not one.
	 */
	protected static function sanitize_width( string $width ): string {
		$width = trim( $width );

		return preg_match( '/^(auto|\d+(\.\d+)?(px|em|rem|%|ch|vw))$/', $width ) ? $width : '';
	}
}
