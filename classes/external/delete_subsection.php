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
 * Delete a subsection.
 *
 * @package    local_aimcp
 * @copyright  2026 Kenneth
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class delete_subsection extends external_api {

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id of the subsection'),
            'keepactivities' => new external_value(PARAM_BOOL, 'If true, activities inside the subsection are moved ' .
                'to the parent section before deletion. If false, they are deleted together with the subsection.',
                VALUE_DEFAULT, true),
        ]);
    }

    /**
     * Delete the subsection.
     *
     * @param int $cmid
     * @param bool $keepactivities
     * @return array
     */
    public static function execute(int $cmid, bool $keepactivities = true): array {
        global $DB;
        $params = self::validate_parameters(self::execute_parameters(), compact('cmid', 'keepactivities'));
        [$course, $cm] = helper::require_cm_editing($params['cmid'], 'subsection');

        $delegated = $DB->get_record('course_sections',
            ['course' => $course->id, 'component' => 'mod_subsection', 'itemid' => $cm->instance]);
        $moved = 0;
        $deleted = 0;
        $cmactions = \core_courseformat\formatactions::cm($course);
        if ($delegated && trim((string) $delegated->sequence) !== '') {
            $childids = array_filter(array_map('intval', explode(',', $delegated->sequence)));
            foreach ($childids as $childid) {
                if ($params['keepactivities']) {
                    $cmactions->move_end_section($childid, (int) $cm->section);
                    $moved++;
                } else {
                    $cmactions->delete($childid);
                    $deleted++;
                }
            }
        }
        $cmactions->delete($cm->id);
        rebuild_course_cache($course->id, true);

        return [
            'deleted' => true,
            'movedactivities' => $moved,
            'deletedactivities' => $deleted,
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
            'deleted' => new external_value(PARAM_BOOL, 'True when the subsection was deleted'),
            'movedactivities' => new external_value(PARAM_INT, 'Number of activities moved to the parent section'),
            'deletedactivities' => new external_value(PARAM_INT,
                'Number of activities deleted with the subsection'),
            'warnings' => new external_warnings(),
        ]);
    }

}
