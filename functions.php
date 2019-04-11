<?php

// Enqueue Ion parent theme CSS file

add_action( 'wp_enqueue_scripts', 'ap3_ion_enqueue_styles' );
function ap3_ion_enqueue_styles() {

	// parent theme css
	$version = '0.1';
    wp_enqueue_style( 'ap3-ion-style', get_template_directory_uri().'/style.css', null, $version );
    
    // child theme css
    wp_enqueue_style( 'ap3-child-style', get_stylesheet_uri(), null, $version );
}

// Add your custom functions here

add_action( 'pre_get_posts', function( $query ) {
	
	$query->set( 'orderby', 'title' );
	$query->set( 'order', 'ASC' );
	
	if ( $query->get( 'categories' ) && 
	   isset( $_GET['search'] ) ) {
		
		$query->set( 's', esc_attr( $_GET['search'] ) );
		
	}
	
} );

// The following code allows you to search by Category Name
// Based on https://rfmeier.net/include-category-and-post-tag-names-in-the-wordpress-search/

add_filter( 'posts_join', 'accessforall_custom_posts_join', 10, 2 );

/**
 * Callback for WordPress 'posts_join' filter.'
 *
 * @global $wpdb
 *
 * @link https://codex.wordpress.org/Plugin_API/Filter_Reference/posts_join
 *
 * @param string $join The sql JOIN clause.
 * @param WP_Query $wp_query The current WP_Query instance.
 *
 * @return string $join The sql JOIN clause.
 */
function accessforall_custom_posts_join( $join, $query ) {

    global $wpdb;

	$join .= "
	LEFT JOIN
	(
		{$wpdb->term_relationships} as relationships
		INNER JOIN
			{$wpdb->term_taxonomy} ON {$wpdb->term_taxonomy}.term_taxonomy_id = relationships.term_taxonomy_id
		INNER JOIN
			{$wpdb->terms} ON {$wpdb->terms}.term_id = {$wpdb->term_taxonomy}.term_id
	)
	ON {$wpdb->posts}.ID = relationships.object_id ";

    return $join;

}

add_filter( 'posts_where', 'accessforall_custom_posts_where', 10, 2 );

/**
 * Callback for WordPress 'posts_where' filter.
 *
 * Modify the where clause to include searches against a WordPress taxonomy.
 *
 * @global $wpdb
 *
 * @see https://codex.wordpress.org/Plugin_API/Filter_Reference/posts_where
 *
 * @param string $where The where clause.
 * @param WP_Query $query The current WP_Query.
 *
 * @return string The where clause.
 */
function accessforall_custom_posts_where( $where, $query ) {

    global $wpdb;
	
	if ( isset( $_GET['search'] ) && 
	   $_GET['search'] ) {

		// get additional where clause for the user
		$user_where = accessforall_custom_get_user_posts_where();

		$where .= " OR (
						(
							{$wpdb->term_taxonomy}.taxonomy IN( 'category' )
							AND
							{$wpdb->terms}.name LIKE '%" . esc_sql( $_GET['search'] ) . "%'
							{$user_where}
							" . apply_filters( 'accessforall_custom_posts_where_category', '' ) . "
						)
						" . apply_filters( 'accessforall_custom_posts_where_after', '' ) . "
					)";
		
	}

    return $where;

}

add_filter( 'accessforall_custom_posts_where_category', function( $where ) {
	
	if ( ! isset( $_GET['categories'] ) || 
	   ! $_GET['categories'] ) return $where;
	
	global $wpdb;
	
	$category_ids = explode( ',', $_GET['categories'] );
	
	$where .= " AND (
	";
	
	$first = true;
	
	foreach ( $category_ids as $category_id ) {
		
		$category = get_term( trim( $category_id ), 'category' );
		
		if ( ! $first ) {
			
			$where .= ' OR ';
		}
		
		$where .= "{$wpdb->terms}.slug LIKE '" . esc_sql( $category->slug ) . "%'";
		
		$first = false;
		
	}
	
	$where .= ')';
	
	return $where;
	
} );

// This accounts for searching by Tag without leaking into other Categories
add_filter( 'accessforall_custom_posts_where_after', function( $where ) {
	
	if ( ! isset( $_GET['search'] ) || 
	   ! $_GET['search'] ) return $where;
	
	global $wpdb;
	
	$user_where = accessforall_custom_get_user_posts_where();
	
	if ( ! isset( $_GET['categories'] ) || 
	   ! $_GET['categories'] ) {
		
		$where .= ' OR ( ';
	
			$where .= "{$wpdb->term_taxonomy}.taxonomy IN( 'post_tag' )";
			$where .= " AND ";
			$where .= "{$wpdb->terms}.name LIKE '%" . esc_sql( $_GET['search'] ) . "%'";
			$where .= $user_where;

		$where .= ' ) ';
		
		return $where;
		
	}
	
	$category_ids = explode( ',', $_GET['categories'] );
	
	$where .= ' OR ( (';
	
	$first = true;
	
	foreach ( $category_ids as $category_id ) {
		
		if ( ! $first ) {
			
			$where .= ' OR ( ';
			
		}
	
		$where .= "{$wpdb->term_taxonomy}.taxonomy IN( 'post_tag' )";
		$where .= " AND ";
		$where .= "{$wpdb->terms}.name LIKE '%" . esc_sql( $_GET['search'] ) . "%'";
		$where .= " AND ";
		$where .= "{$wpdb->term_relationships}.term_taxonomy_id IN (" . esc_sql( $category_id ) . ") ";
		$where .= $user_where;
		
		$where .= ' ) ';
		
	}
	
	$where .= ' )';
	
	return $where;
	
} );

/**
 * Get a where clause dependent on the current user's status.
 *
 * @global $wpdb https://codex.wordpress.org/Class_Reference/wpdb
 *
 * @uses get_current_user_id()
 * @see http://codex.wordpress.org/Function_Reference/get_current_user_id
 *
 * @return string The user where clause.
 */
function accessforall_custom_get_user_posts_where() {

    global $wpdb;

    $user_id = get_current_user_id();
	
	$sql = " AND ({$wpdb->posts}.post_status = 'publish'";

    if ( $user_id ) {

        $sql .= " OR {$wpdb->posts}.post_author = {$user_id} AND {$wpdb->posts}.post_status = 'private'";

    }
	
	$sql .= ")";

    return $sql;

}

add_filter( 'posts_groupby', 'accessforall_custom_posts_groupby', 10, 2 );

/**
 * Callback for WordPress 'posts_groupby' filter.
 *
 * Set the GROUP BY clause to post IDs.
 *
 * @global $wpdb https://codex.wordpress.org/Class_Reference/wpdb
 *
 * @param string $groupby The GROUPBY caluse.
 * @param WP_Query $query The current WP_Query object.
 *
 * @return string The GROUPBY clause.
 */
function accessforall_custom_posts_groupby( $groupby, $query ) {

    global $wpdb;

    $groupby = "{$wpdb->posts}.ID";

    return $groupby;

}