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
 * Create a Text and media area (label).
 *
 * @package    local_aimcp
 * @copyright  2026 Kenneth
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_label extends external_api {

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'section' => new external_value(PARAM_INT, 'Section number (0 = general section at the top)'),
            'content' => new external_value(PARAM_RAW, 'HTML shown directly on the course page'),
            'name' => new external_value(PARAM_TEXT, 'Optional internal name (derived from content if empty)',
                VALUE_DEFAULT, ''),
            'visible' => new external_value(PARAM_BOOL, 'Visible to students', VALUE_DEFAULT, true),
        ]);
    }

    /**
     * Create the label.
     *
     * @param int $courseid
     * @param int $section
     * @param string $content
     * @param string $name
     * @param bool $visible
     * @return array
     */
    public static function execute(int $courseid, int $section, string $content, string $name = '',
            bool $visible = true): array {
        $params = self::validate_parameters(self::execute_parameters(),
            compact('courseid', 'section', 'content', 'name', 'visible'));
        [$course] = helper::require_course_editing($params['courseid']);
        $mi = helper::create_module($course, 'label', $params['section'], $params['name'], $params['content'],
            (int) $params['visible']);
        if ($mi->name === '' || $mi->name === null) {
            global $DB;
            $mi->name = (string) $DB->get_field('label', 'name', ['id' => $mi->instance]);
        }
        return helper::created_result($mi);
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return helper::created_returns();
    }

}
