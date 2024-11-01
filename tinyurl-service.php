<?php
/**
 * Plugin Name: TinyURL Service
 * Plugin URI: https://wordpress.org/plugins/tinyurl-service/
 * Description: TinyURL Service allows you to generate a tinyurl.com shortlink for all of your posts and pages and custom post types.
 * Version: 1.0.1
 * Author: Alberto Ochoa
 * Author URI: https://gitlab.com/albertochoa
 *
 * TinyURL Service allows you to generate a tinyurl.com shortlink for all of
 * your posts and pages and custom post types.
 * Copyright (C) 2011-2018 Alberto Ochoa
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.	See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

class TinyURL_Service {

	/**
	 * Constructor.
	 * 
	 * @since 1.0.0
	 */
	function __construct() {

		/* Filter the new shortlink for the given publication */
		add_filter( 'pre_get_shortlink', array( &$this, 'tinyurl_get_shortlink' ), 10, 4 );

		/* Create a menu in the administration bar */
		add_action( 'admin_bar_menu', array( &$this, 'tinyurl_admin_bar_menu' ), 99 );

		/* Load admin functions */
		add_action( 'admin_init', array( &$this, 'tinyurl_admin' ) );

		/* Deactivation */
		register_deactivation_hook( __FILE__, array( &$this, 'tinyurl_desactivation' ) );
	}

	/**
	 * Filter the new shortlink for the given publication.
	 * 
	 * @since 1.0.0
	 */
	function tinyurl_get_shortlink( $shortlink, $id, $context, $allow_slugs ) {

		if ( is_front_page() ) {
			return false;
		}

		global $wp_query;

		$post_id = '';

		/* Retrieve ID of the given publication */
		if ( 'query' == $context && is_singular() ) {
			$post_id = get_queried_object_id();
		} else if ( 'post' == $context ) {
			$post = get_post( $id );
			$post_id = $post->ID;
		}

		/* Retrieve the shortlink whether it exists */
		if ( $shortlink = get_metadata( 'post', $post_id, '_tinyurl_shortlink', true ) ) {
			return $shortlink;
		}

		/* Retrieve the full permalink of the given publication */
		$url = get_permalink( $post_id );

		/* Get the shortlink */
		$shortlink = $this->service( $url );

		/* Save the new shortlink */
		if ( !empty( $shortlink ) ) {
			update_metadata( 'post', $post_id, '_tinyurl_shortlink', $shortlink );

			return $shortlink;
		}

		return false;
	}

	/**
	 * Add two submenus to the Shortlink menu in the admin bar to share
	 * the short link.
	 *
	 * @since 1.0.0
	 */
	function tinyurl_admin_bar_menu() {
		global $wp_admin_bar;

		/* Returns the shortlink */
		$shortlink = wp_get_shortlink( 0, 'query' );

		/* Si el shortlink no existe o no es algun Post, retorna false */
		if ( empty( $shortlink ) ) {
			return false;
		}

		/* Shortlink */
		$wp_admin_bar->remove_menu( 'get-shortlink' );
		$wp_admin_bar->add_menu( array(
			'id'    => 'shortlink',
			'title' => __( 'Shortlink' ),
			'href'  => $shortlink )
		);

		/* Share on Twitter */
		$twitter = sprintf( 'https://twitter.com/intent/tweet?text=%1$s', str_replace( '+', '%20', urlencode( get_the_title() . ' - ' . $shortlink ) ) );

		$wp_admin_bar->add_menu( array(
			'parent' => 'shortlink',
			'id'     => 'tinyurl-share',
			'title'  => __( 'Share on Twitter', 'tinyurl-service' ),
			'href'   => $twitter,
			'meta'   => array( 'target' => '_blank' ) )
		);

		/* Share on Facebook */
		$facebook = sprintf( 'http://www.facebook.com/sharer.php?u=%1$s', $shortlink );

		$wp_admin_bar->add_menu( array(
			'parent' => 'shortlink',
			'id'     => 'tinyurl-share-facebook',
			'title'  => __( 'Share on Facebook', 'tinyurl-service' ),
			'href'   => $facebook,
			'meta'   => array( 'target' => '_blank' ) )
		);
	}

	/**
	 * Delete cache of a publication updated.
	 * 
	 * @since 1.0.0
	 */
	function tinyurl_admin() {
		add_action( 'save_post',         array( &$this, 'tinyurl_cache_delete' ) );
		add_action( 'added_post_meta',   array( &$this, 'tinyurl_cache_delete' ) );
		add_action( 'updated_post_meta', array( &$this, 'tinyurl_cache_delete' ) );
		add_action( 'deleted_post_meta', array( &$this, 'tinyurl_cache_delete' ) );
	}

	/**
	 * Delete all metadata.
	 *
	 * @since 1.0.0
	 */
	function tinyurl_desactivation() {
		delete_metadata( 'post', false, '_tinyurl_shortlink', '', true );
	}

	/**
	 * Delete '_tinyurl_shortlink' metadata on post ID.
	 *
	 * @since 1.0.0
	 * @param int $post_id Post ID to delete metadata.
	 */
	function tinyurl_cache_delete( $post_id ) {
		delete_metadata( 'post', $post_id, '_tinyurl_shortlink' );
	}

	/**
	 * Retrieve the raw response from TinyURL.
	 *
	 * @since 2.0.0
	 */
	function service( $url ) {

		$shortlink = '';

		$url = urlencode( $url );

		$tinyurl = "https://tinyurl.com/api-create.php?url={$url}";

		$response = wp_remote_get( $tinyurl );

		if ( !is_wp_error( $response ) && 200 == $response['response']['code'] ) {
			$shortlink = $response['body'];
		}

		return $shortlink;
	}
}

/* Creates a new TinyURL_Service object. */
$bitly = new TinyURL_Service();

/**
 * Retrieve the raw response from TinyURL.
 *
 * @since 1.0.0
 * @deprecated Deprecated since version 1.0.1
 */
function do_shortlink( $url ) {
	_deprecated_function( __METHOD__, '1.0.1' );

	$shortlink = '';

	$url = urlencode( $url );

	$tinyurl = "https://tinyurl.com/api-create.php?url={$url}";

	$response = wp_remote_get( $tinyurl );

	if ( !is_wp_error( $response ) && 200 == $response['response']['code'] ) {
		$shortlink = $response['body'];
	}

	return $shortlink;
}

