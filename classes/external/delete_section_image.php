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
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use local_aimcp\local\gridimage;
use local_aimcp\local\helper;

/**
 * Delete the Grid-format tile image of a course section.
 *
 * @package    local_aimcp
 * @copyright  2026 Kenneth
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class delete_section_image extends external_api {

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id (the course must use the Grid format)'),
            'section' => new external_value(PARAM_INT, 'Section number'),
            'clearalttext' => new external_value(PARAM_BOOL, 'Also clear the alt text', VALUE_DEFAULT, true),
        ]);
    }

    /**
     * Delete the image.
     *
     * @param int $courseid
     * @param int $section
     * @param bool $clearalttext
     * @return array
     */
    public static function execute(int $courseid, int $section, bool $clearalttext = true): array {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');
        $params = self::validate_parameters(self::execute_parameters(), compact('courseid', 'section', 'clearalttext'));
        [$course, $context] = helper::require_course_editing($params['courseid']);
        require_capability('moodle/course:update', $context);
        $format = gridimage::require_grid($course);
        $sectioninfo = get_fast_modinfo($course)->get_section_info($params['section'], MUST_EXIST);

        $deleted = gridimage::delete($course, $sectioninfo);
        if ($params['clearalttext']) {
            gridimage::set_alttext($format, $sectioninfo, '');
        }

        $warnings = [];
        if (!$deleted) {
            $warnings[] = ['item' => 'section', 'itemid' => (int) $sectioninfo->id, 'warningcode' => 'noimage',
                'message' => 'The section had no image.'];
        }
        return [
            'sectionid' => (int) $sectioninfo->id,
            'section' => (int) $sectioninfo->section,
            'deleted' => $deleted,
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
            'sectionid' => new external_value(PARAM_INT, 'Section id'),
            'section' => new external_value(PARAM_INT, 'Section number'),
            'deleted' => new external_value(PARAM_BOOL, 'True if an image was deleted'),
            'warnings' => new external_warnings(),
        ]);
    }
}
