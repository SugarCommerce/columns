# SugarCommerce Columns

Add columns, filters, bulk actions and row actions to WordPress's own list
tables — posts, users, terms, comments and media.

## What it does

Four things that sound separate and are not. Each hangs off the same admin
screen, each needs to know which screen that is, and a plugin that wants one
usually wants the others — so adding a "Sales" column means one hook to
register it, another to render it, a third to make it sortable, and a fourth
to fix the ordering, before any of the same again for the filter beside it.

This is the declaration instead: describe the column, and the four hooks are
wired for you.

## Features

* Add a column to any core list table, from a meta key or a callback
* Show a thumbnail — a featured image, an avatar, or one stored in meta
* Make it sortable, including numerically, without writing the query
* Set its width and where it sits relative to the existing columns
* Add a dropdown filter above the table, and have it applied to the query
* Add a bulk action, with a capability and a notice reporting what it did
* Add a row action, running over ajax so the page does not reload
* Remove a core column or row action you do not want

## Installation

```bash
composer require sugarcommerce/columns
```

## Quick start

A sales column on downloads, sortable, next to the title:

```php
use SugarCommerce\Columns\Columns;

add_action( 'admin_init', function () {
	Columns::posts( 'download', [
		'sales' => [
			'label'    => __( 'Sales', 'my-plugin' ),
			'meta_key' => '_sales',
			'position' => 'after:title',
			'sortable' => true,
			'numeric'  => true,
			'width'    => '90px',
		],
	] );
} );
```

`numeric` is the one that catches people out: without it, sorting is
alphabetical and 9 comes after 10.

Register on `admin_init`: early enough that the list table has not asked for
its columns yet, late enough that post types and taxonomies exist.

## Columns

One front door per table, each taking an array of columns and, last, the
core column keys to take off the table:

```php
use SugarCommerce\Columns\Columns;

Columns::posts( [ 'post', 'page' ], $columns, [ 'comments' ] ); // one or several post types
Columns::terms( 'product_brand', $columns );                    // one or several taxonomies
Columns::users( $columns, [ 'posts' ] );
Columns::comments( $columns );
Columns::media( $columns );
```

The post type and taxonomy doors return one table instance per type; the
others return theirs, or `null` when the array was refused. A key that is not
a non-empty string refuses that table's registration and is written to the
error log, rather than taking the screen down.

| Key | Default | What it does |
|---|---|---|
| `label` | `''` | The header, escaped. |
| `meta_key` | `''` | Shows the stored value; a sortable column sorts by it. |
| `display_callback` | `null` | Returns the cell's markup. Given `( $meta_value, $object_id )` when the column has a `meta_key`, `( $object_id )` when it does not. |
| `position` | `''` | `after:{column}` or `before:{column}`; the end otherwise. A neighbour that is not there puts it last (`after:`) or first (`before:`). |
| `sortable` | `false` | Adds it to the sortable columns and sorts the query. |
| `numeric` | `false` | Sorts as a number (`meta_value_num`; on terms `CAST … AS SIGNED`). |
| `desc_first` | `false` | Sorts descending on the first click, as core's date column does. |
| `sortby` | `''` | Sort by something the query already knows (`registered`, `title`) instead of the meta key. |
| `permission_callback` | `null` | Whether the column shows. Without one it needs `manage_options`. |
| `width` | `null` | A CSS length — `90px`, `12%`, `8rem`, `auto`. Anything else is ignored rather than printed into the stylesheet. |
| `image` | `false` | Shows a thumbnail; see below. |
| `image_size` | `32` | Square pixels, a registered size name, or `[ width, height ]`. |

An empty value — nothing stored, an empty string, `false`, an empty array —
shows a dash, with "None" for a screen reader. A stored `0` shows `0`.

A display callback's markup is printed as it is: escape what you put in it.

### Image columns

`image` shows a thumbnail. With no `meta_key` the table uses its own natural
image — the featured image on a post, the avatar on a user or comment, the
file itself on a media item — which is the same relationship core has between
the users table and the avatar it puts in the username column:

