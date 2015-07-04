<?php
/*
Plugin Name: LinkedInclude
Plugin URI: http://wordpress.org/plugins/linkedinclude/
Description: Post Importer for LinkedIn
Version: 0.9.1
Author: era404
Author URI: http://www.era404.com
License: GPLv2 or later.
Copyright 2015 ERA404 Creative Group, Inc.

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as 
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/***********************************************************************************
*     Globals
***********************************************************************************/
define('LINKEDINCLUDE_URL', admin_url() . 'admin.php?page=linkedinclude');
define('LINKEDINCLUDE_TABLE', $wpdb->prefix . 'linkedinclude_posts');
/***********************************************************************************
 *     Setup Plugin > Create Table
***********************************************************************************/
require_once("linkedinclude_setup.php");
// this hook will cause our creation function to run when the plugin is activated
register_activation_hook( __FILE__, 'linkedinclude_install' );

/***********************************************************************************
*     Setup Admin Menus
***********************************************************************************/
add_action( 'admin_init', 'linkedinclude_admin_init' );
add_action( 'admin_menu', 'linkedinclude_admin_menu' );
 
function linkedinclude_admin_init() {
	/* Register our stylesheet. */
	wp_register_style( 'linkedinclude-styles', plugins_url('linkedinclude_admin.css', __FILE__) );
	/* and javascripts */
	wp_enqueue_script( 'linkedinclude-script', plugins_url('linkedinclude_admin.js', __FILE__), array('jquery'), 1.0 ); 	// jQuery will be included automatically
	wp_localize_script('linkedinclude-script', 'ajax_object', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) ) ); 	// setting ajaxurl
}
add_action( 'wp_ajax_showhide', 'linkedInArticleDisplay' ); 	//for loading image to refine
//add_action( 'wp_ajax_cropimage', 'linkedinclude_cropimage' ); 	//for refining crop
 
function linkedinclude_admin_menu() {
	/* Register our plugin page */
	$page = add_submenu_page(	'tools.php', 
								'LinkedInclude', 
								'LinkedInclude', 
								'manage_options', 
								'linkedinclude', 
								'linkedinclude_plugin_options');

	/* Using registered $page handle to hook stylesheet loading */
	add_action( 'admin_print_styles-' . $page, 'linkedinclude_admin_styles' );
	add_action( 'admin_print_scripts-'. $page, 'linkedinclude_admin_scripts' );
}
 
function linkedinclude_admin_styles() {	wp_enqueue_style( 'linkedinclude-styles' );  }
function linkedinclude_admin_scripts() {	wp_enqueue_script( 'linkedinclude-script' ); }
 
function linkedinclude_plugin_options() {
	global $wpdb;

	/* Output our admin page */
	echo "<div id='linkedinclude'><h1>LinkedInclude <span>(Beta)</span></h1>";

	//record author posts
	if(!empty($_POST) && isset($_POST['author'])){
		if(is_numeric($_POST['author'])){
			$authorid = (int) trim($_POST['author']);
			getLinkedInArticles($authorid);
		}
	}
	
	//display all posts, return newest author id
	$li_author = showLinkedInArticles();

	//display author form
	echo <<<FORM
	<form method='post' class='fetch'>
	LinkedIn Author ID: &nbsp;
		<input type='text' name='author' id='author' value='{$li_author}' autocomplete='Off' />
		<input type='submit' name='submit' value='fetch posts' />
	</form>
FORM;
	
	//display donations form
	echo <<<PAYPAL
	<div class="donate" style='display: none;'>
	<input type="hidden" name="cmd" value="_s-xclick">
	<input type="hidden" name="hosted_button_id" value="FPL96ZDKPHR72">
	<input type="image" src="https://www.paypalobjects.com/en_US/i/btn/x-click-but04.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!" align="left" class="donate">
		If <b>LinkedInclude (beta)</b> has made your life easier, and you wish to say thank you, a Secure PayPal link has been provided to the left. 
		See more <a href='http://profiles.wordpress.org/era404/' title='WordPress plugins by ERA404' target='_blank'>WordPress plugins by ERA404</a> or visit us online:
		<a href='http://www.era404.com' title='ERA404 Creative Group, Inc.' target='_blank'>www.era404.com</a>. Thanks for using linkedinclude.
	</div>
PAYPAL;
}

