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
 * Create a Quiz, optionally with GIFT questions.
 *
 * @package    local_aimcp
 * @copyright  2026 Kenneth
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_quiz extends external_api {

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'section' => new external_value(PARAM_INT, 'Section number'),
            'name' => new external_value(PARAM_TEXT, 'Quiz name'),
            'intro' => new external_value(PARAM_RAW, 'Quiz description (HTML)', VALUE_DEFAULT, ''),
            'gift' => new external_value(PARAM_RAW, 'Optional questions in Moodle GIFT format. They are imported ' .
                'into the quiz question bank and added to the quiz in order.', VALUE_DEFAULT, ''),
            'timeopen' => new external_value(PARAM_INT, 'Unix timestamp, 0 = always open', VALUE_DEFAULT, 0),
            'timeclose' => new external_value(PARAM_INT, 'Unix timestamp, 0 = never closes', VALUE_DEFAULT, 0),
            'timelimit' => new external_value(PARAM_INT, 'Time limit in seconds, 0 = none', VALUE_DEFAULT, 0),
            'attempts' => new external_value(PARAM_INT, 'Attempts allowed, 0 = unlimited', VALUE_DEFAULT, 0),
            'preferredbehaviour' => new external_value(PARAM_ALPHA,
                'Question behaviour: deferredfeedback, interactive, immediatefeedback', VALUE_DEFAULT,
                'deferredfeedback'),
            'questionsperpage' => new external_value(PARAM_INT, 'Questions per page, 0 = all on one page',
                VALUE_DEFAULT, 1),
            'grade' => new external_value(PARAM_FLOAT, 'Maximum grade', VALUE_DEFAULT, 10),
            'visible' => new external_value(PARAM_BOOL, 'Visible to students', VALUE_DEFAULT, true),
        ]);
    }

    /**
     * Create the quiz.
     *
     * @param int $courseid
     * @param int $section
     * @param string $name
     * @param string $intro
     * @param string $gift
     * @param int $timeopen
     * @param int $timeclose
     * @param int $timelimit
     * @param int $attempts
     * @param string $preferredbehaviour
     * @param int $questionsperpage
     * @param float $grade
     * @param bool $visible
     * @return array
     */
    public static function execute(int $courseid, int $section, string $name, string $intro = '',
            string $gift = '', int $timeopen = 0, int $timeclose = 0, int $timelimit = 0, int $attempts = 0,
            string $preferredbehaviour = 'deferredfeedback', int $questionsperpage = 1, float $grade = 10,
            bool $visible = true): array {
        global $CFG, $DB;
        $params = self::validate_parameters(self::execute_parameters(), compact('courseid', 'section', 'name',
            'intro', 'gift', 'timeopen', 'timeclose', 'timelimit', 'attempts', 'preferredbehaviour',
            'questionsperpage', 'grade', 'visible'));
        if (!in_array($params['preferredbehaviour'], ['deferredfeedback', 'interactive', 'immediatefeedback'])) {
            throw new \invalid_parameter_exception('Unknown behaviour: ' . $params['preferredbehaviour']);
        }
        [$course] = helper::require_course_editing($params['courseid']);
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');

        $extra = [
            'timeopen' => $params['timeopen'],
            'timeclose' => $params['timeclose'],
            'timelimit' => $params['timelimit'],
            'overduehandling' => 'autosubmit',
            'graceperiod' => 0,
            'preferredbehaviour' => $params['preferredbehaviour'],
            'canredoquestions' => 0,
            'attempts' => max(0, $params['attempts']),
            'attemptonlast' => 0,
            'grademethod' => QUIZ_GRADEHIGHEST,
            'decimalpoints' => 2,
            'questiondecimalpoints' => -1,
            'questionsperpage' => max(0, $params['questionsperpage']),
            'navmethod' => QUIZ_NAVMETHOD_FREE,
            'shuffleanswers' => 1,
            'sumgrades' => 0,
            'grade' => $params['grade'],
            'quizpassword' => '',
            'subnet' => '',
            'browsersecurity' => '-',
            'delay1' => 0,
            'delay2' => 0,
            'showuserpicture' => 0,
            'showblocks' => 0,
            'allowofflineattempts' => 0,
        ];
        // Review options, same defaults as Moodle's own quiz generator.
        foreach (['attempt', 'correctness', 'maxmarks', 'marks', 'specificfeedback', 'generalfeedback',
                'rightanswer', 'overallfeedback'] as $field) {
            foreach (['during', 'immediately', 'open', 'closed'] as $when) {
                $extra[$field . $when] = ($field === 'overallfeedback' && $when === 'during') ? 0 : 1;
            }
        }

        $mi = helper::create_module($course, 'quiz', $params['section'], $params['name'], $params['intro'],
            (int) $params['visible'], $extra);

        $questionids = [];
        if (trim($params['gift']) !== '') {
            $quiz = $DB->get_record('quiz', ['id' => $mi->instance], '*', MUST_EXIST);
            $quiz->cmid = $mi->coursemodule;
            $context = \core\context\module::instance($mi->coursemodule);
            $questionids = add_gift_questions::import($course, $quiz, $context, $params['gift'], true);
        }
        $result = helper::created_result($mi);
        $result['questionids'] = $questionids;
        return $result;
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        $fields = helper::created_fields();
        $fields['questionids'] = new external_multiple_structure(
            new external_value(PARAM_INT, 'Question id'), 'Questions added to the quiz');
        return new external_single_structure($fields);
    }

}
