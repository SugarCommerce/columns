/**
 * Running a row action: the click, the request, and what the row and the
 * page show while it runs and after.
 *
 * Every copy of the library loads this script under its own handle and
 * prints its own settings under that handle; a link names its copy, so it
 * is sent to that copy's REST route with that copy's words and icons.
 */

/**
 * The settings a link's copy of the library printed.
 *
 * @param {Element} link The link.
 * @param {Object}  all  window.SugarCommerceColumns.
 * @return {Object} Its `path`, `strings` and `icons`, or an empty object.
 */
export function configFor( link, all ) {
	return ( all || {} )[ link.getAttribute( 'data-config' ) ] || {};
}

/**
 * The icon for each state, drawn by PHP; Dashicons when none were sent.
 *
 * @param {Object} config The link's settings.
 * @return {{processing: string, success: string, error: string}} Markup.
 */
export function icons( config ) {
	const drawn = ( config || {} ).icons || {};

	return {
		processing:
			drawn.processing ||
			'<span class="dashicons dashicons-update-alt sugarcommerce-icon row-action-spin"></span>',
		success:
			drawn.success ||
			'<span class="dashicons dashicons-yes sugarcommerce-icon row-action-success"></span>',
		error:
			drawn.error ||
			'<span class="dashicons dashicons-no-alt sugarcommerce-icon row-action-error"></span>',
	};
}

/**
 * What a link asks its route for.
 *
 * @param {Element} link The link.
 * @return {Object} The request's data.
 */
export function requestFor( link ) {
	return {
		object_type: link.getAttribute( 'data-object-type' ) || '',
		object_subtype: link.getAttribute( 'data-object-subtype' ) || '',
		action_key: link.getAttribute( 'data-action-key' ) || '',
		object_id: Number( link.getAttribute( 'data-object-id' ) ) || 0,
		options: {},
	};
}

/**
 * A notice under the screen's heading, its message as text: it comes from a
 * plugin's callback and can carry a name somebody typed.
 *
 * @param {Document} doc     The document.
 * @param {string}   message What to say.
 * @param {string}   type    success, error, warning or info.
 * @param {Function} later   setTimeout, or a fake.
 * @return {?Element} The notice, or null with nowhere to put it.
 */
export function showNotice( doc, message, type, later ) {
	const kinds = [ 'success', 'error', 'warning', 'info' ];
	const notice = doc.createElement( 'div' );
	const text = doc.createElement( 'p' );
	const heading = doc.querySelector( '.wrap > h1, .wrap > h2' );
	const wrap = doc.querySelector( '.wrap' );

	notice.className =
		'notice notice-' +
		( kinds.includes( type ) ? type : 'success' ) +
		' is-dismissible';
	text.textContent = String( message );
	notice.append( text );

	if ( heading ) {
		heading.after( notice );
	} else if ( wrap ) {
		wrap.prepend( notice );
	} else {
		return null;
	}

	// Core's common.js gives it its dismiss button.
	doc.dispatchEvent( new doc.defaultView.Event( 'wp-updates-notice-added' ) );

	if ( 'success' === type ) {
		later( () => notice.remove(), 5000 );
	}

	return notice;
}

/**
 * Run the action a link stands for.
 *
 * @param {Element}  link             The `.row-action-ajax` link.
 * @param {Object}   options          Options.
 * @param {Object}   options.all      window.SugarCommerceColumns.
 * @param {Function} options.apiFetch api-fetch, or a fake.
 * @param {Function} options.confirm  window.confirm, or a fake.
 * @param {Function} options.reload   Loads the page again.
 * @param {Function} options.later    setTimeout, or a fake.
 * @return {Promise<void>} Settles when it has answered.
 */
export function run( link, { all, apiFetch, confirm, reload, later } ) {
	const question = link.getAttribute( 'data-confirm' );

	if ( question && ! confirm( question ) ) {
		return Promise.resolve();
	}

	const config = configFor( link, all );
	const strings = config.strings || {};
	const states = icons( config );
	const doc = link.ownerDocument;

	const original = link.innerHTML;
	const icon = link.querySelector( '.sugarcommerce-icon, .dashicons' );
	const restore = () => {
		link.innerHTML = original;
		link.style.pointerEvents = '';
		link.style.opacity = '';
	};

	if ( icon ) {
		icon.outerHTML = states.processing;
	} else {
		link.innerHTML =
			states.processing + ' ' + ( strings.processing || 'Processing...' );
	}

	link.style.pointerEvents = 'none';
	link.style.opacity = '0.6';

	return apiFetch( {
		path: config.path || '',
		method: 'POST',
		data: requestFor( link ),
	} ).then(
		( answer ) => {
			const result = answer || {};

			if ( result.message ) {
				showNotice( doc, result.message, 'success', later );
			}

			if ( false !== result.reload ) {
				link.innerHTML =
					states.success + ' ' + ( strings.success || 'Success' );
				later( () => reload(), 500 );

				return;
			}

			link.innerHTML = states.success;
			later( restore, 1000 );
		},
		( error ) => {
			showNotice(
				doc,
				( error && error.message ) ||
					( strings.error || 'Error' ) + ': Unknown error',
				'error',
				later
			);

			link.innerHTML = states.error;
			later( restore, 2000 );
		}
	);
}
