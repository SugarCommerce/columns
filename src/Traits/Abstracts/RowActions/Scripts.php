<?php
/**
 * Scripts and Icons
 *
 * @package SugarCommerce\Columns
 */

declare( strict_types=1 );

namespace SugarCommerce\Columns\Traits\Abstracts\RowActions;

use SugarCommerce\Columns\Rest\RowActionRoute;
use SugarCommerce\Columns\Utils\Runtime;
use SugarCommerce\Fields\Icons\Icons;
use SugarCommerce\Plugin\Assets;

/**
 * The script and stylesheet a screen with row actions loads, the settings
 * handed to the script, and the icons for each state.
 */
trait Scripts {

	/**
	 * Enqueue assets for AJAX actions.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		// Only enqueue once
		if ( self::$assets_enqueued ) {
			return;
		}

		// The script is for AJAX actions; the stylesheet for them and for
		// any action with an icon, which it lines up with the label.
		$has_ajax_actions = false;
		$has_icons        = false;
		foreach ( self::get_actions( $this->object_type, $this->object_subtype ) as $action ) {
			$has_ajax_actions = $has_ajax_actions || ! empty( $action['ajax'] );
			$has_icons        = $has_icons || ! empty( $action['icon'] );
		}

		if ( ! $has_ajax_actions && ! $has_icons ) {
			return;
		}

		$handle = Runtime::handle();

		Assets::enqueue_style( $handle, __FILE__, 'css/row-actions.css' );

		// Not marked done for the stylesheet alone: a later table with AJAX
		// actions still needs the script.
		if ( ! $has_ajax_actions ) {
			return;
		}

		self::$assets_enqueued = true;

		if ( ! Assets::enqueue_built_script( $handle, __FILE__, 'row-actions' ) ) {
			return;
		}

		// Keyed by handle, and the link names its handle: two plugins each
		// bundling a prefixed copy load two copies of the script, and one
		// shared object would leave whichever printed last owning both.
		wp_add_inline_script(
			$handle,
			sprintf(
				'window.SugarCommerceColumns=window.SugarCommerceColumns||{};window.SugarCommerceColumns[%s]=%s;',
				wp_json_encode( $handle ),
				wp_json_encode( self::script_config() )
			),
			'before'
		);
	}

	/**
	 * What the script is told: where to post, what to say, what to draw.
	 *
	 * @return array{ajaxUrl: string, strings: array<string, string>, icons: array<string, string>}
	 */
	public static function script_config(): array {
		return [
			// The REST route this copy's actions run through, for api-fetch.
			'path'    => RowActionRoute::path(),

			'strings' => [
				'processing' => __( 'Processing...', 'sugarcommerce' ),
				'error'      => __( 'Error', 'sugarcommerce' ),
				'success'    => __( 'Success', 'sugarcommerce' ),
			],

			// Drawn here rather than in the script, so the states an
			// action passes through match the icons it was given.
			'icons'   => [
				'processing' => self::state_icon( 'arrow-path', 'update-alt', 'row-action-spin' ),
				'success'    => self::state_icon( 'check', 'yes', 'row-action-success' ),
				'error'      => self::state_icon( 'x-mark', 'no-alt', 'row-action-error' ),
			],
		];
	}

	/**
	 * An action's icon, ready to print.
	 *
	 * Takes either form: a `set:name` value (`heroicons-outline:star`),
	 * drawn as inline SVG, or a bare Dashicons name (`star-filled`, or the
	 * whole class `dashicons-star-filled`), drawn as the font's span it
	 * always was. Both carry the `sugarcommerce-icon` class the stylesheet
	 * sizes.
	 *
	 * The SVG comes from sugarcommerce/fields, which this library suggests
	 * rather than requires. Without it a bare name still draws -- the span needs
	 * nothing but the font -- and a `set:name` value draws nothing rather
	 * than a broken span.
	 *
	 * @param mixed $value An icon, in either form.
	 * @param array $args  `class` to add.
	 *
	 * @return string Markup, or '' when there is no icon to draw.
	 */
	protected static function icon( $value, array $args = [] ): string {
		if ( class_exists( Icons::class ) ) {
			return Icons::render( $value, $args );
		}

		if ( ! is_string( $value ) ) {
			return '';
		}

		$value = strtolower( trim( $value ) );
		$name  = str_starts_with( $value, 'dashicons-' ) ? substr( $value, strlen( 'dashicons-' ) ) : $value;

		if ( 1 !== preg_match( '/^[a-z0-9][a-z0-9-]*$/', $name ) ) {
			return '';
		}

		if ( function_exists( 'wp_enqueue_style' ) ) {
			wp_enqueue_style( 'dashicons' );
		}

		return sprintf(
			'<span class="%s" aria-hidden="true"></span>',
			esc_attr( trim( 'dashicons dashicons-' . $name . ' ' . self::ICON_CLASS . ' ' . ( $args['class'] ?? '' ) ) )
		);
	}

	/**
	 * One of the icons the script shows while an AJAX action runs.
	 *
	 * Heroicons outline, or the Dashicon it replaced when sugarcommerce/fields
	 * is not there to draw it.
	 *
	 * @param string $heroicon Its Heroicons outline name.
	 * @param string $dashicon The Dashicon to fall back to.
	 * @param string $css_class The class the stylesheet colours it by.
	 *
	 * @return string
	 */
	protected static function state_icon( string $heroicon, string $dashicon, string $css_class ): string {
		$value = class_exists( Icons::class ) ? 'heroicons-outline:' . $heroicon : $dashicon;

		return self::icon( $value, [ 'class' => $css_class ] );
	}
}
