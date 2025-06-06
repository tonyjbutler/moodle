<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * A library of classes used by the grade edit pages
 *
 * @package   core_grades
 * @copyright 2009 Nicolas Connault
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class grade_edit_tree {
    public $columns = array();

    /**
     * @var grade_tree $gtree   @see grade/lib.php
     */
    public $gtree;

    /**
     * @var grade_plugin_return @see grade/lib.php
     */
    public $gpr;

    /**
     * @var string              $moving The eid of the category or item being moved
     */
    public $moving;

    public $deepest_level;

    public $uses_weight = false;

    public $table;

    public $categories = array();

    /**
     * Constructor
     */
    public function __construct($gtree, $moving, $gpr) {
        global $USER, $OUTPUT, $COURSE;

        $this->gtree = $gtree;
        $this->moving = $moving;
        $this->gpr = $gpr;
        $this->deepest_level = $this->get_deepest_level($this->gtree->top_element);

        $this->columns = array(grade_edit_tree_column::factory('name', array('deepest_level' => $this->deepest_level)));

        if ($this->uses_weight) {
            $this->columns[] = grade_edit_tree_column::factory('weight', array('adv' => 'weight'));
        }

        $this->columns[] = grade_edit_tree_column::factory('range'); // This is not a setting... How do we deal with it?
        $this->columns[] = grade_edit_tree_column::factory('status');
        $this->columns[] = grade_edit_tree_column::factory('actions');

        $this->table = new html_table();
        $this->table->id = "grade_edit_tree_table";
        $this->table->attributes['class'] = 'table generaltable simple setup-grades';
        if ($this->moving) {
            $this->table->attributes['class'] .= ' moving';
        }

        foreach ($this->columns as $column) {
            if (!($this->moving && $column->hide_when_moving)) {
                $this->table->head[] = $column->get_header_cell();
            }
        }

        $rowcount = 0;
        $this->table->data = $this->build_html_tree($this->gtree->top_element, true, array(), 0, $rowcount);
    }

    /**
     * Recursive function for building the table holding the grade categories and items,
     * with CSS indentation and styles.
     *
     * @param array   $element The current tree element being rendered
     * @param boolean $totals Whether or not to print category grade items (category totals)
     * @param array   $parents An array of parent categories for the current element (used for indentation and row classes)
     *
     * @return string HTML
     */
    public function build_html_tree($element, $totals, $parents, $level, &$row_count) {
        global $CFG, $COURSE, $PAGE, $OUTPUT;

        $object = $element['object'];
        $eid    = $element['eid'];
        $name = grade_helper::get_element_header($element, true, false, true, false, true);
        $icon = grade_helper::get_element_icon($element);
        $type = grade_helper::get_element_type_string($element);
        $strippedname = grade_helper::get_element_header($element, false, false, false);
        $is_category_item = false;
        if ($element['type'] == 'categoryitem' || $element['type'] == 'courseitem') {
            $is_category_item = true;
        }

        $rowclasses = array();
        foreach ($parents as $parent_eid) {
            $rowclasses[] = $parent_eid;
        }

        $moveaction = '';
        $actions = $this->gtree->get_cell_action_menu($element, 'setup', $this->gpr);

        if ($element['type'] == 'item' or ($element['type'] == 'category' and $element['depth'] > 1)) {
            $aurl = new moodle_url('index.php', array('id' => $COURSE->id, 'action' => 'moveselect', 'eid' => $eid, 'sesskey' => sesskey()));
            $moveaction .= $OUTPUT->action_icon($aurl, new pix_icon('t/move', get_string('move')));
        }

        $returnrows = array();
        $root = false;

        $id = required_param('id', PARAM_INT);

        /// prepare move target if needed
        $last = '';

        /// print the list items now
        if ($this->moving == $eid) {
            // do not diplay children
            $cell = new html_table_cell();
            $cell->colspan = 12;
            $cell->attributes['class'] = $element['type'] . ' moving column-name level' .
                ($level + 1) . ' level' . ($level % 2 ? 'even' : 'odd');
                $cell->text = $name.' ('.get_string('move').')';

            // Create a row that represents the available area to move a grade item or a category into.
            $movingarea = new html_table_row();
            // Obtain all parent category identifiers for this item and add them to its class list. This information
            // will be used when collapsing or expanding grade categories to properly show or hide this area.
            $parentcategories = array_merge($rowclasses, [$eid]);
            $movingarea->attributes = [
                'class' => implode(' ', $parentcategories),
                'data-hidden' => 'false'
            ];
            $movingarea->cells[] = $cell;

            return [$movingarea];
        }

        if ($element['type'] == 'category') {
            $level++;
            $this->categories[$object->id] = $strippedname;
            $category = grade_category::fetch(array('id' => $object->id));
            $category->load_grade_item();

            // Add aggregation coef input if not a course item and if parent category has correct aggregation type
            // Before we print the category's row, we must find out how many rows will appear below it (for the filler cell's rowspan)
            $aggregation_position = grade_get_setting($COURSE->id, 'aggregationposition', $CFG->grade_aggregationposition);
            $category_total_data = null; // Used if aggregationposition is set to "last", so we can print it last

            $html_children = array();

            // Take into consideration that a category item always has an empty row (spacer) below.
            $row_count = 1;

            foreach($element['children'] as $child_el) {
                $moveto = null;

                if (empty($child_el['object']->itemtype)) {
                    $child_el['object']->itemtype = false;
                }

                if (($child_el['object']->itemtype == 'course' || $child_el['object']->itemtype == 'category') && !$totals) {
                    continue;
                }

                $child_eid = $child_el['eid'];
                $first = '';

                if ($child_el['object']->itemtype == 'course' || $child_el['object']->itemtype == 'category') {
                    $first = array('first' => 1);
                    $child_eid = $eid;
                }

                if ($this->moving && $this->moving != $child_eid) {

                    $strmove     = get_string('move');
                    $actions = $moveaction = ''; // no action icons when moving

                    $aurl = new moodle_url('index.php', array('id' => $COURSE->id, 'action' => 'move', 'eid' => $this->moving, 'moveafter' => $child_eid, 'sesskey' => sesskey()));
                    if ($first) {
                        $aurl->params($first);
                    }

                    $cell = new html_table_cell();
                    $cell->colspan = 12;
                    $cell->attributes['class'] = 'movehere level' . ($level + 1) . ' level' . ($level % 2 ? 'even' : 'odd');

                    $cell->text = html_writer::link($aurl, html_writer::empty_tag('hr'),
                        ['title' => get_string('movehere'), 'class' => 'movehere']);

                    // Create a row that represents the available area to move a grade item or a category into.
                    $moveto = new html_table_row();
                    // Obtain all parent category identifiers for this item and add them to its class list. This information
                    // will be used when collapsing or expanding grade categories to properly show or hide this area.
                    $parentcategories = array_merge($rowclasses, [$eid]);
                    $moveto->attributes['class'] = implode(' ', $parentcategories);
                    $moveto->attributes['data-hidden'] = 'false';
                    $moveto->cells[] = $cell;
                }

                $newparents = $parents;
                $newparents[] = $eid;

                $row_count++;
                $child_row_count = 0;

                // If moving, do not print course and category totals, but still print the moveto target box
                if ($this->moving && ($child_el['object']->itemtype == 'course' || $child_el['object']->itemtype == 'category')) {
                    $html_children[] = $moveto;
                } elseif ($child_el['object']->itemtype == 'course' || $child_el['object']->itemtype == 'category') {
                    // We don't build the item yet because we first need to know the deepest level of categories (for category/name colspans)
                    $category_total_item = $this->build_html_tree($child_el, $totals, $newparents, $level, $child_row_count);
                    if (!$aggregation_position) {
                        $html_children = array_merge($html_children, $category_total_item);
                    }
                } else {
                    $html_children = array_merge($html_children, $this->build_html_tree($child_el, $totals, $newparents, $level, $child_row_count));
                    if (!empty($moveto)) {
                        $html_children[] = $moveto;
                    }

                    if ($this->moving) {
                        $row_count++;
                    }
                }

                $row_count += $child_row_count;

                // If the child is a category, increment row_count by one more (for the extra coloured row)
                if ($child_el['type'] == 'category') {
                    $row_count++;
                }
            }

            // Print category total at the end if aggregation position is "last" (1)
            if (!empty($category_total_item) && $aggregation_position) {
                $html_children = array_merge($html_children, $category_total_item);
            }

            // Determine if we are at the root
            if (isset($element['object']->grade_item) && $element['object']->grade_item->is_course_item()) {
                $root = true;
            }

            $levelclass = "level$level level" . ($level % 2 ? 'odd' : 'even');

            $courseclass = '';
            if ($level == 1) {
                $courseclass = 'coursecategory';
            }

            $categoryrow = new html_table_row();
            $categoryrow->id = 'grade-item-' . $eid;
            $categoryrow->attributes['class'] = $courseclass . ' category ';
            $categoryrow->attributes['data-category'] = $eid;
            if (!empty($parent_eid)) {
                $categoryrow->attributes['data-parent-category'] = $parent_eid;
            }
            $categoryrow->attributes['data-aggregation'] = $category->aggregation;
            $categoryrow->attributes['data-grademax'] = $category->grade_item->grademax;
            $categoryrow->attributes['data-aggregationcoef'] = floatval($category->grade_item->aggregationcoef);
            $categoryrow->attributes['data-itemid'] = $category->grade_item->id;
            $categoryrow->attributes['data-hidden'] = 'false';
            foreach ($rowclasses as $class) {
                $categoryrow->attributes['class'] .= ' ' . $class;
            }

            foreach ($this->columns as $column) {
                if (!($this->moving && $column->hide_when_moving)) {
                    $categoryrow->cells[] = $column->get_category_cell($category, $levelclass, [
                        'id' => $id,
                        'name' => $name,
                        'level' => $level,
                        'actions' => $actions,
                        'moveaction' => $moveaction,
                        'eid' => $eid,
                    ]);
                }
            }

            $emptyrow = new html_table_row();
            // Obtain all parent category identifiers for this item and add them to its class list. This information
            // will be used when collapsing or expanding grade categories to properly show or hide this area.
            $parentcategories = array_merge($rowclasses, [$eid]);
            $emptyrow->attributes['class'] = 'spacer ' . implode(' ', $parentcategories);
            $emptyrow->attributes['data-hidden'] = 'false';
            $emptyrow->attributes['aria-hidden'] = 'true';

            $headercell = new html_table_cell();
            $headercell->header = true;
            $headercell->scope = 'row';
            $headercell->attributes['class'] = 'cell column-rowspan rowspan';
            $headercell->attributes['aria-hidden'] = 'true';
            $headercell->rowspan = $row_count;
            $emptyrow->cells[] = $headercell;

            $returnrows = array_merge([$categoryrow, $emptyrow], $html_children);

            // Print a coloured row to show the end of the category across the table
            $endcell = new html_table_cell();
            $endcell->colspan = (19 - $level);
            $endcell->attributes['class'] = 'emptyrow colspan ' . $levelclass;
            $endcell->attributes['aria-hidden'] = 'true';

            $returnrows[] = new html_table_row(array($endcell));

        } else { // Dealing with a grade item

            $item = grade_item::fetch(array('id' => $object->id));
            $element['type'] = 'item';
            $element['object'] = $item;

            $categoryitemclass = '';
            if ($item->itemtype == 'category') {
                $categoryitemclass = 'categoryitem';
            }
            if ($item->itemtype == 'course') {
                $categoryitemclass = 'courseitem';
            }

            $gradeitemrow = new html_table_row();
            $gradeitemrow->id = 'grade-item-' . $eid;
            $gradeitemrow->attributes['class'] = $categoryitemclass . ' item ';
            $gradeitemrow->attributes['data-itemid'] = $object->id;
            $gradeitemrow->attributes['data-hidden'] = 'false';
            // If this item is a course or category aggregation, add a data attribute that stores the identifier of
            // the related category or course. This attribute is used when collapsing a grade category to fetch the
            // max grade from the aggregation and display it in the grade category row when the category items are
            // collapsed and the aggregated max grade is not visible.
            if (!empty($categoryitemclass)) {
                $gradeitemrow->attributes['data-aggregationforcategory'] = $parent_eid;
            } else {
                $gradeitemrow->attributes['data-parent-category'] = $parent_eid;
                $gradeitemrow->attributes['data-grademax'] = $object->grademax;
                $gradeitemrow->attributes['data-aggregationcoef'] = floatval($object->aggregationcoef);
            }
            foreach ($rowclasses as $class) {
                $gradeitemrow->attributes['class'] .= ' ' . $class;
            }

            foreach ($this->columns as $column) {
                if (!($this->moving && $column->hide_when_moving)) {
                    $gradeitemrow->cells[] = $column->get_item_cell(
                        $item,
                        [
                            'id' => $id,
                            'name' => $name,
                            'level' => $level,
                            'actions' => $actions,
                            'element' => $element,
                            'eid' => $eid,
                            'moveaction' => $moveaction,
                            'itemtype' => $object->itemtype,
                            'icon' => $icon,
                            'type' => $type,
                        ]
                    );
                }
            }

            $returnrows[] = $gradeitemrow;
        }

        return $returnrows;

    }

    /**
     * Given a grade_item object, returns a labelled input if an aggregation coefficient (weight or extra credit) applies to it.
     * @param grade_item $item
     * @return string HTML
     */
    static function get_weight_input($item) {
        global $OUTPUT;

        if (!is_object($item) || get_class($item) !== 'grade_item') {
            throw new Exception('grade_edit_tree::get_weight_input($item) was given a variable that is not of the required type (grade_item object)');
            return false;
        }

        if ($item->is_course_item()) {
            return '';
        }

        $parent_category = $item->get_parent_category();
        $parent_category->apply_forced_settings();
        $aggcoef = $item->get_coefstring();

        $itemname = $item->itemname;
        if ($item->is_category_item()) {
            // Remember, the parent category of a category item is the category itself.
            $itemname = $parent_category->get_name();
        }
        $str = '';

        if ($aggcoef == 'aggregationcoefweight' || $aggcoef == 'aggregationcoef' || $aggcoef == 'aggregationcoefextraweight') {

            return $OUTPUT->render_from_template('core_grades/weight_field', [
                'id' => $item->id,
                'itemname' => $itemname,
                'value' => self::format_number($item->aggregationcoef)
            ]);

        } else if ($aggcoef == 'aggregationcoefextraweightsum') {

            $tpldata = [
                'id' => $item->id,
                'itemname' => $itemname,
                'value' => self::format_number($item->aggregationcoef2 * 100.0),
                'checked' => $item->weightoverride,
                'disabled' => !$item->weightoverride
            ];
            $str .= $OUTPUT->render_from_template('core_grades/weight_override_field', $tpldata);

        }

        return $str;
    }

    // Trims trailing zeros.
    // Used on the 'Gradebook setup' page for grade items settings like aggregation co-efficient.
    // Grader report has its own decimal place settings so they are handled elsewhere.
    static function format_number($number) {
        $formatted = rtrim(format_float($number, 4),'0');
        if (substr($formatted, -1)==get_string('decsep', 'langconfig')) { //if last char is the decimal point
            $formatted .= '0';
        }
        return $formatted;
    }

    /**
     * Given an element of the grade tree, returns whether it is deletable or not (only manual grade items are deletable)
     *
     * @param array $element
     * @return bool
     */
    public static function element_deletable($element) {
        global $COURSE;

        if ($element['type'] != 'item') {
            return true;
        }

        $grade_item = $element['object'];

        if ($grade_item->itemtype != 'mod' or $grade_item->is_outcome_item() or $grade_item->gradetype == GRADE_TYPE_NONE) {
            return true;
        }

        $modinfo = get_fast_modinfo($COURSE);
        if (!isset($modinfo->instances[$grade_item->itemmodule][$grade_item->iteminstance])) {
            // module does not exist
            return true;
        }

        return false;
    }

    /**
     * Given an element of the grade tree, returns whether it is duplicatable or not (only manual grade items are duplicatable)
     *
     * @param array $element
     * @return bool
     */
    public static function element_duplicatable($element) {
        if ($element['type'] != 'item') {
            return false;
        }

        $gradeitem = $element['object'];
        if ($gradeitem->itemtype != 'mod') {
            return true;
        }
        return false;
    }

    /**
     * Given the grade tree and an array of element ids (e.g. c15, i42), and expecting the 'moveafter' URL param,
     * moves the selected items to the requested location. Then redirects the user to the given $returnurl
     *
     * @param object $gtree The grade tree (a recursive representation of the grade categories and grade items)
     * @param array $eids
     * @param string $returnurl
     */
    function move_elements($eids, $returnurl) {
        $moveafter = required_param('moveafter', PARAM_INT);

        if (!is_array($eids)) {
            $eids = array($eids);
        }

        if(!$after_el = $this->gtree->locate_element("cg$moveafter")) {
            throw new \moodle_exception('invalidelementid', '', $returnurl);
        }

        $after = $after_el['object'];
        $parent = $after;
        $sortorder = $after->get_sortorder();

        foreach ($eids as $eid) {
            if (!$element = $this->gtree->locate_element($eid)) {
                throw new \moodle_exception('invalidelementid', '', $returnurl);
            }
            $object = $element['object'];

            $object->set_parent($parent->id);
            $object->move_after_sortorder($sortorder);
            $sortorder++;
        }

        redirect($returnurl, '', 0);
    }

    /**
     * Recurses through the entire grade tree to find and return the maximum depth of the tree.
     * This should be run only once from the root element (course category), and is used for the
     * indentation of the Name column's cells (colspan)
     *
     * @param array $element An array of values representing a grade tree's element (all grade items in this case)
     * @param int $level The level of the current recursion
     * @param int $deepest_level A value passed to each subsequent level of recursion and incremented if $level > $deepest_level
     * @return int Deepest level
     */
    function get_deepest_level($element, $level=0, $deepest_level=1) {
        $object = $element['object'];

        $level++;
        $coefstring = $element['object']->get_coefstring();
        if ($element['type'] == 'category') {
            if ($coefstring == 'aggregationcoefweight' || $coefstring == 'aggregationcoefextraweightsum' ||
                    $coefstring == 'aggregationcoefextraweight') {
                $this->uses_weight = true;
            }

            foreach($element['children'] as $child_el) {
                if ($level > $deepest_level) {
                    $deepest_level = $level;
                }
                $deepest_level = $this->get_deepest_level($child_el, $level, $deepest_level);
            }

            $category = grade_category::fetch(array('id' => $object->id));
            $item = $category->get_grade_item();
            if ($item->gradetype == GRADE_TYPE_NONE) {
                // Add 1 more level for grade category that has no total.
                $deepest_level++;
            }
        }

        return $deepest_level;
    }

    /**
     * Updates the provided gradecategory item with the provided data.
     *
     * @param grade_category $gradecategory The category to update.
     * @param stdClass $data the data to update the category with.
     * @return void
     */
    public static function update_gradecategory(grade_category $gradecategory, stdClass $data) {
        // If no fullname is entered for a course category, put ? in the DB.
        if (!isset($data->fullname) || $data->fullname == '') {
            $data->fullname = '?';
        }

        if (!isset($data->aggregateonlygraded)) {
            $data->aggregateonlygraded = 0;
        }
        if (!isset($data->aggregateoutcomes)) {
            $data->aggregateoutcomes = 0;
        }
        grade_category::set_properties($gradecategory, $data);

        // CATEGORY.
        if (empty($gradecategory->id)) {
            $gradecategory->insert();

        } else {
            $gradecategory->update();
        }

        // GRADE ITEM.
        // Grade item data saved with prefix "grade_item_".
        $itemdata = new stdClass();
        foreach ($data as $k => $v) {
            if (preg_match('/grade_item_(.*)/', $k, $matches)) {
                $itemdata->{$matches[1]} = $v;
            }
        }

        if (!isset($itemdata->aggregationcoef)) {
            $itemdata->aggregationcoef = 0;
        }

        if (!isset($itemdata->gradepass) || $itemdata->gradepass == '') {
            $itemdata->gradepass = 0;
        }

        if (!isset($itemdata->grademax) || $itemdata->grademax == '') {
            $itemdata->grademax = 0;
        }

        if (!isset($itemdata->grademin) || $itemdata->grademin == '') {
            $itemdata->grademin = 0;
        }

        $hidden      = empty($itemdata->hidden) ? 0 : $itemdata->hidden;
        $hiddenuntil = empty($itemdata->hiddenuntil) ? 0 : $itemdata->hiddenuntil;
        unset($itemdata->hidden);
        unset($itemdata->hiddenuntil);

        $locked   = empty($itemdata->locked) ? 0 : $itemdata->locked;
        $locktime = empty($itemdata->locktime) ? 0 : $itemdata->locktime;
        unset($itemdata->locked);
        unset($itemdata->locktime);

        $convert = array('grademax', 'grademin', 'gradepass', 'multfactor', 'plusfactor', 'aggregationcoef', 'aggregationcoef2');
        foreach ($convert as $param) {
            if (property_exists($itemdata, $param)) {
                $itemdata->$param = unformat_float($itemdata->$param);
            }
        }
        if (isset($itemdata->aggregationcoef2)) {
            $itemdata->aggregationcoef2 = $itemdata->aggregationcoef2 / 100.0;
        }

        // When creating a new category, a number of grade item fields are filled out automatically, and are required.
        // If the user leaves these fields empty during creation of a category, we let the default values take effect.
        // Otherwise, we let the user-entered grade item values take effect.
        $gradeitem = $gradecategory->load_grade_item();
        $gradeitemcopy = fullclone($gradeitem);
        grade_item::set_properties($gradeitem, $itemdata);

        if (empty($gradeitem->id)) {
            $gradeitem->id = $gradeitemcopy->id;
        }
        if (empty($gradeitem->grademax) && $gradeitem->grademax != '0') {
            $gradeitem->grademax = $gradeitemcopy->grademax;
        }
        if (empty($gradeitem->grademin) && $gradeitem->grademin != '0') {
            $gradeitem->grademin = $gradeitemcopy->grademin;
        }
        if (empty($gradeitem->gradepass) && $gradeitem->gradepass != '0') {
            $gradeitem->gradepass = $gradeitemcopy->gradepass;
        }
        if (empty($gradeitem->aggregationcoef) && $gradeitem->aggregationcoef != '0') {
            $gradeitem->aggregationcoef = $gradeitemcopy->aggregationcoef;
        }

        // Handle null decimals value - must be done before update!
        if (!property_exists($itemdata, 'decimals') or $itemdata->decimals < 0) {
            $gradeitem->decimals = null;
        }

        // Change weightoverride flag. Check if the value is set, because it is not when the checkbox is not ticked.
        $itemdata->weightoverride = isset($itemdata->weightoverride) ? $itemdata->weightoverride : 0;
        if ($gradeitem->weightoverride != $itemdata->weightoverride && $gradecategory->aggregation == GRADE_AGGREGATE_SUM) {
            // If we are using natural weight and the weight has been un-overriden, force parent category to recalculate weights.
            $gradecategory->force_regrading();
        }
        $gradeitem->weightoverride = $itemdata->weightoverride;

        $gradeitem->outcomeid = null;

        // This means we want to rescale overridden grades as well.
        if (!empty($data->grade_item_rescalegrades) && $data->grade_item_rescalegrades == 'yes') {
            $gradeitem->markasoverriddenwhengraded = false;
            $gradeitem->rescale_grades_keep_percentage($gradeitemcopy->grademin, $gradeitemcopy->grademax,
                $gradeitem->grademin, $gradeitem->grademax, 'gradebook');
        }

        // Only update the category's 'hidden' status if it has changed. Leaving a category as 'unhidden' (checkbox left
        // unmarked) and submitting the form without this conditional check will result in displaying any grade items that
        // are in the category, including those that were previously 'hidden'.
        if (($gradecategory->get_hidden() != $hiddenuntil) || ($gradecategory->get_hidden() != $hidden)) {
            if ($hiddenuntil) {
                $gradecategory->set_hidden($hiddenuntil, true);
            } else {
                $gradecategory->set_hidden($hidden, true);
            }
        }

        $gradeitem->set_locktime($locktime); // Locktime first - it might be removed when unlocking.
        $gradeitem->set_locked($locked, false, true);

        $gradeitem->update(); // We don't need to insert it, it's already created when the category is created.

        // Set parent if needed.
        if (isset($data->parentcategory)) {
            $gradecategory->set_parent($data->parentcategory, 'gradebook');
        }
    }
}

