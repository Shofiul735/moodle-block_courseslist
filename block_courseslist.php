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

use core_user\search\course_teacher;

/**
 * Block definition class for the block_pluginname plugin.
 *
 * @package   block_courseslist
 * @copyright 2022, Md. Shofiul Islam
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class block_courseslist extends block_base
{

    /**
     * Initialises the block.
     *
     * @return void
     */
    public function init()
    {
        $this->title = get_string('pluginname', 'block_courses');
    }

    /**
     * Gets the block contents.
     *
     * @return string The block HTML.
     */
    public function get_content()
    {
        global $OUTPUT;
        global $USER, $DB;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->footer = '';

        // Add logic here to define your template data or any other content.

        $index = 0;
        $categories = null;
        if (isset($_GET['id'])) {
            $index = intval($_GET['id']) - 1;
        }
        if (isset($_GET['categories'])) {
            $categories = $_GET['categories'];
        }
        // mdl_course_categories
        $courses = get_courses();
        $courses = array_values($courses);
        $courses = array_slice($courses, 1);
        $content = array_map(fn ($item) => ['id' => $item->id, 'category' => $item->category, 'fullname' => $item->fullname, 'shortname' => $item->shortname], $courses);
        uasort($content, fn ($item1, $item2) => $item1['id'] - $item2['id']);
        $sqlQuery = 'SELECT id,name FROM mdl_course_categories';
        $cat = $DB->get_records_sql($sqlQuery);
        $cat = array_values($cat);
        // https://moodlever.blogspot.com/2011/01/how-to-get-student-list-of-course.html

        for ($i = 0; $i < sizeof($content); $i++) {
            $context = context_course::instance($content[$i]['id']); // https://github.com/marxjohnson moodle-block_quickfindlist/issues/24

            $query = 'select count(u.id) as count from mdl_role_assignments as a, mdl_user as u where contextid=' . $context->id . ' and roleid=5 and a.userid=u.id;';
            $rs = $DB->get_recordset_sql($query);
            $content[$i]['total_student'] = $rs->current()->count;
        }

        if (!$categories) {
            $contentToDisplay = array_slice($content, $index * 4, 4);
        } else {
            $contentToDisplay = array_slice(array_filter($content, fn ($i) => $i['category'] === $categories), 0, 4);
        }


        $pages = [];
        $totalPage = sizeof($content) / 4;
        for ($i = 1; $i <= $totalPage; $i++) {
            $pages[] = ['page' => $i];
        }

        $data = [
            'title' => 'Course List',
            'content' => $contentToDisplay,
            'pages' => $pages,
            'categories' => $cat,
            'pageUrl' => $this->page->url,
        ];
        // check if admin or student?
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']); //getting role id
        $isteacheranywhere = $DB->record_exists('role_assignments', ['userid' => $USER->id, 'roleid' => $roleid]); // if the user is a teacher of any of the courses


        if (is_siteadmin($USER->id) || $isteacheranywhere) {
            $this->content->text = $OUTPUT->render_from_template('block_courseslist/content', $data);
        } else {
            $this->content->text = $OUTPUT->render_from_template('block_courseslist/content_resticted', $data);
        }

        return $this->content;
    }

    /**
     * Defines in which pages this block can be added.
     *
     * @return array of the pages where the block can be added.
     */
    public function applicable_formats()
    {
        return [
            'admin' => false,
            'site-index' => true,
            'course-view' => true,
            'mod' => false,
            'my' => true,
        ];
    }

    public function hide_header()
    {
        return true;
    }
}
