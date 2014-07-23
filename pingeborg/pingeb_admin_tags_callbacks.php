<?php
/*
Copyright 2012 Bruno Hautzenberger

This file is part of Pingeborg.

Pingeborg is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published 
by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
Pingeborg is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of 
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
You should have received a copy of the GNU General Public License along with Pingeborg. If not, see http://www.gnu.org/licenses/.
*/ 

//Returns all pages as JSON Array
//Author: Bruno Hautzenberger
//Date: 09.2012
add_action('wp_ajax_pingeb_get_pages', 'pingeb_get_pages_callback');
function pingeb_get_pages_callback() {
	//Get all pages
	$pages = get_pages(); 
	$arrPage = array ();
	$i = 0;
	foreach ( $pages as $page ) {
		$arrPage[$i] = array(
		'id'=>$page->ID,
		'title'=>$page->post_title
		); 
		$i++;
	}

	echo json_encode($arrPage);
	die();
}

//Returns all url types as JSON Array
//Author: Bruno Hautzenberger
//Date: 09.2012
add_action('wp_ajax_pingeb_get_url_type', 'pingeb_get_url_type_callback');
function pingeb_get_url_type_callback() {
	global $wpdb; 

	$sql = "select id, name from " . $wpdb->prefix . "pingeb_url_type";
	$arrUrlTypes = array ();
	$i = 0;
	$urlTypes = $wpdb->get_results($sql);
	foreach ( $urlTypes as $urlType ) {
		$arrUrlTypes[$i] = array(
		'id'=>$urlType->id,
		'name'=>$urlType->name
		); 
		$i++;
	}

	echo json_encode($arrUrlTypes);
	die();
}

//Returns all Maps Marker LAyers as JSON Array
//Author: Bruno Hautzenberger
//Date: 09.2012
add_action('wp_ajax_pingeb_get_layers', 'pingeb_get_layers_callback');
function pingeb_get_layers_callback() {
	global $wpdb; 

	$sql = "select id, name from " . $wpdb->prefix . "leafletmapsmarker_layers order by name";
	$arr = array ();
	$i = 0;
	$layers = $wpdb->get_results($sql);
	foreach ( $layers as $layer ) {
		$arr[$i] = array(
		'id'=>$layer->id,
		'name'=>$layer->name
		); 
		$i++;
	}

	echo json_encode($arr);
	die();
}

//Returns all tags as JSON Array
//Author: Bruno Hautzenberger
//Date: 09.2012
add_action('wp_ajax_pingeb_get_tags', 'pingeb_get_tags_callback');
function pingeb_get_tags_callback() {
	global $wpdb; 

	//insert missing tags
	pingeb_insert_missing_tags();

	$layer_id = $_POST['layer_id'];
	$marker_name = $_POST['marker_name'];
	$page_id = $_POST['page_id'];

	$arr = array ();
	$i = 0;	

	$sql = "select mm.markername as marker_name, mm.lat as lat, mm.lon as lng, IFNULL(pt.id, -1) as id, mm.id as marker_id, ";
	$sql .= "IFNULL(pt.geofence_radius,20) as geofence_radius, IFNULL(pt.page_id,-1) as page_id, ml.name as layer_name, ml.id as layer_id, IFNULL(pt.custom_html_id, -1) as html_block ";
	$sql .= "from " . $wpdb->prefix . "leafletmapsmarker_markers mm ";
	$sql .= "inner join " . $wpdb->prefix . "leafletmapsmarker_layers ml on mm.layer = ml.id ";
	$sql .= "left outer join " . $wpdb->prefix . "pingeb_tag pt on mm.id = pt.marker_id ";
	$sql .= "where (ml.id = " . $wpdb->escape($layer_id) . " OR " . $wpdb->escape($layer_id) . " = -1) ";
	$sql .= "and (mm.markername like '" . $wpdb->escape($marker_name) . "%' OR '" . $wpdb->escape($marker_name) . "' = '-1') ";
	$sql .= "and (pt.page_id = " . $wpdb->escape($page_id) . " OR " . $wpdb->escape($page_id) . " = -1) ";
	$sql .= "order by marker_name";
	
	$tags = $wpdb->get_results($sql);
	foreach( $tags as $tag ) {
		$sql = "select u.id as id, u.url as url, ut.name as type, ut.id as typeid ";
		$sql .= "from " . $wpdb->prefix . "pingeb_url u, ";
		$sql .= $wpdb->prefix . "pingeb_url_type ut ";
		$sql .= "where u.tag_id = " . $tag->id . " and u.url_type_id = ut.id";
		
		$arrUrl = array ();
		$j = 0;
		$urls = $wpdb->get_results($sql);
		foreach( $urls as $url ) {
			$arrUrl[$j] = array(
			'id'=>$url->id,
			'url'=>$url->url,
			'type'=>$url->type,
			'typeid'=>$url->typeid
			); 
			$j++;
		}
		
		$arr[$i] = array(
		'id'=>$tag->id,
		'marker_id'=>$tag->marker_id,
		'name'=>$tag->marker_name,
		'lat'=>$tag->lat,
		'lng'=>$tag->lng,
		'geofence_radius'=>$tag->geofence_radius,
		'page_id'=>$tag->page_id,
		'urls'=>$arrUrl,
		'layer_name'=>$tag->layer_name,
		'layer_id'=>$tag->layer_id,
		'html_block_id'=>$tag->html_block
		); 
		$i++;
	}

	echo json_encode($arr);
	die();
}

