<?php
/**
 * PHPUnit bootstrap.
 *
 * This is a library, not a plugin: there is no WordPress to load. The handful
 * of WordPress functions the testable paths touch are stubbed here, kept as
 * close to core's real behaviour as the tests depend on and no closer.
 *
 * @package SugarCommerce\Columns
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

if ( ! defined( 'OBJECT' ) ) {
	define( 'OBJECT', 'OBJECT' );
}

/* -----------------------------------------------------------------------
 * Row actions
 *
 * These came with the row actions library when it was absorbed. Its own
 * stubs for the hooks, capabilities and escaping are not here: this file
 * already had them, and where the two disagreed the stricter one stayed --
 * a real add_query_arg over one returning a fixed string, a real
 * did_action over one that always answered 1.
 *
 * The two that came the other way were wp_parse_args, which handles an
 * object, and wp_unslash, which recurses into an array. Both are what core
 * does and neither was here.
 * -------------------------------------------------------------------- */

if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . ltrim( (string) $path, '/' ); }
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value, ...$rest ) {
		foreach ( $GLOBALS['ra_filters'][ $hook ] ?? array() as $cb ) {
			$value = $cb( $value, ...$rest );
		}
		return $value;
	}
}

if ( ! function_exists( 'check_ajax_referer' ) ) {
	function check_ajax_referer( $action, $query_arg = false, $die = true ) {
		$given = $_POST[ $query_arg ] ?? '';
		return 'nonce:' . $action === $given;
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) { return (string) $url; }
}

if ( ! function_exists( 'get_edit_post_link' ) ) {
	function get_edit_post_link( $id = 0, $context = 'display' ) { return 'https://example.test/wp-admin/post.php?post=' . (int) $id; }
}

if ( ! function_exists( 'is_admin' ) ) {
	function is_admin() { return true; }
}

if ( ! function_exists( 'wp_create_nonce' ) ) {
	function wp_create_nonce( $action = -1 ) {
		$GLOBALS['ra_nonces'][] = (string) $action;
		return 'nonce:' . $action;
	}
}

/*
 * What SugarCommerce\Plugin\Assets calls is stubbed, down to the enqueue, which is where a
 * test reads what was asked for. The library's own root stands in for
 * wp-content so its assets resolve to a URL.
 */
if ( ! defined( 'WP_CONTENT_DIR' ) ) {
	define( 'WP_CONTENT_DIR', dirname( __DIR__ ) );
}

if ( ! function_exists( 'wp_normalize_path' ) ) {
	function wp_normalize_path( $path ) { return preg_replace( '#/+#', '/', str_replace( '\\', '/', (string) $path ) ); }
}

if ( ! function_exists( 'content_url' ) ) {
	function content_url( $path = '' ) { return 'https://example.test/wp-content/' . ltrim( (string) $path, '/' ); }
}

if ( ! function_exists( 'site_url' ) ) {
	function site_url( $path = '' ) { return 'https://example.test/' . ltrim( (string) $path, '/' ); }
}

if ( ! function_exists( 'wp_register_script' ) ) {
	function wp_register_script( $handle, ...$rest ) { return true; }
}

if ( ! function_exists( 'wp_register_style' ) ) {
	function wp_register_style( $handle, ...$rest ) { return true; }
}

if ( ! function_exists( 'wp_enqueue_script' ) ) {
	function wp_enqueue_script( $handle, ...$rest ) { $GLOBALS['ra_scripts'][ $handle ] = true; }
}

if ( ! function_exists( 'wp_enqueue_style' ) ) {
	function wp_enqueue_style( $handle, ...$rest ) { $GLOBALS['ra_styles'][ $handle ] = true; }
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, $flags = 0 ) { return json_encode( $data, $flags ); }
}

if ( ! function_exists( 'wp_add_inline_script' ) ) {
	function wp_add_inline_script( $handle, $data, $position = 'after' ) {
		$GLOBALS['ra_inline'][ $handle ][] = array( 'data' => $data, 'position' => $position );
		return true;
	}
}

if ( ! function_exists( 'wp_send_json_error' ) ) {
	function wp_send_json_error( $data = null, $status = 400 ) {
		$GLOBALS['ra_json'][] = array( 'success' => false, 'data' => $data, 'status' => $status );
		throw new RA_Sent();
	}
}

if ( ! function_exists( 'wp_send_json_success' ) ) {
	function wp_send_json_success( $data = null, $status = 200 ) {
		$GLOBALS['ra_json'][] = array( 'success' => true, 'data' => $data, 'status' => $status );
		throw new RA_Sent();
	}
}

