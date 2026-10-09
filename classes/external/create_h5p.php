<?php
// This file is part of Moodle - http://moodle.org/
// Moodle is free software, distributed under the GNU GPL v3 or later.

namespace local_aimcp\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_aimcp\local\helper;
use local_aimcp\local\h5p;

/**
 * Create a native H5P activity using installed content libraries.
 * @package local_aimcp
 * @copyright 2026 Kenneth
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_h5p extends external_api {
    /** Parameter schema. */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Destination course id'),
            'section' => new external_value(PARAM_INT, 'Section number, not section id; 0 = general'),
            'name' => new external_value(PARAM_TEXT, 'Activity title'),
            'packagedata' => new external_value(PARAM_RAW, 'Base64 .h5p; give this OR draftitemid', VALUE_DEFAULT, ''),
            'draftitemid' => new external_value(PARAM_INT, 'Current user draft containing one .h5p', VALUE_DEFAULT, 0),
            'intro' => new external_value(PARAM_RAW, 'Description HTML', VALUE_DEFAULT, ''),
            'visible' => new external_value(PARAM_BOOL, 'Visible to students', VALUE_DEFAULT, true),
            'enabletracking' => new external_value(PARAM_BOOL, 'Record attempts', VALUE_DEFAULT, false),
            'grade' => new external_value(PARAM_FLOAT, 'Maximum points; 0 = no grade, maximum 1000', VALUE_DEFAULT, 0),
            'grademethod' => new external_value(PARAM_INT, '0 manual, 1 highest, 2 average, 3 last, 4 first', VALUE_DEFAULT, 1),
        ]);
    }

    /** Validate first, then create through Moodle's module API. */
    public static function execute(int $courseid, int $section, string $name, string $packagedata = '',
            int $draftitemid = 0, string $intro = '', bool $visible = true, bool $enabletracking = false,
            float $grade = 0, int $grademethod = 1): array {
        global $DB, $CFG;
        $params = self::validate_parameters(self::execute_parameters(), compact('courseid', 'section', 'name',
            'packagedata', 'draftitemid', 'intro', 'visible', 'enabletracking', 'grade', 'grademethod'));
        [$course, $context] = helper::require_course_editing($params['courseid']);
        require_capability('mod/h5pactivity:addinstance', $context);
        require_capability('moodle/h5p:deploy', $context);
        if ($params['section'] < 0 || trim($params['name']) === '') {
            throw new \invalid_parameter_exception('A nonnegative section number and nonempty name are required.');
        }
        h5p::validate_settings($params['grade'], $params['grademethod']);
        require_once($CFG->dirroot . '/course/modlib.php');
        if (!course_allowed_module($course, 'h5pactivity')) {
            throw new \moodle_exception('moduledisable', 'error', '', 'h5pactivity');
        }
        [$draftid, $warnings] = h5p::prepare($params['packagedata'], $params['draftitemid'], $course);
        try {
            $transaction = $DB->start_delegated_transaction();
            $factory = new \core_h5p\factory();
            $displayoptions = \core_h5p\helper::get_display_options($factory->get_core(), (object) []);
            $mi = helper::create_module($course, 'h5pactivity', $params['section'], $params['name'], $params['intro'],
                (int) $params['visible'], [
                    'packagefile' => $draftid, 'grade' => $params['grade'],
                    'enabletracking' => (int) $params['enabletracking'], 'grademethod' => $params['grademethod'],
                    'displayoptions' => $displayoptions, 'reviewmode' => 1,
                ]);
            $transaction->allow_commit();
            return helper::created_result($mi, $warnings);
        } catch (\Throwable $e) {
            if (isset($transaction)) {
                $transaction->rollback($e);
            }
            throw $e;
        } finally {
            h5p::delete_draft($draftid);
        }
    }

    /** Return schema. */
    public static function execute_returns(): external_single_structure {
        return helper::created_returns();
    }
}
