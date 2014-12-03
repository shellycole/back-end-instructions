<?php
/**
 * Plugin Name: Back-End Instructions
 * Plugin URI: http://wordpress.org/extend/plugins/back-end-instructions/
 * Description: Plugin to provide nice little instructions for back-end WordPress users.
 * Version: 3.1
 * Author: Shelly Cole
 * Author URI: http://brassblogs.com
 * Requires at least: 3.1
 * Tested up to: 3.5.1
 * Text Domain: localizationsample
 * Domain Path: /bei_languages
 */


/** 
 * Keep people from loading this file directly
 */

if( preg_match( '#' . basename( __FILE__ ) . '#', $_SERVER['PHP_SELF'] ) )
	die( 'You are not allowed to call this page directly.' );


/*-----------------------------------------------------------------------------
							Initial Setup
-----------------------------------------------------------------------------*/

/** 
 * Definitions
 *
 * $pagenow			filename of current page
 *
 * BEI_DIR_PATH 	gets the server path to the Back-End Instructions directory
 * BEI_DIR_URL  	gets the url to the Back-End Instructions directory
 * BEI_PLUGIN_LOC	gets the plugin directory: i.e. "back-end-instructions"
 * BEI_CUR_URL		gets the url of the current page
 * BEI_CUR_PAGE		gets the filename of the current page in the browser window, sans any query additions and file extensions
 * BEI_CUR_QUERY	gets the current page filename with querystrings
 * BEI_META_KEY		defines the key for the custom post type metaboxes
 *
 * $bei_options 	gets options from the DB
 */

global $pagenow, $post;

define( 'BEI_DIR_PATH', 	plugin_dir_path( __FILE__ ) );
define( 'BEI_DIR_URL', 		plugin_dir_url( __FILE__ ) );
define( 'BEI_PLUGIN_LOC',	dirname( plugin_basename( __FILE__ ) ) );
define( 'BEI_CUR_URL', 		'http://' . $_SERVER['HTTP_HOST'] . $_SERVER["REQUEST_URI"] );
define( 'BEI_CUR_PAGE', 	pathinfo( basename( $_SERVER['PHP_SELF'] ), PATHINFO_FILENAME ) );
define( 'BEI_CUR_QUERY', 	basename( $_SERVER['REQUEST_URI'] ) );
define( 'BEI_META_KEY', 	'_bei_instructions');

$bei_options = get_option( '_bei_options' );


/**
 * I know.  I'm sorry.
 */

if( !function_exists('wp_set_current_user')	) {
  require(ABSPATH . WPINC . '/pluggable.php');
}


/** 
 * Wonder Twin powers, activate!
 *
 * bei_query_vars							make sure "instructions" post type is not returned on front-end search results
 * bei_add_instructions_options				add options from settings page to database
 * bei_languages_for_translation			language/translation goodness
 * bei_create_instructions_management		create the custom post type
 * bei_save_meta_box						meta boxes for the custom post type
 * bei_admin_head_script					script to add to admin head for the dynamic metabox fields
 * bei_instructions_admin_add_options		add options page
 * bei_instructions_admin_init				options settings fields
 * bei_hide_first_post_from_google			hide the example post from search engines
 * bei_add_instructions_button				show the actual instructions in the Help tab
 * bei_search_query_filter					filters the search results based on the settings
 * bei_mess_with_shortcodes					when displaying shortcodes on the front end, replaces the {{}} with HTML character entities
 *
 * wptexturize								removes the "curly quotes" that wordpress pops into the_content
 *
 * bei_custom_instruction_tab				shows a custom tab instead of putting the instructions in the help tab
 */

add_filter( 'pre_get_posts', 		'bei_query_vars' );
add_action( 'admin_init', 			'bei_add_instructions_options' );
add_action( 'plugins_loaded', 		'bei_languages_for_translation' );
add_action( 'init', 				'bei_create_instructions_management' );
add_action( 'save_post', 			'bei_save_meta_box' );
add_action( 'admin_head',			'bei_admin_head_script' );
add_action( 'admin_menu', 			'bei_instructions_admin_add_options' );	
add_action( 'admin_init', 			'bei_instructions_admin_init' );
add_action( 'wp_head', 				'bei_hide_first_post_from_google' );
add_action( 'load-'.$pagenow, 		'bei_add_instructions_button' );
add_filter( 'pre_get_posts', 		'bei_search_query_filter', 10 );
add_filter( 'the_content', 			'bei_mess_with_shortcodes' );

if($post && $post->post_type == 'instructions')
	remove_filter( 'the_content', 'wptexturize' );

if($bei_options
['custom_tab'] == 'yes') 
	add_action( 'admin_head', 'bei_custom_instruction_tab');


/** 
 * Remove from front-end Search Results
 */ 

function bei_query_vars( $query ) {
	// only perform the action on front-end search results
    if( $query->is_search ) {

      // get the array of all post types
      $types = get_post_types(); 
      foreach( $types as $key => $value ) {

      	// if "instructions" post type is found, remove it
		if ( $value == 'instructions' ) unset( $types[$key] );
	  }

	  // set post types listed above (all of them, sans "instructions"
      $query->set( 'post_type', $types );
    }

    // return the query and perform the search
    return $query;
}


/** 
 * Adding/Editing Options to database
 * rename the meta_key that holds the info
 */
				
