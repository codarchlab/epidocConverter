<?php
/**
 *
 * epidocConverter
 *
 * @version 1.1
 * 
 * @year 2016
 * 
 * @author Philipp Franck
 *
 * @desc
 * This is an abstract class, wich is used by both implementations. You can use it, if you want to select the best converter automatically.
 * 
 *
 * @tutorial
 *
 * try {
 * 	$conv = epidocConverter::create($xmlData);
 * } catch (Exception $e) {
 * 	echo $e->getMessage();
 * }
 * 
 * @see epidocConverterSaxon.class.php and epidocConverterFallback.class.php for more hints
 * 
*/
/*

Copyright (C) 2015  Deutsches Archäologisches Institut

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
abstract class epidocConverter {
		
	// position of xslt stylsheets & so on
	public $workingDir; // usually __DIR__
	public $cssFile;
	public $xslFile;
	public $dtdPath = 'tei-epidoc.dtd'; //can be set to anywhere, but default is working directory
	
	public $errors = array(); // tmp array for errors
	
	/**
	 * 
	 * use this function if you want to select converter automatically:
	 * 
	 * @tutorial 
	 * $conv = epidocConverter::create($xmlData);
	 * This selects the SaxonProcessor if possible and if not the internal XSLT 1.0 Processor.
	 * 
	 * @param string $epidoc
	 * @param $mode 'saxon' 'libxml' 'remote'
	 * @return epidocConverterFallback|epidocConverterSaxon
	 */
	function create($epidoc = false, $mode = false) {
		/**
		 * Takes the Epidoc-Data and passes to the available XSLT Processor.
		 *
		 */

		if (file_exists("epidocConverter.$mode.class.php")) {
			require_once("epidocConverter.$mode.class.php");
		} else {
			throw new \Exception("epidocConverter.$mode.class.php does not exist.");
		}
		
		try {
			$class = "\\epidocConverter\\$mode";
			return new $class($epidoc);
		} catch (Exception $e) {
			if ($mode != 'libxml') {
				return epidocConverter::create($epidoc, 'libxml');
			}
			throw $e;
		}
	}
	
	/**
	 *
	 * import Epidoc Data
	 *
	 * @param string | simpleXmlElement $data
	 */
	function set($data) {
		if ($data instanceof SimpleXMLElement) {
			$this->importStr($data->asXML());
		} else {
			$this->importStr((string) $data);
		}
	
		$this->raiseErrors();
	}
	
	/**
	 *
	 * import Epidoc in String fromat.
	 *
	 * @param unknown $str
	 */
	abstract function importStr($str);
	
	/**
	 * 
	 * before importing a String to epidoc, this makes a lot of tests and modifications with it if necessesary
	 * 
	 * @param unknown $str
	 * @throws Exception
	 */
	public function sanitizeStr($str) {		
		// make sure it is a string
		$str = (string) $str;
		
		// make sure it is not empty
		if (!$str) {
			throw new Exception('Empty XML String');
		}
		
		// correct dtd-path if necessary
		$this->dtdPath = ($this->dtdPath == 'tei-epidoc.dtd') ? $this->workingDir . '/' . $this->dtdPath : $this->dtdPath;
		$str = preg_replace('#(\<!DOCTYPE TEI.*?\>)#mi', '<!DOCTYPE TEI SYSTEM "' . $this->dtdPath . '">', $str);
		
		// correct TEI Version if necessary
		$str = str_ireplace(array('<TEI.2', '</TEI.2'), array('<TEI', '</TEI'), $str);
		
		// correct namespace if necessary and check if TEI Document
		$doc = new DOMDocument();
		$doc->loadXML($str, LIBXML_DTDLOAD);
		$tei = $doc->documentElement;
		if (strtoupper($tei->tagName) != 'TEI') {
			throw new Exception('no TEI (root Element is called >>' . $tei->tagName . '<<)');
		}
		$tei->setAttribute('xmlns', "http://www.tei-c.org/ns/1.0");
		$str = $doc->saveXML();
		
		//echo "<textarea style='width:100%'>$str</textarea><hr>";
		
		return $str;
	}
	
	/**
	 * Raise Exception if XML Erros collected
	 *
	 * @param string $return - should errors be returned or raised as exception? defaults to true
	 * @throws Exception
	 */
	abstract function raiseErrors($return = false);
	

	
	/**
	 *
	 * is everything oK?
	 *
	 * @throws Exception if not
	 * @return boolean
	 */
	abstract function status();
	

	/**
	 * just print the stylesheet
	 * @return string
	 */
	function getStylesheet() {
		return file_get_contents($this->workingDir . '/' . $this->cssFile);
	}
	
	/**
	 * converts the imported Data to String using the simple stylsheets
	 * @param $all - return full html page or just body; default: false
	 * @throws Exception
	 * @return String
	 */
	abstract function convert($all = false);
	
	
	/**
	 * private function to distinguish between output as fuill html or just bodypart
	 * @param $result
	 * @param boolean $all
	 */
	private function _returnResult($result, $all) {
		;
	}
}
?>