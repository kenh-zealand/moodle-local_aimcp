<?php
// This file is part of Moodle - http://moodle.org/
// Moodle is free software, distributed under the GNU GPL v3 or later.

namespace local_aimcp\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_aimcp\local\course_blueprint;
use local_aimcp\local\helper;

/**
 * Validate a compact JSON course blueprint without modifying Moodle.
 *
 * @package local_aimcp
 * @copyright 2026 Kenneth
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class validate_course_blueprint extends external_api {
    /** @return external_function_parameters */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'blueprintjson' => new external_value(PARAM_RAW,
                'Complete JSON blueprint: schema_version=1.0, course, sections[].activities. No changes are made.'),
        ]);
    }

    /**
     * Parse, count and check permissions/category/format without creating anything.
     *
     * @param string $blueprintjson
     * @return array
     */
    public static function execute(string $blueprintjson): array {
        global $DB, $USER;
        $params = self::validate_parameters(self::execute_parameters(), compact('blueprintjson'));
        $report = course_blueprint::analyse($params['blueprintjson']);
        $errors = $report['errors'];
        $warnings = $report['warnings'];
        if (!$errors) {
            $course = $report['blueprint']['course'];
            if ($DB->record_exists('course', ['shortname' => $course['shortname']])) {
                $errors[] = 'course.shortname is already in use. A retry will not create a duplicate.';
            }
            $format = $course['format'] ?? '';
            if ($format !== '' && !array_key_exists($format, \core_component::get_plugin_list('format'))) {
                $errors[] = 'course.format is not installed on this Moodle site.';
            }
            try {
                $categoryid = (int) ($course['categoryid'] ?? 0);
                $category = $categoryid ? \core_course_category::get($categoryid) :
                    helper::default_category((int) $USER->id);
                if (!$category) {
                    $errors[] = 'No default AI course category is configured for this user.';
                } else {
                    $context = \core\context\coursecat::instance($category->id);
                    self::validate_context($context);
                    if (!has_capability('moodle/course:create', $context)) {
                        $errors[] = 'The current user cannot create a course in the selected category.';
                    }
                }
            } catch (\Exception $e) {
                $errors[] = 'The selected course category is not available.';
            }
        }
        $counts = $report['counts'];
        $legacycalls = 1 + $counts['sections'] + $counts['activities'] + $counts['images'] +
            $counts['completionrules'];
        return [
            'valid' => !$errors,
            'hash' => $report['hash'],
            'sections' => $counts['sections'],
            'activities' => $counts['activities'],
            'images' => $counts['images'],
            'estimatedlegacycalls' => $legacycalls,
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /** @return external_single_structure */
    public static function execute_returns(): external_single_structure {
        $strings = new external_multiple_structure(new external_value(PARAM_TEXT, 'Message'));
        return new external_single_structure([
            'valid' => new external_value(PARAM_BOOL, 'Can proceed after validation'),
            'hash' => new external_value(PARAM_TEXT, 'SHA-256 hash of the exact JSON payload'),
            'sections' => new external_value(PARAM_INT, 'Number of sections'),
            'activities' => new external_value(PARAM_INT, 'Number of activities including subsections'),
            'images' => new external_value(PARAM_INT, 'Number of Grid images'),
            'estimatedlegacycalls' => new external_value(PARAM_INT,
                'Approximate count of model-controlled calls using the old individual-tool approach'),
            'errors' => $strings,
            'warnings' => $strings,
        ]);
    }
}