function bei_add_instructions_options() {
	global $wpdb, $bei_options
; 
	$tp = $wpdb->prefix . 'options';

	// set up the default array
	$array = array( 'admin' 		=> 'activate_plugins',				
					'public' 		=> 'no',
					'registered' 	=> 'yes',
					'view' 			=> 'delete_posts',
					'custom_tab'	=> 'no'
			 	  );

	// check for version 1 stuff in database
	$old1 = get_option( '_back_end_instructions' );	
	// version 2
	$old2 = get_option( 'bei_options' );	

	if( $old1 ) {													
		delete_option( '_back_end_instructions' );								
	} 

	if( $old2 ) {														
		$array 				 = $old2;
		$array['custom_tab'] = 'no';
		delete_option( 'bei_options' );							
	}
	
	// if the new option is not set...
	if( !$bei_options
 ) {	
		// add the default setup
  		add_option( '_bei_options', $array, '', 'yes' );						
	} else {
		// If the option already exists, it's a 2.x version, and the option name is wrong.
		// It's dumb, but we need to rename the option because I was shortsighted and irregular,
		// and I didn't know any better.  I beg forgiveness.
		// so let's fix it.
		$sql = "SELECT option_name, option_id FROM $tp WHERE $tp.option_name = 'bei_options'";
		$results = $wpdb->get_results( $sql );
		if( $results ) {
			// create a copy with the new name
			$sql = "UPDATE $tp SET option_name = REPLACE( option_name, 'bei_options', '_bei_options' )";
			$wpdb->query( $wpdb->prepare( $sql ) );
			// delete the old
			$del = "DELETE FROM $tp WHERE $tp.option_name = 'bei_options'";			
			$wpdb->query( $wpdb->prepare( $del ) );
		}

		// now we need to rename the meta_key for the instructions posts to retain that information
		// because, again, I was shortsighted and dumb.
		$sql = "SELECT * FROM $wpdb->posts, $wpdb->postmeta WHERE $wpdb->posts.ID = $wpdb->postmeta.post_id
				AND $wpdb->postmeta.meta_key = 'instructions'";
		$results = $wpdb->get_results( $sql );
		$count = 0;
		if( $results ) {
			foreach($results as $result) {
				if($count == 1) break;
				// create a copy with the new name
				$sql = "UPDATE $wpdb->postmeta SET $wpdb->postmeta.meta_key = REPLACE( $wpdb->postmeta.meta_key, 'instructions', '_bei_instructions' )";
				$wpdb->query( $wpdb->prepare( $sql ) );
				$count++;
			}
		} 
	}							
}


/** 
 * Hide the first example post from search engines
 * Deprecated, since the first post is no longer created
 * this is here for older installations that haven't deleted the first post
 */

function bei_hide_first_post_from_google() {
	global $wpdb, $post;
	$how_to_use_id = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_name = 'bei-how-to' AND post_type = 'instructions'" ); 
	if( $post->ID == $how_to_use_id ) echo '<meta name="robots" content="noindex">';
}


/*-----------------------------------------------------------------------------
						Settings Page
-----------------------------------------------------------------------------*/

/** 
 * Options Page startup
 */

function bei_instructions_admin_add_options() {
	add_options_page( __( 'Back End Instructions', 'bei_languages' ), __( 'Back End Instructions', 'bei_languages' ), 'manage_options', 'bei', 'bei_options_page' );
}


/** 
 * Options Settings startup
 */

function bei_instructions_admin_init(){	
	register_setting( 		'_bei_options', 		'_bei_options', 			'bei_options_validate' );
	add_settings_section( 	'bei_main', 		__( '', 						'bei_languages'), 	'bei_section_text', 			'bei' ); 
	add_settings_field( 	'bei_custom_tab', 	__( 'Use a Custom Help Tab?', 	'bei_languages' ), 	'bei_custom_help_tab', 			'bei', 'bei_main' ); 
	add_settings_field( 	'bei_admin', 		__( 'Default Admin Level', 		'bei_languages' ), 	'bei_setting_string', 			'bei', 'bei_main' ); 
	add_settings_field( 	'bei_public', 		__( 'Show in front?', 			'bei_languages' ), 	'bei_setting_string_public', 	'bei', 'bei_main' ); 
	add_settings_field( 	'bei_registered', 	__( 'Logged-in users only?', 	'bei_languages' ), 	'bei_setting_string_private', 	'bei', 'bei_main' ); 
	add_settings_field( 	'bei_view', 		__( 'Default viewing level', 	'bei_languages' ), 	'bei_setting_string_view', 		'bei', 'bei_main' );
}


/** 
 * The actual page contents
 */

function bei_options_page() {  ?>
<div class="wrap">
	<div id="icon-options-general" class="icon32"><br /></div><h2><?php esc_attr_e( 'Back End Instructions', 'bei_languages' ); ?></h2>
	<p><?php esc_attr_e( 'There aren\'t too many default settings for the Back End Instructions, but it makes life easier to have them here.', 'bei_languages' ); ?></p>
	<form action="options.php" method="post">
		<?php settings_fields( '_bei_options' ); ?>
		<?php do_settings_sections('bei'); ?>
		<p><input name="submit" type="submit" id="submit" class="button-primary" value="<?php esc_attr_e( 'Save Changes', 'bei_languages' ); ?>" /></p>
	</form>
</div>
<?php } 


/** 
 * Nuttin' really.  Might use later.  Just setting up for now.
 */

function bei_section_text() {
}


/** 
 * Fields: Custom Help Tab
 */

