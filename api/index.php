<?php 
/**
 * 
 * epidocConverter - Remote Server
 * 
 * @version 1.0
 * 
 * @year 2016
 * 
 * @author Philipp Franck
 * 
 * @desc
 * Use this file if you want to have the epidoc conversion on another machine than your script.
 * 
 * 
 * @tutorial
 * todo
 * 
 * 
 * 
 *
 */
/*

Copyright (C) 2015, 2016  Deutsches Archäologisches Institut

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

// settings
$allowedIps		= array();
$alowedQueriesPerMinute = 120;


// surpress errors (some warning may emerge vom meekro)
error_reporting(E_ALL);
ini_set('display_errors', 'on');
$warnings 		= array();

/**
 * go
 *
 */
try {

	// instanciate db for log and timeout (meekro)
	require_once 'inc/meekrodb.2.3.class.php';
	$mdb = new MeekroDB('localhost', 'epiapi', 'fALFeY9y03qk4ouDdC5M', 'epi_api_log');

	// enabling CORS (would be a shameful webservice without)
	if (isset($_SERVER['HTTP_ORIGIN'])) {
		header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
		header('Access-Control-Allow-Credentials: true');
		header('Access-Control-Max-Age: 86400');    // cache for 1 day
	}
	
	// Access-Control headers are received during OPTIONS request
	if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
		if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
			header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
		}
		if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
			header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
		}
		exit(0);
	}
	
	// low budget security check
	$ip	= $_SERVER['REMOTE_ADDR'];
	if (!in_array($ip, $allowedIps) and count($allowedIps)) {
		throw new Exception("Not allowed, Mr. $ip!");
	}
		
	// get query paramaters
	$post = array_merge($_GET, $_POST);
	$accepted_args = array(
			'epidoc' => 'string',
			'data' => 'string',
			'mode' => 'string',
			'returnAll' => 'bool',
			'dataUrl' => 'string',
			'returnSource' => 'bool',
			'stats' => 'bool'
	);
	foreach ($accepted_args as $arg => $type) {
		$$arg = (isset($post[$arg]) and $post[$arg]) ? $post[$arg] : null;
		settype($$arg, $type);
	};
	$epidoc = $data ? $data : $epidoc; // because backward compability
	if (isset($post['epidoc'])) {$post['epidoc'] = '<epidoc data>';}
	if (isset($post['data'])) {$post['data'] = '<epidoc data>';}
	$mode = empty($mode) ? 'saxon' : $mode;

 	// give timeout if to many requests
	if ($mdb->queryFirstField('SELECT count(*) FROM log where timestamp > date_sub(now(), interval 1 minute)') > $alowedQueriesPerMinute) {
		throw new Exception("Timeout! Too many calls.");
	}

	// get statistics if desired
	if ($stats) {
		$stats_query = "
		(select 'queries' as `name`,			count(*) as `val` from log)
			union
		(select 'queries_last_hour' as `name`,	count(*) from log where now() - timestamp <= '1 hour' )
			union
		(select 'queries_this_day' as `name`,	count(*) from log where extract(day from current_timestamp) = extract(day from timestamp))
			union
		(select 'queries_this_month' as `name`,	count(*) from log where extract(month from current_timestamp) = extract(month from timestamp))
			union
		(select 'last_query' as `name`,			epidoc as `val` from log order by timestamp desc limit 1)
			union
		(select 'last_query_date' as `name`, 	timestamp as `val` from log order by timestamp desc limit 1)
			union
		(select 'users' as `name`, 				count(*) from (select ip from log group by ip) as tmp)
		";
	
		$stats = [];
		foreach ($mdb->query($stats_query) as $stat) {
			$stats[$stat['name']] = $stat['val'];
		}
	
		header('Content-Type: application/json');
		echo json_encode(array(
			'success'		=> true,
			'statistics' 	=> $stats
		));
		die();
	}
	
 	// log
	$mdb->insert('log', array(
		'epidoc'	=>	$dataUrl? $dataUrl : '<epidoc data>',
		'renderer'	=>	$mode,
		'parameter'	=>	serialize($post),
		'ip'		=>	$ip
	));
	
 	// get data from url
	if (!$epidoc and $dataUrl) {

		$dataUrl = trim($dataUrl);
		
		// check if curl exists
		if (!function_exists('curl_init')) {
			throw new Exception('Curl Extension not installed');
		}
		
		// check size 
		/* (does not work in many cases, therefore disabled)
		$ch = curl_init($dataUrl);
		curl_setopt($ch, CURLOPT_NOBODY, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		
		$data = curl_exec($ch);
		curl_close($ch);
		
		if (!preg_match('/Content-Length: (\d+)/', $data, $matches)) {
			throw new Exception("File size could not be detected. ($dataUrl)");
		}
		
		$fileSize = (int) $matches[1];
		
		if ($fileSize >= 512000) {
			throw new Exception("Maximum size of 500kb exceeded; file is {$fileSize}kb!" . print_r($data,1));
		}
		*/
		
		// passed, get data
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $dataUrl);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HEADER, false);
		$epidoc = curl_exec($ch);
		curl_close($ch);
		
		
	}

	
	if (!$epidoc) {
		throw new Exception('Missing a "epidoc" or "dataUrl" paramater!');
	}
	
	// get converter 
	require_once(realpath(dirname(__FILE__) . '/../epidocConverter.class.php'));	
	$converter = epidocConverter::create($epidoc, $mode);
	
	//throw new Exception('yes');
	// get render options
	foreach ($converter->renderOptionset as $option => $optionData) {
		if (!empty($post[$option])) {
			if (!$converter->setRenderOption($option, $post[$option])) {
				$warnings[] = "'{$post[$option]}' is not known value for render option '$option'.";
			}
		}
	}

	// do the conversion thing
	$mode = str_replace('epidocConverter\\', '', get_class($converter));
	$res = $converter->convert($returnAll);
						

} catch (Exception $a) {
	header('Content-Type: application/json');
	echo json_encode(array(
		'success'	=> false,
		'message'	=> $a->getMessage(),
	));
	die();
}	



// return  success
unset($post['epidoc']);
unset($post['data']);

$return = array(
	'success'	=> true,
	'data'		=> $res,
	'mode'		=> $mode,
	'query'		=> $post
);

if (count($warnings)) {
	$return['warnings'] = '<ul><li>' . implode('</li><li>', $warnings) . '</li></ul>';
}

if ($returnSource) {
	$return['source'] = $epidoc;
}

if (!$returnAll) {
	$return['css'] = 'http://' . $_SERVER["SERVER_NAME"] . str_replace('API', '' , dirname($_SERVER["SCRIPT_NAME"])) . $converter->cssFile;
}

header('Content-Type: application/json');
echo json_encode($return);
?>