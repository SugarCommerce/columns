/**
 * Two plugins' copies of the library on one screen: each listens for its own
 * links alone, flagged in its own entry of the registry.
 *
 * The built script itself is run, twice, as WordPress prints it under two
 * handles -- its <script> element's id is what tells it which copy it is.
 */

import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

import { JSDOM } from 'jsdom';
import { beforeEach, describe, expect, test, vi } from 'vitest';

import { handleOf, listen } from '../../assets/js/row-actions/listen';

const BUILT = readFileSync(
	resolve( __dirname, '../../assets/build/row-actions.js' ),
	'utf8'
);

/**
 * A link one copy drew.
 *
 * @param {string} handle The copy's handle.
 * @param {string} key    The action's key.
 * @return {string} Markup.
 */
const link = ( handle, key ) =>
	`<a href="#" class="row-action-ajax" data-config="${ handle }" data-object-type="post" data-object-subtype="post" data-action-key="${ key }" data-object-id="7" data-confirm=""><span class="dashicons sugarcommerce-icon"></span> ${ key }</a>`;

/**
 * The inline settings and the script, as WordPress prints them for a handle.
 *
 * @param {string} handle The copy's handle.
 * @return {string} Markup.
 */
const printed = ( handle ) =>
	`<script>window.SugarCommerceColumns=window.SugarCommerceColumns||{};window.SugarCommerceColumns[${ JSON.stringify(
		handle
	) }]=${ JSON.stringify( {
		path: `${ handle }/v1/row-action`,
	} ) };</script>` +
	`<script id="${ handle }-js">${ BUILT.replace( /<\/script/gi, '<\\/script' ) }</script>`;

test( "a script's handle is its element's id, less -js", () => {
	const script = document.createElement( 'script' );

	script.id = 'one-sugarcommerce-columns-js';
	expect( handleOf( script ) ).toBe( 'one-sugarcommerce-columns' );
	expect( handleOf( null ) ).toBe( '' );
} );

describe( 'listen()', () => {
	let all;
	let apiFetch;

	/**
	 * Listen for one copy, with fakes.
	 *
	 * @param {string} handle The copy's handle.
	 * @return {boolean} Whether it began listening.
	 */
	const start = ( handle ) =>
		listen( document, handle, {
			all,
			apiFetch,
			confirm: () => true,
			reload: () => {},
			later: () => {},
		} );

	beforeEach( () => {
		all = {
			one: { path: 'one/v1/row-action' },
			two: { path: 'two/v1/row-action' },
		};
		apiFetch = vi.fn( () => new Promise( () => {} ) );
		document.body.innerHTML = `<div class="wrap">${ link(
			'one',
			'a'
		) }${ link( 'two', 'b' ) }</div>`;
	} );

	test( 'answers only its own links, and only once', () => {
		expect( start( 'one' ) ).toBe( true );
		expect( start( 'one' ) ).toBe( false );
		expect( all.one.bound ).toBe( true );
		expect( all.two.bound ).toBeUndefined();

		document.querySelector( '[data-config="two"]' ).click();
		expect( apiFetch ).not.toHaveBeenCalled();

		document.querySelector( '[data-config="one"]' ).click();
		expect( apiFetch ).toHaveBeenCalledTimes( 1 );
		expect( apiFetch.mock.calls[ 0 ][ 0 ].path ).toBe(
			'one/v1/row-action'
		);
	} );

	test( 'without a handle, listens for nothing', () => {
		expect( start( '' ) ).toBe( false );
	} );
} );

test( 'the built script, loaded by two copies, runs each copy’s links through its own route', () => {
	const sent = [];
	const dom = new JSDOM(
		`<!doctype html><html><head><script>window.wp={apiFetch:function(o){window.sent.push(o.path);return new Promise(function(){});},domReady:function(f){f();}};</script></head>` +
			`<body><div class="wrap"><h1>Posts</h1>${ link(
				'one-sugarcommerce-columns',
				'a'
			) }${ link( 'two-sugarcommerce-columns', 'b' ) }</div>` +
			printed( 'one-sugarcommerce-columns' ) +
			printed( 'two-sugarcommerce-columns' ) +
			'</body></html>',
		{
			runScripts: 'dangerously',
			beforeParse( window ) {
				window.sent = sent;
			},
		}
	);
	const { document: doc, SugarCommerceColumns: registry } = dom.window;

	expect( registry[ 'one-sugarcommerce-columns' ].bound ).toBe( true );
	expect( registry[ 'two-sugarcommerce-columns' ].bound ).toBe( true );
	expect( dom.window.SugarCommerceColumnsRowActions ).toBeUndefined();

	doc.querySelector( '[data-config="one-sugarcommerce-columns"]' ).click();
	doc.querySelector( '[data-config="two-sugarcommerce-columns"]' ).click();

	// One request each, to each copy's own route: never two for a click.
	expect( sent ).toEqual( [
		'one-sugarcommerce-columns/v1/row-action',
		'two-sugarcommerce-columns/v1/row-action',
	] );

	dom.window.close();
} );

test( "one copy's script never runs another copy's link", () => {
	const sent = [];
	const dom = new JSDOM(
		`<!doctype html><html><head><script>window.wp={apiFetch:function(o){window.sent.push(o.path);return new Promise(function(){});},domReady:function(f){f();}};</script></head>` +
			`<body><div class="wrap"><h1>Posts</h1>${ link(
				'two-sugarcommerce-columns',
				'b'
			) }</div>` +
			// Copy two printed its settings; its own script is not there.
			printed( 'one-sugarcommerce-columns' ) +
			`<script>window.SugarCommerceColumns['two-sugarcommerce-columns']={path:'two-sugarcommerce-columns/v1/row-action'};</script>` +
			'</body></html>',
		{
			runScripts: 'dangerously',
			beforeParse( window ) {
				window.sent = sent;
			},
		}
	);

	dom.window.document
		.querySelector( '[data-config="two-sugarcommerce-columns"]' )
		.click();

	expect( sent ).toEqual( [] );

	dom.window.close();
} );