/**
 * Class grade_edit_tree_column
 *
 * @package   core_grades
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class grade_edit_tree_column {
    public $forced;
    public $hidden;
    public $forced_hidden;
    public $advanced_hidden;
    public $hide_when_moving = true;
    /**
     * html_table_cell object used as a template for header cells in all categories.
     * It must be cloned before being used.
     * @var html_table_cell $headercell
     */
    public $headercell;
    /**
     * html_table_cell object used as a template for category cells in all categories.
     * It must be cloned before being used.
     * @var html_table_cell $categorycell
     */
    public $categorycell;
    /**
     * html_table_cell object used as a template for item cells in all categories.
     * It must be cloned before being used.
     * @var html_table_cell $itemcell
     */
    public $itemcell;

    public static function factory($name, $params=array()) {
        $class_name = "grade_edit_tree_column_$name";
        if (class_exists($class_name)) {
            return new $class_name($params);
        }
    }

    abstract public function get_header_cell();

    public function get_category_cell($category, $levelclass, $params) {
        $cell = clone($this->categorycell);
        $cell->attributes['class'] .= ' ' . $levelclass;
        return $cell;
    }

    public function get_item_cell($item, $params) {
        $cell = clone($this->itemcell);
        if (isset($params['level'])) {
            $level = $params['level'] + (($item->itemtype == 'category' || $item->itemtype == 'course') ? 0 : 1);
            $cell->attributes['class'] .= ' level' . $level;
            $cell->attributes['class'] .= ' level' . ($level % 2 ? 'odd' : 'even');
        }
        return $cell;
    }

    public function __construct() {
        $this->headercell = new html_table_cell();
        $this->headercell->header = true;
        $this->headercell->attributes['class'] = 'header';

        $this->categorycell = new html_table_cell();
        $this->categorycell->attributes['class']  = 'cell';

        $this->itemcell = new html_table_cell();
        $this->itemcell->attributes['class'] = 'cell';

        if (preg_match('/^grade_edit_tree_column_(\w*)$/', get_class($this), $matches)) {
            $this->headercell->attributes['class'] .= ' column-' . $matches[1];
            $this->categorycell->attributes['class'] .= ' column-' . $matches[1];
            $this->itemcell->attributes['class'] .= ' column-' . $matches[1];
        }
    }
}

