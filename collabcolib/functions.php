<?php
	define("COLLABCO_FUNCTIONS", "1.0.0");

	function buildErrorMessage($errorToDisplay)
	{
		$item = "An unexpected error occured whilst processing this request";

		if ($GLOBALS["debug"] == true)
		{
			$item = $errorToDisplay;
		}
		else
		{
			$statusCode = 500;
			$statusMessage = "Internal Server Error";
		}
		
		return "<error>" . makeSafeForOutput($item) . "</error>\n";			
	}
	
	function makeSafeForMYSQL($str) 
	{		
		$str = trim($str);
		$str = mysql_real_escape_string($str);		
		$str = htmlentities($str, ENT_QUOTES, 'UTF-8');
		return $str;
	}
	
	function makeSafeForOutput($str)
	{
		$str = trim($str);
		$str = mb_convert_encoding($str, 'UTF-8', 'UTF-8');		
		$str = htmlentities($str, ENT_QUOTES, 'UTF-8');
		return $str;
	}	
?>