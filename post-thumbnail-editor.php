<?php
/* Plugin name: Powerful Post Thumbnail Editor-Customized
   Plugin URI:
   Author: George 
   Author URI: 
   Version: 1.0.7
   Description: Individually manage your post thumbnails
 */

/* 
 * Useful constants  
 */
define( 'PTE_PLUGINURL', plugins_url(basename( dirname(__FILE__))) . "/");
define( 'PTE_PLUGINPATH', dirname(__FILE__) . "/");
define( 'PTE_DOMAIN', "post-thumbnail-editor");
define( 'PTE_VERSION', "1.0.7");

/*
 * Option Functionality
 */
function pte_get_option_name(){
	global $current_user;
	if ( ! isset( $current_user ) ){
		get_currentuserinfo();
	}
	return "pte-option-{$current_user->ID}";
}

function pte_get_user_options(){
	$pte_options = get_option( pte_get_option_name() );
	if ( !is_array( $pte_options ) ){
		$pte_options = array();
	}
	$defaults = array( 'pte_tb_width' => 750
		, 'pte_tb_height' => 550
		, 'pte_debug' => false
		, 'pte_thickbox' => true
	);
	return array_merge( $defaults, $pte_options );
}

function pte_get_site_options(){
	$pte_site_options = get_option( 'pte-site-options' );
	if ( !is_array( $pte_site_options ) ){
		$pte_site_options = array();
	}
	$defaults = array( 'pte_hidden_sizes' => array()
		, 'pte_jpeg_compression' => 90
  	);
	return array_merge( $defaults, $pte_site_options );
}

function pte_get_options(){
	global $pte_options, $current_user;
	if ( isset( $pte_options ) ){
		return $pte_options;
	}

	$pte_options = array_merge( pte_get_user_options(), pte_get_site_options() );

	return $pte_options;
}

/*
 * Put Hooks and immediate hook functions in this file
 */

/* Hook into the Edit Image page */
function pte_enable_thickbox(){
	$options = pte_get_options();

	if ( $options['pte_thickbox'] ){
		wp_enqueue_style( 'thickbox' );
		wp_enqueue_script( 'thickbox' );
	}
}

function pte_admin_media_scripts($post_type){
   //print("yessir:$post_type:\n");
	$options = pte_get_options();
	pte_enable_thickbox();

//	if ( $options['pte_debug'] ){
		wp_enqueue_script( 'pte'
			, PTE_PLUGINURL . 'js/pte.full.dev.js'
			, array('jquery')
			, PTE_VERSION
		);
/*	}
	else {
		wp_enqueue_script( 'pte'
			, PTE_PLUGINURL . 'js/pte.full.js'
			, array('jquery')
			, PTE_VERSION
		);
	}*/
	wp_localize_script('pte'
		, 'objectL10n'
		, array('PTE' => __('Post Thumbnail Editor', PTE_DOMAIN))
	);
   if ($post_type == "attachment") {
      //add_action("admin_footer","pte_enable_admin_js",100);
      add_action("admin_print_footer_scripts","pte_enable_admin_js",100);
   }
   else {
      add_action("admin_print_footer_scripts","pte_enable_media_js",100);
   }
}

function pte_enable_admin_js(){
	$options = json_encode( pte_get_options() );
	echo <<<EOT
		<script type="text/javascript">
			var options = {$options};
			jQuery( function(){ pte.admin(); } );
		</script>
EOT;
}

function pte_enable_media_js(){
	$options = json_encode( pte_get_options() );
	echo <<<EOT
		<script type="text/javascript">
			var options = {$options};
			jQuery( function(){ pte.media(); } );
		</script>
EOT;
}

// Base url/function.  All pte interactions go through here
function pte_ajax(){
   // Move all adjuntant functions to a separate file and include that here
   require_once(PTE_PLUGINPATH . 'php/functions.php');
	$logger = PteLogger::singleton();
	$logger->debug( "PARAMETERS: " . print_r( $_REQUEST, true ) );

   switch ($_GET['pte-action'])
   {
      case "test":
			pte_test();
			break;
      case "launch":
			pte_launch();
			break;
      case "resize-images":
			pte_resize_images();
			break;
      case "confirm-images":
			pte_confirm_images();
			break;
      case "delete-images":
			pte_delete_images();
			break;
   }
   die();
}

if(is_admin()){
    add_filter('attachment_fields_to_edit', 'my_plugin_action_button', 20, 2);
}

function my_plugin_action_button($form_fields, $post) {
    $edit_link = '<a  style="padding-left:10px;" class="pte_edit_thumbnail" title="Edit Thumbnails" href="/wp-admin/admin-ajax.php?action=pte_ajax&pte-action=launch&id='.$post->ID.'&TB_iframe=true&height=550&width=750">Edit Thumbnails</a>';
    
    $form_fields = array_merge(array(array('tr' => "\t\t<tr><td>$edit_link</td></tr>\n")),$form_fields); 
    return $form_fields;
}

function pte_media_row_actions($actions, $post, $detached){
	// Add capability check
	if ( !current_user_can( 'edit_post', $post->ID ) ){
		return $actions;
	}
	$options = pte_get_options();

	$thickbox = ( $options['pte_thickbox'] ) ? "class='thickbox'" : "";
	$pte_url = admin_url('admin-ajax.php') 
		. "?action=pte_ajax&pte-action=launch&id=" 
		. $post->ID
		. "&TB_iframe=true&height={$options['pte_tb_height']}&width={$options['pte_tb_width']}";

	$actions['pte'] = "<a ${thickbox} href='${pte_url}' title='"
		. __( 'Edit Thumbnails', PTE_DOMAIN )
		. "'>" . __( 'Thumbnails', PTE_DOMAIN ) . "</a>";
	return $actions;
}

// Anonymous function (which apparently some versions of PHP will whine about)
function pte_launch_options_page(){
   require_once( PTE_PLUGINPATH . 'php/options.php' ); pte_options_page();
}

function pte_admin_menu(){
	add_options_page( __('Post Thumbnail Editor', PTE_DOMAIN) . "-title",
		__('Post Thumbnail Editor', PTE_DOMAIN),
		'edit_posts', // Set the capability to null as every user can have different settings set
		'pte',
		'pte_launch_options_page'
	);
}

function pte_options(){
	require_once( PTE_PLUGINPATH . 'php/options.php' );
	pte_options_init();
}

/* This is the main admin media page */
/** For the "Edit Image" stuff **/
//add_action('edit_form_advanced', 'pte_admin_media_scripts');
add_action('dbx_post_advanced', 'pte_edit_form_hook_redirect');
/* Slight redirect so this isn't called on all versions of the media upload page */
function pte_edit_form_hook_redirect(){
   add_action('add_meta_boxes', 'pte_admin_media_scripts');
}

/* Adds the Thumbnail option to the media library list */
add_action('admin_print_styles-upload.php', 'pte_enable_thickbox');
add_filter('media_row_actions', 'pte_media_row_actions', 10, 3); // priority: 10, args: 3

/* For all purpose needs */
add_action('wp_ajax_pte_ajax', 'pte_ajax');

/* Add Settings Page */
add_action( 'admin_menu', 'pte_admin_menu' );
add_action( 'settings_page_pte', 'pte_options' );
add_action( 'load-options.php', 'pte_options' );
//add_action( 'admin_init', 'pte_options' );

/** End Settings Hooks **/

	load_plugin_textdomain( PTE_DOMAIN
		, false
		, basename( PTE_PLUGINPATH ) . DIRECTORY_SEPARATOR . "i18n" );

?>
