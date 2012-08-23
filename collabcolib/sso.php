<?php

	if (!defined('MOODLE_INTERNAL')) 
	{
		die('Direct access to this script is forbidden.');
	}
	
	function generateSSO($url, $username)
	{
		global $CFG;
		global $moodleBaseURL;
		
		$secret = $CFG->passwordsaltmain;
		
		$time = date("d-m-Y-H-i");
		
		$strToHash = $username . $time . $url . $secret;
		$hash = sha1($strToHash);
			
		$params = "?u=" . $username . "&t=" . $time . "&r=" . $url . "&h=" . $hash;
		
		return $moodleBaseURL . "/login/index.php" . urlencode($params);			
	}
?>