<?php

if (!defined("COLLABCO_MOODLE"))
{
	die();
}

//Database////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	
		$databases = "";
		
		if (in_array("DA", $getData, false) || in_array("DAX", $getData, false) || $getAllData === true)
		{
			try
			{
				$databases = "<databases>\n";	

				switch($moodleVersion)
				{
					case "1.9":	
					case "2.2":	
					case "2.3":	
						$databaseQuery = sprintf("SELECT D.id, D.course, D.name, D.intro, D.approval, D.timeavailablefrom, D.timeavailableto, CM.id as cmid". 
												 " FROM " . $moodleTablePrefix . "data D, " . $moodleTablePrefix . "course_modules CM, " . $moodleTablePrefix . "modules M".
												 " WHERE D.course IN (%s)".
												 " AND D.timeavailablefrom < %s".
												 " AND M.name = '%s'".
												 " AND M.visible = '1'".
												 " AND CM.visible = '1'".
												 " AND CM.instance = D.id".
												 " AND CM.module = M.id",									  
												  implode(",",$courseIDArray),
												  time(),	
												  "data"								  
												);
						break;
					default:
						throw new exception ("There is no Database query for this version of Moodle. Please contact support");
						break;
				}
				
				$debugData["query_Database"] = makeSafeForOutput($databaseQuery);
			
				$databaseResult = mysql_query($databaseQuery, $connection);
				
				if ($databaseResult)
				{
					while ($databaseRow = mysql_fetch_assoc($databaseResult)) 
					{	
						$databases .= "<database>\n";
						
						foreach ($databaseRow as $key => $value)
						{		
							$databases .= "<".$key.">".makeSafeForOutput($value)."</".$key.">\n";
						}
						
						$url = "";
						
						switch($moodleVersion)
						{
							case "1.9":
							case "2.2":	
							case "2.3":	
								$url = "mod/data/view.php?id=" . $databaseRow['cmid'];
								break;
							default:
								throw new exception ("There is no Database URL Stub this version of Moodle. Please contact support");
								break;
						}
						
						if($singleSignOnURLs)
						{
							$databases .= "<url>".generateSSO($url, $username)."</url>\n";
						}
						else
						{
							$databases .= "<url>".$CFG->wwwroot . "/" .$url."</url>\n";
						}
						
						if (in_array("DAX", $getData, false) || $getAllData === true)
						{
							switch($moodleVersion)
							{
								case "1.9":
								case "2.2":	
								case "2.3":	
									$databaseSubmissionsQuery = sprintf("SELECT id, timecreated, timemodified, approved". 
																		 " FROM " . $moodleTablePrefix . "data_records". 
																		 " WHERE dataid = '%s'". 
																		 " AND userid = '%s'",
																		 $databaseRow['id'], 
																		 $userID); 
									break;
								default:
									throw new exception ("There is no Database Submissions query for this version of Moodle. Please contact support");
									break;
							}

							$debugData["query_DatabaseSubmissions"] = makeSafeForOutput($databaseSubmissionsQuery);							
							
							$databaseSubmissionsResult = mysql_query($databaseSubmissionsQuery, $connection);
							
							$numDatabaseSubmissions = 0;
							$numApprovedDatabaseSubmissions = 0;							
							$databaseSubmissions = "<submissions>\n";
							
							if ($databaseSubmissionsResult)
							{										
								while ($databaseSubmissionsRow = mysql_fetch_assoc($databaseSubmissionsResult)) 
								{	
									$numDatabaseSubmissions++;
									$databaseSubmissions .= "<submission>\n";
									
									foreach ($databaseSubmissionsRow as $key => $value)
									{		
										$databaseSubmissions .= "<".$key.">".makeSafeForOutput($value)."</".$key.">\n";
										
										if ($key == "approved" && $value == "1")
										{
											$numApprovedDatabaseSubmissions++;
										}
									}
									
									$databaseSubmissions .= "</submission>\n";
								}						
							}
							else
							{
								$numDatabaseSubmissions = -1;
								$numApprovedDatabaseSubmissions = -1;								
							}
						
							$databaseSubmissions .= "</submissions>\n";
												
							$databases .= "<numsubmissions>" . $numDatabaseSubmissions . "</numsubmissions>\n";
							$databases .= "<numsubmissionsapproved>" . $numApprovedDatabaseSubmissions . "</numsubmissionsapproved>\n";
													
							$databases .= $databaseSubmissions;
						}
						else
						{
							switch($moodleVersion)
							{
								case "1.9":
								case "2.2":	
								case "2.3":	
									$databaseSubmissionsQuery = sprintf("SELECT COUNT(*) AS num". 
																		 " FROM " . $moodleTablePrefix . "data_records". 
																		 " WHERE dataid = '%s'". 
																		 " AND userid = '%s'",
																		 $databaseRow['id'], 
																		 $userID
																		 ); 
									$databaseApprovedQuery = sprintf("SELECT COUNT(*) AS num". 
																	  " FROM " . $moodleTablePrefix . "data_records". 
																	  " WHERE approved = '1'".
																	  " AND dataid = '%s'". 
																	  " AND userid = '%s'",
																	  $databaseRow['id'], 
																	  $userID
																      );
									break;
								default:
									throw new exception ("There is no Database Submissions Count query for this version of Moodle. Please contact support");
									break;
							}
							
							$debugData["query_DatabaseSubmissions"] = makeSafeForOutput($databaseSubmissionsQuery);	
							
							$subs = mysql_fetch_assoc(mysql_query($databaseSubmissionsQuery));
							$approved = mysql_fetch_assoc(mysql_query($databaseApprovedQuery));
							
							$databases .= "<numsubmissions>" . $subs['num'] . "</numsubmissions>\n";
							$databases .= "<numsubmissionsapproved>" . $approved['num'] . "</numsubmissionsapproved>\n";
						}
						
						$databases .= "</database>\n";
					}
				}
				
				$databases .= "</databases>\n";
			
			}
			catch (Exception $ex)
			{
				$databases = "<databases>\n";
				$databases .= buildErrorMessage("Unexpected exception: " . $ex->getMessage());
				$databases .= "</databases>\n";
			}
		}
		
		$output .= $databases;
		
?>