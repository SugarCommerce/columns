<?php
/**
 * Position
 *
 * @package     SugarCommerce\Columns
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Columns\Support;

/**
 * Where a column or a row action goes among the ones already there.
 *
 * Both take the same `position`: `after:title`, `before:email`, or nothing
 * for the end. It was two copies of the same few lines leaning on a whole
 * array library for two of its methods; it is one here.
 */
final class Position {

	/**
	 * Insert one entry relative to another.
	 *
	 * `after:` a key that is not there puts the entry last, and `before:` one
	 * that is not there puts it first -- a column whose neighbour another
	 * plugin removed still shows, at the end it was nearest. A position that
	 * is neither form appends.
	 *
	 * An integer-looking reference matches an integer key, the way PHP
	 * stores one: `after:3` finds the key 3.
	 *
	 * @param array<int|string, mixed> $items    The existing entries, in order.
	 * @param string                   $position `after:{key}`, `before:{key}` or empty.
	 * @param string                   $key      The new entry's key.
	 * @param mixed                    $value    The new entry.
	 *
	 * @return array<int|string, mixed>
	 */
	public static function insert( array $items, string $position, string $key, mixed $value ): array {
		if ( str_starts_with( $position, 'after:' ) ) {
			$at = self::find( $items, substr( $position, 6 ) );

			return self::splice( $items, false === $at ? count( $items ) : $at + 1, $key, $value );
		}

		if ( str_starts_with( $position, 'before:' ) ) {
			$at = self::find( $items, substr( $position, 7 ) );

			return self::splice( $items, false === $at ? 0 : $at, $key, $value );
		}

		$items[ $key ] = $value;

		return $items;
	}

	/**
	 * The offset of a key, or false.
	 *
	 * @param array<int|string, mixed> $items     The entries.
	 * @param string                   $reference The key to find.
	 *
	 * @return int|false
	 */
	private static function find( array $items, string $reference ): int|false {
		$reference = (string) (int) $reference === $reference ? (int) $reference : $reference;

		return array_search( $reference, array_keys( $items ), true );
	}

	/**
	 * Put an entry at an offset, keys kept.
	 *
	 * The union keeps the first of two equal keys, so an entry already in
	 * the list stays where it was rather than moving -- which is what
	 * registering the same column twice has always done.
	 *
	 * @param array<int|string, mixed> $items  The entries.
	 * @param int                      $offset Where.
	 * @param string                   $key    The new entry's key.
	 * @param mixed                    $value  The new entry.
	 *
	 * @return array<int|string, mixed>
	 */
	private static function splice( array $items, int $offset, string $key, mixed $value ): array {
		return array_slice( $items, 0, $offset, true ) + [ $key => $value ] + array_slice( $items, $offset, null, true );
	}
}