```php
Columns::posts( 'download', [
	'thumb' => [
		'label'    => __( 'Image', 'my-plugin' ),
		'image'    => true,
		'position' => 'before:title',
	],
] );
```

With a `meta_key`, whatever is stored there is the image. An attachment id and
a URL both work, since both are things people store:

```php
Columns::terms( 'product_brand', [
	'logo' => [
		'label'    => __( 'Logo', 'my-plugin' ),
		'image'    => true,
		'meta_key' => 'brand_logo',
	],
] );
```

`image_size` defaults to 32 — the size core uses for the avatar, so an image
column next to a core one lines up with it — and an image column with no
`width` is as wide as its image. A term has no natural image, so a term column
needs a `meta_key`. An empty value, a post with no featured image, and an id
whose attachment has since been deleted all show the dash every other empty
cell shows, rather than a broken image.

`Support\Image::render( $value, $size )` draws the same thumbnail anywhere
else.

## Filters

A dropdown above the table, its label the empty "all" option the way core's
own filters are:

```php
use SugarCommerce\Columns\Filters;

Filters::posts( 'download', [
	'state'    => [
		'label'   => __( 'All states', 'my-plugin' ),
		'options' => [ 'active' => __( 'Active', 'my-plugin' ), 'expired' => __( 'Expired', 'my-plugin' ) ],
	],
	'category' => [
		'label'      => __( 'All categories', 'my-plugin' ),
		'taxonomy'   => 'download_category',
		'show_count' => true,
	],
	'popular'  => [
		'label'          => __( 'Any popularity', 'my-plugin' ),
		'options'        => [ 'high' => __( 'Widely read', 'my-plugin' ) ],
		'query_callback' => function ( $query, string $value ) {
			$query->set( 'meta_query', [ [ 'key' => '_views', 'value' => 1000, 'type' => 'NUMERIC', 'compare' => '>=' ] ] );
		},
	],
] );

Filters::users( [
	'contacted' => [ 'label' => __( 'Anyone', 'my-plugin' ), 'options' => [ 'yes' => __( 'Contacted', 'my-plugin' ) ] ],
] );
```

