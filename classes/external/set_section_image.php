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
 * Upload or replace the Grid-format tile image of a course section, and/or set its alt text.
 *
 * @package    local_aimcp
 * @copyright  2026 Kenneth
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class set_section_image extends external_api {

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id (the course must use the Grid format)'),
            'section' => new external_value(PARAM_INT, 'Section number'),
            'svg' => new external_value(PARAM_RAW,
                'SVG markup, turned into a PNG on the server. Recommended for AI: use viewBox="0 0 1140 600" (1.9:1). ' .
                'Must be self-contained: no scripts, event handlers, <image>, <foreignObject>, DOCTYPE or ' .
                'references outside the document (only href="#id" and url(#id)). Max 512 KB.',
                VALUE_DEFAULT, ''),
            'imagedata' => new external_value(PARAM_RAW,
                'Base64-encoded PNG, JPEG, GIF or WebP (a data: URI is allowed). Max 5 MB.', VALUE_DEFAULT, ''),
            'imageurl' => new external_value(PARAM_RAW,
                'Public http(s) URL of a PNG, JPEG, GIF or WebP image to download. Max 5 MB.', VALUE_DEFAULT, ''),
            'filename' => new external_value(PARAM_FILE,
                'Base file name, e.g. "bjerg-flag". A short hash and the right extension are added.', VALUE_DEFAULT, ''),
            'alttext' => new external_value(PARAM_TEXT,
                'Alt text for the image; null/omitted = unchanged. Send it alone to change only the alt text.',
                VALUE_DEFAULT, null),
            'width' => new external_value(PARAM_INT, 'Width in pixels of the PNG made from svg (200-2400).',
                VALUE_DEFAULT, gridimage::SVGWIDTH),
        ]);
    }

    /**
     * Set the image.
     *
     * @param int $courseid
     * @param int $section
     * @param string $svg
     * @param string $imagedata
     * @param string $imageurl
     * @param string $filename
     * @param string|null $alttext
     * @param int $width
     * @return array
     */
    public static function execute(int $courseid, int $section, string $svg = '', string $imagedata = '',
            string $imageurl = '', string $filename = '', ?string $alttext = null, int $width = gridimage::SVGWIDTH): array {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');
        $params = self::validate_parameters(self::execute_parameters(),
            compact('courseid', 'section', 'svg', 'imagedata', 'imageurl', 'filename', 'alttext', 'width'));
        [$course, $context] = helper::require_course_editing($params['courseid']);
        require_capability('moodle/course:update', $context);
        $format = gridimage::require_grid($course);
        $sectioninfo = get_fast_modinfo($course)->get_section_info($params['section'], MUST_EXIST);

        $sources = array_filter([
            'svg' => trim($params['svg']),
            'imagedata' => trim($params['imagedata']),
            'imageurl' => trim($params['imageurl']),
        ], fn($value) => $value !== '');
        if (count($sources) > 1) {
            throw new \moodle_exception('imagesource', 'local_aimcp');
        }

        $warnings = [];
        $source = array_key_first($sources);
        if ($source === null) {
            // Alt text only.
            $current = gridimage::describe($course, $format, $sectioninfo);
            if ($params['alttext'] === null || !$current['hasimage']) {
                throw new \moodle_exception('imagesource', 'local_aimcp');
            }
        } else {
            if ($source === 'svg') {
                $bytes = gridimage::svg_to_png($sources['svg'], (int) $params['width']);
            } else if ($source === 'imagedata') {
                $bytes = gridimage::decode_base64($sources['imagedata']);
            } else {
                $bytes = gridimage::download($sources['imageurl']);
            }
            $type = gridimage::detect($bytes);
            [, $warning] = gridimage::store($course, $format, $sectioninfo, $bytes, $type, $params['filename']);
            if ($warning !== null) {
                $warnings[] = ['item' => 'section', 'itemid' => (int) $sectioninfo->id,
                    'warningcode' => 'displayedimage', 'message' => $warning];
            }
        }

        if ($params['alttext'] !== null) {
            gridimage::set_alttext($format, $sectioninfo, $params['alttext']);
        }
        if (!empty($sectioninfo->component)) {
            $warnings[] = ['item' => 'section', 'itemid' => (int) $sectioninfo->id, 'warningcode' => 'delegated',
                'message' => 'This is a subsection. The Grid format only shows tiles for top-level sections.'];
        }

        $sectioninfo = get_fast_modinfo($course)->get_section_info($params['section'], MUST_EXIST);
        $result = gridimage::describe($course, $format, $sectioninfo);
        if ($source !== null && $result['alttext'] === '') {
            $warnings[] = ['item' => 'section', 'itemid' => (int) $sectioninfo->id, 'warningcode' => 'noalttext',
                'message' => 'The image has no alt text. Set alttext unless the image is purely decorative.'];
        }
        $result['source'] = $source ?? 'alttext';
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        $structure = gridimage::section_structure();
        $structure->keys['source'] = new external_value(PARAM_ALPHA, 'What was used: svg, imagedata, imageurl or alttext');
        $structure->keys['warnings'] = new external_warnings();
        return $structure;
    }
}
