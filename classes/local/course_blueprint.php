<?php
// This file is part of Moodle - http://moodle.org/
// Moodle is free software, distributed under the GNU GPL v3 or later.

namespace local_aimcp\local;

/**
 * Syntax-only validation of a compact AI course blueprint.
 *
 * This class never writes to Moodle. It deliberately does not claim that
 * GIFT content, H5P packages, capabilities or HTML are semantically valid.
 *
 * @package local_aimcp
 * @copyright 2026 Kenneth
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class course_blueprint {
    /** Maximum JSON payload; H5P files must be uploaded as drafts separately. */
    public const MAX_BYTES = 2097152;
    /** Prevent very long synchronous HTTP/MCP requests. */
    public const MAX_SECTIONS = 24;
    /** Prevent very long synchronous HTTP/MCP requests. */
    public const MAX_ACTIVITIES = 60;
    /** Bound book chapter creation. */
    public const MAX_CHAPTERS = 40;

    /** Fields supported by each existing local_aimcp create function. */
    public const FIELDS = [
        'page' => ['name', 'content', 'intro', 'visible'],
        'label' => ['content', 'name', 'visible'],
        'url' => ['name', 'externalurl', 'intro', 'visible'],
        'forum' => ['name', 'intro', 'forumtype', 'visible'],
        'assign' => ['name', 'intro', 'activity', 'allowsubmissionsfromdate', 'duedate', 'cutoffdate',
            'onlinetext', 'wordlimit', 'filesubmissions', 'maxfiles', 'grade', 'teamsubmission', 'visible'],
        'quiz' => ['name', 'intro', 'gift', 'timeopen', 'timeclose', 'timelimit', 'attempts',
            'preferredbehaviour', 'questionsperpage', 'grade', 'visible'],
        'book' => ['name', 'intro', 'numbering', 'navstyle', 'customtitles', 'visible', 'chapters'],
        'h5p' => ['name', 'draftitemid', 'intro', 'visible', 'enabletracking', 'grade', 'grademethod'],
        'subsection' => ['name', 'summary', 'visible', 'activities'],
    ];

    /**
     * Inspect a JSON blueprint without writing to Moodle.
     *
     * @param string $json
     * @return array{valid:bool,hash:string,errors:array,warnings:array,counts:array,blueprint:array}
     */
    public static function analyse(string $json): array {
        $result = [
            'valid' => false,
            'hash' => hash('sha256', $json),
            'errors' => [],
            'warnings' => [],
            'counts' => ['sections' => 0, 'activities' => 0, 'images' => 0, 'completionrules' => 0],
            'blueprint' => [],
        ];
        if (strlen($json) > self::MAX_BYTES) {
            $result['errors'][] = 'Blueprint exceeds 2 MiB; use uploaded H5P drafts instead of inline packages.';
            return $result;
        }
        try {
            $data = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $result['errors'][] = 'Invalid JSON: ' . $e->getMessage();
            return $result;
        }
        if (!self::is_object($data)) {
            $result['errors'][] = 'Root must be a JSON object.';
            return $result;
        }
        $result['blueprint'] = $data;
        self::allowed($data, ['schema_version', 'course', 'sections'], 'root', $result);
        if (($data['schema_version'] ?? null) !== '1.0') {
            $result['errors'][] = 'schema_version must be "1.0".';
        }
        $course = $data['course'] ?? null;
        if (!self::is_object($course)) {
            $result['errors'][] = 'course must be a JSON object.';
        } else {
            self::allowed($course, ['fullname', 'shortname', 'categoryid', 'summary', 'format', 'startdate',
                'enddate', 'visible', 'enablecompletion'], 'course', $result);
            self::required_text($course, 'fullname', 'course', $result);
            self::required_text($course, 'shortname', 'course', $result);
            if (isset($course['format']) && (!is_string($course['format']) ||
                    !preg_match('/^[a-z][a-z0-9_]*$/D', $course['format']))) {
                $result['errors'][] = 'course.format must be a valid Moodle format name.';
            }
            foreach (['categoryid', 'startdate', 'enddate'] as $field) {
                if (array_key_exists($field, $course) && (!is_int($course[$field]) || $course[$field] < 0)) {
                    $result['errors'][] = "course.$field must be a nonnegative integer.";
                }
            }
            foreach (['visible', 'enablecompletion'] as $field) {
                self::optional_bool($course, $field, 'course', $result);
            }
            self::optional_text($course, 'summary', 'course', $result);
        }
        $sections = $data['sections'] ?? null;
        if (!is_array($sections) || !array_is_list($sections)) {
            $result['errors'][] = 'sections must be a JSON array.';
            return self::finish($result);
        }
        $result['counts']['sections'] = count($sections);
        if (count($sections) > self::MAX_SECTIONS) {
            $result['errors'][] = 'At most ' . self::MAX_SECTIONS . ' sections are supported per build.';
        }
        $keys = [];
        foreach ($sections as $i => $section) {
            $path = 'sections[' . $i . ']';
            if (!self::is_object($section)) {
                $result['errors'][] = "$path must be an object.";
                continue;
            }
            self::allowed($section, ['id', 'name', 'summary', 'visible', 'image', 'activities'], $path, $result);
            self::key($section, $path, $keys, $result);
            self::required_text($section, 'name', $path, $result);
            self::optional_text($section, 'summary', $path, $result);
            self::optional_bool($section, 'visible', $path, $result);
            if (isset($section['image'])) {
                $result['counts']['images']++;
                self::image($section['image'], $path . '.image', $result);
                if (($course['format'] ?? '') !== 'grid') {
                    $result['errors'][] = "$path.image requires course.format = grid.";
                }
            }
            self::activities($section['activities'] ?? [], $path . '.activities', false, $keys, $result);
        }
        if ($result['counts']['activities'] > self::MAX_ACTIVITIES) {
            $result['errors'][] = 'At most ' . self::MAX_ACTIVITIES . ' activities are supported per build.';
        }
        if ($result['counts']['activities'] > 25) {
            $result['warnings'][] = 'Large synchronous builds may exceed the HTTP/MCP timeout; test on staging.';
        }
        return self::finish($result);
    }

    /** JSON objects are associative arrays after json_decode(..., true). */
    private static function is_object($value): bool {
        return is_array($value) && !array_is_list($value);
    }

    /** Enforce exact supported keys; no silent dropping of user content. */
    private static function allowed(array $value, array $allowed, string $path, array &$result): void {
        foreach (array_keys($value) as $key) {
            if (!in_array($key, $allowed, true)) {
                $result['errors'][] = "$path.$key is not supported.";
            }
        }
    }

    /** Optional stable identifiers for concise reporting. */
    private static function key(array $value, string $path, array &$keys, array &$result): void {
        if (!array_key_exists('id', $value)) {
            return;
        }
        if (!is_string($value['id']) || !preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $value['id'])) {
            $result['errors'][] = "$path.id must contain 1-64 letters, digits, dashes or underscores.";
            return;
        }
        if (isset($keys[$value['id']])) {
            $result['errors'][] = "$path.id duplicates a previous id.";
        }
        $keys[$value['id']] = true;
    }

    private static function required_text(array $value, string $field, string $path, array &$result): void {
        if (!isset($value[$field]) || !is_string($value[$field]) || trim($value[$field]) === '' ||
                strlen($value[$field]) > 255) {
            $result['errors'][] = "$path.$field must be a nonempty string (max 255 bytes).";
        }
    }

    private static function optional_text(array $value, string $field, string $path, array &$result): void {
        if (array_key_exists($field, $value) && !is_string($value[$field])) {
            $result['errors'][] = "$path.$field must be a string.";
        }
    }

    private static function optional_bool(array $value, string $field, string $path, array &$result): void {
        if (array_key_exists($field, $value) && !is_bool($value[$field])) {
            $result['errors'][] = "$path.$field must be a boolean.";
        }
    }

    /** Validate Grid image sources; the existing service performs final security validation. */
    private static function image($image, string $path, array &$result): void {
        if (!self::is_object($image)) {
            $result['errors'][] = "$path must be an object.";
            return;
        }
        self::allowed($image, ['svg', 'imageurl', 'alttext'], $path, $result);
        $sources = (int) !empty($image['svg']) + (int) !empty($image['imageurl']);
        if ($sources !== 1) {
            $result['errors'][] = "$path requires exactly one of svg or imageurl.";
        }
        if (isset($image['svg']) && (!is_string($image['svg']) || strlen($image['svg']) > 524288)) {
            $result['errors'][] = "$path.svg must be a string of at most 512 KiB.";
        }
        if (isset($image['imageurl']) && (!is_string($image['imageurl']) ||
                !preg_match('~^https?://~i', $image['imageurl']))) {
            $result['errors'][] = "$path.imageurl must be an HTTP(S) URL.";
        }
        self::required_text($image, 'alttext', $path, $result);
    }

    /**
     * Validate activity arrays including one level of subsections.
     */
    private static function activities($activities, string $path, bool $issubsection,
            array &$keys, array &$result): void {
        if (!is_array($activities) || !array_is_list($activities)) {
            $result['errors'][] = "$path must be a JSON array.";
            return;
        }
        foreach ($activities as $i => $activity) {
            $itempath = "$path[$i]";
            $result['counts']['activities']++;
            if (!self::is_object($activity)) {
                $result['errors'][] = "$itempath must be an object.";
                continue;
            }
            $type = $activity['type'] ?? null;
            if (!is_string($type) || !isset(self::FIELDS[$type])) {
                $result['errors'][] = "$itempath.type is not supported.";
                continue;
            }
            self::allowed($activity, array_merge(['id', 'type', 'completion'], self::FIELDS[$type]), $itempath, $result);
            self::key($activity, $itempath, $keys, $result);
            if ($type !== 'label') {
                self::required_text($activity, 'name', $itempath, $result);
            }
            foreach (self::FIELDS[$type] as $field) {
                if (array_key_exists($field, $activity) && !is_string($activity[$field]) &&
                        in_array($field, ['intro', 'content', 'externalurl', 'gift', 'summary', 'activity', 'forumtype'], true)) {
                    $result['errors'][] = "$itempath.$field must be a string.";
                }
            }
            self::optional_bool($activity, 'visible', $itempath, $result);
            // Optional display titles are still strings, including on labels.
            if ($type === 'label' && array_key_exists('name', $activity) &&
                    !is_string($activity['name'])) {
                $result['errors'][] = "$itempath.name must be a string.";
            }
            foreach (['onlinetext', 'filesubmissions', 'teamsubmission', 'customtitles',
                    'enabletracking'] as $boolfield) {
                self::optional_bool($activity, $boolfield, $itempath, $result);
            }
            foreach (['draftitemid', 'timeopen', 'timeclose', 'timelimit', 'attempts',
                    'questionsperpage', 'numbering', 'navstyle', 'allowsubmissionsfromdate',
                    'duedate', 'cutoffdate', 'wordlimit', 'maxfiles', 'grademethod'] as $intfield) {
                if (array_key_exists($intfield, $activity) && (!is_int($activity[$intfield]) ||
                        $activity[$intfield] < 0)) {
                    $result['errors'][] = "$itempath.$intfield must be a nonnegative integer.";
                }
            }
            if (array_key_exists('grade', $activity) && !is_int($activity['grade']) &&
                    !is_float($activity['grade'])) {
                $result['errors'][] = "$itempath.grade must be a number.";
            }
            if ($type === 'forum' && isset($activity['forumtype']) && !in_array(
                    $activity['forumtype'], ['general', 'qanda', 'single', 'eachuser', 'blog'], true)) {
                $result['errors'][] = "$itempath.forumtype is not supported.";
            }
            if ($type === 'quiz' && isset($activity['preferredbehaviour']) && !in_array(
                    $activity['preferredbehaviour'], ['deferredfeedback', 'interactive', 'immediatefeedback'], true)) {
                $result['errors'][] = "$itempath.preferredbehaviour is not supported.";
            }
            if (in_array($type, ['page', 'label'], true) && !isset($activity['content'])) {
                $result['errors'][] = "$itempath.content is required.";
            }
            if ($type === 'url' && (!isset($activity['externalurl']) ||
                    !is_string($activity['externalurl']) ||
                    !preg_match('~^https?://~i', $activity['externalurl']))) {
                $result['errors'][] = "$itempath.externalurl must be an HTTP(S) URL.";
            }
            if ($type === 'h5p' && (!isset($activity['draftitemid']) ||
                    !is_int($activity['draftitemid']) || $activity['draftitemid'] <= 0)) {
                $result['errors'][] = "$itempath.draftitemid must be an uploaded draft file id.";
            }
            if (isset($activity['grade']) && (float) $activity['grade'] < 0) {
                $result['errors'][] = "$itempath.grade must not be negative.";
            }
            if ($type === 'h5p') {
                if (isset($activity['grade']) && (float) $activity['grade'] > 1000) {
                    $result['errors'][] = "$itempath.grade exceeds the H5P maximum of 1000.";
                }
                if (isset($activity['grademethod']) && (int) $activity['grademethod'] > 4) {
                    $result['errors'][] = "$itempath.grademethod must be between 0 and 4.";
                }
            }
            if ($type === 'book') {
                if (isset($activity['numbering']) && $activity['numbering'] > 3) {
                    $result['errors'][] = "$itempath.numbering must be between 0 and 3.";
                }
                if (isset($activity['navstyle']) && $activity['navstyle'] > 2) {
                    $result['errors'][] = "$itempath.navstyle must be between 0 and 2.";
                }
                self::chapters($activity['chapters'] ?? [], $itempath . '.chapters', $result);
            }
            if ($type === 'subsection') {
                if ($issubsection) {
                    $result['errors'][] = "$itempath cannot nest a subsection inside another subsection.";
                }
                if (array_key_exists('completion', $activity)) {
                    $result['errors'][] = "$itempath cannot set completion on a subsection.";
                }
                self::activities($activity['activities'] ?? [], $itempath . '.activities', true, $keys, $result);
            }
            if (array_key_exists('completion', $activity)) {
                $result['counts']['completionrules']++;
                $completion = $activity['completion'];
                if (!self::is_object($completion)) {
                    $result['errors'][] = "$itempath.completion must be an object.";
                } else {
                    self::allowed($completion, ['tracking', 'view', 'usegrade', 'passgrade', 'gradepass', 'submit',
                        'posts', 'discussions', 'replies', 'minattempts', 'expected'], $itempath . '.completion', $result);
                    if (!in_array($completion['tracking'] ?? '', ['none', 'manual', 'auto'], true)) {
                        $result['errors'][] = "$itempath.completion.tracking must be none, manual or auto.";
                    }
                    foreach (['view', 'usegrade', 'passgrade', 'submit'] as $field) {
                        self::optional_bool($completion, $field, $itempath . '.completion', $result);
                    }
                    foreach (['posts', 'discussions', 'replies', 'minattempts', 'expected'] as $field) {
                        if (array_key_exists($field, $completion) && (!is_int($completion[$field]) ||
                                $completion[$field] < 0)) {
                            $result['errors'][] = "$itempath.completion.$field must be a nonnegative integer.";
                        }
                    }
                    if (array_key_exists('gradepass', $completion) && !is_int($completion['gradepass']) &&
                            !is_float($completion['gradepass'])) {
                        $result['errors'][] = "$itempath.completion.gradepass must be a number.";
                    }
                }
            }
            if (preg_match('/<script\b/i', (string) ($activity['content'] ?? ''))) {
                $result['warnings'][] = "$itempath contains script markup; Moodle may strip it.";
            }
        }
    }

    private static function chapters($chapters, string $path, array &$result): void {
        if (!is_array($chapters) || !array_is_list($chapters) || count($chapters) > self::MAX_CHAPTERS) {
            $result['errors'][] = "$path must be an array with at most " . self::MAX_CHAPTERS . ' chapters.';
            return;
        }
        foreach ($chapters as $i => $chapter) {
            $itempath = "$path[$i]";
            if (!self::is_object($chapter)) {
                $result['errors'][] = "$itempath must be an object.";
                continue;
            }
            self::allowed($chapter, ['title', 'content', 'subchapter', 'hidden'], $itempath, $result);
            self::required_text($chapter, 'title', $itempath, $result);
            if (!isset($chapter['content']) || !is_string($chapter['content'])) {
                $result['errors'][] = "$itempath.content must be a string.";
            }
            foreach (['subchapter', 'hidden'] as $field) {
                self::optional_bool($chapter, $field, $itempath, $result);
            }
        }
    }

    private static function finish(array $result): array {
        $result['errors'] = array_slice($result['errors'], 0, 30);
        $result['warnings'] = array_slice($result['warnings'], 0, 30);
        $result['valid'] = count($result['errors']) === 0;
        return $result;
    }
}
