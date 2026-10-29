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

/**
 * Create a complete draft course by orchestrating the existing safe AI MCP tools.
 *
 * This is intentionally synchronous and size bounded. Moodle module creation
 * cannot safely be assumed atomic across modules, files and events. A failure
 * leaves the partial course hidden and reports the course ID for manual review.
 *
 * @package local_aimcp
 * @copyright 2026 Kenneth
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_complete_course extends external_api {
    /** @return external_function_parameters */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'blueprintjson' => new external_value(PARAM_RAW,
                'Complete validated JSON blueprint with schema_version 1.0, course and sections.'),
            'expectedhash' => new external_value(PARAM_TEXT,
                'Optional SHA-256 hash returned by validate_course_blueprint; reject if the payload changed.',
                VALUE_DEFAULT, ''),
            'includeids' => new external_value(PARAM_BOOL,
                'Include per-activity CM IDs in the response (larger token usage).', VALUE_DEFAULT, false),
        ]);
    }

    /**
     * Build one course. Always create hidden; publish only when fully completed.
     *
     * @param string $blueprintjson
     * @param string $expectedhash
     * @param bool $includeids
     * @return array
     */
    public static function execute(string $blueprintjson, string $expectedhash = '', bool $includeids = false): array {
        global $CFG, $DB;
        $params = self::validate_parameters(self::execute_parameters(),
            compact('blueprintjson', 'expectedhash', 'includeids'));
        $report = validate_course_blueprint::execute($params['blueprintjson']);
        $result = [
            'status' => 'rejected',
            'hash' => $report['hash'],
            'courseid' => 0,
            'url' => '',
            'createdsections' => 0,
            'createdactivities' => 0,
            'createdimages' => 0,
            'published' => false,
            'failedpath' => '',
            'failurecode' => '',
            'errors' => $report['errors'],
            'warnings' => $report['warnings'],
            'items' => [],
        ];
        if ($params['expectedhash'] !== '' &&
                !hash_equals($params['expectedhash'], $report['hash'])) {
            $result['errors'][] = 'Blueprint hash differs from the validated payload.';
            return $result;
        }
        if (!$report['valid']) {
            return $result;
        }
        $blueprint = course_blueprint::analyse($params['blueprintjson'])['blueprint'];
        $course = $blueprint['course'];
        $path = 'course';
        try {
            $created = create_course::execute($course['fullname'], $course['shortname'],
                (int) ($course['categoryid'] ?? 0), (string) ($course['summary'] ?? ''),
                (string) ($course['format'] ?? ''), count($blueprint['sections']),
                (int) ($course['startdate'] ?? 0), (int) ($course['enddate'] ?? 0),
                false, (bool) ($course['enablecompletion'] ?? true));
            $id = (int) $created['id'];
            $result['courseid'] = $id;
            $result['url'] = $created['url'];
            foreach ($created['warnings'] as $warning) {
                $result['warnings'][] = 'course: ' . ($warning['message'] ?? 'Unknown warning');
            }
            foreach ($blueprint['sections'] as $index => $section) {
                $sectionnum = $index + 1;
                $path = 'sections[' . $index . ']';
                $result['failedpath'] = $path;
                update_section::execute($id, $sectionnum, (string) $section['name'],
                    $section['summary'] ?? null, $section['visible'] ?? null);
                $result['createdsections']++;
                if (isset($section['image'])) {
                    $path .= '.image';
                    $result['failedpath'] = $path;
                    $image = $section['image'];
                    set_section_image::execute($id, $sectionnum,
                        (string) ($image['svg'] ?? ''), '', (string) ($image['imageurl'] ?? ''), '',
                        (string) $image['alttext']);
                    $result['createdimages']++;
                }
                $path = 'sections[' . $index . '].activities';
                $result['failedpath'] = $path;
                self::make_activities($id, $sectionnum, $section['activities'] ?? [], $path,
                    $params['includeids'], $result);
            }
            if (!empty($course['visible'])) {
                $path = 'course.publish';
                $result['failedpath'] = $path;
                require_once($CFG->dirroot . '/course/lib.php');
                $saved = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);
                $saved->visible = 1;
                update_course($saved);
                $result['published'] = true;
            }
            $result['status'] = 'created';
            $result['failedpath'] = '';
        } catch (\Throwable $e) {
            // A failed operation must never expose a partially built course to students.
            $result['status'] = $result['courseid'] ? 'partial' : 'rejected';
            $result['failedpath'] = $result['failedpath'] ?: $path;
            $result['failurecode'] = $e instanceof \moodle_exception ? $e->errorcode : 'unexpected';
            $result['errors'][] = $e instanceof \moodle_exception ?
                $e->getMessage() : 'An unexpected server error occurred; inspect Moodle error logs.';
            if ($result['courseid']) {
                try {
                    // Never delete or retry modules automatically after a partial write.
                    require_once($CFG->dirroot . '/course/lib.php');
                    $saved = $DB->get_record('course', ['id' => $result['courseid']], '*', MUST_EXIST);
                    if ((int) $saved->visible !== 0) {
                        $saved->visible = 0;
                        update_course($saved);
                    }
                } catch (\Throwable $ignored) {
                    $result['warnings'][] = 'Could not confirm that the partial course is hidden; check manually.';
                }
                $result['warnings'][] = 'Partial course retained for inspection; do not blindly resubmit.';
            }
        }
        return $result;
    }

    /**
     * Dispatch each activity to its existing implementation, keeping server operations off-model.
     *
     * @param int $courseid
     * @param int $sectionnum
     * @param array $activities
     * @param string $path
     * @param bool $includeids
     * @param array $report
     */
    private static function make_activities(int $courseid, int $sectionnum, array $activities, string $path,
            bool $includeids, array &$report): void {
        foreach ($activities as $index => $activity) {
            $itempath = $path . '[' . $index . ']';
            // The marker is returned if a later activity fails.
            $report['failedpath'] = $itempath;
            $type = $activity['type'];
            $fields = course_blueprint::FIELDS[$type];
            $args = ['courseid' => $courseid, 'section' => $sectionnum];
            foreach ($fields as $field) {
                if (array_key_exists($field, $activity) && $field !== 'activities') {
                    $args[$field === 'forumtype' ? 'type' : $field] = $activity[$field];
                }
            }
            // Only whitelisted method names and fields from course_blueprint are dispatched.
            $method = $type === 'h5p' ? create_h5p::class : __NAMESPACE__ . '\\create_' . $type;
            if ($type === 'subsection') {
                // Existing service returns a delegated section number, not a section ID.
                $created = $method::execute(...$args);
                $report['createdactivities']++;
                if ($includeids) {
                    $report['items'][] = ['id' => (string) ($activity['id'] ?? $itempath),
                        'type' => $type, 'cmid' => (int) $created['cmid']];
                }
                self::make_activities($courseid, (int) $created['sectionnum'],
                    $activity['activities'] ?? [], $itempath . '.activities', $includeids, $report);
                continue;
            }
            $created = $method::execute(...$args);
            $report['createdactivities']++;
            if ($includeids) {
                $report['items'][] = ['id' => (string) ($activity['id'] ?? $itempath),
                    'type' => $type, 'cmid' => (int) $created['cmid']];
            }
            foreach ($created['warnings'] ?? [] as $warning) {
                $report['warnings'][] = $itempath . ': ' . ($warning['message'] ?? 'Unknown warning');
            }
            if (isset($activity['completion'])) {
                $report['failedpath'] = $itempath . '.completion';
                $completion = set_completion::execute([(int) $created['cmid']], $activity['completion']);
                foreach ($completion['warnings'] ?? [] as $warning) {
                    $report['warnings'][] = $itempath . ': ' . ($warning['message'] ?? 'Completion warning');
                }
            }
        }
    }

    /** @return external_single_structure */
    public static function execute_returns(): external_single_structure {
        $strings = new external_multiple_structure(new external_value(PARAM_TEXT, 'Message'));
        return new external_single_structure([
            'status' => new external_value(PARAM_TEXT, 'created, rejected or partial'),
            'hash' => new external_value(PARAM_TEXT, 'SHA-256 blueprint hash'),
            'courseid' => new external_value(PARAM_INT, 'Course id (0 if not created)'),
            'url' => new external_value(PARAM_TEXT, 'Course URL or empty'),
            'createdsections' => new external_value(PARAM_INT, 'Number of sections completed'),
            'createdactivities' => new external_value(PARAM_INT, 'Number of activities created'),
            'createdimages' => new external_value(PARAM_INT, 'Number of Grid images created'),
            'published' => new external_value(PARAM_BOOL, 'Only true when complete and explicitly requested'),
            'failedpath' => new external_value(PARAM_TEXT, 'Blueprint path of the failed operation'),
            'failurecode' => new external_value(PARAM_TEXT, 'Moodle error code or unexpected'),
            'errors' => $strings,
            'warnings' => $strings,
            'items' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_TEXT, 'Stable blueprint id or path'),
                'type' => new external_value(PARAM_TEXT, 'Activity type'),
                'cmid' => new external_value(PARAM_INT, 'Created course module id'),
            ]), 'Per-item IDs, only if requested'),
        ]);
    }
}
