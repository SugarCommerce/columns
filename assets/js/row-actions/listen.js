/**
 * Which links a copy of the library answers: its own, and only once.
 *
 * Every plugin bundling a prefixed copy loads the script under its own
 * handle, and each link names the copy that drew it (`data-config`). So
 * each copy listens for its own links alone: another plugin's copy -- of
 * whatever version -- never runs this one's actions, and two copies on one
 * screen never send an action twice.
 */

import { run } from './run';

/**
 * The handle a script element was printed under: WordPress gives it the id
 * "{handle}-js".
 *
 * @param {Element|null} element The <script> element, or null.
 * @return {string} The handle, or an empty string.
 */
export function handleOf( element ) {
	return element && element.id ? element.id.replace( /-js$/, '' ) : '';
}

/**
 * Listen for clicks on one copy's links.
 *
 * The flag that keeps a copy from listening twice is kept in the copy's
 * own entry of the registry, never in a name two copies share.
 *
 * @param {Document} doc              The document.
 * @param {string}   handle           The copy's script handle.
 * @param {Object}   options          Options.
 * @param {Object}   options.all      window.SugarCommerceColumns.
 * @param {Function} options.apiFetch api-fetch, or a fake.
 * @param {Function} options.confirm  window.confirm, or a fake.
 * @param {Function} options.reload   Loads the page again.
 * @param {Function} options.later    setTimeout, or a fake.
 * @return {boolean} Whether it began listening: not without a handle, and
 *                   not a second time.
 */
export function listen(
	doc,
	handle,
	{ all, apiFetch, confirm, reload, later }
) {
	if ( ! handle || ! all ) {
		return false;
	}

	const entry = ( all[ handle ] = all[ handle ] || {} );

	if ( entry.bound ) {
		return false;
	}

	entry.bound = true;

	doc.addEventListener( 'click', ( event ) => {
		const link =
			event.target instanceof doc.defaultView.Element
				? event.target.closest( '.row-action-ajax' )
				: null;

		if ( ! link || link.getAttribute( 'data-config' ) !== handle ) {
			return;
		}

		event.preventDefault();
		run( link, { all, apiFetch, confirm, reload, later } );
	} );

	return true;
}
