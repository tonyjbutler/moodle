<?php

if (!defined("COLLABCO_MOODLE"))
{
	die();
}

//Workshop///////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	
		$workshops = "";
		
		if (in_array("WO", $getData, false) || in_array("WOX", $getData, false) || $getAllData === true)
		{
			try
			{	
				$workshops = "<workshops>\n";
				
				switch($moodleVersion)
				{
					case "1.9":
					case "2.2":	
						$workshopQuery = sprintf("SELECT W.id, W.course, W.name, W.description, W.submissionstart, W.submissionend, CM.id as cmid 
												  FROM mdl_workshop W, mdl_course_modules CM, mdl_modules M 
												  WHERE W.course IN (%s) 
												  AND M.name = '%s' 
												  AND CM.visible = 1 
												  AND M.visible = 1 
												  AND CM.instance = W.id
												  AND CM.module = M.id", 
												  implode(",",$courseIDArray),
												  "workshop"
												);
							break;
					case "2.3":		
						$workshopQuery = sprintf("SELECT W.id, W.course, W.name, W.intro as description, W.submissionstart, W.submissionend, CM.id as cmid 
												  FROM mdl_workshop W, mdl_course_modules CM, mdl_modules M 
												  WHERE W.course IN (%s) 
												  AND M.name = '%s' 
												  AND CM.visible = 1 
												  AND M.visible = 1 
												  AND CM.instance = W.id
												  AND CM.module = M.id", 
												  implode(",",$courseIDArray),
												  "workshop"
												);
						break;
					default:
						throw new exception ("There is no Workshop query for this version of Moodle. Please contact support");
						break;
				}
				
				$workshopResult = mysql_query($workshopQuery, $connection);		
				
				if ($workshopResult)
				{
					while ($workshopRow = mysql_fetch_assoc($workshopResult)) 
					{
						$workshops .= "<workshop>\n";
						
						foreach ($workshopRow as $key => $value)
						{		
							$workshops .= "<".$key.">".makeSafeForOutput($value)."</".$key.">\n";
						}
						
						$url = "";
						
						switch($moodleVersion)
						{
							case "1.9":
							case "2.2":	
							case "2.3":	
								$url = "mod/workshop/view.php?id=" . $workshopRow['cmid'];
								break;
							default:
								throw new exception ("There is no Workshop URL Stub this version of Moodle. Please contact support");
								break;
						}

						if($singleSignOnURLs)
						{
							$workshops .= "<url>".generateSSO($url, $username)."</url>\n";
						}
						else
						{
							$workshops .= "<url>".$CFG->wwwroot . "/" .$url."</url>\n";
						}
						
						if (in_array("WOX", $getData, false) || $getAllData === true)
						{						
							switch($moodleVersion)
							{
								case "1.9":								
									$workshopSubmissionsQuery = sprintf("SELECT id, timecreated, finalgrade, late 
																		 FROM mdl_workshop_submissions 
																		 WHERE workshopid = %s 
																		 AND userid = %s",
																		 $workshopRow['id'], 
																		 $userID
																		 );
									break;
								case "2.3":
								case "2.2":
									$workshopSubmissionsQuery = sprintf("SELECT id, timecreated, grade as finalgrade, late 
																		 FROM mdl_workshop_submissions 
																		 WHERE workshopid = %s 
																		 AND authorid = %s",
																		 $workshopRow['id'], 
																		 $userID
																		 );
									break;
								
								default:
									throw new exception ("There is no Workshop Submissions query for this version of Moodle. Please contact support");
									break;
							}	

							$workshopSubmissionsResult = mysql_query($workshopSubmissionsQuery, $connection);
							
							$numWorkshopSubmissions = 0;
							$workshopSubmissions = "<submissions>\n";
							
							if ($workshopSubmissionsResult)
							{
								while ($workshopSubmissionRow = mysql_fetch_assoc($workshopSubmissionsResult)) 
								{	
									$numWorkshopSubmissions++;
									$workshopSubmissions .= "<submission>\n";
									
									foreach ($workshopSubmissionRow as $key => $value)
									{		
										$workshopSubmissions .= "<".$key.">".makeSafeForOutput($value)."</".$key.">\n";
									}
									
									$workshopSubmissions .= "</submission>\n";
								}						
							}
							else
							{
								$numWorkshopSubmissions = -1;							
							}
							
							$workshopSubmissions .= "</submissions>\n";
												
							$workshops .= "<numsubmissions>" . $numWorkshopSubmissions . "</numsubmissions>\n";
												
							$workshops .= $workshopSubmissions;
						}
						else
						{
							switch($moodleVersion)
							{
								case "1.9":								
									$workshopSubmissionsQuery = sprintf("SELECT COUNT(*) AS num 
																		 FROM mdl_workshop_submissions 
																		 WHERE workshopid = %s 
																		 AND userid = %s",
																		 $workshopRow['id'], 
																		 $userID
																		 );
									break;
								case "2.3":
								case "2.2":
									$workshopSubmissionsQuery = sprintf("SELECT COUNT(*) AS num 
																		 FROM mdl_workshop_submissions 
																		 WHERE workshopid = %s 
																		 AND authorid = %s",
																		 $workshopRow['id'], 
																		 $userID);
									break;
								
								default:
									throw new exception ("There is no Workshop Submissions query for this version of Moodle. Please contact support");
									break;
							}						

							$subs = mysql_query($workshopSubmissionsQuery);
							$row = mysql_fetch_assoc($subs);
							
							$workshops .= "<numsubmissions>" . $row['num'] . "</numsubmissions>\n";						
						}
						
						$workshops .= "</workshop>\n";
					}
				}
				$workshops .= "</workshops>\n";
			}
			catch (Exception $ex)
			{
				$workshops = "<workshops>\n";
				$workshops .= buildErrorMessage("Unexpected exception: " . $ex->getMessage());
				$workshops .= "</workshops>\n";
			}
		}
		
		$output .= $workshops;
		
?>