<?php
/*
This work is licensed under the Creative Commons Namensnennung-Nicht-kommerziell 3.0 Unported License. To view a copy of this license, visit http://creativecommons.org/licenses/by-nc/3.0/.
*/

function pingeb_api($url){
	//return "UrlParts: " . json_encode(pingeb_getUrlParts($url)) . " Params: " . json_encode(pingeb_getParams($url));
	
	$urlParts = pingeb_getUrlParts($url);
	$params = pingeb_getParams($url);
	
	//set return value
	$data = array();
	
	if(count($urlParts) == 2){
		if($urlParts[1] === "tags"){
			$data = pingeb_api_get_tags($params);
		} elseif($urlParts[1] === "downloads") {
			$data = pingeb_api_get_downloads($params);
		} else {
			pingeb_send_404();
		}
	} else { 
		pingeb_send_404();
	}
	
	return json_encode($data);
}

//gets all downloads
//accetps params layer, from (yyyy-mm-dd hh24:mi:ss), to (yyyy-mm-dd hh24:mi:ss), page (default = 1), pageSize (default = 100, max = 1000) 
//Author: Bruno Hautzenberger
//Date: 09.2012
function pingeb_api_get_downloads($params){
	global $wpdb; 
	
	//load params
	$from = "-1";
	$to = "-1";
	$page = 1;
	$pageSize = 100;
	
	foreach ( $params as $param ) {
			if($param[0] === "from"){
				$from = str_replace("%20", " ",$param[1]);
			} elseif($param[0] === "to") {
				$to = str_replace("%20", " ",$param[1]);
			} elseif($param[0] === "page") {
				$page = $param[1];
			} elseif($param[0] === "pageSize") {
				$pageSize = $param[1];
			}
	}

	//build statement
	$sql = "select DATE_FORMAT(stat.visit_time,'%Y-%m-%d %k:%i:%S') as time,
		ut.name as type, mm.markername tagname, mm.lat as lat, mm.lon as lon, 
		if(upper(visitor_os) like '%WINDOWS PHONE%', 'Windows Phone', 
		if(upper(visitor_os) like '%IPHONE%', 'iOS',
		if(upper(visitor_os) like '%IPAD%', 'iOS',
		if(upper(visitor_os) like '%ANDROID%', 'Android',
		if(upper(visitor_os) like '%SYMBIAN%', 'Symbian',
		if(upper(visitor_os) like '%BADA%', 'Bada',
		if(upper(visitor_os) like '%BLACKBERRY%', 'BlackBerry',
		'other'))))))) as os
		from " . $wpdb->prefix . "pingeb_statistik stat, " . $wpdb->prefix . "pingeb_url_type ut, " . $wpdb->prefix . "leafletmapsmarker_markers mm
		where ut.id = stat.url_type and mm.id = stat.tag_id ";
	
	if($from != '-1'){	
		$sql .= "and stat.visit_time >= '" . $wpdb->escape($from) . "' ";
	}
	
	if($to != '-1'){	
		$sql .= "and stat.visit_time <= '" . $wpdb->escape($to) . "' ";
	}
		
	$sql .= "order by stat.visit_time
		LIMIT " . ((($wpdb->escape($page) - 1) * $wpdb->escape($pageSize)) + 1) . "," . $wpdb->escape($pageSize);
	
	//select tags
	$arr = array ();
	$i = 0;
	$downloads = $wpdb->get_results($sql);
	foreach ( $downloads as $download ) {
		$arr[$i] = array(
		'time'=>$download->time,
		'type'=>$download->typ,
		'tagname'=>$download->tagname,
		'lat'=>$download->lat,
		'lon'=>$download->lon,
		'os'=>$download->os
		); 
		$i++;
	}
	
	return $arr;
}

//gets all tags
//accetps params layer, box (lat1,lon1,lat2,lon2), order (name,clicks,layer), orderAsc (true, false)
//Author: Bruno Hautzenberger
//Date: 09.2012
function pingeb_api_get_tags($params){
	global $wpdb; 
	
	//load params
	$layer = "-1";
	$box = "-1";
	$order = "name";
	$orderDirection = "ASC";
	
	foreach ( $params as $param ) {
			if($param[0] === "layer"){
				$layer = $param[1];
			} elseif($param[0] === "box") {
				$box = explode(",",$param[1]);
				if(count($box) != 4){
					$box = "-1";
				}
			} elseif($param[0] === "order") {
				if($param[1] === 'name' || $param[1] === 'clicks' || $param[1] === 'layer'){
					$order = $param[1];
				}
			} elseif($param[0] === "orderAsc") {
				if($param[1] === 'false'){
					$orderDirection = "DESC";
				}
			}
	}

	//build statement
	$sql = "select mm.markername as name, mm.lat as lat, mm.lon as lon, ml.name layer, IFNULL(stat.count,0) as clicks from " . $wpdb->prefix . "leafletmapsmarker_markers mm
		left outer join (select tag_id, count(*) as count from " . $wpdb->prefix . "pingeb_statistik group by tag_id) stat on mm.id = stat.tag_id
		join  " . $wpdb->prefix . "leafletmapsmarker_layers ml on ml.id = mm.layer where 1=1 ";
		
	if($box != "-1"){
		$sql .= "and mm.lat <= " . $wpdb->escape($box[0]) . " and mm.lon >= " . $wpdb->escape($box[1]) . " and mm.lat >= " . $wpdb->escape($box[2]) . " and mm.lon <= " . $wpdb->escape($box[3]) . " ";
	}
	
	if($layer != "-1"){
		$sql .= "and ml.name = '" . $wpdb->escape($layer) . "' ";
	}
	
	$sql .= "order by " . $wpdb->escape($order) . " " . $orderDirection; 
	
	//select tags
	$arr = array ();
	$i = 0;
	$tags = $wpdb->get_results($sql);
	foreach ( $tags as $tag ) {
		$arr[$i] = array(
		'name'=>$tag->name,
		'layer'=>$tag->layer,
		'clicks'=>$tag->clicks,
		'lat'=>$tag->lat,
		'lon'=>$tag->lon
		); 
		$i++;
	}

	return $arr;
}

//gets the query string url parts
//Author: Bruno Hautzenberger
//Date: 09.2012
function pingeb_getUrlParts($url){
	$urlparts = explode("?",$url);
	return explode("/",$urlparts[0]);
}

//gets the query string paramters
//Author: Bruno Hautzenberger
//Date: 09.2012
function pingeb_getParams($url){
	$rawparams = explode("?",$url);
	$rawparams = explode("&",$rawparams[1]);
	
	$params = array ();
	$i = 0;
	foreach ( $rawparams as $param ) {
		$params[$i] = explode("=",$param);
		$i++;
	}
	
	return $params;
}

//404!!!!
//Author: Bruno Hautzenberger
//Date: 09.2012
function pingeb_send_404(){
	//api call not found
	status_header(404);
	nocache_headers();
	include( get_404_template() );
	exit;
}

?>
