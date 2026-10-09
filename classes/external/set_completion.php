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
 * Set activity completion on one or more activities.
 *
 * @package    local_aimcp
 * @copyright  2026 Kenneth
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class set_completion extends external_api {

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Course module id'), 'Activities to update (may be of different types)'),
            'completion' => new external_single_structure([
                'tracking' => new external_value(PARAM_ALPHA, 'none | manual (student ticks it off) | auto ' .
                    '(Moodle marks it when the conditions below are met)', VALUE_DEFAULT, 'none'),
                'view' => new external_value(PARAM_BOOL, 'auto: student must view the activity', VALUE_DEFAULT, false),
                'usegrade' => new external_value(PARAM_BOOL, 'auto: student must receive a grade', VALUE_DEFAULT, false),
                'passgrade' => new external_value(PARAM_BOOL,
                    'auto: student must receive a passing grade (set gradepass as well)', VALUE_DEFAULT, false),
                'gradepass' => new external_value(PARAM_FLOAT, 'Grade to pass (0 = leave unchanged)', VALUE_DEFAULT, 0),
                'submit' => new external_value(PARAM_BOOL, 'auto, assignment: student must submit', VALUE_DEFAULT, false),
                'posts' => new external_value(PARAM_INT, 'auto, forum: number of posts (discussions or replies)',
                    VALUE_DEFAULT, 0),
                'discussions' => new external_value(PARAM_INT, 'auto, forum: number of discussions', VALUE_DEFAULT, 0),
                'replies' => new external_value(PARAM_INT, 'auto, forum: number of replies', VALUE_DEFAULT, 0),
                'minattempts' => new external_value(PARAM_INT, 'auto, quiz: minimum number of attempts',
                    VALUE_DEFAULT, 0),
                'expected' => new external_value(PARAM_INT, 'Expected completion date (unix timestamp, 0 = none)',
                    VALUE_DEFAULT, 0),
            ], 'Completion settings applied to every activity; module-specific conditions are ignored where they ' .
                'do not apply.'),
        ]);
    }

    /**
     * Apply the completion settings.
     *
     * @param array $cmids
     * @param array $completion
     * @return array
     */
    public static function execute(array $cmids, array $completion): array {
        global $CFG, $DB;
        require_once($CFG->libdir . '/completionlib.php');
        require_once($CFG->libdir . '/gradelib.php');
        $params = self::validate_parameters(self::execute_parameters(), compact('cmids', 'completion'));
        $c = $params['completion'];
        if (!in_array($c['tracking'], ['none', 'manual', 'auto'])) {
            throw new \invalid_parameter_exception('tracking must be none, manual or auto');
        }
        $trackingmap = ['none' => COMPLETION_TRACKING_NONE, 'manual' => COMPLETION_TRACKING_MANUAL,
            'auto' => COMPLETION_TRACKING_AUTOMATIC];
        $auto = $c['tracking'] === 'auto';

        $warnings = [];
        $activities = [];
        $courses = [];
        foreach (array_unique($params['cmids']) as $cmid) {
            try {
                [$course, $cm] = helper::require_cm_editing($cmid);
            } catch (\Exception $e) {
                $warnings[] = ['item' => 'cm', 'itemid' => $cmid, 'warningcode' => 'cmerror',
                    'message' => $e->getMessage()];
                continue;
            }
            if ($c['tracking'] !== 'none' && empty($course->enablecompletion)) {
                $DB->set_field('course', 'enablecompletion', 1, ['id' => $course->id]);
                $course->enablecompletion = 1;
                $warnings[] = ['item' => 'course', 'itemid' => $course->id, 'warningcode' => 'completionenabled',
                    'message' => 'Completion tracking was switched on for the course.'];
            }
            $modname = $cm->modname;
            $hasgrade = plugin_supports('mod', $modname, FEATURE_GRADE_HAS_GRADE, false);

            $usegrade = $auto && ($c['usegrade'] || $c['passgrade']) && $hasgrade;
            $view = $auto && $c['view'] && plugin_supports('mod', $modname, FEATURE_COMPLETION_TRACKS_VIEWS, false);
            $cmrecord = (object) [
                'id' => $cm->id,
                'completion' => $trackingmap[$c['tracking']],
                'completionview' => $view ? 1 : 0,
                'completiongradeitemnumber' => $usegrade ? 0 : null,
                'completionpassgrade' => ($usegrade && $c['passgrade']) ? 1 : 0,
                'completionexpected' => $c['tracking'] === 'none' ? 0 : max(0, $c['expected']),
            ];
            $DB->update_record('course_modules', $cmrecord);

            // Module specific rules.
            $modfields = [];
            if ($modname === 'assign') {
                $modfields['completionsubmit'] = ($auto && $c['submit']) ? 1 : 0;
            } else if ($modname === 'forum') {
                $modfields['completionposts'] = $auto ? max(0, $c['posts']) : 0;
                $modfields['completiondiscussions'] = $auto ? max(0, $c['discussions']) : 0;
                $modfields['completionreplies'] = $auto ? max(0, $c['replies']) : 0;
            } else if ($modname === 'quiz') {
                $modfields['completionminattempts'] = $auto ? max(0, $c['minattempts']) : 0;
                $modfields['completionattemptsexhausted'] = 0;
            }
            if ($modfields) {
                $modfields['id'] = $cm->instance;
                $DB->update_record($modname, (object) $modfields);
            }

            if ($c['passgrade'] && $c['gradepass'] > 0 && $hasgrade) {
                $gi = \grade_item::fetch(['itemtype' => 'mod', 'itemmodule' => $modname,
                    'iteminstance' => $cm->instance, 'itemnumber' => 0, 'courseid' => $course->id]);
                if ($gi) {
                    $gi->gradepass = $c['gradepass'];
                    $gi->update();
                    if ($modname === 'quiz') {
                        $DB->set_field('quiz', 'timemodified', time(), ['id' => $cm->instance]);
                    }
                } else {
                    $warnings[] = ['item' => 'cm', 'itemid' => $cm->id, 'warningcode' => 'nogradeitem',
                        'message' => 'No grade item found; gradepass was not set.'];
                }
            }
            if ($auto && ($c['usegrade'] || $c['passgrade']) && !$hasgrade) {
                $warnings[] = ['item' => 'cm', 'itemid' => $cm->id, 'warningcode' => 'nograde',
                    'message' => "Module $modname has no grade; grade conditions ignored."];
            }

            \core_completion\api::update_completion_date_event($cm->id, $modname, $cm->instance,
                $cmrecord->completionexpected);
            $courses[$course->id] = $course->id;
            $activities[] = [
                'cmid' => (int) $cm->id,
                'modname' => $modname,
                'tracking' => $c['tracking'],
                'view' => (bool) $cmrecord->completionview,
                'usegrade' => $usegrade,
            ];
        }
        foreach ($courses as $courseid) {
            rebuild_course_cache($courseid, true);
        }
        return ['activities' => $activities, 'warnings' => $warnings];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'activities' => new external_multiple_structure(new external_single_structure([
                'cmid' => new external_value(PARAM_INT, 'Course module id'),
                'modname' => new external_value(PARAM_PLUGIN, 'Module type'),
                'tracking' => new external_value(PARAM_ALPHA, 'Resulting tracking: none, manual or auto'),
                'view' => new external_value(PARAM_BOOL, 'Requires view'),
                'usegrade' => new external_value(PARAM_BOOL, 'Requires grade'),
            ])),
            'warnings' => new external_warnings(),
        ]);
    }

}
