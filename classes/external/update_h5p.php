<?php
// This file is part of Moodle - http://moodle.org/
// Moodle is free software, distributed under the GNU GPL v3 or later.

namespace local_aimcp\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use local_aimcp\local\helper;
use local_aimcp\local\h5p;

/**
 * Replace a native H5P package without deleting the activity or its attempts.
 * @package local_aimcp
 * @copyright 2026 Kenneth
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class update_h5p extends external_api {
    /** Parameter schema. */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Native H5P course module id'),
            'packagedata' => new external_value(PARAM_RAW, 'Base64 replacement .h5p; give this OR draftitemid', VALUE_DEFAULT, ''),
            'draftitemid' => new external_value(PARAM_INT, 'Current user draft containing one replacement .h5p', VALUE_DEFAULT, 0),
            'name' => new external_value(PARAM_TEXT, 'New title; omitted/null = preserve', VALUE_DEFAULT, null),
            'intro' => new external_value(PARAM_RAW, 'New description HTML; omitted/null = preserve', VALUE_DEFAULT, null),
        ]);
    }

    /** Validate the replacement before touching the stored package. */
    public static function execute(int $cmid, string $packagedata = '', int $draftitemid = 0,
            ?string $name = null, ?string $intro = null): array {
        global $CFG, $DB;
        $params = self::validate_parameters(self::execute_parameters(), compact('cmid', 'packagedata', 'draftitemid',
            'name', 'intro'));
        [$course, $cm, $context] = helper::require_cm_editing($params['cmid'], 'h5pactivity');
        require_capability('moodle/h5p:deploy', $context);
        if ($params['name'] !== null && trim($params['name']) === '') {
            throw new \invalid_parameter_exception('The activity name cannot be empty.');
        }
        require_once($CFG->dirroot . '/mod/h5pactivity/lib.php');
        [$draftid, $warnings] = h5p::prepare($params['packagedata'], $params['draftitemid'], $course);
        try {
            $transaction = $DB->start_delegated_transaction();
            $activity = $DB->get_record('h5pactivity', ['id' => $cm->instance], '*', MUST_EXIST);
            $activity->instance = $cm->instance;
            $activity->coursemodule = $cm->id;
            $activity->packagefile = $draftid;
            $activity->cmidnumber = $cm->idnumber;
            if ($params['name'] !== null) {
                $activity->name = $params['name'];
            }
            if ($params['intro'] !== null) {
                $activity->intro = $params['intro'];
                $activity->introformat = FORMAT_HTML;
            }
            if (!h5pactivity_update_instance($activity)) {
                throw new \moodle_exception('cannotupdatemod', 'error', '', 'h5pactivity');
            }
            rebuild_course_cache($course->id, true);
            \core\event\course_module_updated::create_from_cm(get_fast_modinfo($course)->get_cm($cm->id))->trigger();
            $transaction->allow_commit();
            return ['cmid' => (int) $cm->id,
                'url' => (new \moodle_url('/mod/h5pactivity/view.php', ['id' => $cm->id]))->out(false),
                'warnings' => $warnings];
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
        return new external_single_structure([
            'cmid' => new external_value(PARAM_INT, 'Updated course module id'),
            'url' => new external_value(PARAM_URL, 'Activity link'),
            'warnings' => new external_warnings(),
        ]);
    }
}
