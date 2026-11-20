/**
 * Row actions that run without leaving the list: a link in a row of one of
 * core's list tables, sent to this copy's REST route (see
 * row-actions/run.js) with api-fetch, which carries the REST nonce and
 * renews it.
 *
 * Every plugin bundling a prefixed copy of the library loads this file under
 * its own handle. One click handler serves all of them -- each link names
 * its own settings -- so a second copy binding again would send every
 * action twice; `window.SugarCommerceColumnsRowActions` is the flag.
 */

import apiFetch from '@wordpress/api-fetch';
import domReady from '@wordpress/dom-ready';

import { run } from './row-actions/run';

if ( ! window.SugarCommerceColumnsRowActions ) {
	const handler = {
		/**
		 * Run the action a link stands for.
		 *
		 * @param {Element} link The `.row-action-ajax` link.
		 * @return {Promise<void>} Settles when it has answered.
		 */
		run: ( link ) =>
			run( link, {
				all: window.SugarCommerceColumns,
				apiFetch,
				// The question the action asks before it runs, as it always
				// has: the browser's own.
				// eslint-disable-next-line no-alert
				confirm: ( question ) => window.confirm( question ),
				reload: () => window.location.reload(),
				later: ( callback, wait ) =>
					window.setTimeout( callback, wait ),
			} ),
	};

	window.SugarCommerceColumnsRowActions = handler;

	domReady( () => {
		document.addEventListener( 'click', ( event ) => {
			const link =
				event.target instanceof window.Element
					? event.target.closest( '.row-action-ajax' )
					: null;

			if ( link ) {
				event.preventDefault();
				handler.run( link );
			}
		} );
	} );
}
