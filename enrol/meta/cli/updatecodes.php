<?php // Updates meta courses with new child course occurrences

    if(!empty($_SERVER['GATEWAY_INTERFACE'])){
        error_log("should not be called from apache!");
        exit;
    }
    error_reporting(E_ALL);

    define('CLI_SCRIPT', true);

    require(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php'); // global moodle config file
    require_once("$CFG->dirroot/enrol/meta/locallib.php");

    $time = time();
    $timeformatted = strftime('%Y%m%d-%H%M%S', $time);
    $log = fopen($CFG->dataroot.'/log/meta_update/update_'.$timeformatted.'.html','w');
    fwrite($log,"<div id=\"page\"><div id=\"page-content\"><div class=\"generalbox\">");

    $server = $CFG->dbhost;
    $username = $CFG->dbuser;
    $password = $CFG->dbpass;
    $database = $CFG->dbname;

    $link = mysqli_connect($server, $username, $password);
    if($link == false){
        fwrite($log,"Unable to connect to server $server. Error: ".mysqli_error($link)."<br />");
    }
    $sel = mysqli_select_db($link, $database);
    if($sel == false){
        fwrite($log,"Unable to select database $database. Error: ".mysqli_error($link)."<br />");
    }

    $starttime = userdate($time, '%H:%M:%S');
    $startdate = userdate($time, '%A %e %B %Y');
    fwrite($log,"Starting update at $starttime on $startdate.<br /><br />");

    $success = 0;
    $fail = 0;

    for($i=1; $i<=6; $i++){
        $date1 = $time - ($i*31556926) - 1209600;   // use start dates from 2 weeks before (today's date - $i years)
        $date2 = $date1 + 2419200;                  // to 2 weeks after (today's date - $i years)
        $modified = $time - 7257600;                // but only if meta relationship not modified in last 12 weeks
        $sql = "SELECT `mdl_course`.`idnumber`, `mdl_enrol`.`id` FROM `mdl_course`, `mdl_enrol`
                WHERE (`mdl_course`.`id` = `mdl_enrol`.`customint1`) AND (`mdl_course`.`startdate` BETWEEN $date1 AND $date2)
                AND (`mdl_course`.`idnumber` LIKE '%_%/%') AND (`mdl_enrol`.`enrol` = 'meta') AND (`mdl_enrol`.`timemodified` < $modified)";
        $res = mysqli_query($link, $sql);
        if($res == false){
            fwrite($log,"Unable to run query $sql. Error: ".mysqli_error($link)."<br />");
        }

        while($rows = mysqli_fetch_array($res)){
            $id_number = $rows[0];
            $meta_id = $rows[1];
            $id_parts = explode('_',$id_number);
            $course_code = $id_parts[0];
            $occurrence = $id_parts[1];
            if (strpos($occurrence,'-') !== false) {
                $occ_parts = explode('-',$occurrence);
                $years = $occ_parts[0];
                $term = $occ_parts[1];
            } else {
                $years = $occurrence;
                $term = false;
            }
            $years = explode('/',$years);
            $year1 = $years[0]; $year2 = $years[1];
            $year1++; $year2++;
            if($year1 < 10) $year1 = "0".$year1;
            if($year2 < 10) $year2 = "0".$year2;
            $new_years = $year1.'/'.$year2;
            if ($term) {
                $new_occ = $new_years.'-'.$term;
            } else {
                $new_occ = $new_years;
            }
            $new_code = $course_code.'_'.$new_occ;

            $sql = "SELECT `id`, `startdate` FROM `mdl_course` WHERE `idnumber` = '$new_code'";
            $resource = mysqli_query($link, $sql);
            if($resource == false){
                fwrite($log,"Unable to run query $sql. Error: ".mysqli_error($link)."<br />");
            }
            else if(mysqli_num_rows($resource) == 0){
                $sql = "SELECT `mdl_course`.`id`, `mdl_course`.`shortname` FROM `mdl_course`, `mdl_enrol` WHERE (`mdl_course`.`id` = `mdl_enrol`.`courseid`) AND (`mdl_enrol`.`id` = $meta_id)";
                $resource_parent = mysqli_query($link, $sql);
                if($resource_parent == false){
                    fwrite($log,"Unable to run query $sql. Error: ".mysqli_error($link)."<br />");
                }
                else{
                    $row_parent = mysqli_fetch_array($resource_parent);
                    $parent_id = $row_parent[0];
                    $shortname = $row_parent[1];
                    fwrite($log,"<span style=\"color: #C00;\">Unable to find new occurrence ($new_occ) of $course_code for meta course <a href=\"".$CFG->wwwroot."/course/view.php?id=".$parent_id."\" target=\"_blank\">$shortname</a>.</span><br />");
                    $fail++;
                }
            }
            else{
                $row = mysqli_fetch_array($resource);
                $child_course = $row[0];
                $start_date = $row[1];
                $start_date = userdate($start_date, '%d/%m/%y');

                $sql = "UPDATE `mdl_enrol` SET `customint1` = $child_course, `timemodified` = $time WHERE `mdl_enrol`.`id` = $meta_id";
                $resource = mysqli_query($link, $sql);
                if($resource == false){
                    fwrite($log,"Unable to run query $sql. Error: ".mysqli_error($link)."<br />");
                }
                else{
                    $sql = "SELECT `mdl_course`.`id`, `mdl_course`.`shortname` FROM `mdl_course`, `mdl_enrol` WHERE (`mdl_course`.`id` = `mdl_enrol`.`courseid`) AND (`mdl_enrol`.`id` = $meta_id)";
                    $resource_parent = mysqli_query($link, $sql);
                    if($resource_parent == false){
                        fwrite($log,"Unable to run query $sql. Error: ".mysqli_error($link)."<br />");
                    }
                    else{
                        $row_parent = mysqli_fetch_array($resource_parent);
                        $parent_id = $row_parent[0];
                        $shortname = $row_parent[1];
                        enrol_meta_sync($parent_id, true);
                        fwrite($log,"<span style=\"color: #0C0;\">Successfully updated meta course <a href=\"".$CFG->wwwroot."/course/view.php?id=".$parent_id."\" target=\"_blank\">$shortname</a> with new child course $new_code (start date $start_date).</span><br />");
                        $success++;
                    }
                }
            }    
        }
    }

    $endtime = time();
    $endtime = userdate($endtime, '%H:%M:%S');
    fwrite($log,"<br />Update completed at $endtime.<br />$success courses updated, $fail courses not found.");
    fwrite($log,"</div></div></div>");

    fclose($log);

?>
