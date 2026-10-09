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
 * Create an Assignment.
 *
 * @package    local_aimcp
 * @copyright  2026 Kenneth
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_assign extends external_api {

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'section' => new external_value(PARAM_INT, 'Section number'),
            'name' => new external_value(PARAM_TEXT, 'Assignment name'),
            'intro' => new external_value(PARAM_RAW, 'Assignment description (HTML)', VALUE_DEFAULT, ''),
            'activity' => new external_value(PARAM_RAW,
                'Activity instructions shown only on the submission page (HTML)', VALUE_DEFAULT, ''),
            'allowsubmissionsfromdate' => new external_value(PARAM_INT, 'Unix timestamp, 0 = no limit',
                VALUE_DEFAULT, 0),
            'duedate' => new external_value(PARAM_INT, 'Unix timestamp, 0 = no due date', VALUE_DEFAULT, 0),
            'cutoffdate' => new external_value(PARAM_INT, 'Unix timestamp, 0 = no cut-off', VALUE_DEFAULT, 0),
            'onlinetext' => new external_value(PARAM_BOOL, 'Allow online text submissions', VALUE_DEFAULT, true),
            'wordlimit' => new external_value(PARAM_INT, 'Word limit for online text, 0 = none', VALUE_DEFAULT, 0),
            'filesubmissions' => new external_value(PARAM_BOOL, 'Allow file submissions', VALUE_DEFAULT, false),
            'maxfiles' => new external_value(PARAM_INT, 'Max number of files', VALUE_DEFAULT, 1),
            'grade' => new external_value(PARAM_INT, 'Maximum points, 0 = no grading', VALUE_DEFAULT, 100),
            'teamsubmission' => new external_value(PARAM_BOOL, 'Students submit in groups', VALUE_DEFAULT, false),
            'visible' => new external_value(PARAM_BOOL, 'Visible to students', VALUE_DEFAULT, true),
        ]);
    }

    /**
     * Create the assignment.
     *
     * @param int $courseid
     * @param int $section
     * @param string $name
     * @param string $intro
     * @param string $activity
     * @param int $allowsubmissionsfromdate
     * @param int $duedate
     * @param int $cutoffdate
     * @param bool $onlinetext
     * @param int $wordlimit
     * @param bool $filesubmissions
     * @param int $maxfiles
     * @param int $grade
     * @param bool $teamsubmission
     * @param bool $visible
     * @return array
     */
    public static function execute(int $courseid, int $section, string $name, string $intro = '',
            string $activity = '', int $allowsubmissionsfromdate = 0, int $duedate = 0, int $cutoffdate = 0,
            bool $onlinetext = true, int $wordlimit = 0, bool $filesubmissions = false, int $maxfiles = 1,
            int $grade = 100, bool $teamsubmission = false, bool $visible = true): array {
        global $CFG;
        $params = self::validate_parameters(self::execute_parameters(), compact('courseid', 'section', 'name',
            'intro', 'activity', 'allowsubmissionsfromdate', 'duedate', 'cutoffdate', 'onlinetext', 'wordlimit',
            'filesubmissions', 'maxfiles', 'grade', 'teamsubmission', 'visible'));
        [$course] = helper::require_course_editing($params['courseid']);
        require_once($CFG->dirroot . '/mod/assign/locallib.php');

        $warnings = [];
        if (!$params['onlinetext'] && !$params['filesubmissions']) {
            $warnings[] = ['item' => 'assign', 'warningcode' => 'nosubmissiontypes',
                'message' => 'Neither online text nor file submissions are enabled; students cannot submit anything.'];
        }
        $cfg = get_config('assign');
        $mi = helper::create_module($course, 'assign', $params['section'], $params['name'], $params['intro'],
            (int) $params['visible'], [
                'activityeditor' => [
                    'text' => $params['activity'],
                    'format' => FORMAT_HTML,
                    'itemid' => file_get_unused_draft_itemid(),
                ],
                'alwaysshowdescription' => 1,
                'submissiondrafts' => 0,
                'requiresubmissionstatement' => 0,
                'sendnotifications' => 0,
                'sendstudentnotifications' => 1,
                'sendlatenotifications' => 0,
                'allowsubmissionsfromdate' => $params['allowsubmissionsfromdate'],
                'duedate' => $params['duedate'],
                'cutoffdate' => $params['cutoffdate'],
                'gradingduedate' => 0,
                'grade' => max(0, $params['grade']),
                'teamsubmission' => $params['teamsubmission'] ? 1 : 0,
                'requireallteammemberssubmit' => 0,
                'teamsubmissiongroupingid' => 0,
                'preventsubmissionnotingroup' => 0,
                'blindmarking' => 0,
                'hidegrader' => 0,
                'attemptreopenmethod' => !empty($cfg->attemptreopenmethod) ? $cfg->attemptreopenmethod : 'untilpass',
                'maxattempts' => isset($cfg->maxattempts) ? (int) $cfg->maxattempts : 1,
                'markingworkflow' => 0,
                'markingallocation' => 0,
                'markercount' => 0,
                'multimarkmethod' => null,
                'multimarkrounding' => null,
                'markinganonymous' => 0,
                'timelimit' => 0,
                'submissionattachments' => 0,
                'gradepenalty' => 0,
                'completionsubmit' => 0,
                'assignsubmission_onlinetext_enabled' => $params['onlinetext'] ? 1 : 0,
                'assignsubmission_onlinetext_wordlimit_enabled' => $params['wordlimit'] > 0 ? 1 : 0,
                'assignsubmission_onlinetext_wordlimit' => max(0, $params['wordlimit']),
                'assignsubmission_file_enabled' => $params['filesubmissions'] ? 1 : 0,
                'assignsubmission_file_maxfiles' => max(1, $params['maxfiles']),
                'assignsubmission_file_maxsizebytes' => 0,
                'assignsubmission_file_filetypes' => '',
                'assignsubmission_comments_enabled' => 1,
                'assignfeedback_comments_enabled' => 1,
                'assignfeedback_comments_commentinline' => 0,
                'assignfeedback_file_enabled' => 0,
                'assignfeedback_editpdf_enabled' => 0,
            ]);
        return helper::created_result($mi, $warnings);
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