A choice is applied in this order: the `query_callback`, given the query and
the chosen value (on users, an object with the same `set()` and `get()` over
the list table's query arguments); a tax query by slug for a `taxonomy`
filter; otherwise a meta query on the filter's own key. Several filters are
ANDed, and ANDed with any meta or tax query another plugin (or a
`query_callback`) already set, which stays as it was as one nested clause
rather than being replaced. A taxonomy's terms are read when the table is drawn, so a taxonomy
registered after the filter still fills it; `hide_empty` (default `true`)
leaves out terms with nothing in them. A filter needs `capability` (default
`manage_options`) to show and to apply. The users table has no Filter button
of its own, so one is added beside the dropdowns.

## Bulk actions

```php
use SugarCommerce\Columns\BulkActions;

BulkActions::posts( [ 'post', 'page' ], [
	'mark_reviewed' => [
		'label'      => __( 'Mark as reviewed', 'my-plugin' ),
		'capability' => 'edit_others_posts',
		'callback'   => function ( array $post_ids ) {
			foreach ( $post_ids as $post_id ) {
				update_post_meta( $post_id, '_reviewed', time() );
			}

			return [ 'message' => sprintf( __( '%d reviewed.', 'my-plugin' ), count( $post_ids ) ) ];
		},
	],
] );
```

`BulkActions::users()`, `::terms( $taxonomies, … )`, `::comments()` and
`::media()` take the same array. The callback is given the selected ids as
integers. Return `[ 'message' => …, 'success' => false ]` to say something,
or anything else for the default "N items processed."; a thrown exception's
message is shown as an error. The action is offered only to those with its
`capability` (default `manage_options`), and checked again when it runs. The
notice is core's own (`wp_admin_notice()`), so the `wp_admin_notice_args` and
`wp_admin_notice_markup` filters reach it.

## Row actions

The links under a row's title:

```php
use SugarCommerce\Columns\RowActions;

RowActions::posts( 'download', [
	'stats'  => [
		'label'    => __( 'Stats', 'my-plugin' ),
		'url'      => admin_url( 'admin.php?page=my-stats' ), // ?id={object id} is added
		'position' => 'after:edit',
		'icon'     => 'chart-bar',
	],
	'resend' => [
		'label'    => __( 'Resend', 'my-plugin' ),
		'ajax'     => true,
		'confirm'  => __( 'Resend the receipt?', 'my-plugin' ),
		'callback' => function ( int $post_id, array $options ) {
			my_plugin_resend( $post_id );

			return [ 'message' => __( 'Sent.', 'my-plugin' ), 'reload' => false ];
		},
		'icon'     => 'heroicons-outline:paper-airplane',
	],
], [ 'inline hide-if-no-js' ] ); // core actions to take off
```

`RowActions::users()`, `::terms( $taxonomies, … )`, `::comments()` and
`::media()` take the same array and removals.

| Key | Default | What it does |
|---|---|---|
| `label` / `label_callback` | `''` / `null` | The text, escaped; the callback is given the object id. |
| `url` / `url_callback` | `''` / `null` | Where a link goes. `url` gets `?id=`; the callback is given the id. |
| `ajax` + `callback` | `false` / `null` | Calls `callback( $object_id, $options )` through the copy's REST route without leaving the page. |
| `confirm` | `''` | Asks first. |
| `capability` | `manage_options` | Needed to see it and to run it. |
| `permission_callback` | `null` | Given the object id; false hides it for that row. |
| `position` | `''` | `after:{action}` or `before:{action}`. |
| `class`, `target` | `''` | On the link. |
| `icon` | `''` | Before the label; see below. |

An `ajax` action runs through one REST route per copy of the library,
`{prefix}-sugarcommerce-columns/v1/row-action` (`Rest\RowActionRoute::path()`),
sent by the script with `@wordpress/api-fetch`, which carries the REST nonce
and renews it. The route's arguments are typed (`object_type`,
`object_subtype`, `action_key`, `object_id`, `options`), and it refuses an
unknown action, one without `ajax`, or a missing capability or
`permission_callback` before the callback runs. The callback's array (or a
`WP_Error`) is sent back with a default `message`; the page reloads after
success unless it says `'reload' => false`. A thrown exception answers that
the action could not be completed, its own message only under `WP_DEBUG`
with `WP_DEBUG_DISPLAY`. Messages are shown as text.

A REST request runs no `admin_init`, so a plugin with `ajax` row actions
registers them on `init` (or anything both an admin screen and a REST
request run); the columns, filters and bulk actions are fine on
`admin_init`.

### Row action icons

