<?php
/*
Plugin Name: Global Taxonomy
Description: MU Plugin 
Author: Javier Arques
Version: 1.0.0
Author URI: http://artvisual.net
*/


class Global_Taxonomy 
{
	var $plugin_path;
	var $plugin_url;
	var $slug = "global-tag";
	var $taxonomy = 'global-taxonomy';
	var $admin_setting_url;
	

	
	/**
	 * Contructor, set variables and hooks
	 *
	 */
	public function __construct(){
	
		// Set Admin URL
		if ( ! is_multisite() ){
			$this->admin_setting_url = admin_url('admin.php?page=global-taxonomy');
		} else {
			$this->admin_setting_url = network_admin_url('settings.php?page=global-taxonomy');		
		}
		// Set Plugin Path
		$this->plugin_path = dirname(__FILE__);
	
		// Set Plugin URL
		$this->plugin_url = WP_PLUGIN_URL . '/global-taxonomy';
		
		// Includes
		require_once( $this->plugin_path . '/php/functions.php');
		
	
		// Saving options page 
		
		add_action( 'admin_init', array(&$this, 'save_settings'));
		
		// Register taxonomy
		
		add_action( 'init', array(&$this, 'register_taxonomy'));
		
		// Adding new terms
		/*
		add_action("created_$this->taxonomy", array(&$this, 'created_term'), 10, 2);
		add_action("delete_$this->taxonomy",  array(&$this, 'delete_term'), 10, 2);
		add_action("edit_$this->taxonomy",  array(&$this, 'edit_term'), 10, 2);
		*/
		
		if ( is_multisite() ) {
			add_action('network_admin_menu', array(&$this, 'admin_menu')); // Añade al menú del administrador la función menu()
		} else {
			add_action('admin_menu', array(&$this, 'admin_menu')); // Añade al menú del administrador la función menu()
		}
		
		add_action( 'admin_menu', array( &$this, 'remove_taxonomy_menu') );
		add_action( 'admin_head', array( &$this, 'remove_add_taxonomy') );
		
		add_action( 'admin_init', array( &$this, 'process_actions'));
		
		//add_filter( 'the_posts', array( &$this, 'populate_posts_data'), 10, 2);
		
		add_filter( 'posts_request', array( &$this, 'new_query'), 10, 2);
		
	}
	