//Saves a Tag
//Author: Bruno Hautzenberger
//Date: 09.2012
add_action('wp_ajax_pingeb_save_tag', 'pingeb_save_tag_callback');
function pingeb_save_tag_callback() {
	global $wpdb; 

	$geofence_radius = $_POST['geofence_radius'];
	$page = $_POST['page_id'];
	$html_block_id = $_POST['html_block_id'];
	$id = $_POST['tag_id'];

	$wpdb->update( 
			$wpdb->prefix . "pingeb_tag", 
			array( 
				'geofence_radius' => $geofence_radius, 
				'custom_html_id' => $html_block_id,
				'page_id' => $page 
			), 
			array( 'id' => $id ),
			array( 
				'%s', 
				'%d' 
			),
			array( '%d' )
		);

	echo "Saved tag!";
	die();
}

//Returns deletes an url
//Author: Bruno Hautzenberger
//Date: 09.2012
add_action('wp_ajax_pingeb_delete_url', 'pingeb_delete_url_callback');
function pingeb_delete_url_callback() {
	global $wpdb; 

	$id = $_POST['url_id'];

	$table = $wpdb->prefix . "pingeb_url";
	$wpdb->query( 
	$wpdb->prepare( 
			"
		     DELETE FROM $table
			 WHERE id = %d
			",
			$id 
		)
	);

	echo "Url deleted!";
	die();
}

//Inserts default values for Markers without Pingeborg data
//Author: Bruno Hautzenberger
//Date: 09.2012
function pingeb_insert_missing_tags() {
	global $wpdb; 
	
	//get markers without tags
	$sql = "select mm.id as id from " . $wpdb->prefix . "leafletmapsmarker_markers mm where not exists (Select * from " . $wpdb->prefix . "pingeb_tag pt where mm.id = pt.marker_id)";
	$missing_tag_ids = $wpdb->get_results($sql);
	
	//insert default data for these tags
	foreach( $missing_tag_ids as $missing_tag_id ) {
		$wpdb->insert( 
		$wpdb->prefix . "pingeb_tag", 
			array( 
				'geofence_radius' => 20, 
				'marker_id' => $missing_tag_id->id,
				'page_id' => -1
			), 
			array( 
				'%d', 
				'%d',
				'%d' 
			) 
		);
	}
}

//Inserts a new Url
//Author: Bruno Hautzenberger
//Date: 09.2012
add_action('wp_ajax_pingeb_insert_url', 'pingeb_insert_url_callback');
function pingeb_insert_url_callback() {
	global $wpdb; 

	$url_type = $_POST['url_type_id'];
	$url = $_POST['url'];
	$tag_id = $_POST['tag_id'];

	$wpdb->insert( 
	$wpdb->prefix . "pingeb_url", 
		array( 
			'tag_id' => $tag_id, 
			'url' => $url,
			'url_type_id' => $url_type
		), 
		array( 
			'%d', 
			'%s',
			'%d' 
		) 
	);
	
	echo $wpdb->insert_id;
	die();
}

//Send push
//Author: Bruno Hautzenberger
//Date: 06.2013
add_action('wp_ajax_pingeb_send_push', 'pingeb_send_push_callback');
function pingeb_send_push_callback() {
	$use_push = get_option('push_enabled');
	$push_url =  get_option('push_url');
	$push_key =  get_option('push_key');
     
	if($use_push == 1){ // push enabled
	   if ( !wp_is_post_revision( $post_id ) ) {
	      $post_title = get_the_title( $post_id );
	      $post_url = get_permalink( $post_id );
	      
	      $qry_str = "?pushKey=" . $push_key . "&content=TAGUPDATE";
	      
	      $ch = curl_init();
	      
	      curl_setopt($ch, CURLOPT_URL, $push_url . $qry_str); 
	      
	      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	      curl_setopt($ch, CURLOPT_TIMEOUT, '3');
	      $content = trim(curl_exec($ch));
	      curl_close($ch);
	   }
	}
	
	echo "DONE!";
	die();
}

?>