if ( ! class_exists( 'RA_Sent' ) ) {
	/**
	 * wp_send_json_*() exits; this stands in for that so a test can continue.
	 *
	 * Extends Error rather than Exception on purpose. handle_ajax() wraps the
	 * callback in catch ( Exception ), so an Exception-based stub is swallowed by
	 * the code under test and reported as a 500 -- which is not what a real exit
	 * does, and made a passing action look like a crashing one.
	 */
	class RA_Sent extends Error {}
}

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

/*
 * Hooks are recorded rather than run, so a test can ask what the library
 * asked WordPress for. A column library is mostly hooks: registering one is
 * almost entirely a matter of attaching the right callback to the right
 * filter, and a wrong hook name is invisible until somebody loads the screen.
 */
if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
		$GLOBALS['rc_hooks'][ $hook ][] = $callback;

		return true;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
		$GLOBALS['rc_hooks'][ $hook ][] = $callback;

		return true;
	}
}

if ( ! function_exists( 'wp_parse_args' ) ) {
	function wp_parse_args( $args, $defaults = array() ) {
		if ( is_object( $args ) ) { $args = get_object_vars( $args ); }
		return array_merge( $defaults, (array) $args );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'sanitize_html_class' ) ) {
	function sanitize_html_class( $class, $fallback = '' ) {
		$class = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $class );

		return '' === $class ? $fallback : $class;
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = 'default' ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

/*
 * $GLOBALS['rc_caps'] is what the current user may do. Null means everything,
 * which keeps every test that is not about permissions from having to care.
 */
if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $capability, ...$args ) {
		$allowed = $GLOBALS['rc_caps'] ?? null;

		return null === $allowed || in_array( $capability, (array) $allowed, true );
	}
}

/* -----------------------------------------------------------------------
 * Images
 *
 * $GLOBALS['rc_attachments'] maps attachment id => URL; an id absent from it
 * is one whose file has been deleted, which core reports by returning ''.
 * $GLOBALS['rc_thumbnails'] maps post id => attachment id.
 * $GLOBALS['rc_avatars'] maps user id => the markup core would return.
 * -------------------------------------------------------------------- */

if ( ! function_exists( 'wp_get_attachment_image' ) ) {
	function wp_get_attachment_image( $attachment_id, $size = 'thumbnail', $icon = false, $attr = array() ) {
		$url = $GLOBALS['rc_attachments'][ (int) $attachment_id ] ?? '';

		if ( '' === $url ) {
			return '';
		}

		$dimensions = is_array( $size ) ? $size : array( 32, 32 );

		return sprintf(
			'<img src="%s" width="%d" height="%d" alt="" />',
			$url,
			(int) $dimensions[0],
			(int) $dimensions[1]
		);
	}
}

if ( ! function_exists( 'get_post_thumbnail_id' ) ) {
	function get_post_thumbnail_id( $post = null ) {
		return $GLOBALS['rc_thumbnails'][ (int) $post ] ?? 0;
	}
}

if ( ! function_exists( 'get_avatar' ) ) {
	function get_avatar( $id_or_email, $size = 96, $default = '', $alt = '', $args = null ) {
		$key = is_object( $id_or_email ) ? ( $id_or_email->comment_ID ?? 0 ) : $id_or_email;

		if ( ! isset( $GLOBALS['rc_avatars'][ (int) $key ] ) ) {
			return false;
		}

		return sprintf( '<img class="avatar" width="%d" src="%s" alt="" />', (int) $size, $GLOBALS['rc_avatars'][ (int) $key ] );
	}
}

if ( ! function_exists( 'get_comment' ) ) {
	function get_comment( $id = null, $output = OBJECT ) {
		return (object) array( 'comment_ID' => (int) $id );
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $option, $default_value = false ) {
		return $GLOBALS['rc_options'][ $option ] ?? $default_value;
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $id, $key = '', $single = false ) {
		return $GLOBALS['rc_meta']['post'][ $id ][ $key ] ?? '';
	}
}

if ( ! function_exists( 'get_user_meta' ) ) {
	function get_user_meta( $id, $key = '', $single = false ) {
		return $GLOBALS['rc_meta']['user'][ $id ][ $key ] ?? '';
	}
}

if ( ! function_exists( 'get_term_meta' ) ) {
	function get_term_meta( $id, $key = '', $single = false ) {
		return $GLOBALS['rc_meta']['term'][ $id ][ $key ] ?? '';
	}
}

