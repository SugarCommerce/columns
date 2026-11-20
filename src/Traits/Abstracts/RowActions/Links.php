<?php
/**
 * Action Links
 *
 * @package SugarCommerce\Columns
 */

declare( strict_types=1 );

namespace SugarCommerce\Columns\Traits\Abstracts\RowActions;

use SugarCommerce\Columns\Utils\Runtime;

/**
 * The link an action draws in a row: to a URL, or one the script runs
 * through the copy's REST route.
 */
trait Links {

	/**
	 * Generate the action link HTML.
	 *
	 * @param array  $action     The action configuration.
	 * @param int    $object_id  The object ID.
	 * @param string $action_key The action's key.
	 *
	 * @return string The action link HTML.
	 */
	protected function get_action_link( array $action, int $object_id, string $action_key = '' ): string {
		// Use label_callback if provided, otherwise use static label
		if ( ! empty( $action['label_callback'] ) && is_callable( $action['label_callback'] ) ) {
			$label = esc_html( call_user_func( $action['label_callback'], $object_id ) );
		} else {
			$label = esc_html( $action['label'] );
		}

		if ( $action['ajax'] ) {
			return $this->get_ajax_action_link( $action, $object_id, $label, $action_key );
		}

		return $this->get_url_action_link( $action, $object_id, $label );
	}

	/**
	 * Generate an AJAX action link.
	 *
	 * @param array  $action     The action configuration.
	 * @param int    $object_id  The object ID.
	 * @param string $label      The action label.
	 * @param string $action_key The action's key. Looked up by its
	 *                           configuration when not given, which finds the
	 *                           first of two identically configured actions.
	 *
	 * @return string The AJAX action link HTML.
	 */
	protected function get_ajax_action_link( array $action, int $object_id, string $label, string $action_key = '' ): string {
		if ( '' === $action_key ) {
			$action_key = (string) array_search( $action, self::get_actions( $this->object_type, $this->object_subtype ), true );
		}

		$class = 'row-action-ajax ' . esc_attr( $action['class'] );

		$icon_html = self::icon( $action['icon'] );
		$icon_html = '' !== $icon_html ? $icon_html . ' ' : '';

		// The configuration's name goes on the link rather than one shared
		// JavaScript object: each prefixed copy has a route of its own, and
		// they all load the same script under different handles, so a shared
		// global would be owned by whichever printed last -- and every other
		// copy's links would go to the wrong plugin's route.
		return sprintf(
			'<a href="#" class="%s" data-config="%s" data-object-type="%s" data-object-subtype="%s" data-action-key="%s" data-object-id="%s" data-confirm="%s">%s%s</a>',
			$class,
			esc_attr( Runtime::handle() ),
			esc_attr( $this->object_type ),
			esc_attr( $this->object_subtype ),
			esc_attr( $action_key ),
			esc_attr( $object_id ),
			esc_attr( $action['confirm'] ),
			$icon_html,
			$label
		);
	}

	/**
	 * Generate a URL-based action link.
	 *
	 * @param array  $action    The action configuration.
	 * @param int    $object_id The object ID.
	 * @param string $label     The action label.
	 *
	 * @return string The URL action link HTML.
	 */
	protected function get_url_action_link( array $action, int $object_id, string $label ): string {
		if ( is_callable( $action['url_callback'] ) ) {
			$url = call_user_func( $action['url_callback'], $object_id );
		} else {
			$url = ! empty( $action['url'] ) ? add_query_arg( 'id', $object_id, $action['url'] ) : '#';
		}

		$target = ! empty( $action['target'] ) ? sprintf( ' target="%s"', esc_attr( $action['target'] ) ) : '';
		$class  = ! empty( $action['class'] ) ? esc_attr( $action['class'] ) : '';

		$icon_html = self::icon( $action['icon'] );
		$icon_html = '' !== $icon_html ? $icon_html . ' ' : '';

		return sprintf(
			'<a href="%s" class="%s"%s>%s%s</a>',
			esc_url( $url ),
			$class,
			$target,
			$icon_html,
			$label
		);
	}
}
