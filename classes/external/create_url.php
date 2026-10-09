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
 * Create a URL resource.
 *
 * @package    local_aimcp
 * @copyright  2026 Kenneth
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_url extends external_api {

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'section' => new external_value(PARAM_INT, 'Section number'),
            'name' => new external_value(PARAM_TEXT, 'Link title'),
            'externalurl' => new external_value(PARAM_URL, 'The web address to link to'),
            'intro' => new external_value(PARAM_RAW, 'Description (HTML)', VALUE_DEFAULT, ''),
            'visible' => new external_value(PARAM_BOOL, 'Visible to students', VALUE_DEFAULT, true),
        ]);
    }

    /**
     * Create the URL resource.
     *
     * @param int $courseid
     * @param int $section
     * @param string $name
     * @param string $externalurl
     * @param string $intro
     * @param bool $visible
     * @return array
     */
    public static function execute(int $courseid, int $section, string $name, string $externalurl,
            string $intro = '', bool $visible = true): array {
        global $CFG;
        $params = self::validate_parameters(self::execute_parameters(),
            compact('courseid', 'section', 'name', 'externalurl', 'intro', 'visible'));
        [$course] = helper::require_course_editing($params['courseid']);
        require_once($CFG->libdir . '/resourcelib.php');
        $mi = helper::create_module($course, 'url', $params['section'], $params['name'], $params['intro'],
            (int) $params['visible'], [
                'externalurl' => $params['externalurl'],
                'display' => RESOURCELIB_DISPLAY_AUTO,
                'printintro' => 1,
            ]);
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
