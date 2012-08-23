<?php

	if (!defined("COLLABCO_MOODLE"))
	{
		die();
	}

//Courses/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

		$courses = "";
		
		try 
		{
			$courses = "<courses>\n";
			
			switch($moodleVersion)
			{
				case "1.9":		
				case "2.2":
				case "2.3":
					$courseQuery = sprintf("SELECT DISTINCT CS.id, CS.category, CS.shortname, CS.fullname, CS.summary".
											" FROM " . $moodleTablePrefix . "context C, " . $moodleTablePrefix . "role_assignments R, " . $moodleTablePrefix . "course CS".
											" WHERE CS.id = C.instanceid". 
											" AND CS.visible = '1'". 
											" AND C.id = R.contextid". 
											" AND C.contextlevel = '50'". 
											" AND R.userid = '%s'", 
										   $userID
										   );
					break;
				default:
					throw new exception ("There is no Course query for this version of Moodle. Please contact support");
					break;
			}
			
			$debugData["query_Course"] = makeSafeForOutput($courseQuery);
								   
			$courseResult = mysql_query($courseQuery, $connection);
			
			if (!$courseResult) 
			{	
				throw new Exception ("Could not get courses: " . mysql_error());		
			}
			
			while ($courseRow = mysql_fetch_array($courseResult, MYSQL_ASSOC)) 
			{	
				$courses .= "<course>\n";		
				
				array_push($courseIDArray, $courseRow['id']);
				
				switch($moodleVersion)
				{
					case "1.9":
					case "2.2":	
					case "2.3":	
						$teacherQuery = sprintf("SELECT U.id, U.username, U.firstname, U.lastname".
												 " FROM " . $moodleTablePrefix . "role_assignments RA, " . $moodleTablePrefix . "context C, " . $moodleTablePrefix . "user U, " . $moodleTablePrefix . "role R".
												 " WHERE C.contextlevel = '%s'".
												 " AND C.instanceid = '%s'". 
												 " AND R.name IN (%s)". 
												 " AND RA.roleid = R.id". 
												 " AND RA.contextid = C.id". 
												 " AND U.id = RA.userid", 
												 50,
												 $courseRow['id'],
												 "'Course creator','Teacher','Non-Editing Teacher'"
												);
						break;
					default:
						throw new exception ("There is no Teacher query for this version of Moodle. Please contact support");
						break;
				}
				
				$debugData["query_Teacher"] = makeSafeForOutput($teacherQuery);
				
				$teacherResult = mysql_query($teacherQuery, $connection);
				
				foreach ($courseRow as $key => $value)
				{		
					$courses .= "<".$key.">".makeSafeForOutput($value)."</".$key.">\n";
				}
				
				$url = "";
				
				switch($moodleVersion)
				{
					case "1.9":
					case "2.2":	
					case "2.3":	
						$url = "course/view.php?id=" . $courseRow['id'];
						break;
					default:
						throw new exception ("There is no Course URL Stub this version of Moodle. Please contact support");
						break;
				}
				
				if($singleSignOnURLs)
				{
					$courses .= "<url>".generateSSO($url, $username)."</url>\n";
				}
				else
				{
					$courses .= "<url>".$CFG->wwwroot . "/" .$url."</url>\n";
				}
				
				if ($teacherResult) 
				{
					$teacherArray = array();
				
					while ($teacherRow = mysql_fetch_array($teacherResult, MYSQL_ASSOC)) 
					{
						$teacherName = trim($teacherRow['firstname']) . " " . trim($teacherRow['lastname']);
						$teacherUsername = trim($teacherRow['username']);
					
						if ($teacherName != " ")
						{
							array_push($teacherArray, trim($teacherRow['id']) . "|" . $teacherName);
						}
						else
						{
							array_push($teacherArray, trim($teacherRow['id']) . "|" . $teacherUsername);
						}							
					}
					
					$courses .= "<teacher>".implode(",",$teacherArray)."</teacher>\n";
				}
				
				$courses .= "</course>\n";			
			}
			
			$courses .= "</courses>\n";
		}
		catch (Exception $ex)
		{
			$courses = "<courses>\n";
			$courses .= buildErrorMessage("Unexpected exception: " . $ex->getMessage());
			$courses .= "</courses>\n";
		}		
		
		$output .= $courses;
		
?>