	function new_query ( $request, $query ) {

		global $wpdb;
		
		
		
		if ( ! is_main_site() || ! is_main_query() )
			return $request;
			
		if ( ! empty( $query->query_vars['global-taxonomy'])) {
		
			//$this->dump( 'entra' );
		
			$enabled_sites = get_site_option( 'gt_sites' );
			$enabled_sites[] = 1;
		
			$sql = array();
			
			foreach ( $enabled_sites as $site ) {
			
				switch_to_blog( $site );
				
				$term = get_term_by( 'slug', $query->query_vars['global-taxonomy'], $this->taxonomy);
				
				$sql[] = "SELECT $wpdb->posts.*, $site as blog_id  FROM $wpdb->posts INNER JOIN $wpdb->term_relationships ON ($wpdb->posts.ID = $wpdb->term_relationships.object_id) WHERE ( $wpdb->term_relationships.term_taxonomy_id IN ($term->term_taxonomy_id) ) AND $wpdb->posts.post_type IN ('post') AND ($wpdb->posts.post_status = 'publish')";
			
				restore_current_blog();

			}
			$request = implode( ' UNION ', $sql);
			$request .= " ORDER BY post_date DESC LIMIT {$query->query_vars['paged']}, {$query->query_vars['posts_per_page']}";
		
		}
		
		//$this->dump( $request );
		return $request;
		
		
	}
	/**
	 * Cathing edit/delete/add term actions, propagate changes through the sites
	 *
	 */
	function process_actions () {
	
		if ( ! empty($_REQUEST['taxonomy']) && ($this->taxonomy == $_REQUEST['taxonomy']) && ! empty( $_REQUEST['action']) && is_main_site()){
			$sites = $this->get_sites();
			switch ( $_REQUEST['action'] ) {
				case 'add-tag':
					// Tiene padre? lo buscamos				
					if ( $_REQUEST['parent'] != -1 ) {
						$parent = get_term( $_REQUEST['parent'], $this->taxonomy );
					}
					foreach ( $sites as $site ) {
			
						switch_to_blog( $site->blog_id );
						
						$args['description'] = $_REQUEST['description'];
						
						if ( ! empty( $parent )) {
							$new_term_parent = get_term_by( 'name', $parent->name, $this->taxonomy );
							$args['parent'] = $new_term_parent->term_id;
						}
						
						wp_insert_term( $_REQUEST['tag-name'], $this->taxonomy, $args );
						restore_current_blog();
					}
					
					break;
				case 'delete-tag':
						
						$term = get_term( $_REQUEST['tag_ID'], $this->taxonomy );
						foreach ( $sites as $site ) {
							switch_to_blog( $site->blog_id );
							$term_to_delete = get_term_by( 'name', $term->name, $this->taxonomy );
							wp_delete_term( $term_to_delete->term_id, $this->taxonomy );
							restore_current_blog();
						}
				
					break;
				case 'bulk-delete':
		
						$tags = (array) $_REQUEST['delete_tags'];
						
						foreach ( $tags as $tag_ID ) {
						
							$term = get_term( $tag_ID, $this->taxonomy );
							
							foreach ( $sites as $site ) {
								switch_to_blog( $site->blog_id );
								$term_to_delete = get_term_by( 'name', $term->name, $this->taxonomy );
								wp_delete_term( $term_to_delete->term_id, $this->taxonomy );
								restore_current_blog();
							}
						}
						
						
					break;
				case 'editedtag':
					
					
					$term = get_term( $_REQUEST['tag_ID'], $this->taxonomy );
					
					if ( $_REQUEST['parent'] != -1 ) {
						$parent = get_term( $_REQUEST['parent'], $this->taxonomy );
					}
					
					foreach ( $sites as $site ) {
			
						switch_to_blog( $site->blog_id );
						
						$args['description'] = $_REQUEST['description'];
						$args['name'] = $_REQUEST['name'];
						$args['slug'] = $_REQUEST['slug'];
						
						$previous_term = get_term_by( 'name', $term->name, $this->taxonomy );
						
						if ( ! empty( $parent )) {
							$new_term_parent = get_term_by( 'name', $parent->name, $this->taxonomy );
							$args['parent'] = $new_term_parent->term_id;
						}
						
						wp_update_term( $previous_term->term_id, $this->taxonomy, $args);
						restore_current_blog();
					}
					
					
				
					break;
				
				case 'inline-save-tax':
				
					$term = get_term( $_REQUEST['tax_ID'], $this->taxonomy );
					
					foreach ( $sites as $site ) {
			
						switch_to_blog( $site->blog_id );
						
						$previous_term = get_term_by( 'name', $term->name, $this->taxonomy );
						
						wp_update_term( $previous_term->term_id, $this->taxonomy, array( 'name' => $_REQUEST['name'], 'slug' => $_REQUEST['slug']));
						restore_current_blog();
					}
					
					break;
					
				case -1:
					if ( $_REQUEST['action2'] == 'delete') {
					
						$tags = (array) $_REQUEST['delete_tags'];
					
						foreach ( $tags as $tag_ID ) {
						
							$term = get_term( $tag_ID, $this->taxonomy );
							foreach ( $sites as $site ) {
								switch_to_blog( $site->blog_id );
								$term_to_delete = get_term_by( 'name', $term->name, $this->taxonomy );
								wp_delete_term( $term_to_delete->term_id, $this->taxonomy );
								restore_current_blog();
							}
						}
					}
				
			}
			
		}
	}
	function admin_menu () {
	
		
		global $blog_id;
		
		if ( is_multisite()) {
			add_submenu_page( 'settings.php', __('Global Taxonomy', 'gt'), __('Global Taxonomy', 'gt'), 'manage_options', 'global-taxonomy', array($this, 'settings_page'));
		}
		

		
	}
	
