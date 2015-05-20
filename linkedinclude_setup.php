<?php

function linkedinclude_install () {
	
	global $wpdb;
	$table_name = $wpdb->prefix . "linkedinclude_posts";
	
	$sql = "CREATE TABLE $table_name (
	li_id 		bigint(20) 	unsigned NOT NULL,
	li_author 	bigint(20) 	unsigned NOT NULL,
	li_title 	varchar(64) CHARACTER SET utf8 NOT NULL,
	li_image 	text 		CHARACTER SET utf8 ,
	li_date 	int(10) 	unsigned NOT NULL,
	li_views 	int(10) 	unsigned DEFAULT 0,
	li_likes 	int(10) 	unsigned DEFAULT 0,
	li_comments int(10) 	unsigned DEFAULT 0,
	li_href 	text 		CHARACTER SET utf8,
	li_content 	text 		CHARACTER SET utf8,
	li_display  tinyint(1)  NOT NULL DEFAULT 1,
	UNIQUE KEY li_id (li_id)
	) ENGINE=MyISAM DEFAULT CHARSET=latin1;";
	
	//echo "SQL: $sql";
	
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );

	return;
}

?>
