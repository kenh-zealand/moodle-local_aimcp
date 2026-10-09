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
 * Create a subsection.
 *
 * @package    local_aimcp
 * @copyright  2026 Kenneth
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_subsection extends external_api {

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'section' => new external_value(PARAM_INT,
                'Number of the (normal) parent section the subsection is placed in. Subsections cannot be nested.'),
            'name' => new external_value(PARAM_TEXT, 'Name of the subsection'),
            'summary' => new external_value(PARAM_RAW, 'Optional HTML summary shown at the top of the subsection',
                VALUE_DEFAULT, ''),
            'visible' => new external_value(PARAM_BOOL, 'Visible to students', VALUE_DEFAULT, true),
        ]);
    }

    /**
     * Create the subsection.
     *
     * @param int $courseid
     * @param int $section
     * @param string $name
     * @param string $summary
     * @param bool $visible
     * @return array
     */
    public static function execute(int $courseid, int $section, string $name, string $summary = '',
            bool $visible = true): array {
        global $DB;
        $params = self::validate_parameters(self::execute_parameters(),
            compact('courseid', 'section', 'name', 'summary', 'visible'));
        [$course] = helper::require_course_editing($params['courseid']);

        helper::ensure_section($course, $params['section']);
        $parent = get_fast_modinfo($course)->get_section_info($params['section']);
        if ($parent && $parent->is_delegated()) {
            throw new \invalid_parameter_exception('Subsections cannot be nested: section ' . $params['section'] .
                ' is itself a subsection.');
        }

        $mi = helper::create_module($course, 'subsection', $params['section'], $params['name'], '',
            (int) $params['visible']);

        $delegated = $DB->get_record('course_sections',
            ['course' => $course->id, 'component' => 'mod_subsection', 'itemid' => $mi->instance], '*', MUST_EXIST);
        if ($params['summary'] !== '') {
            $sectioninfo = get_fast_modinfo($course)->get_section_info_by_id($delegated->id);
            \core_courseformat\formatactions::section($course)->update($sectioninfo,
                ['summary' => $params['summary'], 'summaryformat' => FORMAT_HTML]);
        }

        $result = helper::created_result($mi);
        $result['sectionid'] = (int) $delegated->id;
        $result['sectionnum'] = (int) $delegated->section;
        return $result;
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cmid' => new external_value(PARAM_INT, 'Course module id of the subsection (use it to delete)'),
            'instanceid' => new external_value(PARAM_INT, 'Instance id in the subsection table'),
            'name' => new external_value(PARAM_TEXT, 'Subsection name'),
            'url' => new external_value(PARAM_URL, 'Link to the subsection'),
            'sectionid' => new external_value(PARAM_INT, 'Id of the delegated section'),
            'sectionnum' => new external_value(PARAM_INT, 'Section number of the subsection. Pass it as "section" ' .
                'to the create_* functions to put activities inside it.'),
            'warnings' => new external_warnings(),
        ]);
    }

}