/**
 * Class grade_edit_tree_column_name
 *
 * @package   core_grades
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grade_edit_tree_column_name extends grade_edit_tree_column {
    public $forced = false;
    public $hidden = false;
    public $forced_hidden = false;
    public $advanced_hidden = false;
    public $deepest_level = 1;
    public $hide_when_moving = false;

    public function __construct($params) {
        if (empty($params['deepest_level'])) {
            throw new Exception('Tried to instantiate a grade_edit_tree_column_name object without the "deepest_level" param!');
        }

        $this->deepest_level = $params['deepest_level'];
        parent::__construct();
    }

    public function get_header_cell() {
        $headercell = clone($this->headercell);
        $headercell->colspan = $this->deepest_level + 1;
        $headercell->text = get_string('name');
        return $headercell;
    }

    public function get_category_cell($category, $levelclass, $params) {
        global $OUTPUT;

        if (empty($params['name']) || empty($params['level'])) {
            throw new Exception('Array key (name or level) missing from 3rd param of grade_edit_tree_column_name::get_category_cell($category, $levelclass, $params)');
        }
        $visibilitytoggle = $OUTPUT->render_from_template('core_grades/grade_category_visibility_toggle', [
            'category' => $params['eid']
        ]);

        $togglercheckbox = '';
        if ($this->deepest_level > 1) {
            if (empty($params['eid'])) {
                throw new Exception('Array key (eid) missing from 3rd param of ' .
                    'grade_edit_tree_column_name::get_category_cell($category, $levelclass, $params)');
            }

            // Get toggle group for this toggler checkbox.
            $togglegroup = $this->get_checkbox_togglegroup($category);
            // Set label for this toggler checkbox.
            $togglerlabel = $params['level'] === 1 ? get_string('all') : $params['name'];
            // Build the toggler checkbox.
            $togglercheckbox = new \core\output\checkbox_toggleall($togglegroup, true, [
                'id' => 'select_category_' . $category->id,
                'name' => $togglegroup,
                'value' => 1,
                'classes' => 'itemselect ignoredirty',
                'label' => $togglerlabel,
                // Consistent label to prevent the select column from resizing.
                'selectall' => $togglerlabel,
                'deselectall' => $togglerlabel,
                'labelclasses' => 'accesshide',
            ]);

            $togglercheckbox = $OUTPUT->render($togglercheckbox);
        }

        $moveaction = isset($params['moveaction']) ? $params['moveaction'] : '';
        $categorycell = parent::get_category_cell($category, $levelclass, $params);
        $categorycell->colspan = ($this->deepest_level + 2) - $params['level'];
        $rowtitle = html_writer::div($params['name'], 'rowtitle');
        $categorycell->text = html_writer::div($togglercheckbox . $visibilitytoggle . $moveaction . $rowtitle, 'fw-bold');
        return $categorycell;
    }

    public function get_item_cell($item, $params) {
        if (empty($params['element']) || empty($params['name']) || empty($params['level'])) {
            throw new Exception('Array key (name, level or element) missing from 2nd param of grade_edit_tree_column_name::get_item_cell($item, $params)');
        }

        $itemicon = \html_writer::div($params['icon'], 'me-1');
        $itemtype = \html_writer::span($params['type'], 'd-block text-uppercase small dimmed_text');
        $itemtitle = html_writer::div($params['name'], 'rowtitle');
        $content = \html_writer::div($itemtype . $itemtitle);

        $moveaction = isset($params['moveaction']) ? $params['moveaction'] : '';

        $itemcell = parent::get_item_cell($item, $params);
        $itemcell->colspan = ($this->deepest_level + 1) - $params['level'];

        $checkbox = '';
        if (($this->deepest_level > 1) && ($params['itemtype'] != 'course') && ($params['itemtype'] != 'category')) {
            global $OUTPUT;

            $label = get_string('select', 'grades', $params['name']);

            if (empty($params['itemtype']) || empty($params['eid'])) {
                throw new \moodle_exception('missingitemtypeoreid', 'core_grades');
            }

            // Fetch the grade item's category.
            $category = $item->get_parent_category();
            $togglegroup = $this->get_checkbox_togglegroup($category);

            $checkboxid = 'select_' . $params['eid'];
            $checkbox = new \core\output\checkbox_toggleall($togglegroup, false, [
                'id' => $checkboxid,
                'name' => $checkboxid,
                'label' => $label,
                'labelclasses' => 'accesshide',
                'classes' => 'itemselect ignoredirty',
            ]);
            $checkbox = $OUTPUT->render($checkbox);
        }

        $itemcell->text = \html_writer::div($checkbox . $moveaction . $itemicon . $content,
            "{$params['itemtype']} d-flex align-items-center");
        return $itemcell;
    }

    /**
     * Generates a toggle group name for a bulk-action checkbox based on the given grade category.
     *
     * @param grade_category $category The grade category.
     * @return string
     */
    protected function get_checkbox_togglegroup(grade_category $category): string {
        $levels = [];
        $categories = explode('/', $category->path);
        foreach ($categories as $categoryid) {
            $level = 'category' . $categoryid;
            if (!in_array($level, $levels)) {
                $levels[] = 'category' . $categoryid;
            }
        }
        $togglegroup = implode(' ', $levels);

        return $togglegroup;
    }
}