function bei_custom_help_tab() {
	global $bei_options;
	var_dump($bei_options);
	echo '<span class="description" style="display:block;">' . __( 'By default, Back End Instructions just ties into the WordPress standard "help" tab.  This option will allow you to use a standalone custom tab, instead.', 'bei_languages' ) , '</span>';
	
	if( !isset( $bei_options
['custom_tab'] ) ) $bei_options
['custom_tab'] = 'no';
	echo '<input id="bei_custom_tab" name="_bei_options[custom_tab]" size="40" type="radio" value="yes" ' . ( isset($bei_options
["custom_tab"] ) && $bei_options
["custom_tab"] == "yes" ? 'checked="checked" ' : '' ) . '/> Yes &nbsp; &nbsp; ' . "\n";
	echo '<input id="bei_custom_tab" name="_bei_options[custom_tab]" size="40" type="radio" value="no"  ' . ( isset($bei_options
["custom_tab"] ) && $bei_options
["custom_tab"] == "no"  ? 'checked="checked" ' : '' ) . '/> No' . "\n\n";
}

/** 
 * Fields: Admin Level
 */

function bei_setting_string() {
	global $bei_options
;
	
	echo '<span class="description" style="display:block;">' . __( 'Choose the lowest level logged-in user to create/edit/delete Instructions.', 'bei_languages' ) , '</span>';
	
	if(is_multisite())																	// test that this is a multi-site install	
	  echo '<input id="bei_admin" name="_bei_options[admin]" size="40" type="radio" value="manage_network" ' . ( isset( $bei_options
["admin"] ) && $bei_options
["admin"] == "manage_network" ? 'checked="checked" ' : '' ) . '/> ' . __( 'Super Administrator (for multi-site only)', 'bei_languages' ) . '<br />';

	
	echo '<input id="bei_admin" name="_bei_options[admin]" size="40" type="radio" value="activate_plugins" ' 		. ( isset( $bei_options
["admin"] ) && $bei_options
["admin"] == "activate_plugins" ? 		'checked="checked" ' : '' ) . '/> ' . __( 'Administrator', 'bei_languages' ) . '<br />';
	echo '<input id="bei_admin" name="_bei_options[admin]" size="40" type="radio" value="edit_others_posts" ' 		. ( isset( $bei_options
["admin"] ) && $bei_options
["admin"] == "edit_others_posts" ? 		'checked="checked" ' : '' ) . '/> ' . __( 'Editor', 'bei_languages' ) . '<br />';
	echo '<input id="bei_admin" name="_bei_options[admin]" size="40" type="radio" value="delete_published_posts" ' 	. ( isset( $bei_options
["admin"] ) && $bei_options
["admin"] == "delete_published_posts" ? 	'checked="checked" ' : '' ) . '/> ' . __( 'Author', 'bei_languages' );
}


/** 
 * Fields: Show in front
 */

function bei_setting_string_public() {	
	global $bei_options;

	$permalink = get_option( 'site_url' ) . '/wp-admin/options-permalink.php';
	
	echo '<span class="description" style="display:block;">' . sprintf( __( 'Check "yes" if you\'d like to make your instructions viewable on the front end of the site. <br /><strong>PLEASE NOTE</strong>: The first time you change this option, you WILL have to <a href="%1$s">re-save your permalink settings</a> for this to take effect.  You may not ever have to do it again, but if you find you have issues after swapping back and forth, then try resetting them again to see if it helps.</span>', 'bei_languages' ), $permalink ) . "\n\n";
	
	if( !isset( $bei_options
['public'] ) ) $bei_options
['public'] = 'no';
	echo '<input id="bei_public" name="_bei_options[public]" size="40" type="radio" value="yes" ' . ( isset($bei_options
["public"] ) && $bei_options
["public"] == "yes" ? 'checked="checked" ' : '' ) . '/> Yes &nbsp; &nbsp; ' . "\n";
	echo '<input id="bei_public" name="_bei_options[public]" size="40" type="radio" value="no"  ' . ( isset($bei_options
["public"] ) && $bei_options
["public"] == "no"  ? 'checked="checked" ' : '' ) . '/> No' . "\n\n";
}


/** 
 * Fields: Logged-in users only
 */

function bei_setting_string_private() {
	global $bei_options
;
	
	echo '<span class="description" style="display:block;">' . __( 'Check "yes" if you\'d like to make front-end instructions visible only to logged-in users.<br /><strong>PLEASE NOTE</strong>: if you check "yes" ANYONE can see ALL of these instructions.  See the next option to help with that a bit.', 'bei_languages' ) . '</span>' . "\n\n";
	
	echo '<input id="bei_registered" name="_bei_options[registered]" size="40" type="radio" value="yes" ' . ( isset( $bei_options
["registered"] ) && $bei_options
["registered"] == "yes" ? 'checked="checked" ' : '') . '/> Yes &nbsp; &nbsp; ' . "\n";
	echo '<input id="bei_registered" name="_bei_options[registered]" size="40" type="radio" value="no"  ' . ( isset( $bei_options
["registered"] ) && $bei_options
["registered"] == "no"  ? 'checked="checked" ' : '') . '/> No' . "\n\n";
}


/** 
 * Fields: Default viewing level
 */