/**************************************************************************************************
*	Helper Functions
/**************************************************************************************************
*	GetLinkedInArticles - Loads the Author Post Page and Scrapes Articles
**************************************************************************************************/
function getLinkedInArticles($li_author){
	global $wpdb;
	$formats = array('%s','%d','%s','%s','%d','%d','%d','%d','%s','%s');
	$r = array(); require_once("simple_html_dom.php");
	
	//fetch & scrape linkedin author page
	$html = file_get_html("https://www.linkedin.com/today/author/{$li_author}");
	
	//echo "<textarea rows=20 cols=120>{$html}</textarea>";
	
	// Find all article blocks
	foreach($html->find('.card-list > li') as $article) {
		$item = array();
		if(@$article->find('.article-title', 0)->plaintext != ""){
			//basic
			$item['li_id'] 			= $li_id = @$article->{'data-li-article-id'};
			$item['li_author'] 		= $li_author;
			$exists = $wpdb->get_var( "SELECT count(li_id) FROM ".LINKEDINCLUDE_TABLE." ". 
      								  "WHERE li_author={$li_author} ". 
									  "AND li_id={$li_id}" );
			
			//only insert if it's new ((TODO: check update/date ))
			if($exists < 1){
				$item['li_title']   = @$article->find('.article-title', 0)->plaintext;
				$item['li_image']   = @$article->find('.article-image-photo', 0)->src;
				$item['li_date']    = @$article->find('.article-date', 0)->plaintext;
									  $item['li_date'] = (""!=$item['li_date']) ? strtotime($item['li_date']) : -1;
				//social
				$item['li_views']   = @$article->find('.social-view-count', 0)->plaintext;
				$item['li_likes']   = @$article->find('.social-gestures-likes', 0)->plaintext;
				$item['li_comments']= @$article->find('.social-gestures-comments', 0)->plaintext;
			  
				//content
				$item['li_href']   	= $src = @$article->find('.article-summary a', 0)->href;
				if(""!=trim($src)){
					try {
						$content = file_get_html($src);
						if(@$content->find('.article-body', 0)->plaintext != ""){
							$item['li_content'] = @$content->find('.article-body', 0)->plaintext;
						}
					} catch (Exception $e) {
						$item['content'] = $e->getMessage();
					}
				}
				
				if($wpdb->insert(LINKEDINCLUDE_TABLE, $item, $formats)){	
					   $r['a'][] = $item['li_title']; } //add success
				else { $r['f'][] = $item['li_title']; } //add fail
			}

		}
	}
	displayLinkedIncludeResults($r);
	return;
}
/**************************************************************************************************
*	Show the Results of the Scrape
**************************************************************************************************/
function displayLinkedIncludeResults($r){
	if(empty($r)){ echo "<div class='msg'>No posts have been added or updated.</div>"; }
	else {
		$results = "<div class='msg'>";
		if(isset($r['a']) && !empty($r['a'])){
			$results .= "<strong>".count($r['a'])." LinkedIn posts were found and recorded:</strong> <em>";
			$results .= implode(", ", $r['a']). "</em></div>";
		}
		if(isset($r['f']) && !empty($r['f'])){
			$results .= "<div class='msg err'><strong>".count($r['f'])." LinkedIn posts were found but could not be recorded:</strong> <em>";
			$results .= implode(", ", $r['f']). "</em></div>";
		}
		echo $results;
	}
	return;
}
/**************************************************************************************************
*	Front-End: Organize the Articles into Blocks and Display
**************************************************************************************************/
function linkedinclude_frontend_styles() { 
	wp_enqueue_style( 'linkedinclude', plugins_url('linkedinclude.css', __FILE__) );
}
add_action( 'wp_enqueue_scripts', 'linkedinclude_frontend_styles' );
function showLinkedInArticles(){
	global $wpdb; $li_author = false;
	echo "<section>";
	//returns the newest author id
	$li_posts = $wpdb->get_results("SELECT * FROM ".LINKEDINCLUDE_TABLE." ORDER BY li_date DESC");
	if(!empty($li_posts)){
		foreach ( $li_posts as $li_post ) {
		$p = (array) $li_post;
		if(!$li_author) $li_author = $p['li_author'];
		echo "<article class='item_{$p['li_id']} ".($p['li_display'] > 0 ? "":"unchecked")."'>".
			 "<aside><span>&#9654;</span> {$p['li_views']}
			 	    <span>&hearts;</span> {$p['li_likes']} 
			 	    <span>&#9733;</span> {$p['li_comments']}
			 	  </aside>
			 <h1><input type='checkbox' data-liid='{$p['li_id']}' class='li_display' value='1' ".($p['li_display'] > 0 ? "checked='checked'":"")." title='Show/Hide' /><a href='{$p['li_href']}' title='{$p['li_title']}' target='_blank'>{$p['li_title']}</a></h1>".
			 "<div><img src='{$p['li_image']}' alt='{$p['li_title']}' height='80' />"."<strong>".date("l, F jS, Y",$p['li_date'])." &ndash; </strong>".
			 substr($p['li_content'], 0, @strpos($p['li_content'], ' ', 500)).
			 "...</div></article>";
		}
	} else { echo "<article>No LinkedIn posts have been imported yet. Enter a valid Author ID and click FETCH POSTS.</article>"; }
	echo "</section>";
	return($li_author);
}
/**************************************************************************************************
 *	Ajax Functions
**************************************************************************************************/
function linkedInArticleDisplay(){
	global $wpdb;
	$li_display = (in_array($_POST['lish'], array("true", "false"))  ? (int) ($_POST['lish']=="true"?1:-1) : false);
	$li_id = (is_numeric($_POST['liid']) ? trim($_POST['liid']) : false);
	if(!$li_id || !$li_display) die("-1");	
	$wpdb->query("UPDATE ".LINKEDINCLUDE_TABLE." SET li_display={$li_display} WHERE li_id={$li_id}");
	die("0");
}

