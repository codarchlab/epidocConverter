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
ini_set('display_errors', 'off');
$warnings 		= array();

/**
 * go
 *
 */
try {

	// instanciate db for log and timeout (meekro)
	require_once 'inc/meekrodb.2.3.class.php';
	$mdb = new MeekroDB('localhost', 'mysqluser', 'pEaS!S%8@Z#%+3GM', 'epidoc_api_log');

	// low budget security check
	$ip			= $_SERVER['REMOTE_ADDR'];
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
			'returnSource' => 'bool'
	);
	foreach ($accepted_args as $arg => $type) {
		$$arg = (isset($post[$arg]) and $post[$arg]) ? $post[$arg] : null;
		settype($$arg, $type);
	};
	$epidoc = $data ? $data : $epidoc; // because backward compability
	if (isset($post['epidoc'])) {$post['epidoc'] = '<epidoc data>';}
	if (isset($post['data'])) {$post['data'] = '<epidoc data>';}
		
	// give timeout if to many requests
	if ($mdb->queryFirstField('SELECT count(*) FROM log where timestamp > date_sub(now(), interval 1 minute)') > $alowedQueriesPerMinute) {
		throw new Exception("Timeout! Too many calls.");
	}
	
	// log
	$mdb->insert('log', array(
		'epidoc'	=>	$dataUrl? $dataUrl : '<epidoc data>',
		'renderer'	=>	$mode,
		'parameter'	=>	serialize($post),
		'ip'		=>	$_SERVER['SERVER_ADDR']
	));
	
	// get data from url
	if (!$epidoc and $dataUrl) {
		
		$dataUrl = trim($dataUrl);
		
		// check size
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
		
		// passed, get data
		$epidoc = file_get_contents($dataUrl);
	}

	
	if (!$epidoc) {
		throw new Exception('Missing a "epidoc" or "dataUrl" paramater!');
	}
	
	// get converter 
	require_once('../epidocConverter.class.php');	
	$converter = epidocConverter::create($epidoc, $mode);
	
	// get render options
	foreach ($converter->renderOptionset as $option => $optionData) {
		if (!empty($post[$option])) {
			if (!$converter->setRenderOption($option, $post[$option])) {
				$warnings[] = "'{$post[$option]}' is not known value for render option '$option'.";
			}
		}
	}
	/*ob_start();	var_dump($converter->renderOptions);	$result = ob_get_clean();	throw new Exception('die! ' . $result)	;*/
	// do the conversion thing
	$mode = str_replace('epidocConverter\\', '', get_class($converter));
	$res = $converter->convert($returnAll);
						

} catch (Exception $a) {
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
	'query'		=> $post,
	'debug'		=> print_r($converter->renderOptions,1)
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

echo json_encode($return);
?>