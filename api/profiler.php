<?php
class profiler {
	
	public $start = 0;
	
	public $log = array();
	
	function __construct() {
		$this->start = microtime(1);
		$this->log('Profiler started');
	}
	
	function log($msg = false) {
		if (!$msg) {
			$msg = 'Log Nr. ' . count($this->log);
		}
		$this->log[] = array(microtime(1) - $this->start, $msg);
	}
	
	function dump() {
		$echo = "<table>";
		foreach($this->log as $log) {
			$echo .= "<tr><td>{$log[0]}</td>><td>{$log[1]}</td></tr>";
		}
		$echo .= "</table>";
		return $echo;
	}
}
?>