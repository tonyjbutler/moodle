<?php

	if (!defined("COLLABCO_MOODLE"))
	{
		die();
	}
	
//Assignments/////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	
	$assignments = "";
	
	try 
	{
		$assignments = "<assignments>\n";
		
		switch($moodleVersion)
		{
			case "1.9":	
				$assignmentQuery = sprintf("SELECT DISTINCT A.id, A.name, A.course, A.description, A.timedue, A.timeavailable, A.timemodified, CM.id as cmid".
											" FROM " . $moodleTablePrefix . "assignment A, " . $moodleTablePrefix . "course_modules CM, " . $moodleTablePrefix . "modules M".
											" WHERE A.timeavailable <= %s". 
											" AND A.course IN (%s)". 
											" AND M.name = '%s'". 
											" AND CM.instance = A.id". 
											" AND CM.module = M.id". 
											" AND CM.visible = '1'". 
											" AND M.visible = '1'", 
											time(), 
											implode(",",$courseIDArray),
											"assignment"											
											);
			case "2.2":
				$assignmentQuery = sprintf("SELECT DISTINCT A.id, A.name, A.course, A.intro as description, A.timedue, A.timeavailable, A.timemodified, CM.id as cmid".
											" FROM " . $moodleTablePrefix . "assignment A, " . $moodleTablePrefix . "course_modules CM, " . $moodleTablePrefix . "modules M".
											" WHERE A.timeavailable <= %s". 
											" AND A.course IN (%s)". 
											" AND M.name = '%s'". 
											" AND CM.instance = A.id". 
											" AND CM.module = M.id". 
											" AND CM.visible = '1'". 
											" AND M.visible = '1'", 
											time(), 
											implode(",",$courseIDArray),
											"assignment"											
											);												
				break;					
			case "2.3":							
				$assignmentQuery = sprintf("SELECT DISTINCT A.id, A.name, A.course, A.intro AS description, A.allowsubmissionsfromdate as timeavailable, A.duedate as timedue, A.timemodified, CM.id AS cmid".
											" FROM " . $moodleTablePrefix . "assign A, " . $moodleTablePrefix . "course_modules CM, " . $moodleTablePrefix . "modules M".
											" WHERE A.allowsubmissionsfromdate <= %s". 
											" AND A.course IN (%s)". 
											" AND M.name = '%s'". 
											" AND CM.instance = A.id". 
											" AND CM.module = M.id". 
											" AND CM.visible = '1'". 
											" AND M.visible = '1'", 
											time(), 
											implode(",",$courseIDArray),
											"assign"											
											);												
				break;
			default:
				throw new exception ("There is no Assignment query for this version of Moodle. Please contact support");
				break;
		}
		
		$debugData["query_Assignment"] = makeSafeForOutput($assignmentQuery);		
		
		$assignmentResult = mysql_query($assignmentQuery, $connection);
		
		if ($assignmentResult)
		{
			while ($assignmentRow = mysql_fetch_assoc($assignmentResult)) 
			{
				$assignments .= "<assignment>\n";
						
				foreach ($assignmentRow as $key => $value)
				{		
					$assignments .= "<".$key.">".makeSafeForOutput($value)."</".$key.">\n";
				}
				
				$url = "";
				
				switch($moodleVersion)
				{
					case "1.9":
					case "2.2":	
						$url = "mod/assignment/view.php?id=" . $assignmentRow['cmid'];
						break;
					case "2.3":	
						$url = "mod/assign/view.php?id=" . $assignmentRow['cmid'];
						break;
					default:
						throw new exception ("There is no Assignemt URL Stub for this version of Moodle. Please contact support");
						break;
				}

				if($singleSignOnURLs)
				{
					$assignments .= "<url>".generateSSO($url, $username)."</url>\n";
				}
				else
				{
					$assignments .= "<url>".$CFG->wwwroot . "/" .$url."</url>\n";
				}				

				$assignmentSubmissions = "<submissions>\n";						
				
				if (in_array("ASX", $getData, false) || $getAllData === true)
				{					
					switch($moodleVersion)
					{
						case "1.9":	
						case "2.2":	
						case "2.3":							
							$submissionQuery = sprintf("SELECT id, timemodified, timemarked, grade, submissioncomment, data2 as status". 
													" FROM " . $moodleTablePrefix . "assignment_submissions". 
													" WHERE assignment = '%s'". 
													" AND userid = '%s'", 
													$assignmentRow['id'], 
													$userID
													);
							break;
						default:
							throw new exception ("There is no Assignment Submission query for this version of Moodle. Please contact support");
							break;
					}
					
					$debugData["query_AssignmentSubmissions"] = makeSafeForOutput($submissionQuery);	
											
					$submissionResult = mysql_query($submissionQuery , $connection);
										
					$numAssSubmissions = 0;
					
					if ($submissionResult)
					{								
						while ($submissionRow = mysql_fetch_assoc($submissionResult)) 
						{
							$numAssSubmissions++;
							
							$assignmentSubmissions .= "<submission>\n";
						
							foreach ($submissionRow as $key => $value)
							{		
								$assignmentSubmissions .= "<".$key.">".makeSafeForOutput($value)."</".$key.">\n";
							}
							
							$assignmentSubmissions .= "</submission>\n";
						}
					}
					
					$assignmentSubmissions .= "</submissions>\n";
					
					$assignments .= "<numsubmissions>" . $numAssSubmissions . "</numsubmissions>\n";
				

					$assignments .= $assignmentSubmissions;
				}
				else
				{						
					switch($moodleVersion)
					{
						case "1.9":	
						case "2.2":	
						case "2.3":							
							$submissionQuery = sprintf("SELECT COUNT(*) AS num". 
														" FROM " . $moodleTablePrefix . "assignment_submissions". 
														" WHERE assignment = '%s'". 
														" AND userid = '%s'", 
														$assignmentRow['id'], 
														$userID
														);
							break;
						default:
							throw new exception ("There is no Assignment Submission Count query for this version of Moodle. Please contact support");
							break;
					}
					
					$debugData["query_AssignmentSubmissions"] = makeSafeForOutput($submissionQuery);	
					
					$subs = mysql_query($submissionQuery);
					$row = mysql_fetch_assoc($subs);

					$assignments .= "<numsubmissions>" . $row['num'] . "</numsubmissions>\n";
				}
				
				$assignments .= "</assignment>\n";
			}
		}
		
		$assignments .= "</assignments>\n";
	}
	catch (Exception $ex)
	{
		$assignments = "<assignments>\n";
		$assignments .= buildErrorMessage("Unexpected exception: " . $ex->getMessage());
		$assignments .= "</assignments>\n";
	}
	
	$output .= $assignments;
		
?>