A row action's `icon` goes before its label, in either of two forms. A
`set:name` value — `heroicons-outline:*`, `heroicons-solid:*`, or any set
[sugarcommerce/fields](https://github.com/SugarCommerce/fields) knows — is
drawn as inline SVG in the link's colour. That needs sugarcommerce/fields,
which is suggested rather than required: without it a `set:name` icon draws
nothing, and a bare Dashicons name (`chart-bar`, or `dashicons-chart-bar`)
draws as the font's span either way. Both carry the `sugarcommerce-icon`
class.

While an AJAX action runs, its icon (or, with none, its label) shows a
spinning arrow, then a tick or a cross: Heroicons' `arrow-path`, `check` and
`x-mark`, or the Dashicons `update-alt`, `yes` and `no-alt` without
sugarcommerce/fields.

### Helpers for toggles

For an action that flips a flag:

```php
'feature' => [
	'label_callback' => fn( int $id ) => RowActions::toggle_label( 'post', $id, '_featured', __( 'Unfeature', 'my-plugin' ), __( 'Feature', 'my-plugin' ) ),
	'ajax'           => true,
	'callback'       => fn( int $id ) => [ 'featured' => RowActions::toggle_meta( 'post', $id, '_featured' ) ],
],
```

Both take `post`, `attachment`, `comment`, `user` or `term`. An action of
your own that a script calls is a REST route -- `'ajax' => true` here already
is one -- never admin-ajax.

## A table of your own

Another plugin's list table — EDD's orders, a custom `WP_List_Table` — gets
the same column array by extending `SugarCommerce\Columns\Abstracts\Columns`:
set `OBJECT_TYPE`, and implement `load_hooks()` (attach `register_columns`,
`register_sortable_columns` and your own renderer to that table's filters),
`is_screen()`, `get_meta()` and `render_column_content()`, which hands the
cell to `render_custom_column_content()`. `get_order()` and `get_orderby()`
read the table's sort from the request; `check_column_permission()` can be
overridden to change the default capability.

## Two plugins, one library

Bundled by Strauss into two plugins, each copy has its own REST namespace,
script handle and keys, worked out from its prefixed namespace
(`Utils\Runtime`): `sugarcommerce-columns` unprefixed,
`{prefix}-sugarcommerce-columns` in a prefixed build. The script's settings
are kept per copy in `window.SugarCommerceColumns[handle]`, each link names
its copy, and the script binds its click handler once however many copies
load it.

## The script

`assets/js/row-actions.js` and `assets/js/row-actions/run.js`, ES modules
built by `@wordpress/scripts` into `assets/build/row-actions.js` (with its
`.asset.php`: `wp-api-fetch`, `wp-dom-ready`), registered through
`SugarCommerce\Plugin\Assets::enqueue_built_script()`. No jQuery. One click
handler on the document for every copy (`window.SugarCommerceColumnsRowActions`
is the flag); each link names its copy's settings in
`window.SugarCommerceColumns[handle]` (`path`, `strings`, `icons`). A notice
goes under the screen's heading, as text, and core is told
(`wp-updates-notice-added`) so it gets its dismiss button.

```bash
npm ci
npm run build      # assets/build, committed
npm run test:js    # Vitest, in jsdom (composer test:js runs it too)
npm run lint:js
```

## Security

* Running an action you may not: the REST route asks the action's
  capability and its `permission_callback` for that row before the callback
  runs; core's REST nonce covers forgery.
* Running something that is not an action: only an action registered with
  `ajax` on that table runs; anything else is refused before a callback.
* Arguments of the wrong shape: the route's arguments are typed and matched
  (`object_id` an integer from 1, keys `[a-z0-9_-]`, `options` an object).
* Words put on the list by a link: a bulk action's message is kept for the
  user who ran it and named by a token in the address; a message written into
  the address is never shown.
* Leaking internals: a callback's exception is shown only under `WP_DEBUG`
  with `WP_DEBUG_DISPLAY`; otherwise the action "could not be completed".
* Markup in a message: notices and cells are escaped (`esc_html`, text in
  the script), and a filter's value is sanitized before it reaches a query.

## Source layout

* `src/Columns.php`, `Filters.php`, `BulkActions.php`, `RowActions.php`: the
  front doors; `src/Tables/`, `src/Filters/`, `src/BulkActions/`,
  `src/RowActions/`: one class per core list (posts, users, terms, comments,
  media).
* `src/Abstracts/`: what each of those extends, with their jobs as traits in
  `src/Traits/Abstracts/Columns/` (cells, styles, the request's sort) and
  `src/Traits/Abstracts/RowActions/` (links, the script and icons).
* `src/Rest/RowActionRoute.php`: the route `ajax` actions run through.
* `src/Support/` (screens, positions, images, query arguments, the
  registrar) and `src/Utils/Runtime.php` (names per copy).
* `src/` is PHP only. The script's sources are in `assets/js/`, built to
  `assets/build/`; the styles are in `assets/css/`.

## Requirements

* PHP 8.3 or later
* WordPress 7.1 or later
* `sugarcommerce/plugin` (its `Assets` finds this package's `assets/` URL)

## License

GPL-2.0-or-later