	function remove_taxonomy_menu () {

		if ( ! is_main_site( ) )	{
			remove_submenu_page( 'edit.php', 'edit-tags.php?taxonomy=global-taxonomy' );
		}
	}
	function remove_add_taxonomy () {
		if ( ! is_main_site()) {
			echo '<style>#global-taxonomy-adder{display: none;}</style>';
		}
		
	}

	
	function  settings_page () {
		?>
		<div class="wrap">
			<div class="icon32" id="icon-options-general" style="background: url(<?php echo $this->plugin_url . '/img/global-32.png'?>) 0 0; width: 32px; height: 32px; "><br></div>
			<h2><?php _e('Global Taxonomy', 'gt') ?></h2>
			
			<?php if ( is_multisite() ): ?>
			
			<?php 
				$gt_slug = get_site_option(  'gt_slug' );
				$gt_sites = get_site_option( 'gt_sites' );
				
				//$this->dump($gt_sites);
				
				if ( ! empty( $_GET['settings-updated'])) {
					echo '<div class="updated settings-error" id="setting-error-settings_updated"><p><strong>' . __('Settings saved', 'gt') .'</strong></p></div>';
				}
			
			?>
			<form method="post" action="">
		
			    <h3><?php _e('Global settings', 'gt')?></h3>
			    
			    <table class="form-table">
			        <tr valign="top">
			        	<th scope="row"><?php _e('Global Taxonomy Slug', 'gt')?></th>
			        	<td><input type="text" name="gt_slug" value="<?php echo $gt_slug ?>" /></td>
			        </tr>
			         
		
			  </table>
			  <h3><?php _e('Where do you want to enable the taxonomy?', 'gt')?></h3>
			  <p><?php _e('Please, select only blogs where you use the taxonomy. Many blogs reduce site performance.', 'gt')?></p>
			   <table class="form-table">
			        <?php
			        $sites = $this->get_sites();
			        
			        foreach ( $sites as $site ) {
			        	$checked = '';
			        	if ( ! empty( $gt_sites )) {
			        		if ( in_array($site->blog_id, $gt_sites)){
			        			$checked = 'checked';
			        		}
			        	}
			       
			        ?>
			        <tr valign="top">
						<th scope="row"><label for="blog_<?php echo $site->blog_id ?>"><?php echo $site->domain ?><?php echo $site->path ?></label></th> 
						<td><input type="checkbox" value="<?php echo $site->blog_id ?>" id="blog_<?php echo $site->blog_id ?>" name="gt_sites[]" <?php echo $checked ?>/><br></td>
					</tr>
			        <?php
			        	
					}
			        
			        ?>
			    </table>
			    
			  <h3><?php _e('Propagate changes', 'gt')?></h3>
			  <p><?php _e('Check this option and the plugin will check that all the taxonomy terms are created in all blogs', 'gt')?></p>
			  
				<table class="form-table">
					<tr valign="top">
						<th scope="row"><label for="gt_propagate"><?php _e('Propagate changes', 'gt')?></label></th> 
						<td><input type="checkbox" value="1" name="gt_propagate" id="gt_propagate" /><br></td>
					</tr>
				</table>
			  
			    <?php wp_nonce_field();?>
			    
			    <p class="submit">
			    	<input type="submit" class="button-primary" value="<?php _e('Save Changes', 'gt') ?>" />
			    </p>
			
			</form>
			<?php else : ?>
				<div id="setting-error-settings_updated" class="error settings-error below-h2">
					<p><strong><?php _e('This plugin only work with Wordpress Multisite enabled', 'gt')?></strong></p>
				</div>
			<?php endif;?>
		</div>
		<?php
	}
	/**
	 * Save options page settings
	 *
	 */
	function save_settings () {
			
		if ( ! empty( $_POST ) && ! empty($_GET['page']) && $_GET['page'] == 'global-taxonomy') {
		
			//$this->dump( $_POST ); 
			extract($_POST);
			
			if ( isset( $gt_slug)) {
				$gt_slug = sanitize_title( $gt_slug );
				update_site_option( 'gt_slug', $gt_slug);
			}
			
			if ( isset( $gt_sites)) {
				update_site_option( 'gt_sites', $gt_sites);
			} else {
				update_site_option( 'gt_sites', 0);
			}
			
			if ( ! empty( $gt_propagate )) {
				$this->propagate_changes();
			}
			
			flush_rewrite_rules();
			wp_redirect( add_query_arg( 'settings-updated', 'true',  $this->admin_setting_url ) );
		

		}
	}
	/**
	 * Checks that all main blog terms are created site wide
	 *
	 */
	function propagate_changes () {
	
		global $already_inserted;
		$already_inserted = array();
		$sites = $this->get_sites();
	
		$all_terms = get_terms( $this->taxonomy, array('hide_empty' => false));
		
		
		foreach ( $all_terms as $current_term ) {
		
			foreach ( $sites as $site ) {
				$this->wp_insert_term( $current_term, $site->blog_id );
				//$this->wp_insert_term( $current_term, 3 );
				
				
			}
		}
		
		/*
		foreach ( $all_terms as $current_term ) {
			foreach ( $sites as $site ) {
				$this->wp_insert_term( $current_term, $site->blog_id );
			}
		}
		*/
	
	}
	