/**************************************************************************************************
*	Widget Functions
/**************************************************************************************************
 *	Create the Widget
**************************************************************************************************/
class linkedinclude_widget extends WP_Widget {
	function __construct() {
		parent::__construct(
			'linkedinclude_widget',
			'LinkedInclude Widget',
			array( 'description' => 'Display the Articles from a LinkedIn Posts Feed', )
		);
	}
	public function widget( $args, $instance ) {
		//widget title
		$title = apply_filters( 'widget_title', $instance['title'] );
		echo $args['before_widget'] . (!empty($title) ? $args['before_title'] . $title . $args['after_title'] : "");
		
		//get posts from database
		global $wpdb;
		$where = " WHERE " . (is_numeric($instance['author']) ? " li_author={$instance['author']} AND " : "")." li_display>0 ";
		$length = (is_numeric($instance['length']) ? (int) $instance['length'] : 200);
		$li_posts = $wpdb->get_results("SELECT * FROM ".LINKEDINCLUDE_TABLE." $where ORDER BY li_date DESC LIMIT {$instance['postcount']}");


		
		//iterate
		if(!empty($li_posts)) {
			$author = false;
			echo "<ul>";
			foreach ( $li_posts as $li_post ) {
				$p = (array) $li_post;
				if(!$author && is_numeric($p['li_author'])) $author = $p['li_author'];
				echo "<li><a href='{$p['li_href']}' title='{$p['li_title']}' target='_blank'>
				<img class='scale-with-grid align-left wp-post-image' 
					alt='{$p['li_title']}' 
					src='{$p['li_image']}'>
				</a>
				<h5><a href='{$p['li_href']}' title='{$p['li_title']}' target='_blank'>{$p['li_title']}</a></h5>
				<p>".date("M j, Y",$p['li_date'])." &ndash; ".
					substr($p['li_content'], 0, @strpos($p['li_content'], ' ', $length)).
					"... <a href='{$p['li_href']}' class='limore' target='_blank' title='{$p['li_title']}'>Read More &raquo;</a></p>";
			}
			if($author) echo "<li><p><a href='https://www.linkedin.com/today/author/{$author}' title='All posts on LinkedIn' target='_blank' class='limore'>View All LinkedIn Posts &raquo;</a></p></li>";
			echo "</ul>";
		}
		//finish
		echo $args['after_widget'];
	}

