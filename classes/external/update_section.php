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
 * Set name, summary and/or visibility of a course section.
 *
 * @package    local_aimcp
 * @copyright  2026 Kenneth
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class update_section extends external_api {

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'section' => new external_value(PARAM_INT, 'Section number (created if missing)'),
            'name' => new external_value(PARAM_TEXT, 'New section name; null/omitted = unchanged', VALUE_DEFAULT, null),
            'summary' => new external_value(PARAM_RAW, 'New section summary (HTML); null/omitted = unchanged',
                VALUE_DEFAULT, null),
            'visible' => new external_value(PARAM_BOOL, 'Section visibility; null/omitted = unchanged',
                VALUE_DEFAULT, null),
        ]);
    }

    /**
     * Update the section.
     *
     * @param int $courseid
     * @param int $section
     * @param string|null $name
     * @param string|null $summary
     * @param bool|null $visible
     * @return array
     */
    public static function execute(int $courseid, int $section, ?string $name = null, ?string $summary = null,
            ?bool $visible = null): array {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');
        $params = self::validate_parameters(self::execute_parameters(),
            compact('courseid', 'section', 'name', 'summary', 'visible'));
        [$course, $context] = helper::require_course_editing($params['courseid']);
        require_capability('moodle/course:update', $context);

        helper::ensure_section($course, $params['section']);
        $actions = \core_courseformat\formatactions::section($course);
        $sectioninfo = get_fast_modinfo($course)->get_section_info($params['section'], MUST_EXIST);

        $fields = [];
        if ($params['name'] !== null) {
            $fields['name'] = $params['name'];
        }
        if ($params['summary'] !== null) {
            $fields['summary'] = $params['summary'];
            $fields['summaryformat'] = FORMAT_HTML;
        }
        if ($fields) {
            $actions->update($sectioninfo, $fields);
        }
        if ($params['visible'] !== null) {
            require_capability('moodle/course:sectionvisibility', $context);
            $sectioninfo = get_fast_modinfo($course)->get_section_info($params['section'], MUST_EXIST);
            $actions->set_visibility($sectioninfo, (bool) $params['visible']);
        }

        $sectioninfo = get_fast_modinfo($course)->get_section_info($params['section'], MUST_EXIST);
        return [
            'sectionid' => (int) $sectioninfo->id,
            'section' => (int) $sectioninfo->section,
            'name' => get_section_name($course, $sectioninfo),
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
            'sectionid' => new external_value(PARAM_INT, 'Section id'),
            'section' => new external_value(PARAM_INT, 'Section number'),
            'name' => new external_value(PARAM_TEXT, 'Displayed section name'),
            'warnings' => new external_warnings(),
        ]);
    }

}
