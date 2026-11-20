/**
 * Row actions that run without leaving the list: a link in a row of one of
 * core's list tables, sent to this copy's REST route (see
 * row-actions/run.js) with api-fetch, which carries the REST nonce and
 * renews it.
 *
 * Every plugin bundling a prefixed copy of the library loads this file under
 * its own handle, and answers its own links only (row-actions/listen.js):
 * its settings, and the flag that it is listening, are its entry in
 * `window.SugarCommerceColumns`, keyed by that handle.
 */

import apiFetch from '@wordpress/api-fetch';
import domReady from '@wordpress/dom-ready';

import { handleOf, listen } from './row-actions/listen';

// Read while the script runs. Once it has, document.currentScript is null.
const handle = handleOf( document.currentScript );

domReady( () => {
	listen( document, handle, {
		all: ( window.SugarCommerceColumns =
			window.SugarCommerceColumns || {} ),
		apiFetch,
		// The question the action asks before it runs, as it always has:
		// the browser's own.
		// eslint-disable-next-line no-alert
		confirm: ( question ) => window.confirm( question ),
		reload: () => window.location.reload(),
		later: ( callback, wait ) => window.setTimeout( callback, wait ),
	} );
} );