if ( ! function_exists( 'get_comment_meta' ) ) {
	function get_comment_meta( $id, $key = '', $single = false ) {
		return $GLOBALS['rc_meta']['comment'][ $id ][ $key ] ?? '';
	}
}

if ( ! function_exists( 'get_current_screen' ) ) {
	function get_current_screen() {
		return $GLOBALS['rc_screen'] ?? null;
	}
}

/*
 * The bulk actions and filters halves reach for a few more. Recorded or
 * given core's real behaviour, as thinly as the tests depend on.
 */
if ( ! function_exists( 'did_action' ) ) {
	function did_action( $hook ) {
		return (int) ( $GLOBALS['rc_did'][ $hook ] ?? 0 );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return strtolower( (string) preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $key ) );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return trim( (string) wp_strip_all_tags( (string) $str ) );
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $text, $remove_breaks = false ) {
		return strip_tags( (string) $text );
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return is_array( $value ) ? array_map( 'wp_unslash', $value ) : ( is_string( $value ) ? stripslashes( $value ) : $value );
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return abs( (int) $value );
	}
}

if ( ! function_exists( 'add_query_arg' ) ) {
	function add_query_arg( ...$given ) {
		// Both of core's forms: ( $array, $url ) and ( $key, $value, $url ).
		if ( is_array( $given[0] ) ) {
			$args = $given[0];
			$url  = (string) ( $given[1] ?? '' );
		} else {
			$args = array( (string) $given[0] => $given[1] ?? '' );
			$url  = (string) ( $given[2] ?? '' );
		}

		$parts = explode( '?', $url, 2 );
		parse_str( $parts[1] ?? '', $existing );

		return $parts[0] . '?' . http_build_query( array_merge( $existing, $args ) );
	}
}

if ( ! function_exists( 'selected' ) ) {
	function selected( $selected, $current = true, $display = true ) {
		$result = (string) $selected === (string) $current ? " selected='selected'" : '';

		if ( $display ) {
			echo $result;
		}

		return $result;
	}
}

if ( ! function_exists( '_n' ) ) {
	function _n( $single, $plural, $number, $domain = 'default' ) {
		return 1 === (int) $number ? $single : $plural;
	}
}

/*
 * Core's admin notice markup (wp-admin/includes/misc.php, 6.4+), as core
 * builds it: the message is not escaped, the id and classes are not either,
 * and wp_admin_notice() passes the result through wp_kses_post(), which a
 * test cannot run and so is left out.
 */
if ( ! function_exists( 'wp_get_admin_notice' ) ) {
	function wp_get_admin_notice( $message, $args = array() ) {
		$args = array_merge(
			array(
				'type'               => '',
				'dismissible'        => false,
				'id'                 => '',
				'additional_classes' => array(),
				'attributes'         => array(),
				'paragraph_wrap'     => true,
			),
			(array) $args
		);

		$id         = '';
		$classes    = 'notice';
		$attributes = '';

		if ( is_string( $args['id'] ) && '' !== trim( $args['id'] ) ) {
			$id = 'id="' . trim( $args['id'] ) . '" ';
		}

		if ( is_string( $args['type'] ) && '' !== trim( $args['type'] ) ) {
			$classes .= ' notice-' . trim( $args['type'] );
		}

		if ( true === $args['dismissible'] ) {
			$classes .= ' is-dismissible';
		}

		if ( is_array( $args['additional_classes'] ) && ! empty( $args['additional_classes'] ) ) {
			$classes .= ' ' . implode( ' ', $args['additional_classes'] );
		}

		if ( is_array( $args['attributes'] ) ) {
			foreach ( $args['attributes'] as $attr => $val ) {
				if ( is_bool( $val ) ) {
					$attributes .= $val ? ' ' . $attr : '';
				} elseif ( is_int( $attr ) ) {
					$attributes .= ' ' . esc_attr( trim( $val ) );
				} elseif ( $val ) {
					$attributes .= ' ' . $attr . '="' . esc_attr( trim( $val ) ) . '"';
				}
			}
		}

		if ( false !== $args['paragraph_wrap'] ) {
			$message = "<p>$message</p>";
		}

		return sprintf( '<div %1$sclass="%2$s"%3$s>%4$s</div>', $id, $classes, $attributes, $message );
	}
}

