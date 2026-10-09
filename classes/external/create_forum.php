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
 * Create a Forum activity.
 *
 * @package    local_aimcp
 * @copyright  2026 Kenneth
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_forum extends external_api {

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'section' => new external_value(PARAM_INT, 'Section number'),
            'name' => new external_value(PARAM_TEXT, 'Forum name'),
            'intro' => new external_value(PARAM_RAW, 'Forum description / instructions (HTML)', VALUE_DEFAULT, ''),
            'type' => new external_value(PARAM_ALPHA,
                'Forum type: general, qanda (question and answer), single (one simple discussion), eachuser, blog',
                VALUE_DEFAULT, 'general'),
            'visible' => new external_value(PARAM_BOOL, 'Visible to students', VALUE_DEFAULT, true),
        ]);
    }

    /**
     * Create the forum.
     *
     * @param int $courseid
     * @param int $section
     * @param string $name
     * @param string $intro
     * @param string $type
     * @param bool $visible
     * @return array
     */
    public static function execute(int $courseid, int $section, string $name, string $intro = '',
            string $type = 'general', bool $visible = true): array {
        $params = self::validate_parameters(self::execute_parameters(),
            compact('courseid', 'section', 'name', 'intro', 'type', 'visible'));
        if (!in_array($params['type'], ['general', 'qanda', 'single', 'eachuser', 'blog'])) {
            throw new \invalid_parameter_exception('Unknown forum type: ' . $params['type']);
        }
        [$course] = helper::require_course_editing($params['courseid']);
        $mi = helper::create_module($course, 'forum', $params['section'], $params['name'], $params['intro'],
            (int) $params['visible'], [
                'type' => $params['type'],
                'forcesubscribe' => 0,
                'trackingtype' => 1,
                'assessed' => 0,
                'scale' => 0,
                'grade_forum' => 0,
                'maxbytes' => 0,
                'maxattachments' => 9,
                'displaywordcount' => 0,
                'blockafter' => 0,
                'blockperiod' => 0,
                'warnafter' => 0,
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
