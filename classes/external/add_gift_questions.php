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
 * Import GIFT questions into a quiz.
 *
 * @package    local_aimcp
 * @copyright  2026 Kenneth
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class add_gift_questions extends external_api {

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id of the quiz'),
            'gift' => new external_value(PARAM_RAW, 'Questions in Moodle GIFT format'),
            'addtoquiz' => new external_value(PARAM_BOOL,
                'Add the imported questions to the quiz (false = only put them in its question bank)',
                VALUE_DEFAULT, true),
        ]);
    }

    /**
     * Import the questions.
     *
     * @param int $cmid
     * @param string $gift
     * @param bool $addtoquiz
     * @return array
     */
    public static function execute(int $cmid, string $gift, bool $addtoquiz = true): array {
        global $DB;
        $params = self::validate_parameters(self::execute_parameters(), compact('cmid', 'gift', 'addtoquiz'));
        [$course, $cm, $context] = helper::require_cm_editing($params['cmid'], 'quiz');
        $quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);
        $quiz->cmid = $cm->id;
        $ids = self::import($course, $quiz, $context, $params['gift'], $params['addtoquiz']);
        return ['cmid' => $cm->id, 'questionids' => $ids, 'warnings' => []];
    }

    /**
     * Import GIFT text into the quiz's own question bank and optionally add the questions to the quiz.
     *
     * @param \stdClass $course
     * @param \stdClass $quiz quiz record with cmid
     * @param \context $context quiz module context
     * @param string $gift
     * @param bool $addtoquiz
     * @return int[] question ids
     */
    public static function import(\stdClass $course, \stdClass $quiz, \context $context, string $gift,
            bool $addtoquiz): array {
        global $CFG;
        require_once($CFG->libdir . '/questionlib.php');
        require_once($CFG->dirroot . '/question/format.php');
        require_once($CFG->dirroot . '/question/format/gift/format.php');
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');

        require_capability('moodle/question:add', $context);
        if ($addtoquiz) {
            require_capability('mod/quiz:manage', $context);
        }

        $category = question_get_default_category($context->id, true);
        $file = make_request_directory() . '/import.gift.txt';
        file_put_contents($file, $gift);

        $qformat = new \qformat_gift();
        $qformat->setCategory($category);
        $qformat->setContexts([$context]);
        $qformat->setCourse($course);
        $qformat->setFilename($file);
        $qformat->setRealfilename('import.txt');
        $qformat->setMatchgrades('nearest');
        $qformat->setCatfromfile(false);
        $qformat->setContextfromfile(false);
        $qformat->setStoponerror(true);
        $qformat->set_display_progress(false);

        ob_start();
        try {
            $ok = $qformat->importpreprocess() && $qformat->importprocess() && $qformat->importpostprocess();
        } finally {
            $output = ob_get_clean();
        }
        if (!$ok) {
            $msg = trim(preg_replace('/\s+/', ' ', strip_tags($output)));
            throw new \moodle_exception('giftimportfailed', 'local_aimcp', '', $msg);
        }

        $ids = array_map('intval', $qformat->questionids);
        if ($addtoquiz && $ids) {
            foreach ($ids as $qid) {
                quiz_add_quiz_question($qid, $quiz, 0);
            }
            \mod_quiz\quiz_settings::create($quiz->id)->get_grade_calculator()->recompute_quiz_sumgrades();
        }
        return $ids;
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cmid' => new external_value(PARAM_INT, 'Quiz course module id'),
            'questionids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Question id'), 'Imported question ids'),
            'warnings' => new external_warnings(),
        ]);
    }

}
