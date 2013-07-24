<?php // Updates meta courses with new child course occurrences.

if (!empty($_SERVER['GATEWAY_INTERFACE'])) {
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
if ($link == false) {
    fwrite($log,"Unable to connect to server $server. Error: ".mysqli_error($link)."<br />");
}
$sel = mysqli_select_db($link, $database);
if ($sel == false) {
    fwrite($log,"Unable to select database $database. Error: ".mysqli_error($link)."<br />");
}

$starttime = userdate($time, '%H:%M:%S');
$startdate = userdate($time, '%A %e %B %Y');
fwrite($log,"Starting update at $starttime on $startdate.<br /><br />");

$success = 0;
$fail = 0;

for ($i=1; $i<=6; $i++) {
    $date1 = $time - ($i*31556926) - 1209600;   // Use start dates from 2 weeks before (today's date - $i years)
    $date2 = $date1 + 2419200;                  // to 2 weeks after (today's date - $i years)
    $modified = $time - 7257600;                // but only if meta relationship not modified in last 12 weeks.
    $sql = "SELECT `mdl_course`.`idnumber`, `mdl_enrol`.`id`, `mdl_enrol`.`courseid`
            FROM `mdl_course`, `mdl_enrol`
            WHERE (`mdl_course`.`id` = `mdl_enrol`.`customint1`)
            AND (`mdl_course`.`startdate` BETWEEN $date1 AND $date2)
            AND (`mdl_course`.`idnumber` LIKE '%_%/%')
            AND (`mdl_enrol`.`enrol` = 'meta')
            AND (`mdl_enrol`.`timemodified` < $modified)";
    $res = mysqli_query($link, $sql);
    if ($res == false) {
        fwrite($log,"Unable to run query $sql. Error: ".mysqli_error($link)."<br />");
    }

    while ($rows = mysqli_fetch_array($res)) {
        $id_number = $rows[0];
        $meta_id = $rows[1];
        $parent_id = $rows[2];
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
        $year1 = $years[0];
        $year2 = $years[1];

        // Look for other occurrences (of same duration) of UI code in parent course.
        $duration = $year2 - $year1;
        $latest_start = $year1;
        $sql = "SELECT `mdl_course`.`idnumber`, `mdl_enrol`.`id`
                FROM `mdl_course`, `mdl_enrol`
                WHERE (`mdl_course`.`id` = `mdl_enrol`.`customint1`)
                AND (`mdl_course`.`idnumber` LIKE '".$course_code."\_%/%')
                AND (`mdl_enrol`.`courseid` = $parent_id)";
        $res_others = mysqli_query($link, $sql);
        if ($res_others == false) {
            fwrite($log,"Unable to run query $sql. Error: ".mysqli_error($link)."<br />");
        // Don't bother if only original occurrence returned.
        } else if (mysqli_num_rows($res_others) > 1) {
            // Create empty array for meta enrolment IDs.
            $meta_ids = array();
            // Find earliest occurrence and use that instead of original.
            while ($rows_others = mysqli_fetch_array($res_others)) {
                $id_number_other = $rows_others[0];
                $id_parts_other = explode('_',$id_number_other);
                $occ_other = $id_parts_other[1];
                if (strpos($occ_other,'-') !== false) {
                    $occ_parts_other = explode('-',$occ_other);
                    $years_other = $occ_parts_other[0];
                    $term_other = $occ_parts_other[1];
                } else {
                    $years_other = $occ_other;
                    $term_other = false;
                }
                $years_other = explode('/',$years_other);
                $year1_other = $years_other[0];
                $year2_other = $years_other[1];
                // Make sure occurrence is of the same duration and term is the same.
                if (($year2_other - $year1_other == $duration) && ($term_other == $term)) {
                    // Use this occurrence if it's earlier.
                    if ($year1_other < $year1) {
                        $meta_id = $rows_others[1];
                        $year1 = $year1_other;
                        $year2 = $year2_other;
                    }
                    // Also need to find latest existing start year.
                    if ($year1_other > $latest_start) {
                        $latest_start = $year1_other;
                    }
                    // Add to array of meta enrolment IDs.
                    $meta_ids[] = $rows_others[1];
                }
            }
        }
        $increment = ($latest_start - $year1) + 1;
        $year1 = $year1 + $increment;
        $year2 = $year2 + $increment;
        if ($year1 < 10) $year1 = "0".$year1;
        if ($year2 < 10) $year2 = "0".$year2;
        $new_years = $year1.'/'.$year2;
        if ($term) {
            $new_occ = $new_years.'-'.$term;
        } else {
            $new_occ = $new_years;
        }
        $new_code = $course_code.'_'.$new_occ;

        $sql = "SELECT `id`, `startdate`
                FROM `mdl_course`
                WHERE `idnumber` = '$new_code'";
        $resource = mysqli_query($link, $sql);
        if ($resource == false) {
            fwrite($log,"Unable to run query $sql. Error: ".mysqli_error($link)."<br />");
        } else if (mysqli_num_rows($resource) == 0) {
            $sql = "SELECT `mdl_course`.`id`, `mdl_course`.`shortname`
                    FROM `mdl_course`, `mdl_enrol`
                    WHERE (`mdl_course`.`id` = `mdl_enrol`.`courseid`)
                    AND (`mdl_enrol`.`id` = $meta_id)";
            $resource_parent = mysqli_query($link, $sql);
            if ($resource_parent == false) {
                fwrite($log,"Unable to run query $sql. Error: ".mysqli_error($link)."<br />");
            } else {
                $row_parent = mysqli_fetch_array($resource_parent);
                $parent_id = $row_parent[0];
                $shortname = $row_parent[1];
                fwrite($log,"<span style=\"color: #C00;\">Unable to find new occurrence ($new_occ) of $course_code for meta course <a href=\"".$CFG->wwwroot."/course/view.php?id=".$parent_id."\" target=\"_blank\">$shortname</a>.</span><br />");
                $fail++;
            }
        } else {
            $row = mysqli_fetch_array($resource);
            $child_course = $row[0];
            $start_date = $row[1];
            $start_date = userdate($start_date, '%d/%m/%y');

            // Update meta enrolment with new child course ID.
            $sql = "UPDATE `mdl_enrol`
                    SET `customint1` = $child_course, `timemodified` = $time
                    WHERE `mdl_enrol`.`id` = $meta_id";
            $resource = mysqli_query($link, $sql);
            if ($resource == false) {
                fwrite($log,"Unable to run query $sql. Error: ".mysqli_error($link)."<br />");
            } else {
                $sql = "SELECT `mdl_course`.`id`, `mdl_course`.`shortname`
                        FROM `mdl_course`, `mdl_enrol`
                        WHERE (`mdl_course`.`id` = `mdl_enrol`.`courseid`)
                        AND (`mdl_enrol`.`id` = $meta_id)";
                $resource_parent = mysqli_query($link, $sql);
                if ($resource_parent == false) {
                    fwrite($log,"Unable to run query $sql. Error: ".mysqli_error($link)."<br />");
                } else {
                    $row_parent = mysqli_fetch_array($resource_parent);
                    $parent_id = $row_parent[0];
                    $shortname = $row_parent[1];
                    enrol_meta_sync($parent_id, true);
                    fwrite($log,"<span style=\"color: #0C0;\">Successfully updated meta course <a href=\"".$CFG->wwwroot."/course/view.php?id=".$parent_id."\" target=\"_blank\">$shortname</a> with new child course $new_code (start date $start_date).</span><br />");
                    $success++;
                }
            }
            // Also mark meta links for other occurrences as updated.
            if (isset($meta_ids) && !empty($meta_ids)) {
                foreach ($meta_ids as $meta_id) {
                    // Just update time modified.
                    $sql = "UPDATE `mdl_enrol`
                            SET `timemodified` = $time
                            WHERE `mdl_enrol`.`id` = $meta_id";
                    $resource = mysqli_query($link, $sql);
                    if ($resource == false) {
                        fwrite($log,"Unable to run query $sql. Error: ".mysqli_error($link)."<br />");
                    }
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