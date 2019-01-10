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