/**
 * Class grade_edit_tree_column_weight
 *
 * @package   core_grades
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grade_edit_tree_column_weight extends grade_edit_tree_column {

    public function get_header_cell() {
        global $OUTPUT;
        $headercell = clone($this->headercell);
        $headercell->text = get_string('weights', 'grades').$OUTPUT->help_icon('aggregationcoefweight', 'grades');
        return $headercell;
    }

    public function get_category_cell($category, $levelclass, $params) {

        $item = $category->get_grade_item();
        $categorycell = parent::get_category_cell($category, $levelclass, $params);
        $categorycell->text = grade_edit_tree::get_weight_input($item);
        return $categorycell;
    }

    public function get_item_cell($item, $params) {
        global $CFG;
        if (empty($params['element'])) {
            throw new Exception('Array key (element) missing from 2nd param of grade_edit_tree_column_weightorextracredit::get_item_cell($item, $params)');
        }
        $itemcell = parent::get_item_cell($item, $params);
        $itemcell->text = '&nbsp;';
        $object = $params['element']['object'];

        if (!in_array($object->itemtype, array('courseitem', 'categoryitem', 'category'))
                && !in_array($object->gradetype, array(GRADE_TYPE_NONE, GRADE_TYPE_TEXT))
                && (!$object->is_outcome_item() || $object->load_parent_category()->aggregateoutcomes)
                && ($object->gradetype != GRADE_TYPE_SCALE || !empty($CFG->grade_includescalesinaggregation))) {
            $itemcell->text = grade_edit_tree::get_weight_input($item);
        }

        return $itemcell;
    }
}

/**
 * Class grade_edit_tree_column_range
 *
 * @package   core_grades
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grade_edit_tree_column_range extends grade_edit_tree_column {

    public function get_header_cell() {
        $headercell = clone($this->headercell);
        $headercell->text = get_string('maxgrade', 'grades');
        return $headercell;
    }

    public function get_category_cell($category, $levelclass, $params) {
        $categorycell = parent::get_category_cell($category, $levelclass, $params);
        $categorycell->text = '';
        return $categorycell;
    }

    public function get_item_cell($item, $params) {
        global $DB, $OUTPUT;

        // If the parent aggregation is Natural, we should show the number, even for scales, as that value is used...
        // ...in the computation. For text grades, the grademax is not used, so we can still show the no value string.
        $parentcat = $item->get_parent_category();
        if ($item->gradetype == GRADE_TYPE_TEXT) {
            $grademax = ' - ';
        } else if ($item->gradetype == GRADE_TYPE_SCALE) {
            $scale = $DB->get_record('scale', array('id' => $item->scaleid));
            $scale_items = null;
            if (empty($scale)) { //if the item is using a scale that's been removed
                $scale_items = array();
            } else {
                $scale_items = explode(',', $scale->scale);
            }
            if ($parentcat->aggregation == GRADE_AGGREGATE_SUM) {
                $grademax = end($scale_items) . ' (' .
                        format_float($item->grademax, $item->get_decimals()) . ')';
            } else {
                $grademax = end($scale_items) . ' (' . count($scale_items) . ')';
            }
        } else {
            $grademax = format_float($item->grademax, $item->get_decimals());
        }

        $isextracredit = false;
        if ($item->aggregationcoef > 0) {
            // For category grade items, we need the grandparent category.
            // The parent is just category the grade item represents.
            if ($item->is_category_item()) {
                $grandparentcat = $parentcat->get_parent_category();
                if ($grandparentcat->is_extracredit_used()) {
                    $isextracredit = true;
                }
            } else if ($parentcat->is_extracredit_used()) {
                $isextracredit = true;
            }
        }
        if ($isextracredit) {
            $grademax .= ' ' . html_writer::tag('abbr', get_string('aggregationcoefextrasumabbr', 'grades'),
                array('title' => get_string('aggregationcoefextrasum', 'grades')));
        }

        $itemcell = parent::get_item_cell($item, $params);
        $itemcell->text = $grademax;
        return $itemcell;
    }
}

/**
 * Class grade_edit_tree_column_status
 *
 * @package   core_grades
 * @copyright 2023 Ilya Tregubov <ilya@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grade_edit_tree_column_status extends grade_edit_tree_column {

    /**
     * Get status column header cell
     * @return html_table_cell status column header cell
     */
    public function get_header_cell() {
        $headercell = clone($this->headercell);
        $headercell->text = get_string('status');
        return $headercell;
    }

    /**
     * Get category cell in status column
     *
     * @param grade_category $category grade category
     * @param string $levelclass Category level info
     * @param array $params Params (category id, action performed etc)
     * @return html_table_cell category cell in status columns
     */
    public function get_category_cell($category, $levelclass, $params) {
        global $OUTPUT, $gtree;

        $category->load_grade_item();
        $categorycell = parent::get_category_cell($category, $levelclass, $params);
        $element = [];
        $element['object'] = $category;
        $categorycell->text = $gtree->set_grade_status_icons($element);

        $context = new stdClass();
        if ($category->grade_item->is_calculated()) {
            $context->calculatedgrade = get_string('calculatedgrade', 'grades');
        } else {
            // Aggregation type.
            $aggrstrings = grade_helper::get_aggregation_strings();
            $context->aggregation = $aggrstrings[$category->aggregation];

            // Include/exclude empty grades.
            if ($category->aggregateonlygraded) {
                $context->aggregateonlygraded = $category->aggregateonlygraded;
            }

            // Aggregate outcomes.
            if ($category->aggregateoutcomes) {
                $context->aggregateoutcomes = $category->aggregateoutcomes;
            }

            // Drop the lowest.
            if ($category->droplow) {
                $context->droplow = $category->droplow;
            }

            // Keep the highest.
            if ($category->keephigh) {
                $context->keephigh = $category->keephigh;
            }
        }
        $categorycell->text .= $OUTPUT->render_from_template('core_grades/category_settings', $context);
        return $categorycell;
    }

    /**
     * Get category cell in status column
     *
     * @param grade_item $item grade item
     * @param array $params Params
     * @return html_table_cell item cell in status columns
     */
    public function get_item_cell($item, $params) {
        global $gtree;

        $element = [];
        $element['object'] = $item;
        $itemcell = parent::get_item_cell($item, $params);
        $itemcell->text = $gtree->set_grade_status_icons($element);
        return $itemcell;
    }
}

/**
 * Class grade_edit_tree_column_actions
 *
 * @package   core_grades
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grade_edit_tree_column_actions extends grade_edit_tree_column {

    public function __construct($params) {
        parent::__construct();
    }

    public function get_header_cell() {
        $headercell = clone($this->headercell);
        $headercell->text = get_string('actions');
        return $headercell;
    }

    public function get_category_cell($category, $levelclass, $params) {

        if (empty($params['actions'])) {
            throw new Exception('Array key (actions) missing from 3rd param of grade_edit_tree_column_actions::get_category_actions($category, $levelclass, $params)');
        }

        $categorycell = parent::get_category_cell($category, $levelclass, $params);
        $categorycell->text = $params['actions'];
        return $categorycell;
    }

    public function get_item_cell($item, $params) {
        if (empty($params['actions'])) {
            throw new Exception('Array key (actions) missing from 2nd param of grade_edit_tree_column_actions::get_item_cell($item, $params)');
        }
        $itemcell = parent::get_item_cell($item, $params);
        $itemcell->text = $params['actions'];
        return $itemcell;
    }
}
