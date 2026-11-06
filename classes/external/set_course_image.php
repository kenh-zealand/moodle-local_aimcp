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
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use local_aimcp\local\gridimage;
use local_aimcp\local\helper;

/**
 * Upload, replace or remove the course image (the "Course image" field, file area course/overviewfiles).
 *
 * The course image is shown on course cards (Dashboard, My courses, course listings). It is independent of
 * the course format, so it works for Grid, Topics and every other format.
 *
 * @package    local_aimcp
 * @copyright  2026 Kenneth
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class set_course_image extends external_api {

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'imageurl' => new external_value(PARAM_RAW,
                'Public http(s) URL of a PNG, JPEG, GIF or WebP image to download, e.g. a Canva export URL. Max 5 MB.',
                VALUE_DEFAULT, ''),
            'imagedata' => new external_value(PARAM_RAW,
                'Base64-encoded PNG, JPEG, GIF or WebP (a data: URI is allowed). Max 5 MB.', VALUE_DEFAULT, ''),
            'svg' => new external_value(PARAM_RAW,
                'SVG markup, turned into a PNG on the server. Must be self-contained: no scripts, event handlers, ' .
                '<image>, <foreignObject>, DOCTYPE or external references. Max 512 KB.', VALUE_DEFAULT, ''),
            'filename' => new external_value(PARAM_FILE,
                'Base file name, e.g. "linux-forside". A short hash and the right extension are added.',
                VALUE_DEFAULT, ''),
            'width' => new external_value(PARAM_INT, 'Width in pixels of the PNG made from svg (200-2400).',
                VALUE_DEFAULT, 1200),
            'remove' => new external_value(PARAM_BOOL,
                'True = remove the course image. Give no image source together with remove.', VALUE_DEFAULT, false),
        ]);
    }

    /**
     * Set or remove the course image.
     *
     * @param int $courseid
     * @param string $imageurl
     * @param string $imagedata
     * @param string $svg
     * @param string $filename
     * @param int $width
     * @param bool $remove
     * @return array
     */
    public static function execute(int $courseid, string $imageurl = '', string $imagedata = '', string $svg = '',
            string $filename = '', int $width = 1200, bool $remove = false): array {
        global $CFG, $USER;
        require_once($CFG->libdir . '/filelib.php');

        $params = self::validate_parameters(self::execute_parameters(),
            compact('courseid', 'imageurl', 'imagedata', 'svg', 'filename', 'width', 'remove'));
        [$course, $context] = helper::require_course_editing($params['courseid']);
        require_capability('moodle/course:update', $context);

        $sources = array_filter([
            'imageurl' => trim($params['imageurl']),
            'imagedata' => trim($params['imagedata']),
            'svg' => trim($params['svg']),
        ], fn($value) => $value !== '');
        if (count($sources) > 1 || ($params['remove'] && count($sources) > 0) || (!$params['remove'] && !$sources)) {
            throw new \moodle_exception('courseimagesource', 'local_aimcp');
        }

        $fs = get_file_storage();
        $warnings = [];

        if ($params['remove']) {
            $fs->delete_area_files($context->id, 'course', 'overviewfiles');
            self::purge_cache((int) $course->id);
            $result = self::describe($context);
            $result['source'] = 'remove';
            $result['warnings'] = $warnings;
            return $result;
        }

        if (empty($CFG->courseoverviewfileslimit)) {
            throw new \moodle_exception('courseimagesdisabled', 'local_aimcp');
        }

        $source = array_key_first($sources);
        if ($source === 'svg') {
            $bytes = gridimage::svg_to_png($sources['svg'], (int) $params['width']);
        } else if ($source === 'imagedata') {
            $bytes = gridimage::decode_base64($sources['imagedata']);
        } else {
            $bytes = gridimage::download($sources['imageurl']);
        }
        $type = gridimage::detect($bytes);

        $base = clean_param(pathinfo($params['filename'], PATHINFO_FILENAME), PARAM_FILE);
        if ($base === '') {
            $base = 'course' . $course->id . '-image';
        }
        $storedname = $base . '-' . substr(sha1($bytes), 0, 8) . '.' . $type['ext'];

        // Respect the site's "Course image file extensions" setting, like the course settings form does.
        $allowed = $CFG->courseoverviewfilesext ?? 'web_image';
        $util = new \core_form\filetypes_util();
        if (!$util->is_allowed_file_type($storedname, $allowed)) {
            throw new \moodle_exception('imageinvalid', 'local_aimcp', '',
                'the site does not allow .' . $type['ext'] . ' course images (Course image file extensions setting)');
        }

        // The course image is the first image in the area; replace whatever is there.
        $fs->delete_area_files($context->id, 'course', 'overviewfiles');
        $fs->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'course',
            'filearea' => 'overviewfiles',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => $storedname,
            'userid' => $USER->id,
            'author' => fullname($USER),
            'license' => $CFG->sitedefaultlicense ?? 'unknown',
            'mimetype' => $type['mime'],
        ], $bytes);
        self::purge_cache((int) $course->id);

        if ($type['width'] < 600) {
            $warnings[] = ['item' => 'course', 'itemid' => (int) $course->id, 'warningcode' => 'smallimage',
                'message' => 'The image is only ' . $type['width'] . ' px wide and may look blurry on course cards.'];
        }

        $result = self::describe($context);
        $result['source'] = $source;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Forget cached course images, so course cards show the new image at once.
     *
     * @param int $courseid
     */
    private static function purge_cache(int $courseid): void {
        \cache::make('core', 'course_image')->delete($courseid);
    }

    /**
     * Describe the current course image.
     *
     * @param \core\context\course $context
     * @return array
     */
    private static function describe(\core\context\course $context): array {
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'course', 'overviewfiles', false, 'filename', false);
        $file = $files ? reset($files) : null;
        $info = $file ? $file->get_imageinfo() : false;
        return [
            'courseid' => (int) $context->instanceid,
            'hasimage' => !empty($file),
            'filename' => $file ? $file->get_filename() : '',
            'mimetype' => $file ? $file->get_mimetype() : '',
            'width' => $info ? (int) $info['width'] : 0,
            'height' => $info ? (int) $info['height'] : 0,
            'imageurl' => $file ? \moodle_url::make_pluginfile_url($context->id, 'course', 'overviewfiles', null,
                $file->get_filepath(), $file->get_filename())->out(false) : '',
        ];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'hasimage' => new external_value(PARAM_BOOL, 'The course has a course image'),
            'filename' => new external_value(PARAM_FILE, 'Stored file name, empty if none'),
            'mimetype' => new external_value(PARAM_RAW, 'MIME type, empty if none'),
            'width' => new external_value(PARAM_INT, 'Image width in pixels, 0 if none'),
            'height' => new external_value(PARAM_INT, 'Image height in pixels, 0 if none'),
            'imageurl' => new external_value(PARAM_URL, 'URL of the course image (needs a login), empty if none'),
            'source' => new external_value(PARAM_ALPHA, 'What was used: imageurl, imagedata, svg or remove'),
            'warnings' => new external_warnings(),
        ]);
    }
}
