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
use local_aimcp\local\h5p_package;

/**
 * Read a native H5P activity and its source JSON without accessing student attempts.
 * @package local_aimcp
 * @copyright 2026 Kenneth
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_h5p extends external_api {
    /** Parameter schema. */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Native H5P course module id'),
            'includecontent' => new external_value(PARAM_BOOL, 'Include source JSON', VALUE_DEFAULT, true),
        ]);
    }

    /** Read the actual activity package (not a potentially stale player cache). */
    public static function execute(int $cmid, bool $includecontent = true): array {
        global $DB;
        $params = self::validate_parameters(self::execute_parameters(), compact('cmid', 'includecontent'));
        [$course, $cm, $context] = helper::require_cm_editing($params['cmid'], 'h5pactivity');
        $activity = $DB->get_record('h5pactivity', ['id' => $cm->instance], '*', MUST_EXIST);
        $files = get_file_storage()->get_area_files($context->id, 'mod_h5pactivity', 'package', 0, 'id', false);
        if (count($files) !== 1) {
            throw new \invalid_parameter_exception('The activity must have exactly one H5P package.');
        }
        $file = reset($files);
        $path = make_request_directory() . '/activity.h5p';
        if ($file->get_filesize() > h5p_package::MAX_BYTES) {
            throw new \invalid_parameter_exception('H5P package exceeds the 20 MiB inspection limit.');
        }
        $file->copy_content_to($path);
        $info = h5p_package::inspect($path);
        return [
            'cmid' => (int) $cm->id, 'courseid' => (int) $course->id, 'section' => (int) $cm->sectionnum,
            'name' => $activity->name, 'intro' => $activity->intro, 'visible' => (bool) $cm->visible,
            'enabletracking' => (bool) $activity->enabletracking, 'grade' => (float) $activity->grade,
            'grademethod' => (int) $activity->grademethod, 'filename' => $file->get_filename(),
            'contenthash' => $file->get_contenthash(), 'manifest' => json_encode($info['manifest']),
            'content' => $params['includecontent'] ? $info['content'] : '',
            'url' => (new \moodle_url('/mod/h5pactivity/view.php', ['id' => $cm->id]))->out(false),
            'packageurl' => \moodle_url::make_pluginfile_url($context->id, 'mod_h5pactivity', 'package', 0,
                $file->get_filepath(), $file->get_filename(), true)->out(false),
            'warnings' => [],
        ];
    }

    /** Return schema. */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'section' => new external_value(PARAM_INT, 'Section number, not section id'),
            'name' => new external_value(PARAM_TEXT, 'Activity title'),
            'intro' => new external_value(PARAM_RAW, 'Description HTML'),
            'visible' => new external_value(PARAM_BOOL, 'Visible to students'),
            'enabletracking' => new external_value(PARAM_BOOL, 'Attempt tracking enabled'),
            'grade' => new external_value(PARAM_FLOAT, 'Maximum grade'),
            'grademethod' => new external_value(PARAM_INT, 'Grading method'),
            'filename' => new external_value(PARAM_FILE, 'Package filename'),
            'contenthash' => new external_value(PARAM_ALPHANUM, 'SHA1 hash of the stored package'),
            'manifest' => new external_value(PARAM_RAW, 'h5p.json as JSON string'),
            'content' => new external_value(PARAM_RAW, 'content/content.json, or empty when not requested'),
            'url' => new external_value(PARAM_URL, 'Activity link'),
            'packageurl' => new external_value(PARAM_URL, 'Authenticated package download for backup; not public'),
            'warnings' => new external_warnings(),
        ]);
    }
}
