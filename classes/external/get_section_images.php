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
use local_aimcp\local\gridimage;
use local_aimcp\local\helper;

/**
 * List the Grid-format tile images of a course: which sections have an image, file name and alt text.
 *
 * @package    local_aimcp
 * @copyright  2026 Kenneth
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_section_images extends external_api {

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'includesubsections' => new external_value(PARAM_BOOL, 'Also list subsections (they are not shown as tiles)',
                VALUE_DEFAULT, false),
        ]);
    }

    /**
     * List the images.
     *
     * @param int $courseid
     * @param bool $includesubsections
     * @return array
     */
    public static function execute(int $courseid, bool $includesubsections = false): array {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');
        $params = self::validate_parameters(self::execute_parameters(), compact('courseid', 'includesubsections'));
        [$course] = helper::require_course_editing($params['courseid']);
        $format = gridimage::require_grid($course);

        $sections = [];
        foreach (get_fast_modinfo($course)->get_section_info_all() as $sectioninfo) {
            if (!empty($sectioninfo->component) && !$params['includesubsections']) {
                continue;
            }
            $sections[] = gridimage::describe($course, $format, $sectioninfo);
        }
        return [
            'courseid' => (int) $course->id,
            'format' => $format->get_format(),
            'sections' => $sections,
            'warnings' => [],
        ];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'format' => new external_value(PARAM_PLUGIN, 'Course format'),
            'sections' => new external_multiple_structure(gridimage::section_structure()),
            'warnings' => new external_warnings(),
        ]);
    }
}
