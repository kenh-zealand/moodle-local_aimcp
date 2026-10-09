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

namespace local_aimcp\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use local_aimcp\local\helper;

/**
 * Create a course, by default in the calling user's AI category.
 *
 * @package    local_aimcp
 * @copyright  2026 Kenneth
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_course extends external_api {

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'fullname' => new external_value(PARAM_TEXT, 'Course full name'),
            'shortname' => new external_value(PARAM_TEXT, 'Course short name (must be unique on the site)'),
            'categoryid' => new external_value(PARAM_INT, 'Category id. 0 = automatic: the default AI category of ' .
                'the calling user (internal users: the internal AI category; external users: their own ' .
                'subcategory). Normally leave this at 0.', VALUE_DEFAULT, 0),
            'summary' => new external_value(PARAM_RAW, 'Course summary (HTML)', VALUE_DEFAULT, ''),
            'format' => new external_value(PARAM_PLUGIN, 'Course format, e.g. topics or weeks (empty = site default)',
                VALUE_DEFAULT, ''),
            'numsections' => new external_value(PARAM_INT, 'Number of sections (-1 = site default)', VALUE_DEFAULT, -1),
            'startdate' => new external_value(PARAM_INT, 'Unix timestamp (0 = today)', VALUE_DEFAULT, 0),
            'enddate' => new external_value(PARAM_INT, 'Unix timestamp (0 = no end date)', VALUE_DEFAULT, 0),
            'visible' => new external_value(PARAM_BOOL, 'Visible to students', VALUE_DEFAULT, true),
            'enablecompletion' => new external_value(PARAM_BOOL, 'Enable completion tracking', VALUE_DEFAULT, true),
        ]);
    }

    /**
     * Create the course.
     *
     * @param string $fullname
     * @param string $shortname
     * @param int $categoryid
     * @param string $summary
     * @param string $format
     * @param int $numsections
     * @param int $startdate
     * @param int $enddate
     * @param bool $visible
     * @param bool $enablecompletion
     * @return array
     */
    public static function execute(string $fullname, string $shortname, int $categoryid = 0, string $summary = '',
            string $format = '', int $numsections = -1, int $startdate = 0, int $enddate = 0, bool $visible = true,
            bool $enablecompletion = true): array {
        global $CFG, $DB, $USER;
        require_once($CFG->dirroot . '/course/lib.php');
        $params = self::validate_parameters(self::execute_parameters(), compact('fullname', 'shortname', 'categoryid',
            'summary', 'format', 'numsections', 'startdate', 'enddate', 'visible', 'enablecompletion'));

        $warnings = [];
        $usedefault = empty($params['categoryid']);
        if ($usedefault) {
            $category = helper::default_category((int) $USER->id);
            if (!$category) {
                throw new \moodle_exception('nodefaultcategory', 'local_aimcp');
            }
        } else {
            $category = \core_course_category::get($params['categoryid']);
        }
        $context = \core\context\coursecat::instance($category->id);
        self::validate_context($context);
        require_capability('moodle/course:create', $context);

        if (trim($params['shortname']) === '' || trim($params['fullname']) === '') {
            throw new \invalid_parameter_exception('fullname and shortname must not be empty');
        }
        if ($DB->record_exists('course', ['shortname' => $params['shortname']])) {
            throw new \moodle_exception('shortnametaken', '', '', $params['shortname']);
        }

        $courseconfig = get_config('moodlecourse');
        $format = $params['format'] !== '' ? $params['format'] : ($courseconfig->format ?? 'topics');
        $formats = \core_component::get_plugin_list('format');
        if (!isset($formats[$format])) {
            $warnings[] = ['item' => 'format', 'warningcode' => 'unknownformat',
                'message' => "Unknown course format '$format'; site default used."];
            $format = $courseconfig->format ?? 'topics';
        }
        $data = (object) [
            'category' => $category->id,
            'fullname' => $params['fullname'],
            'shortname' => $params['shortname'],
            'summary' => $params['summary'],
            'summaryformat' => FORMAT_HTML,
            'format' => $format,
            'numsections' => $params['numsections'] >= 0 ? $params['numsections'] : ($courseconfig->numsections ?? 4),
            'startdate' => $params['startdate'] ?: usergetmidnight(time()),
            'enddate' => $params['enddate'],
            'visible' => $params['visible'] ? 1 : 0,
            'enablecompletion' => ($params['enablecompletion'] && !empty($CFG->enablecompletion)) ? 1 : 0,
        ];
        $course = create_course($data);

        return [
            'id' => (int) $course->id,
            'fullname' => (string) $course->fullname,
            'shortname' => (string) $course->shortname,
            'categoryid' => (int) $category->id,
            'categoryname' => $category->get_formatted_name(),
            'defaultcategoryused' => $usedefault,
            'url' => (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
            'warnings' => $warnings,
        ];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Course id'),
            'fullname' => new external_value(PARAM_TEXT, 'Course full name'),
            'shortname' => new external_value(PARAM_TEXT, 'Course short name'),
            'categoryid' => new external_value(PARAM_INT, 'Category the course was created in'),
            'categoryname' => new external_value(PARAM_TEXT, 'Category name'),
            'defaultcategoryused' => new external_value(PARAM_BOOL, 'True if the automatic default category was used'),
            'url' => new external_value(PARAM_URL, 'Link to the course'),
            'warnings' => new external_warnings(),
        ]);
    }

}