function bei_setting_string_view() {
	global $bei_options
;
	
	echo '<span class="description" style="display:block;">' . __( 'You only need to choose an option from this dropdown if you set "Show in front?" to "yes" AND "Logged-in users only?" to "no".  If this option were not here, then ANY visitor to the site could see ALL instructions just by visiting the page.  If the user is logged in, they would see only instructions that were available to their level, but if they aren\'t, they would see them for ALL levels.  This option will allow you to treat a non-logged-in user as if they have a user level.  The default is "Contributor."', 'bei_languages' ) . '</span>' . "\n\n";
	
	// setup array
	$choices = array();
	
	if(is_multisite())
	$choices['Super Administrator'] = 'manage_networks';
	
	$choices['Administrator'] 		= 'activate_plugins';
	$choices['Editor'] 				= 'edit_others_posts';
	$choices['Author'] 				= 'delete_published_posts';
	$choices['Contributor'] 		= 'delete_posts';
	$choices['Subscriber'] 			= 'read';
		
	echo '<p><select id="bei_view" name="_bei_options[view]">' . "\n";
		
	foreach( $choices as $key => $value ) {
		echo '<option value="' . $value . '"' . selected($bei_options
['view'], $value, false) . '>' . $key .'</option>' . "\n";
	}	
	
	echo '</select></p>' . "\n";	
}

/** 
 * Fields: Validate (hidden)
 */

function bei_options_validate( $input ) {
	isset( $input['custom_tab'] ) 	? $newinput['custom_tab'] 	= trim( $input['custom_tab'] ) 	: $newinput['custom_tab'] 	= '';
	isset( $input['admin'] ) 		? $newinput['admin'] 		= trim( $input['admin'] ) 		: $newinput['admin'] 		= '';
	isset( $input['public'] ) 		? $newinput['public'] 		= trim( $input['public'] ) 		: $newinput['public'] 		= '';
	isset( $input['registered'] ) 	? $newinput['registered'] 	= trim( $input['registered'] ) 	: $newinput['registered'] 	= '';
	isset( $input['view'] ) 		? $newinput['view'] 		= trim( $input['view'] ) 		: $newinput['view'] 		= '';
	return $newinput;
}


/** 
 * Translate!
 */

function bei_languages_for_translation() {
	load_plugin_textdomain( 'bei_languages', false, BEI_DIR_PATH . 'bei_languages' );
}


/*-----------------------------------------------------------------------------
							Post Type Info
-----------------------------------------------------------------------------*/

/** 
 * Create the post type
 */

function bei_create_instructions_management() {
	global $current_user, $bei_options
;

	$level = $bei_options
['admin'];
	$front = $bei_options
['public'];

	// version check
	if( !function_exists( 'get_site_url' ) ) 
		$install = get_bloginfo( 'wpurl' );
	else 
		$install = get_site_url();
	
	$warning = sprintf( __( 'This plugin will not work in versions earlier than 3.1. However, it\'s highly recommended that you upgrade to the <a href="%1$s/wp-admin/update-core.php" target="_parent">most current and secure version</a>, even though you can use this plugin in version 3.1.', 'bei_languages' ), $install );
	
	if( !function_exists( 'register_post_type' ) || get_bloginfo( 'version' ) < 3.1 ) { 
		die( '<p style="font: 0.8em Tahoma, Helvetica, sans-serif;">' . $warning. '</p>' );

	// if passes version muster, register the post type
	} else {  
	  // show or hide menu?
	  if( current_user_can( $level ) ) {
	  	$show = true;
	  } else {
	  	$show = false;
	  }

	  // show in front/make instructions public?
	  if( $front == 'yes' ) { 
	  	$front = true;
	  	$rewrite = array( 'slug' => 'instructions', 'with_front' => true );
	  } else {
	  	$front = false;
	  	$rewrite = false;
	  }

	  $labels = array( 'name' 				=> __( 'Instructions', 						'bei_languages' ),
	  				   'singular_name'		=> __( 'Instruction', 						'bei_languages' ),
	  				   'add_new' 			=> __( 'Add New Instruction', 				'bei_languages' ),
	  				   'add_new_item' 		=> __( 'Add New Instruction', 				'bei_languages' ),
	  				   'edit' 				=> __( 'Edit', 								'bei_languages' ),
	  				   'edit_item' 			=> __( 'Edit Instruction', 					'bei_languages' ),
	  				   'new_item' 			=> __( 'New Instruction', 					'bei_languages' ),
	  				   'view' 				=> __( 'View Instruction', 					'bei_languages' ),
	  				   'view_item' 			=> __( 'View Instruction', 					'bei_languages' ),
	  				   'search_items' 		=> __( 'Search Instructions', 				'bei_languages' ),
	  				   'not_found' 			=> __( 'No instructions found.', 			'bei_languages' ),
	  				   'not_found_in_trash' => __( 'No instructions found in trash.',	'bei_languages' ),
	  				   'parent' 			=> __( 'Parent Instruction', 				'bei_languages' )
					 );

	  $args = array('labels' 				=> $labels,
	  				'description' 			=> __( 'Section to add and manage instructions.', 'bei_languages' ),
	  				'show_ui' 				=> $show,
	  				'menu_position' 		=> 5,
	  				'publicly_queryable' 	=> $front, 
	  				'public' 				=> $front,
	  				'exclude_from_search' 	=> false,
	  				'heirarchical' 			=> false,
	  				'query_var' 			=> 'instructions',
	  				'supports' 				=> array( 'title', 'editor', 'excerpt', 'thumbnail' ),
	  				'rewrite' 				=> $rewrite,
	  				'has_archive' 			=> $front,
	  				'can_export' 			=> true,
	  				'show_tagcloud' 		=> false,
	  				'show_in_menu'			=> $show,
					'register_meta_box_cb' 	=> 'bei_create_meta_box'
	  			   );
	 
	  register_post_type( 'instructions', $args );

	}
}


