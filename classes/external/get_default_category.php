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
 * Tell which category new courses will be created in for the calling user.
 *
 * @package    local_aimcp
 * @copyright  2026 Kenneth
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_default_category extends external_api {

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        // At least one parameter is needed: MCP clients reject an input schema with an empty properties list.
        return new external_function_parameters([
            'includepath' => new external_value(PARAM_BOOL, 'Include the full category path in the answer',
                VALUE_DEFAULT, true),
        ]);
    }

    /**
     * Resolve the default category.
     *
     * @param bool $includepath
     * @return array
     */
    public static function execute(bool $includepath = true): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), compact('includepath'));
        self::validate_context(\core\context\system::instance());
        $category = helper::default_category((int) $USER->id);
        if (!$category) {
            return ['categoryid' => 0, 'categoryname' => '', 'path' => '', 'cancreate' => false];
        }
        $context = \core\context\coursecat::instance($category->id);
        $names = [];
        $parents = $params['includepath'] ? $category->get_parents() : [];
        foreach ($parents as $parentid) {
            $names[] = \core_course_category::get($parentid, IGNORE_MISSING, true)?->get_formatted_name() ?? '';
        }
        $names[] = $category->get_formatted_name();
        return [
            'categoryid' => (int) $category->id,
            'categoryname' => $category->get_formatted_name(),
            'path' => implode(' / ', $names),
            'cancreate' => has_capability('moodle/course:create', $context),
        ];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'categoryid' => new external_value(PARAM_INT, 'Default category id (0 = none configured)'),
            'categoryname' => new external_value(PARAM_TEXT, 'Category name'),
            'path' => new external_value(PARAM_TEXT, 'Full category path'),
            'cancreate' => new external_value(PARAM_BOOL, 'Whether the user may create courses there'),
        ]);
    }

}
