<?php

	if (!defined("COLLABCO_MOODLE"))
	{
		die();
	}

//Choices/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	
	$choices = "";
		
	try
	{				
		switch($moodleVersion)
		{
			case "1.9":		
				$choiceQuery = sprintf("SELECT C.id, C.course, C.name, C.text, C.timeopen, C.timeclose, CM.id as cmid". 
										" FROM " . $moodleTablePrefix . "choice C, " . $moodleTablePrefix . "course_modules CM, " . $moodleTablePrefix . "modules M". 
										" WHERE C.course IN (%s)". 
										" AND M.name = '%s'".
										" AND M.visible = '1'".
										" AND CM.visible = '1'". 
										" AND CM.instance = C.id".
										" AND CM.module = M.id", 
										implode(",",$courseIDArray),
										"choice"
										);
				break;
			case "2.2":
			case "2.3":					
				$choiceQuery = sprintf("SELECT C.id, C.course, C.name, C.intro as text, C.timeopen, C.timeclose, CM.id as cmid". 
										" FROM " . $moodleTablePrefix . "choice C, " . $moodleTablePrefix . "course_modules CM, " . $moodleTablePrefix . "modules M". 
										" WHERE C.course IN (%s)". 
										" AND M.name = '%s'".
										" AND M.visible = '1'".
										" AND CM.visible = '1'". 
										" AND CM.instance = C.id".
										" AND CM.module = M.id", 
										implode(",",$courseIDArray),
										"choice"
										);
				break;
			default:
				throw new exception ("There is no Choice query for this version of Moodle. Please contact support");
				break;
		}
		
		$debugData["query_Choice"] = makeSafeForOutput($choiceQuery);

		$choiceResult = mysql_query($choiceQuery, $connection);
		
		$choices = "<choices>\n";
		
		if ($choiceResult)
		{		
			while ($choiceRow = mysql_fetch_assoc($choiceResult)) 
			{
				$choices .= "<choice>\n";
				
				foreach ($choiceRow as $key => $value)
				{		
					$choices .= "<".$key.">".makeSafeForOutput($value)."</".$key.">\n";
				}
				
				$url = "";
				
				switch($moodleVersion)
				{
					case "1.9":
					case "2.2":	
					case "2.3":	
						$url = "mod/choice/view.php?id=" . $choiceRow['cmid'];
						break;
					default:
						throw new exception ("There is no Choice URL Stub this version of Moodle. Please contact support");
						break;
				}

				if($singleSignOnURLs)
				{
					$choices .= "<url>".generateSSO($url, $username)."</url>\n";
				}
				else
				{
					$choices .= "<url>".$CFG->wwwroot . "/" .$url."</url>\n";
				}
				
				if (in_array("CHX", $getData, false) || $getAllData === true)
				{							
					switch($moodleVersion)
					{
						case "1.9":		
						case "2.2":		
						case "2.3":						
							$choicesSubmissionsQuery = sprintf("SELECT id, timemodified".
																" FROM " . $moodleTablePrefix . "choice_answers".
																" WHERE choiceid = '%s' AND".
																" userid = '%s'",
																$choiceRow['id'], 
																$userID
																);
							break;
						default:
							throw new exception ("There is no Choice Submissions query for this version of Moodle. Please contact support");
							break;
					}
					
					$debugData["query_ChoiceSubmissions"] = makeSafeForOutput($choicesSubmissionsQuery);
						
					$choicesSubmissionsResult = mysql_query($choicesSubmissionsQuery,$connection);
					
					$chosubmissions = mysql_num_rows($choicesSubmissionsResult);
					
					$choices .= "<numsubmissions>" . $chosubmissions . "</numsubmissions>\n";
				}
				else
				{
					switch($moodleVersion)
					{
						case "1.9":		
						case "2.2":		
						case "2.3":						
							$choicesSubmissionsQuery = sprintf("SELECT COUNT(*) AS num". 
																" FROM " . $moodleTablePrefix . "choice_answers". 
																" WHERE choiceid = '%s'". 
																" AND userid = '%s'",
																$choiceRow['id'], 
																$userID
																);
							break;
						default:
							throw new exception ("There is no Choice Submissions Count query for this version of Moodle. Please contact support");
							break;
					}
					
					$debugData["query_ChoiceSubmissions"] = makeSafeForOutput($choicesSubmissionsQuery);
					
					$subs = mysql_query($choicesSubmissionsQuery);
					$row = mysql_fetch_assoc($subs);

					$choices .= "<numsubmissions>" . $row['num'] . "</numsubmissions>\n";						
				}

				$choices .= "</choice>\n";
			}
		}
		
		$choices .= "</choices>\n";
	}
	catch (Exception $ex)
	{
		$choices = "<choices>\n";
		$choices .= buildErrorMessage("Unexpected exception: " . $ex->getMessage());
		$choices .= "</choices>\n";
	}
		
	$output .= $choices;
		
?>