	function wp_insert_term ( $term, $blog_id) {
	
		
		
		error_reporting(E_ALL);
		ini_set('display_errors', '1');
		
		global $already_inserted;
		
		// Check if we have already inserted this term due to recursivity
		/*
		if ( in_array( $term->term_id, $already_inserted)) {
			return true;
		}
		*/
		
		
		
		$parent_term = false;
		$parent_term_ms_id = 0;
		$args = array('name' => $term->name, 'description' => $term->description, 'parent' => 0, 'slug' => $term->slug );		
		
		if ( $term->parent != 0) {
			$parent_term = get_term( $term->parent, $this->taxonomy );
		}
		

		
		switch_to_blog( $blog_id );
		

		
		if ( $parent_term) {
			$parent_term_ms = get_term_by( 'name', $parent_term->name, $this->taxonomy );
			

		
		
			if ( ! $parent_term_ms ) {
				$parent_term_ms_id = $this->wp_insert_term( $parent_term, $blog_id );
			} else {
				$parent_term_ms_id = $parent_term_ms->term_id;
			}
			$args['parent'] = $parent_term_ms_id;
		}
		
		$term_ms = get_term_by( 'name', $term->name, $this->taxonomy);
		if ( $term_ms ) {
			$return = wp_update_term( $term_ms->term_id, $this->taxonomy, $args);
		} else {
			$return = wp_insert_term( $term->name, $this->taxonomy, $args );
		}
		
		restore_current_blog();				
		
		//array_push( $already_inserted, $term->term_id );	
		
		return $return['term_id'];
	
	}
	
	function get_sites () {
		global $wpdb;
		return $wpdb->get_results("SELECT * FROM $wpdb->blogs where blog_id > 1");
	}
	
	function dump () {
	
	    list($callee) = debug_backtrace();
	    $arguments = func_get_args();
	    $total_arguments = count($arguments);
	 
	    echo '<fieldset style="background: #fefefe !important; border:2px red solid; padding:5px">';
	    echo '<legend style="background:lightgrey; padding:5px;">'.$callee['file'].' @ line: '.$callee['line'].'</legend><pre>';
	    $i = 0;
	    foreach ($arguments as $argument)
	    {
	        echo '<br/><strong>Debug #'.(++$i).' of '.$total_arguments.'</strong>: ';
	        print_r($argument);
	    }
	 
	    echo "</pre>";
	    echo "</fieldset>";

	
	}
	/**
	 * Create Global Taxonomy
	 *
	 */
	function register_taxonomy () {
	
		$gt_slug = get_site_option( 'gt_slug' );
		if ( empty( $gt_slug )) {
			$gt_slug = 'global-tax';
		}
		
	  // Add new taxonomy, make it hierarchical (like categories)
	  $labels = array(
	    'name' => _x( 'Global Taxonomy', 'taxonomy general name', 'gt'),
	    'singular_name' => _x( 'Global Taxonomy', 'taxonomy singular name', 'gt'),
	    'search_items' =>  __( 'Search Global Taxonomies', 'gt'),
	    'all_items' => __( 'All Global Taxonomies', 'gt'),
	    'parent_item' => __( 'Parent Global Taxonomy', 'gt'),
	    'parent_item_colon' => __( 'Parent Global Taxonomy:', 'gt'),
	    'edit_item' => __( 'Edit Global Taxonomy', 'gt'), 
	    'update_item' => __( 'Update Global Taxonomy', 'gt'),
	    'add_new_item' => __( 'Add New Global Taxonomy', 'gt'),
	    'new_item_name' => __( 'New Global Taxonomy Name', 'gt'),
	    'menu_name' => __( 'Global Taxonomy', 'gt'),
	  ); 	

	
		if ( is_main_site()) {
		  register_taxonomy( $this->taxonomy, array('post'), array(
		    'hierarchical' => true,
		    'labels' => $labels,
		    'show_ui' => true,
		    'query_var' => true,
		    'rewrite' => array( 'slug' => $gt_slug ),
		  ));
		} else {
		  register_taxonomy( $this->taxonomy , array('post'), array(
		    'hierarchical' => true,
		    'labels' => $labels,
		    'show_ui' => true,
		    'query_var' => true,
		    'rewrite' => array( 'slug' => $gt_slug ),
		  ));
		}
	}
	
