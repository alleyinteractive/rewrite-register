<?php

/*
	Plugin Name: Rewrite Queue
	Plugin URI: https://github.com/alleyinteractive/rewrite-queue
	Description: A new way to think about rewrites rule manipulation.
	Version: 0.1
	Author: Matthew Boynes
	Author URI: http://www.alleyinteractive.com/
*/
/*  This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

require_once( dirname( __FILE__ ) . '/class-rewrite-queue.php' );

/**
 * Enqueue a set of rewrite rules.
 *
 * @see  Rewrite_Queue::enqueue
 *
 * @param  string $name    A reference key for your rules.
 * @param  mixed $version  A version identifier.
 * @param  string $after   The placement for your rewrite rules.
 */
function wp_enqueue_rewrites( $name, $version = null, $after = 'bottom' ) {
	Rewrite_Queue()->enqueue( $name, $version, $after );
}

/**
 * Import the core rules to handle automated flushing. While we are "enqueueing"
 * these rules, they don't really play nice with this system (yet).
 */
function temp_import_core_rules() {
	global $wp_rewrite;

	// Top
	wp_enqueue_rewrites( 'core-top', md5( serialize( $wp_rewrite->extra_rules_top ) ), 'top' );

	// Permastructs
	foreach ( $wp_rewrite->extra_permastructs as $name => $args ) {
		wp_enqueue_rewrites( $name, md5( serialize( $args ) ) );
	}

	// Bottom
	wp_enqueue_rewrites( 'core-bottom', md5( serialize( $wp_rewrite->extra_rules ) ) );
}
add_action( 'enqueue_rewrites', 'temp_import_core_rules', 100 );