<?php

//Show Server Metrics?
//Default: true
//When set to true the script will include the total execution time of the script in the return message
$showMetrics = true;

//Generate Single Sign On URLs?
//Default: true
//When set to true SSO URLs will be generated for all links to Moodle
$singleSignOnURLs = true;

//Return Unencrypted data?
//Default: false
//When set to true the script will include an unencrypted copy of the users data in the return message
$returnClearText = false;

//Return Cipher Text?
//Default: true
//When set to true the server will include a block of encrypted text representing the users data in the return message
$returnCipherText = true;

//Server Shared Secret
//Default: none
//This variable defines the secret used for encryption and decryption
$serverSecret = "m43ZXDLohXo75sJzXR1J";

//Maximum time drift
//Default: 90
//This setting specifies the maximum ammount of time that the clocks on the client and server can differ.
$maximumTimeDrift = 90;

//Override Moodle version detection.
//Default: none
//Setting this variable will override the Moodle version detection built into this script.
$moodleVersion = "";

//Override moodle table prefix
//Default: none
//Setting this variable will force the connector to use the given table prefix - for example "mdl_"
$moodleTablePrefix = "";

//Is debug mode turned on?
//Default: false
//If debug mode is enabled detailed error messages will be returned to the caller. This can pose a security risk.
$debug = true;

//The URL on which moodle is installed. For example https://moodle.domain.com.
//Default: none
//If this setting is overriden then the base path provided in config.php will be ignored.
$moodleBaseURL = "https://moodle.blackpool.ac.uk";