/**
 * Set up the metaboxes for the custom post type
 */

$bei_meta_boxes = array('_bei_pages' => array('name' 		=> 'page_id',  
	  									 	  'description' => __('Page Name: ', 	'bei_languages'),
	  									 	  'type'		=> '',
	  									 	  'choices' 	=> ''
											 ), 
						'_bei_multi' => array('name' 		=> 'multi',  
	  										  'description' => __('+ ', 			'bei_languages'),
	  										  'type' 		=> 'dynamic',
	  										  'choices'		=> ''
											 ),
						'_bei_video' => array('name' 		=> 'video_url',  
	  										  'description' => __('Video URL: ', 	'bei_languages'),
	  										  'type'		=> '',
	  										  'choices' 	=> ''
											 ),
						'_bei_level' => array('name' 		=> 'user_level',  
	  										  'description' => __('User Level: ', 	'bei_languages'),
	  										  'type' 		=> 'dropdown',
	  										  'choices' 	=> array('manage_network' 			=> __('Super Administrator', 	'bei_languages'),
 						 											 'activate_plugins' 		=> __('Administrator', 			'bei_languages'),
 						 											 'edit_others_posts' 		=> __('Editor', 				'bei_languages'),
 						 											 'delete_published_posts' 	=> __('Author', 				'bei_languages'),
 						 											 'delete_posts' 			=> __('Contributor', 			'bei_languages'),
 						 											 'read' 					=> __('Subscriber', 			'bei_languages')
				   													)
											 )
					   );

/**
 * Add the custom metaboxes
 */

function bei_create_meta_box() {
  	add_meta_box( 'bei-meta-boxes', __('Instruction Page Information', 'bei_languages'), 'bei_display_meta_box', 'instructions', 'side', 'low' );
}


/**
 * create the display for the custom meta boxes
 */

function bei_display_meta_box() {
	global $post, $bei_meta_boxes;

	wp_nonce_field( plugin_basename( __FILE__ ), BEI_META_KEY . '_wpnonce', false, true );

	echo '<div class="form-wrap">' . "\n";

	$output = '';

  	foreach($bei_meta_boxes as $meta_box) {
    	$data 		= get_post_meta($post->ID, '_bei_instructions', true);
    	$name 		= $meta_box['name'];
    	$desc 		= $meta_box['description'];
    	$type 		= $meta_box['type'];
    	$choices 	= $meta_box['choices'];
	
		// dropdown choices
		if($type == 'dropdown') {					
	  		$output .= '<div class="misc-pub-section ' . sanitize_title_with_dashes($name) . '" style="border-bottom:none;">
	  					   <label for="' . $name . '" style="display:inline;">' . $desc . '</label>
	  					   <select name="' . $name . '">' . "\n";

			foreach($choices as $dropdown_key => $dropdown_value) {
				if(!is_multisite() && $dropdown_key == 'manage_network') continue;
				$output .= '<option value="' . $dropdown_key . '"' . (isset($data[$name]) ? selected($data[$name], $dropdown_key, false) : '') .'>' . $dropdown_value . '</option>' . "\n";
			}

			$output .= '</select>
					  </div>' . "\n";
		
		// dynamic fields
		} elseif($type == 'dynamic') {
			
			$output .= '<div class="more_fields">';

			if($data) {
				// unset any empty array elements
				$data[$name] = array_filter($data[$name]);

				$count = 0;

				foreach($data[$name] as $key => $value) {
					// don't show a field if there's no value
					if($data[$name][$key] == '') unset($data[$name][$key]); 

    				$output .= '<p style="margin-left:75px;">
    							<input type="text" name="' . $name . '[]" value="' . $value . '" style="width:97%;" />
    							</p>' . "\n";
    				$count++;
    			}
    		}

    		$output .= '<p style="margin-left:40px;">
    					<strong style="display:inline-block; width:26px; text-align:right; margin-right:6px;">
    					<a id="' . $name . '" class="add_field" style="text-decoration:none; color:#666; font-style:normal; cursor:pointer;">' . $desc . '</a>
    					 </strong>
    					<input type="text" name="' . $name . '[]" value="" style="width:80%;" />
    					</p>' . "\n" . '</div></div>' . "\n\n";
    	
    	// default text input
    	} else {
			$output .= '<div class="misc-pub-section ' . sanitize_title_with_dashes($name) . '">
						   <label for="' . $name . '" style="display:inline;">' . $desc . '</label>
						   <input type="text" name="' . $name . '" value="' . (isset($data[$name]) ? $data[$name] : '') . '" style="display:inline; width:66%;" />';
			if($name != 'page_id')
				$output .= '</div>' . "\n\n";
		}
  	}
  
  	echo $output . '</div>' . "\n\n";
}


/**
 * Save the custom meta box input
 */

function bei_save_meta_box( $post_id ) {
	global $post, $bei_meta_boxes;

	if( !isset( $_POST[BEI_META_KEY . '_wpnonce'] ) || !current_user_can( 'edit_post', $post_id ) || !wp_verify_nonce( $_POST[BEI_META_KEY . '_wpnonce'], plugin_basename( __FILE__ ) ) )
			return $post_id;

	foreach( $bei_meta_boxes as $meta_box ) {
		$data[ $meta_box[ 'name' ] ] = $_POST[ $meta_box[ 'name' ] ];
  	}

  	update_post_meta( $post_id, BEI_META_KEY, $data );
}


