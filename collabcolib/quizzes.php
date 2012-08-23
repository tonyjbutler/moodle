<?php

if (!defined("COLLABCO_MOODLE"))
{
	die();
}

//Quiz////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	
		$quizzes = "";
		
		if (in_array("QU", $getData, false) || in_array("QUX", $getData, false) || $getAllData === true)
		{
			try
			{
				$quizzes .= "<quizzes>\n";	

				switch($moodleVersion)
				{
					case "1.9":	
					case "2.2":	
					case "2.3":		
						$quizQuery = sprintf("SELECT Q.id, Q.course, Q.name, Q.intro, Q.timeopen, Q.timeclose, CM.id as cmid".
											  " FROM " . $moodleTablePrefix . "quiz Q, " . $moodleTablePrefix . "course_modules CM, " . $moodleTablePrefix . "modules M". 
											  " WHERE Q.course IN (%s)". 
											  " AND M.name = '%s'". 
											  " AND M.visible = '1'". 
											  " AND CM.visible = '1'".									  
											  " AND CM.instance = Q.id".
											  " AND CM.module = M.id",								   									  
											  implode(",",$courseIDArray),
											  "quiz"
											  );
						break;
					default:
						throw new exception ("There is no Quiz query for this version of Moodle. Please contact support");
						break;
				}
				
				$debugData["query_Quiz"] = makeSafeForOutput($quizQuery);
				
				$quizResult = mysql_query($quizQuery, $connection);
				
				if ($quizResult)
				{
					while ($quizRow = mysql_fetch_assoc($quizResult)) 
					{		
						$quizzes .= "<quiz>\n";
						
						foreach ($quizRow as $key => $value)
						{		
							$quizzes .= "<".$key.">".makeSafeForOutput($value)."</".$key.">\n";
						}
						
						$url = "";
						
						switch($moodleVersion)
						{
							case "1.9":
							case "2.2":	
							case "2.3":	
								$url = "mod/quiz/view.php?id=" . $quizRow['cmid'];
								break;
							default:
								throw new exception ("There is no Quiz URL Stub this version of Moodle. Please contact support");
								break;
						}
						
						if($singleSignOnURLs)
						{
							$quizzes .= "<url>".generateSSO($url, $username)."</url>\n";
						}
						else
						{
							$quizzes .= "<url>".$CFG->wwwroot . "/" .$url."</url>\n";
						}
						
						if (in_array("QUX", $getData, false) || $getAllData === true)
						{
							switch($moodleVersion)
							{
								case "1.9":	
								case "2.2":	
								case "2.3":							
									$quizSubmissionsQuery = sprintf("SELECT id, attempt, timestart, timefinish, timemodified". 
																	 " FROM " . $moodleTablePrefix . "quiz_attempts". 
																	 " WHERE quiz = '%s'". 
																	 " AND userid = '%s'",
																	 $quizRow['id'], 
																	 $userID
																	 );
									break;
								default:
									throw new exception ("There is no Quiz Submissions query for this version of Moodle. Please contact support");
									break;
							}
							
							$debugData["query_QuizSubmissions"] = makeSafeForOutput($quizSubmissionsQuery);

							$quizSubmissionsResult = mysql_query($quizSubmissionsQuery, $connection);
							
							$numQuizSubmissions = 0;
							$quizSubmissions = "<submissions>\n";
							
							if ($quizSubmissionsResult)
							{
								while ($quizSubmissionRow = mysql_fetch_assoc($quizSubmissionsResult)) 
								{	
									$numQuizSubmissions++;
									$quizSubmissions .= "<submission>\n";
									
									foreach ($quizSubmissionRow as $key => $value)
									{		
										$quizSubmissions .= "<".$key.">".makeSafeForOutput($value)."</".$key.">\n";
									}
									
									$quizSubmissions .= "</submission>\n";
								}						
							}
							else
							{
								$numQuizSubmissions = -1;							
							}
							
							$quizSubmissions .= "</submissions>\n";
												
							$quizzes .= "<numsubmissions>" . $numQuizSubmissions . "</numsubmissions>\n";
							
							$quizzes .= $quizSubmissions;
						}
						else
						{
							switch($moodleVersion)
							{
								case "1.9":	
								case "2.2":	
								case "2.3":							
									$quizSubmissionsQuery = sprintf("SELECT COUNT (*) AS num".
																	 " FROM " . $moodleTablePrefix . "quiz_attempts". 
																	 " WHERE quiz = '%s'". 
																	 " AND userid = '%s'",
																	 $quizRow['id'], 
																	 $userID
																	 );
									break;
								default:
									throw new exception ("There is no Quiz Submissions Count query for this version of Moodle. Please contact support");
									break;
							}
							
							$debugData["query_QuizSubmissions"] = makeSafeForOutput($quizSubmissionsQuery);
							
							$subs = mysql_query($quizSubmissionsQuery);
							$row = mysql_fetch_assoc($subs);
							
							$quizzes .= "<numsubmissions>" . $row['num'] . "</numsubmissions>\n";							
						}
						
						$quizzes .= "</quiz>\n";
					}
				}
				$quizzes .= "</quizzes>\n";
			}
			catch (Exception $ex)
			{
				$quizzes = "<quizzes>\n";
				$quizzes .= buildErrorMessage("Unexpected exception: " . $ex->getMessage());
				$quizzes .= "</quizzes>\n";
			}
		}
		
		$output .= $quizzes;
		
?>