//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//DO NOT EDIT BELOW THIS LINE/////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	
	define("COLLABCO_MOODLE", "1.0.0");

	require_once("collabcolib/functions.php");
	require_once("collabcolib/crypto.php");
	

	$retDebug = false;
	$debugData = array();
	$debugData["var_showMetrics"] = $showMetrics;
	$debugData["var_singleSignOnURLs"] = $singleSignOnURLs;
	$debugData["var_returnClearText"] = $returnClearText;
	$debugData["var_returnCipherText"] = $returnCipherText;
	$debugData["var_serverSecret"] = $serverSecret;
	$debugData["var_maximumTimeDrift"] = $maximumTimeDrift;
	$debugData["var_moodleVersionOverride"] = $moodleVersion;
	$debugData["var_moodleTablePrefixOverride"] = $moodleTablePrefix;
	$encrypted = "";
	
	
	$statusCode = 200;
	$statusMessage = "OK";
	
	try
	{  	
//Setup System Parameters/////////////////////////////////////////////////////////////////////////////////////////////////////
	
		ini_set('display_errors', 'On');
		error_reporting(E_ALL | E_STRICT);		
		@date_default_timezone_set('UTC');	
	
//Start tracking execution time///////////////////////////////////////////////////////////////////////////////////////////////
	
		if ($showMetrics === true)
		{
			$mtime = microtime(); 
			$mtime = explode(" ",$mtime); 
			$mtime = $mtime[1] + $mtime[0]; 
			$starttime = $mtime;
		}		

//Setup Variables/////////////////////////////////////////////////////////////////////////////////////////////////////////////
		
		$username = "";
		$CFG = null;	
		$signature = null;
		$expectedSignature = null;

		$timestamp = $_SERVER['HTTP_HUB_TIMESTAMP'];
		
		$debugData["timestampRecieved"] = $timestamp;

		$output = "";
	
//Get Post Variables/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

		if (isset($_POST['sig']) && strlen($_POST['sig']) == 32) 
		{
			$signature = $_POST['sig'];
			$debugData["var_signature"] = $signature;
		} 
		else 
		{
			throw new exception("Signature parameter missing or invalid");
		}
		
		if (isset($_POST['un']))
		{
			$username =  $_POST['un'];
			$debugData["var_username"] = $username;
		} 
		else 
		{
			throw new exception("User parameter missing");
		}

//Setup Data Retrieval List///////////////////////////////////////////////////////////////////////////////////////////////////
	
		$getData = array();
		
		$getAllData = false;
		
		if (isset($_POST['data'])) 
		{
			$getData = explode(",", trim($_POST['data']));
			
			foreach($getData as $index => $value)
			{
				$getData[$index] = trim($value);
			}
			
			if (in_array("ALL", $getData, false))
			{
				$getAllData = true;
			}
		}
		
		$debugData["var_getAllData"] = $getAllData;
		$debugData["var_getData"] = implode(",", $getData);
	
//Security Validation/////////////////////////////////////////////////////////////////////////////////////////////////////////

		if (strtotime($timestamp) < strtotime("-" . $maximumTimeDrift . "  seconds") || strtotime($timestamp) > strtotime("+" . $maximumTimeDrift . "  seconds"))
		{
			throw new exception("Timestamp (".$timestamp.") invalid. Check time on server (".date("Y-m-d\TH:i:s\Z").") and client (".strtotime($timestamp).") are correct. " . strtotime("-" . $maximumTimeDrift . " seconds")  . "-" .  strtotime("+" . $maximumTimeDrift . "  seconds"));
		}
		
		if (strtolower($signature) != strtolower(md5($serverSecret . "==" . $username . "==" . $timestamp)))
		{
			throw new exception("Signature incorrect " . $signature . " != " . md5($serverSecret . "==" . $username . "==" . $timestamp));
		}

//Setup Connection////////////////////////////////////////////////////////////////////////////////////////////////////////////

		require_once("config.php");

		$connection = mysql_connect($CFG->dbhost, $CFG->dbuser, $CFG->dbpass);
	
		if (!$connection) 
		{
			throw new exception("Could not connect: " . mysql_error());		
		}

		$selected_db = mysql_select_db($CFG->dbname, $connection);

		if (!$selected_db) 
		{
			throw new exception("Could not select database: " . mysql_error());
		}
		
//Setup Base Moodle URL///////////////////////////////////////////////////////////////////////////////////////////////////////

		if(strlen($moodleBaseURL) == 0)
		{
			$moodleBaseURL = $CFG->wwwroot;
		}
		
//Set Moodle Table Prefix/////////////////////////////////////////////////////////////////////////////////////////////////////

		if ($moodleTablePrefix === "")
		{
			$moodleTablePrefix = $CFG->prefix;
		}
		
		$debugData["var_moodleTablePrefix"] = $moodleTablePrefix;
		
//Get Moodle Version//////////////////////////////////////////////////////////////////////////////////////////////////////////

		if ($moodleVersion == "")
		{
			require_once("version.php");		

			$moodleBuild = "";
			
			$moodleVersionString = substr($version, 0, 8);
			$moodleBuildString = substr($version, 8);
	
			switch ($moodleVersionString)
			{
				case "20071015":
					$moodleVersion = "1.9";
					break;
				case "20101124":
				case "20101225":
				case "20110221":
				case "20110330":
					$moodleVersion = "2.0";
					break;
				case "20110701":
					$moodleVersion = "2.1";
					break;
				case "20111205":
					$moodleVersion = "2.2";
					break;
				case "20120625":
				case "20120701":
					$moodleVersion = "2.3";
					break;
				default:
					$moodleVersion = "UNKNOWN";
					break;
				break;
			}
			
			if ($moodleVersion == "UNKNOWN")
			{
				throw new exception("This version of Moodle is unsupported (" . $version . ") Please contact support.");
			}	
		}
		
		$debugData["var_moodleVersion"] = $moodleVersion;

//Get User ID/////////////////////////////////////////////////////////////////////////////////////////////////////////////////
		
		switch($moodleVersion)
		{
			case "1.9":
			case "2.2":
			case "2.3":
				$userIDQuery = sprintf("SELECT id".
										" FROM " . $moodleTablePrefix . "user".
										" WHERE username = '%s'", 
										makeSafeForMYSQL($username)
										);
				break;
			default:
				throw new exception ("There is no User ID query for this version of Moodle. Please contact support");
				break;
		}

		$debugData["query_UserID"] = makeSafeForOutput($userIDQuery);
		
		$userIDResult = mysql_query($userIDQuery, $connection);
		
		if (!$userIDResult) 
		{		
			throw new exception("Could not select user: " . $username);
		}

		$row = mysql_fetch_object($userIDResult);

		$userID = $row->id; 		

		if (!$userID) 
		{		
			throw new exception("Could not get ID for user: " . $username);
		}
		
		$debugData["var_userID"] = $userID;

		$courseIDArray = array();		
		
//Include SSO Script//////////////////////////////////////////////////////////////////////////////////////////////////////////

		if($singleSignOnURLs)
		{
			require_once("collabcolib/sso.php");
		}

//Begin Output////////////////////////////////////////////////////////////////////////////////////////////////////////////////

		$output = "<data>\n";

//Load data from modules//////////////////////////////////////////////////////////////////////////////////////////////////////
		
		require_once("collabcolib/courses.php");
		

				
		if (count($courseIDArray) > 0)
		{		
			foreach($getData as $index => $value)
			{		
				switch ($value)
				{
					case "AS":
					case "ASX":
						require_once("collabcolib/assignments.php");
						break;
					case "CH":
					case "CHX":
						require_once("collabcolib/choices.php");
						break;
					case "LE":
					case "LEX":
						require_once("collabcolib/lessons.php");
						break;
					case "WO":
					case "WOX":
						require_once("collabcolib/workshops.php");
						break;
					case "DA":
					case "DAX":
						require_once("collabcolib/databases.php");
						break;
					case "QU":
					case "QUX":
						require_once("collabcolib/quizzes.php");
						break;
					case "ALL":
						require_once("collabcolib/assignments.php");
						require_once("collabcolib/choices.php");
						require_once("collabcolib/lessons.php");
						require_once("collabcolib/workshops.php");
						require_once("collabcolib/databases.php");
						require_once("collabcolib/quizzes.php");
						break;
					case "DBG":
						$retDebug = true;
						break;
				}
			}
		}
			
//Close Off Data Stream///////////////////////////////////////////////////////////////////////////////////////////////////////
	
		$output .= "</data>\n";
	
//Open Encrypted Output///////////////////////////////////////////////////////////////////////////////////////////////////////
				
		$encrypted = "<data>\n";

//Encrypt Data////////////////////////////////////////////////////////////////////////////////////////////////////////////////
		
		$encryptedData = encrypt($output,generateKey($username));
		
		if ($returnClearText === true)
		{
			$encrypted .= "<cleartext>". $output . "</cleartext>\n";
		}
		
		if ($returnCipherText === true)
		{
			$encrypted .= "<ciphertext>". $encryptedData . "</ciphertext>\n";
		}

//Add Metrics to Output///////////////////////////////////////////////////////////////////////////////////////////////////////
		
		if ($showMetrics === true)
		{
			$mtime = microtime(); 
			$mtime = explode(" ",$mtime); 
			$encrypted .= "<executiontime>".(($mtime[1] + $mtime[0]) - $starttime)."</executiontime>\n";
		}
		
//Add Instance URL to Output//////////////////////////////////////////////////////////////////////////////////////////////////
		
		if($singleSignOnURLs)
		{
			$moodleurl .= "<instanceurl>". generateSSO("index.php", $username)."</instanceurl>\n";
		}
		else
		{
			$moodleurl .= "<instanceurl>" . $CFG->wwwroot . "</instanceurl>\n";
		}
		
		 $encrypted .= $moodleurl;		
		
//Close encrypted Output///////////////////////////////////////////////////////////////////////////////////////////////////////
		
	}
	catch (exception $ex)
	{
		$encrypted = "<data>\n";
		$encrypted .= buildErrorMessage("Unexpected exception: " . $ex->getMessage());
	}
	
//Insert Debug data into stream///////////////////////////////////////////////////////////////////////////////////////////////

	if ($retDebug === true && $debug === true)
	{	
		$debuglines = "<debugdata>\n";
		
		try
		{
			foreach ($debugData as $key => $value) {
				$debuglines .= "<$key>$value</$key>\n";
			}
		}
		catch (Exception $ex)
		{
			$debuglines .= "<error>\n<message>An exception was encountered whilst adding debug data</message>\n<exception>" . $ex . "</exception>\n</error>\n";
		}
		
		$debuglines .= "</debugdata>\n";
		
		$encrypted .= $debuglines;
	}
	
	$encrypted .= "</data>\n";
	
	header("Content-type: text/xml");
	header("HTTP/1.1 " . $statusCode . " " . $statusMessage);
	
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//Echo Output////////////////////////////////////////////////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	  
	echo $encrypted;
?>