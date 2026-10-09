<?php
// This file is part of Moodle - http://moodle.org/
// Moodle is free software, distributed under the GNU GPL v3 or later.

namespace local_aimcp\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use local_aimcp\local\helper;

/**
 * Discover installed H5P libraries and their exact authoring schemas.
 * @package local_aimcp
 * @copyright 2026 Kenneth
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_h5p_libraries extends external_api {
    /** Parameter schema. */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course the caller may edit'),
            'machinename' => new external_value(PARAM_TEXT, 'Exact library name, e.g. H5P.MultiChoice; empty = all',
                VALUE_DEFAULT, ''),
            'includesemantics' => new external_value(PARAM_BOOL, 'Include semantics JSON for authoring', VALUE_DEFAULT, false),
        ]);
    }

    /** Read installed libraries only; nothing is installed or enabled. */
    public static function execute(int $courseid, string $machinename = '', bool $includesemantics = false): array {
        global $DB;
        $params = self::validate_parameters(self::execute_parameters(), compact('courseid', 'machinename', 'includesemantics'));
        helper::require_course_editing($params['courseid']);
        $conditions = $params['machinename'] === '' ? [] : ['machinename' => $params['machinename']];
        $libraries = [];
        foreach ($DB->get_records('h5p_libraries', $conditions, 'machinename, majorversion DESC, minorversion DESC') as $library) {
            $libraries[] = [
                'id' => (int) $library->id, 'machinename' => $library->machinename, 'title' => $library->title,
                'majorversion' => (int) $library->majorversion, 'minorversion' => (int) $library->minorversion,
                'patchversion' => (int) $library->patchversion, 'runnable' => (bool) $library->runnable,
                'enabled' => (bool) $library->enabled,
                'semantics' => $params['includesemantics'] ? (string) $library->semantics : '',
            ];
        }
        return ['libraries' => $libraries, 'warnings' => []];
    }

    /** Return schema. */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'libraries' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Installed library id'),
                'machinename' => new external_value(PARAM_TEXT, 'Machine name'),
                'title' => new external_value(PARAM_TEXT, 'Title'),
                'majorversion' => new external_value(PARAM_INT, 'Major version'),
                'minorversion' => new external_value(PARAM_INT, 'Minor version'),
                'patchversion' => new external_value(PARAM_INT, 'Patch version'),
                'runnable' => new external_value(PARAM_BOOL, 'Can be a main content type'),
                'enabled' => new external_value(PARAM_BOOL, 'Enabled in Moodle'),
                'semantics' => new external_value(PARAM_RAW, 'semantics.json or empty when not requested'),
            ])),
            'warnings' => new external_warnings(),
        ]);
    }
}
