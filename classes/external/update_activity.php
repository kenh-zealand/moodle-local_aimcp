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
 * Change name, description or page content of an activity.
 *
 * @package    local_aimcp
 * @copyright  2026 Kenneth
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class update_activity extends external_api {

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'name' => new external_value(PARAM_TEXT, 'New name; null/omitted = unchanged', VALUE_DEFAULT, null),
            'intro' => new external_value(PARAM_RAW, 'New description HTML (for a Text and media area this is the ' .
                'displayed content); null = unchanged', VALUE_DEFAULT, null),
            'content' => new external_value(PARAM_RAW, 'New page content HTML (mod_page only); null = unchanged',
                VALUE_DEFAULT, null),
        ]);
    }

    /**
     * Update the activity.
     *
     * @param int $cmid
     * @param string|null $name
     * @param string|null $intro
     * @param string|null $content
     * @return array
     */
    public static function execute(int $cmid, ?string $name = null, ?string $intro = null,
            ?string $content = null): array {
        global $DB;
        $params = self::validate_parameters(self::execute_parameters(), compact('cmid', 'name', 'intro', 'content'));
        [$course, $cm, $context] = helper::require_cm_editing($params['cmid']);
        $warnings = [];

        $record = ['id' => $cm->instance];
        if ($params['intro'] !== null) {
            if (plugin_supports('mod', $cm->modname, FEATURE_MOD_INTRO, true)) {
                $record['intro'] = $params['intro'];
                $record['introformat'] = FORMAT_HTML;
            } else {
                $warnings[] = ['item' => 'intro', 'warningcode' => 'nointro',
                    'message' => 'This activity type has no description; intro ignored.'];
            }
        }
        if ($params['content'] !== null) {
            if ($cm->modname === 'page') {
                $record['content'] = $params['content'];
                $record['contentformat'] = FORMAT_HTML;
                $record['revision'] = (int) $DB->get_field('page', 'revision', ['id' => $cm->instance]) + 1;
            } else {
                $warnings[] = ['item' => 'content', 'warningcode' => 'notpage',
                    'message' => 'content is only used for Page activities; ignored.'];
            }
        }
        if (count($record) > 1) {
            $record['timemodified'] = time();
            $DB->update_record($cm->modname, (object) $record);
        }
        if ($params['name'] !== null && $params['name'] !== '') {
            \core_courseformat\formatactions::cm($course)->rename($cm->id, $params['name']);
        } else if ($cm->modname === 'label' && $params['intro'] !== null) {
            // Keep the label's internal name in step with its content.
            $labelname = shorten_text(trim(html_to_text($params['intro'], 0, false)), 50);
            if ($labelname !== '') {
                $DB->set_field('label', 'name', $labelname, ['id' => $cm->instance]);
            }
        }

        rebuild_course_cache($course->id, true);
        \core\event\course_module_updated::create_from_cm(get_fast_modinfo($course)->get_cm($cm->id))->trigger();
        return ['cmid' => (int) $cm->id, 'warnings' => $warnings];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'warnings' => new external_warnings(),
        ]);
    }

}