/**
 * Script to add to the header so the dynamic fields in the meta boxes can be populated
 */

function bei_admin_head_script() { 
	global $pagenow, $typenow;

	// make script show up only where needed 
	if($typenow == 'instructions') {
		if(($pagenow == 'post.php') || ($pagenow == 'post-new.php')) { ?>

<!-- back end instructions-->
<script type="text/javascript">
jQuery(document).ready(function($) { 

	$(".add_field").click(function() { 

        var intId = $(".more_fields").length + 1;
        var fieldWrapper = $("<p class=\"fieldwrapper\" style=\"margin-left:40px;\" id=\"field" + intId + "\"/>");
        var fName = $("<input type=\"text\" name=\"multi[]\" value=\"\" style=\"width:80%; display:inline;\" />");
        var removeButton = $("<strong style=\"display:inline-block; width:26px; text-align:right; margin-right:10px;\"><a class=\"remove_field\" style=\"text-decoration:none; font-size:1.3em; color:#666; font-style:normal; cursor:pointer\"> -</a></strong>");
        removeButton.click(function() {
            $(this).parent().remove();
        });
        fieldWrapper.append(removeButton);
        fieldWrapper.append(fName);
        $(".more_fields").append(fieldWrapper);
    });

});

</script>
<!-- /back end instructions-->

<?php }
	}
}


/*-----------------------------------------------------------------------------
	 					Display the instructions
	 					we're having an issue with the dhasboard and anything with a query string
-----------------------------------------------------------------------------*/

/** 
 * Test function to compare the instruction against the current page location
 */

function bei_array_find( $needle, $haystack ) {	
	// check to see if the queried page we're on has a "?"
	if( strstr( $needle, '?' ) !== false ) {
		// if it does, get the part before the "?"
		$test = explode('?', $needle);
		$test = $test[0]; 		 													
	} else {
		// if it doesn't, just use the current page
		$test = $needle;
	}				 													

	if($haystack) {
		if(is_array($haystack)) {
			foreach ( $haystack as $key=>$item ) {
				if( ( $item == $test ) || ( $item == $needle ) ) return true; 
			}
		} else {
			if( ( $haystack == $test ) || ( $haystack == $needle ) ) return true;
		}
	}

	return false; 
}


/**
 * Function to be used if a custom tab is required (set in the options page)
 */