	/**
	 * Enter description here...
	 *
	 * @param unknown_type $term_id
	 * @param unknown_type $tt_id
	 */
	function created_term ( $term_id, $tt_id ){
		
		$sites = $this->get_sites();
		$term = get_term( $term_id, $this->taxonomy );
		
		
		foreach ( $sites as $site ) {
			switch_to_blog( $site->blog_id );
			wp_insert_term( $term->name, $this->taxonomy );
			restore_current_blog();
		}
	
	}
	/**
	 * Delete all terms
	 *
	 * @param unknown_type $term
	 * @param unknown_type $tt_id
	 */
	
	function delete_term ( $term, $tt_id ) {
	
		$sites = $this->get_sites();
		$term_object = get_term( $term, $this->taxonomy );
		
		foreach ( $sites as $site ) {
		
			switch_to_blog( $site->blog_id );
			
			$blog_term = get_term_by( 'name', $term_object->name, $this->taxonomy );

			wp_delete_term( $blog_term->term_id, $this->taxonomy );
			restore_current_blog();
			
		}
	
	}
	/**
	 * Enter description here...
	 *
	 * @param unknown_type $term_id
	 * @param unknown_type $tt_id
	 */
	function edit_term ( $term_id, $tt_id ) {
	
		$sites = $this->get_sites();
		$term_object = get_term( $term_id, $this->taxonomy, 'ARRAY_A');
		
		
		foreach ( $sites as $site ) {
		
			switch_to_blog( $site->blog_id );
			
			$blog_term = get_term_by( 'name', $term_object['name'], $this->taxonomy );
			
			wp_update_term( $blog_term->term_id, $this->taxonomy, $term_object);
			restore_current_blog();
			
		}
	}
	/**
	 * In
	 *
	 * @param unknown_type $posts
	 * @param unknown_type $query
	 * @return unknown
	 */
	function populate_posts_data (  $posts, $query ) {
	
	
		if ( is_main_site() && $query->is_main_query() ) {
		
			if ( $term = get_query_var( $this->taxonomy )) {
			
				$posts = array_merge( $posts, $this->get_posts( $term ));
				usort( $posts, 'cmp_post_date');
				
				if ( $query->query_vars['posts_per_page'] < count( $posts )) {
					
					$query->max_num_pages = ceil( count( $posts ) / $query->query_vars['posts_per_page']);
				}
				$posts = array_slice( $posts,  $query->query_vars['paged'], $query->query_vars['posts_per_page'] );
				
			}
		}

		return $posts;
	}
	/**
	 * Enter description here...
	 *
	 * @param unknown_type $term
	 * @return unknown
	 */
	function get_posts ( $term ) {
	
		$enabled_sites = get_site_option( 'gt_sites' );
		$array_posts = array();
		if ( ! empty( $enabled_sites ) ) {
			foreach ( $enabled_sites as $site ) {
				switch_to_blog( $site );
				$site_posts = get_posts( array( $this->taxonomy => $term ) );
				for ( $i=0; $i< count($site_posts); $i++) $site_posts[$i]->blog_id = $site;
				$array_posts = array_merge( $array_posts, $site_posts );
				restore_current_blog();
			}
		}
		return $array_posts;
	
	}


}

$global_taxonomy = new Global_Taxonomy();