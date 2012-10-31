<?php
/**
 * Register Plugin Taxonomy
 */

  // Add new taxonomy, make it hierarchical (like categories)
  $labels = array(
    'name' => _x( 'Global Taxonomy', 'taxonomy general name' ),
    'singular_name' => _x( 'Global Taxonomy', 'taxonomy singular name' ),
    'search_items' =>  __( 'Search Global Taxonomies' ),
    'all_items' => __( 'All Global Taxonomies' ),
    'parent_item' => __( 'Parent Global Taxonomy' ),
    'parent_item_colon' => __( 'Parent Global Taxonomy:' ),
    'edit_item' => __( 'Edit Global Taxonomy' ), 
    'update_item' => __( 'Update Global Taxonomy' ),
    'add_new_item' => __( 'Add New Global Taxonomy' ),
    'new_item_name' => __( 'New Global Taxonomy Name' ),
    'menu_name' => __( 'Global Taxonomy' ),
  ); 	
	
  register_taxonomy('global',array('post'), array(
    'hierarchical' => true,
    'labels' => $labels,
    'show_ui' => true,
    'query_var' => true,
    'rewrite' => array( 'slug' => 'genre' ),
  ));