if ( ! function_exists( 'wp_admin_notice' ) ) {
	function wp_admin_notice( $message, $args = array() ) {
		echo wp_get_admin_notice( $message, $args );
	}
}

/*
 * Writing meta, for the toggle helpers. Into the same store the getters read.
 */
if ( ! function_exists( 'update_post_meta' ) ) {
	function update_post_meta( $id, $key, $value ) {
		$GLOBALS['rc_meta']['post'][ $id ][ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'update_user_meta' ) ) {
	function update_user_meta( $id, $key, $value ) {
		$GLOBALS['rc_meta']['user'][ $id ][ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'update_term_meta' ) ) {
	function update_term_meta( $id, $key, $value ) {
		$GLOBALS['rc_meta']['term'][ $id ][ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'update_comment_meta' ) ) {
	function update_comment_meta( $id, $key, $value ) {
		$GLOBALS['rc_meta']['comment'][ $id ][ $key ] = $value;
		return true;
	}
}

if ( ! class_exists( 'WP_Post' ) ) {
	/**
	 * Core's post, as far as the row action filters read it.
	 */
	#[AllowDynamicProperties]
	final class WP_Post {
		public $ID        = 0;
		public $post_type = 'post';
	}
}

/**
 * Forget everything a previous test set up.
 *
 * @return void
 */
function rc_reset_globals(): void {
	$GLOBALS['rc_hooks'] = [];
	$GLOBALS['rc_meta']  = [];
	$GLOBALS['rc_caps']  = null;
	$GLOBALS['rc_screen'] = null;
	$GLOBALS['rc_did']    = [];
	$GLOBALS['ra_scripts'] = [];
	$GLOBALS['ra_styles']  = [];
	$GLOBALS['ra_inline']  = [];
	$GLOBALS['ra_json']    = [];
	$GLOBALS['ra_nonces']  = [];

	$GLOBALS['rc_attachments'] = [];
	$GLOBALS['rc_thumbnails']  = [];
	$GLOBALS['rc_avatars']     = [];
	$GLOBALS['rc_options']     = [];

	// The column registry and the record of which tables have had their
	// hooks attached are both static, which is right in a request and wrong
	// across tests: the second test would find no hooks attached at all,
	// because the first already claimed the table.
	$statics = [
		'RowActions'  => [ 'actions', 'removed', 'hooked' ],
		'Columns'     => [ 'columns', 'removed', 'hooked' ],
		'BulkActions' => [ 'actions', 'hooked' ],
		'ListFilters' => [ 'filters', 'instances' ],
	];

	foreach ( $statics as $class => $properties ) {
		foreach ( $properties as $property ) {
			( new ReflectionProperty( 'SugarCommerce\\Columns\\Abstracts\\' . $class, $property ) )->setValue( null, [] );
		}
	}

	( new ReflectionProperty( 'SugarCommerce\\Columns\\Abstracts\\RowActions', 'assets_enqueued' ) )->setValue( null, false );
}

/*
 * Core's REST pieces, as far as the row action route uses them.
 */
if ( ! function_exists( 'register_rest_route' ) ) {
	function register_rest_route( $route_namespace, $route, $args = array(), $override = false ) {
		$GLOBALS['rc_routes'][ $route_namespace . $route ] = $args;

		return true;
	}
}

if ( ! class_exists( 'WP_REST_Request' ) ) {
	class WP_REST_Request {
		private array $params = array();

		public function __construct( $method = '', $route = '' ) {
		}

		public function set_param( $key, $value ) {
			$this->params[ $key ] = $value;
		}

		public function get_param( $key ) {
			return $this->params[ $key ] ?? null;
		}
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		public function __construct( public $code = '', public $message = '', public $data = null ) {
		}

		public function get_error_code() {
			return $this->code;
		}

		public function get_error_message() {
			return $this->message;
		}

		public function get_error_data() {
			return $this->data;
		}
	}
}

if ( ! class_exists( 'WP_REST_Response' ) ) {
	class WP_REST_Response {
		public function __construct( public $data = null, public $status = 200 ) {
		}

		public function get_data() {
			return $this->data;
		}
	}
}

if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}

if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( $key, $value, $expiration = 0 ) {
		$GLOBALS['rc_transients'][ $key ] = $value;

		return true;
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $key ) {
		return $GLOBALS['rc_transients'][ $key ] ?? false;
	}
}

if ( ! function_exists( 'get_current_user_id' ) ) {
	function get_current_user_id() {
		return (int) ( $GLOBALS['rc_user_id'] ?? 1 );
	}
}
