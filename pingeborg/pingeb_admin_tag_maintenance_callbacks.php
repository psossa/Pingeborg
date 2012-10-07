<?php
/*
* This work is licensed under the Creative Commons Attribution 3.0 Unported License. 
* To view a copy of this license, visit http://creativecommons.org/licenses/by/3.0/.
*/

//Returns all markers as JSON Array
//Author: Bruno Hautzenberger
//Date: 10.2012
add_action('wp_ajax_pingeb_get_get_markers', 'pingeb_get_markers_callback');
function pingeb_get_markers_callback() {
	global $wpdb; 

	$sql = "select id, markername from " . $wpdb->prefix . "leafletmapsmarker_markers";
	$arr = array ();
	$i = 0;
	$results = $wpdb->get_results($sql);
	foreach ( $results as $result ) {
		$arr[$i] = array(
		'id'=>$result->id,
		'markername'=>$result->markername
		); 
		$i++;
	}

	echo json_encode($arr);
	die();
}

//Returns all tags without downloads in the last 4 weeks
//Author: Bruno Hautzenberger
//Date: 10.2012
add_action('wp_ajax_pingeb_get_tag_without_downloads', 'pingeb_get_tag_without_downloads_callback');
function pingeb_get_tag_without_downloads_callback() {
	global $wpdb; 

	$sql = "select mm.id as id, mm.markername as name, IFNULL( DATE_FORMAT(max(stat.visit_time),'%M %d, %Y'), 'Never') last_download from " . $wpdb->prefix . "leafletmapsmarker_markers mm 
		left outer join " . $wpdb->prefix . "pingeb_statistik stat on stat.tag_id = mm.id
		where mm.id not in  (
		select ps.tag_id from " . $wpdb->prefix . "pingeb_statistik ps where ps.visit_time > DATE(NOW()) - INTERVAL 4 WEEK group by ps.tag_id
		) group by mm.id, mm.markername order by IFNULL(max(stat.visit_time), '0')";
		
	$arr = array ();
	$i = 0;
	$results = $wpdb->get_results($sql);
	foreach ( $results as $result ) {
		$arr[$i] = array(
		'id'=>$result->id,
		'markername'=>$result->name,
		'last_download'=>$result->last_download
		); 
		$i++;
	}

	echo json_encode($arr);
	die();
}

//Returns all tags without markers
//Author: Bruno Hautzenberger
//Date: 10.2012
add_action('wp_ajax_pingeb_get_tag_without_markers', 'pingeb_get_tag_without_markers_callback');
function pingeb_get_tag_without_markers_callback() {
	global $wpdb; 

	$sql = "select pt.id as id, pt.marker_id as marker_id, IFNULL(stat.count,0) as downloads from " . $wpdb->prefix . "pingeb_tag pt
		left outer join (select tag_id as id, count(*) as count from " . $wpdb->prefix . "pingeb_statistik group by tag_id) stat on stat.id = pt.marker_id
		where marker_id not in (
		select id from " . $wpdb->prefix . "leafletmapsmarker_markers
		)";
		
	$arr = array ();
	$i = 0;
	$results = $wpdb->get_results($sql);
	foreach ( $results as $result ) {
		$arr[$i] = array(
		'id'=>$result->id,
		'marker_id'=>$result->marker_id,
		'downloads'=>$result->downloads
		); 
		$i++;
	}
	
	echo json_encode($arr);
	die();
}

//Assigns all downloads of a "broken" tag to another marker and removes old tag and urls
//Author: Bruno Hautzenberger
//Date: 10.2012
add_action('wp_ajax_pingeb_merge_tag', 'pingeb_merge_tag_callback');
function pingeb_merge_tag_callback() {
	global $wpdb; 
	
	$id = $_POST['tag_id'];
	$new_marker_id = $_POST['new_marker_id'];
	
	$marker_id = $wpdb->get_var( $wpdb->prepare( "select marker_id from " . $wpdb->prefix . "pingeb_tag where id = " . $id ) );
	
	//update statistik
	$wpdb->update( 
			$wpdb->prefix . "pingeb_statistik", 
			array( 
				'tag_id' => $new_marker_id
			), 
			array( 'tag_id' => $marker_id ),
			array( 
				'%d'
			),
			array( '%d' )
		);
		
	//delete tag urls
	$table = $wpdb->prefix . "pingeb_url";
	$wpdb->query( 
	$wpdb->prepare( 
			"
		     DELETE FROM $table
			 WHERE tag_id = %d
			",
			$id 
		)
	);
		
	//delete old tag
	$table = $wpdb->prefix . "pingeb_tag";
	$wpdb->query( 
	$wpdb->prepare( 
			"
		     DELETE FROM $table
			 WHERE id = %d
			",
			$id 
		)
	);
	
	echo "DONE";
	die();
}

//Deletes a "broken" tag and its downloads and urls
//Author: Bruno Hautzenberger
//Date: 10.2012
add_action('wp_ajax_pingeb_delete_broken_tag', 'pingeb_delete_broken_tag');
function pingeb_delete_broken_tag() {
	global $wpdb; 
	
	$id = $_POST['tag_id'];	
	$marker_id = $wpdb->get_var( $wpdb->prepare( "select marker_id from " . $wpdb->prefix . "pingeb_tag where id = " . $id ) );
	
	//delete downloads
	$table = $wpdb->prefix . "pingeb_statistik";
	$wpdb->query( 
	$wpdb->prepare( 
			"
		     DELETE FROM $table
			 WHERE tag_id = %d
			",
			$marker_id 
		)
	);
	
	//delete tag urls
	$table = $wpdb->prefix . "pingeb_url";
	$wpdb->query( 
	$wpdb->prepare( 
			"
		     DELETE FROM $table
			 WHERE tag_id = %d
			",
			$id 
		)
	);
		
	//delete old tag
	$table = $wpdb->prefix . "pingeb_tag";
	$wpdb->query( 
	$wpdb->prepare( 
			"
		     DELETE FROM $table
			 WHERE id = %d
			",
			$id 
		)
	);
	
	echo "DONE";
	die();
}

?>