	// Widget Backend
	public function form( $instance ) {
		global $wpdb;
		
		$title = 		( isset( $instance[ 'title' ] ) ?  $instance[ 'title' ] : "");
		$author = 		( isset( $instance[ 'author' ] ) ?  $instance[ 'author' ] : "");
		$postcount = 	( isset( $instance[ 'postcount' ] ) ?  $instance[ 'postcount' ] : 5);
		$length = 		( isset( $instance[ 'length' ] ) ?  $instance[ 'length' ] : 200);
		
		//get authors for selector
		$authors = $wpdb->get_results("SELECT DISTINCT li_author FROM ".LINKEDINCLUDE_TABLE." ORDER BY li_author ASC");
		
		//form options
		echo "<p><label for='".$this->get_field_id( 'title' )."'>Title</label>
				<input class='widefat' 
				 id='".$this->get_field_id( 'title' )."' 
				 name='".$this->get_field_name( 'title' )."' type='text' 
				 value='".esc_attr( $title )."' /></p>";
		echo "<p><label for='".$this->get_field_id( 'author' )."'>Author ID</label>
				<select class='widefat'
				 id='".$this->get_field_id( 'author' )."'
				 name='".$this->get_field_name( 'author' )."'>
				 		<option value='all'>All Authors</option>";
				foreach($authors as $authorid) { 
					echo "<option value='{$authorid->li_author}' ".
						(esc_attr($author)==$authorid->li_author?"selected='selected'":"")
						.">{$authorid->li_author}</option>";
				}
				 echo "</select></p>";
		echo "<p><label for='".$this->get_field_id( 'postcount' )."'>Posts to Show</label>
				<input class='widefat'
				 id='".$this->get_field_id( 'postcount' )."'
				 name='".$this->get_field_name( 'postcount' )."' type='text'
				 value='".esc_attr( $postcount )."' /></p>";
		echo "<p><label for='".$this->get_field_id( 'length' )."'>Excerpt Length</label>
				<input class='widefat'
				 id='".$this->get_field_id( 'length' )."'
				 name='".$this->get_field_name( 'length' )."' type='text'
				 value='".esc_attr( $length )."' /></p>";
}
	public function update( $new_instance, $old_instance ) {
		$instance = array();
		$instance['title'] = 	( ! empty( $new_instance['title'])  	? strip_tags( $new_instance['title'] ) : '');
		$instance['author'] = 	( ! empty( $new_instance['author']) 	? strip_tags( $new_instance['author'] ) : '');
		$instance['postcount']= ( ! empty( $new_instance['postcount']) 	? strip_tags( $new_instance['postcount'] ) : '');
		$instance['length']= 	( ! empty( $new_instance['length']) 	? strip_tags( $new_instance['length'] ) : '');
		return $instance;
	}
	
} // Class wpb_widget ends here


// Register and load the widget
function linkedinclude_widget_load_widget() {
	register_widget( 'linkedinclude_widget' );
}
add_action( 'widgets_init', 'linkedinclude_widget_load_widget' );

?>