function bei_custom_instruction_tab() {

	// cget user preference for color scheme
	$user_ID = get_current_user_id();
	// classic (blue) or fresh (gray)
	$color_pref = get_user_meta($user_ID, 'admin_color', true);
	
	if($color_pref 		== 'fresh') 	$output = '<link rel="stylesheet" href="' . BEI_DIR_URL . 'css/bei_colors-fresh.css" type="text/css" media="all" />';
	elseif($color_pref 	== 'classic')	$output = '<link rel="stylesheet" href="' . BEI_DIR_URL . 'css/bei_colors-classic.css" type="text/css" media="all" />';

	$custom_tab_info = bei_add_instructions_button( 'content' );

	if($custom_tab_info) {
		$tab_output 	= '';
		$panel_output 	= '';
		foreach($custom_tab_info as $id => $bei_value) { 
			$tab_output 	.= '<li id="tab-' . $id . '" class="bei-tab-nav"><a href="#tab-' . $id . '" aria-controls="tab-' . $id . '">' . $bei_value[0] . '</a></li>';
			$panel_output 	.= '<div id="panel-' . $id . '" class="bei-tab-content">' . $bei_value[1] . '</div>';
		}

		$output .= "\n" . '<script type="text/javascript">
					jQuery(document).ready(function($) {
						$(\'#screen-meta-links div:first-child\').before(\'<div id="contextual-bei-link-wrap" class="hide-if-no-js screen-meta-toggle"><a href="#contextual-bei-wrap" id="contextual-bei-link" class="show-settings" aria-controls="contextual-bei-wrap" aria-expanded="false">Instructions</a></div>\'); 
						$(\'#contextual-help-wrap\').before(\'<div id="contextual-bei-wrap" class="hidden no-sidebar" tabindex="-1" aria-label="Contextual Instructions Tab"><div id="contextual-bei-back"></div><div id="contextual-bei-columns"><div class="contextual-bei-tabs"><ul>' . $tab_output . '</ul></div><div class="contextual-bei-tabs-wrap">' . $panel_output . '</div></div></div></div>\');	
						$(\'.contextual-bei-tabs li:first-child\').addClass(\'active\');
						$(\'.contextual-bei-tabs-wrap div:first-child\').addClass(\'active\');

						$(\'li.bei-tab-nav\').click(function(e){
							e.preventDefault();
							$(this).addClass(\'active\').siblings().removeClass(\'active\');

							var text 	= $(this).get(0).id;
							var newtext = text.replace(\'tab-bei-tab\', \'panel-bei-tab\');

							$(\'#\'+newtext).addClass(\'active\').siblings().removeClass(\'active\');
							/* stop YouTube videos on tab switch */
							$(\'iframe[src*="http://www.youtube.com/embed/"]\').each(function(i) {
      							this.contentWindow.postMessage(\'{"event":"command","func":"pauseVideo","args":""}\', \'*\');
    						});
						});

					});
				   </script>' . "\n";

		//jQuery, apparently, doesn't like whitespace!
		echo str_replace(array("\r", "\n"), '', $output);
	}
}


/**
 * The meat of the whole thing: get the intructions and show them where needed
 */

function bei_add_instructions_button($items = '') {
	global $wpdb, $current_user, $user_level, $post, $pagenow, $bei_options
;
	get_currentuserinfo();	

	// if a custom instruction tab is needed, this is the array setup
	$custom_tab_info = array();
	
	// obtain current screen info 	
	$screen 		= get_current_screen();
	$this_screen 	= $screen->base;										
	$address 		= BEI_CUR_QUERY;	
	
	// we must use a custom select query, and NOT us "setup_postdata", because the $post variable conflicts with other plugins
    $sql = "SELECT $wpdb->posts.* FROM $wpdb->posts WHERE $wpdb->posts.post_type = 'instructions' AND $wpdb->posts.post_status = 'publish'";
    $instructions = $wpdb->get_results($sql, OBJECT);
    if( $instructions ) :
    	foreach( $instructions as $ins ) : 
			// instruction page info
			$post_id = $ins->ID;
			$bei_pages = get_post_meta( $post_id, '_bei_instructions', false );
			if($bei_pages) {
				$bei_page = $bei_pages[0]['page_id'];
				$bei_multi = $bei_pages[0]['multi'];


				// combine the two fields into one array
				if( !empty( $bei_multi ) ) {
					// push the individual page into the multi array
					$bei_multi[] = $bei_page;
					// remove any empty elements
					$bei_page = array_filter($bei_multi);
				} 

				// level that can see this instruction
				$bei_level = $bei_pages[0]['user_level'];

				// video url
				$bei_video = $bei_pages[0]['video_url'];
				$bei_vid_id = 'player-' . $post_id;

				// user level info - includes version 1 fixes
				if( $bei_level == 'administrator' 	|| $bei_level == 'Administrator' 	) 	$bei_level = 'activate_plugins';	
				if( $bei_level == 'editor' 			|| $bei_level == 'Editor' 			) 	$bei_level = 'edit_others_posts';				
				if( $bei_level == 'author' 			|| $bei_level == 'Author' 			) 	$bei_level = 'delete_published_posts';			
				if( $bei_level == 'contributor' 	|| $bei_level == 'Contributor' 		) 	$bei_level = 'delete_posts';
				if( $bei_level == 'subscriber' 		|| $bei_level == 'Subscriber' 		) 	$bei_level = 'read';

				// make the "dashboard" universal
				if( $address == 'index.php' || $address == 'wp-admin') $address = 'dashboard';

				$find = bei_array_find( $address, $bei_page ); 

				// if the current page is not part of the array of instructions, skip it
				if( $find == FALSE ) continue; 

				if(current_user_can($bei_level)) :
					$post_info 	= get_post( $post_id );
					$id 		= 'bei-tab-' . $post_id;
					$title 		= $post_info->post_title;
					$content 	= wpautop( $post_info->post_content );
					// use {{}} for shortcodes instead of [] - brackets break the jQuery
					$content 	= preg_replace_callback( "/(\{\{)(.*)(\}\})/", create_function( '$matches', 'return "[" . $matches[2] . "]";' ), $content );
					$excerpt 	= '<p>'. $post_info->post_excerpt . '</p>';			

					$output = '';
					if( !empty( $bei_video ) ) {
						// youtube
						if(strpos( $bei_video, 'youtube.com' ) !== false ) {
	            			$fixvideo = str_replace( 'watch?v=', 'embed/', $bei_video ); 
	            			$output .= '<iframe id="' . $post_id . '" name="' . $post_id . '" style="display:block; margin: 15px auto;" width="480" height="360" src="' . $fixvideo . '?rel=0" 	frameborder="0" allowfullscreen></iframe><br />' . "\n";
         				// vimeo
	          			} elseif( strpos( $bei_video, 'vimeo.com' ) !== false ) { 						
	            			$fixvideo = explode( '/',$bei_video );								
	            			$vidid = end( $fixvideo );											
	            			$output .= '<iframe style="display:block; margin: 15px auto;" width="480" height="360" src="http://player.vimeo.com/video/' . $vidid . '" width="640" height="366" frameborder="0"></iframe>' . "\n";
	            		// plain .swf file	
	          			} elseif( strpos( $bei_video, '.swf' ) !== false ) {							
	          				$output .= '<object data="' . $bei_video . '" width="480" height="360" style="display:block; margin:15px auto;">' . "\n";
    						$output .= '<embed src="' . $bei_video . '" width="480" height="360">' . "\n";
    						$output .= '</embed>' . "\n";
  							$output .= '</object>' . "\n\n";
  						// HTML5
	          			} else {						
	          				$ogg = strstr( $bei_video, '.iphone.mp4' ); 
	        				if( $ogg !== FALSE ) $ogg = str_replace( '.iphone.mp4', '.ogv', $bei_video );
	        				else $ogg = str_replace( '.mp4', '.ogv', $bei_video );					     			
	        				
	        				$output .= '<video class="html5-video" style="display:inline-block; margin: 15px auto;" width="480" height="360" controls>' . "\n";
							$output .= '<source src="' . $bei_video . '"  type="video/mp4" />' . "\n";
							
							if( $ogg ) $output .= '<source src="' . $ogg . '"  type="video/ogg" />' . "\n";
							
							$output .= '<object type="application/x-shockwave-flash" data="' . BEI_DIR_URL . 'player.swf">' . "\n";
							$output .= '<param name="movie" value="' . BEI_DIR_URL . 'player.swf" />' . "\n";
							$output .= '<param name="flashvars" value="autostart=false&amp;controlbar=over&amp;file=' . $bei_video . '" />' . "\n";
							$output .= '</object>' . "\n";
							$output .= '</video>' . "\n";
							$output .= '<p class="small">' . sprintf(__('If you have an issue viewing this video, please contact <a href="mailto:%1$s">%1$s</a>.', 'bei_languages'), antispambot(get_option("admin_email"))) . '</p>' . "\n";
	          			}
					}

					if( $items == '' && $bei_options
['custom_tab'] == 'no' )	{	
						// Now show them :)		
						$screen->add_help_tab( array('id' 		=> $id,
	   												 'title' 	=> $title, 
	   												 'content' 	=> $output . $content . $excerpt
	   											   ) 
											 );
					} else {
						// we need to clean the tab content so it'll display properly
						// because - especially on the Media/uploads page, the content of 
						// the first item is pulled in without this
						$content 				= preg_replace('%<p\s+class="attachment">.*?</p>%s', '', $content);
						$custom_content 		= $output . $content . $excerpt;
						$custom_tab_info[$id]	= array( $title, $custom_content );
					}

				endif;
			}
		endforeach;

		// custom tab call with all the info we need for external output
		return $custom_tab_info;

	endif;
}


/*-----------------------------------------------------------------------------
				Functions for later use in theme files
-----------------------------------------------------------------------------*/

/**
 * makes a fake list of capabilities, since people who aren't logged in won't have any
 */

function bei_caps() {
	global $bei_options
; 
	$view = $bei_options
['view'];
	$caps = array();
	if( $view == 'manage_networks' ) 		$caps[] = array('manage_networks', 'activate_plugins', 'edit_others_posts', 'delete_published_posts', 'delete_posts', 'read');
	if( $view == 'activate_plugins' ) 		$caps[] = array('activate_plugins', 'edit_others_posts', 'delete_published_posts', 'delete_posts', 'read');
	if( $view == 'edit_others_posts' ) 		$caps[] = array('edit_others_posts', 'delete_published_posts', 'delete_posts', 'read');
	if( $view == 'delete_published_posts' ) $caps[] = array('delete_published_posts', 'delete_posts', 'read');
	if( $view == 'delete_posts' )			$caps[] = array('delete_posts', 'read');
	if( $view == 'read' ) 					$caps[] = array('read');
	
	// returns the array of capabilities from the settings page ("Default Viewing Level")
	return $caps[0];
}
 

/**
 * gets the allowed ID's of the instructions post type to add to the front-end search query
 */

function bei_search_query_filter( $query ) {	
	global $wpdb, $post, $bei_options
, $current_user;
	// default user level for non-logged-in users 
	$view 	= $bei_options
['view'];
	// show in front?
	$public = $bei_options
['public'];
	// logged-in users only?
	$reg 	= $bei_options
['registered']; 
	// fake capabilities (user is not logged in)
	if( !is_user_logged_in() ) $caps = bei_caps();
	// actual capabilities (user is logged in)
	else $caps = $current_user->allcaps;
	// login url 								 
	$login 	= get_option( 'home' ) . '/wp-login.php';

	if( !is_admin() && is_search() && $query->is_main_query() ) :

		// first check if the instructions are public
		// if not, then skip everything else
		if( $public != 'yes' ) return;

		if( $public == 'yes' ) { 
			// they're public, but you're required to be logged in
			if( $reg == 'yes' && !is_user_logged_in() ) return;
			// if public, and required to be logged in, and they are logged in
			// or, if public, and user not required to be logged in
			if( ( $reg == 'yes' && is_user_logged_in() ) || $reg == 'no' ) {
				// set up the values
				$ids 	= array();
				$inner 	= array();
				// the query
				$where 	= "SELECT * FROM $wpdb->posts 
						   WHERE $wpdb->posts.ID IN 
						   (SELECT post_id FROM $wpdb->postmeta 
						   WHERE meta_key = '_bei_instructions' 
						   AND meta_value LIKE '%user_level%' 
						   AND (";

				// make an array of checks from the $caps
				foreach($caps as $key => $value) {
					$inner[] = $wpdb->prepare( "meta_value NOT LIKE %s", '%' . $value . '%' );
				}

				// finish the query
				$where 	.= implode(" AND ", $inner) . ") AND post_status = 'publish') ORDER BY post_date";
				$results = $wpdb->get_results($where);

				if($results)
					foreach($results as $result) $ids[] = $result->ID;

			}
		}

	// get currently used post types so we don't interfere with any other actions
	$post_types = $query->query_vars['post_type'];
	// add "instructions" to the post_typesin the current query
	$post_types['instructions'] = 'instructions';
	// now set the query with the alterations
	$query->set( 'post_type', $post_types );
	$query->set( 'post__not_in', $ids );

	return $query->query_vars;

	endif;
}


/**
 * strips the {{}} tags from the content on the front end, 
 * and replaces them with html character entities for the brackets
 * so the shortcode is seen, not parsed
 */

function bei_mess_with_shortcodes($content) {
	$content = preg_replace_callback( "/(\{\{)(.*)(\}\})/", create_function( '$matches', 'return "&#91;" . $matches[2] . "&#93;";' ), $content );
	return $content;
}


/*-----------------------------------------------------------------------------
							Administrative Stuff
-----------------------------------------------------------------------------*/

/**
 * Debug - show stuff on activation
 */

/*
add_action('activated_plugin','save_error');
function save_error(){
    update_option('plugin_error',  ob_get_contents());
}
echo get_option('plugin_error');
*/