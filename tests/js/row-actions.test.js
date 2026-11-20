/**
 * A row action run from its link: sent to its copy's REST route through
 * api-fetch, with what the row and the page show before and after.
 */

import { beforeEach, describe, expect, test, vi } from 'vitest';

import {
	configFor,
	icons,
	requestFor,
	run,
	showNotice,
} from '../../assets/js/row-actions/run';

const ALL = {
	'one-sugarcommerce-columns': {
		path: 'one-sugarcommerce-columns/v1/row-action',
		strings: { processing: 'Working', success: 'Done', error: 'Oops' },
		icons: {
			processing: '<i class="spin"></i>',
			success: '<i class="ok"></i>',
			error: '<i class="no"></i>',
		},
	},
	'two-sugarcommerce-columns': {
		path: 'two-sugarcommerce-columns/v1/row-action',
	},
};

let link;
let timers;
let later;

/**
 * A list screen with one action link.
 *
 * @param {string} config  The copy the link names.
 * @param {string} confirm Its question, if any.
 */
function page( config = 'one-sugarcommerce-columns', confirm = '' ) {
	document.body.innerHTML = `
		<div class="wrap"><h1>Tracks</h1>
			<table><tr><td><span class="feature">
				<a href="#" class="row-action-ajax" data-config="${ config }" data-object-type="post" data-object-subtype="scpg_col_track" data-action-key="feature" data-object-id="42" data-confirm="${ confirm }"><span class="dashicons dashicons-star-filled sugarcommerce-icon"></span> Feature</a>
			</span></td></tr></table>
		</div>`;
	link = document.querySelector( '.row-action-ajax' );
}

beforeEach( () => {
	page();
	timers = [];
	later = ( callback, wait ) => timers.push( [ wait, callback ] );
} );

test( "a link's settings are its own copy's", () => {
	expect( configFor( link, ALL ).path ).toBe(
		'one-sugarcommerce-columns/v1/row-action'
	);

	page( 'two-sugarcommerce-columns' );
	expect( configFor( link, ALL ).path ).toBe(
		'two-sugarcommerce-columns/v1/row-action'
	);

	page( 'nobody' );
	expect( configFor( link, ALL ) ).toEqual( {} );
} );

test( 'the request names the table, the action and the row', () => {
	expect( requestFor( link ) ).toEqual( {
		object_type: 'post',
		object_subtype: 'scpg_col_track',
		action_key: 'feature',
		object_id: 42,
		options: {},
	} );
} );

test( 'Dashicons when PHP sent no icons', () => {
	expect( icons( {} ).processing ).toContain( 'dashicons-update-alt' );
	expect( icons( ALL[ 'one-sugarcommerce-columns' ] ).error ).toBe(
		'<i class="no"></i>'
	);
} );

describe( 'a run', () => {
	test( "is sent to the copy's route, the link busy meanwhile", async () => {
		let settle;
		const apiFetch = vi.fn(
			() => new Promise( ( resolve ) => ( settle = resolve ) )
		);
		const reload = vi.fn();

		const done = run( link, {
			all: ALL,
			apiFetch,
			confirm: () => true,
			reload,
			later,
		} );

		expect( apiFetch ).toHaveBeenCalledWith( {
			path: 'one-sugarcommerce-columns/v1/row-action',
			method: 'POST',
			data: requestFor( link ),
		} );
		expect( link.innerHTML ).toContain( 'class="spin"' );
		expect( link.style.pointerEvents ).toBe( 'none' );

		settle( { message: 'Featured.', featured: true } );
		await done;

		const notice = document.querySelector( '.notice' );

		expect( notice.className ).toBe(
			'notice notice-success is-dismissible'
		);
		expect( notice.textContent ).toBe( 'Featured.' );
		expect( notice.previousElementSibling.tagName ).toBe( 'H1' );
		expect( link.innerHTML ).toBe( '<i class="ok"></i> Done' );

		// Reloads after a moment, unless told not to.
		timers.find( ( [ wait ] ) => 500 === wait )[ 1 ]();
		expect( reload ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'that says reload: false puts the link back instead', async () => {
		const reload = vi.fn();
		const before = link.innerHTML;

		await run( link, {
			all: ALL,
			apiFetch: () => Promise.resolve( { reload: false } ),
			confirm: () => true,
			reload,
			later,
		} );

		expect( document.querySelector( '.notice' ) ).toBeNull();
		timers.find( ( [ wait ] ) => 1000 === wait )[ 1 ]();
		expect( link.innerHTML ).toBe( before );
		expect( link.style.pointerEvents ).toBe( '' );
		expect( reload ).not.toHaveBeenCalled();
	} );

	test( "that is refused says the route's words, as text", async () => {
		await run( link, {
			all: ALL,
			apiFetch: () =>
				Promise.reject( {
					code: 'rest_forbidden',
					message: '<b>Insufficient</b> permissions',
				} ),
			confirm: () => true,
			reload: vi.fn(),
			later,
		} );

		const notice = document.querySelector( '.notice-error' );

		expect( notice.textContent ).toBe( '<b>Insufficient</b> permissions' );
		expect( notice.querySelector( 'b' ) ).toBeNull();
		expect( link.innerHTML ).toBe( '<i class="no"></i>' );
	} );

	test( 'asks first when the action says to, and does nothing on No', async () => {
		page( 'one-sugarcommerce-columns', 'Really?' );

		const apiFetch = vi.fn();
		const confirm = vi.fn( () => false );

		await run( link, {
			all: ALL,
			apiFetch,
			confirm,
			reload: vi.fn(),
			later,
		} );

		expect( confirm ).toHaveBeenCalledWith( 'Really?' );
		expect( apiFetch ).not.toHaveBeenCalled();
	} );
} );

test( 'a notice with nowhere to go is not drawn', () => {
	document.body.innerHTML = '';

	expect( showNotice( document, 'Hi', 'success', later ) ).toBeNull();
} );

test( 'a success notice goes after five seconds; core is told it is there', () => {
	const added = vi.fn();

	document.addEventListener( 'wp-updates-notice-added', added );

	const notice = showNotice( document, 'Hi', 'success', later );

	expect( added ).toHaveBeenCalledTimes( 1 );
	timers.find( ( [ wait ] ) => 5000 === wait )[ 1 ]();
	expect( notice.isConnected ).toBe( false );
} );
