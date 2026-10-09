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

namespace local_aimcp\local;

use core_external\external_single_structure;
use core_external\external_value;

/**
 * Section (tile) images for courses in the Grid format (format_grid).
 *
 * The image is stored exactly as the Grid format's own section form stores it: one file in the
 * format_grid/sectionimage file area (itemid = section id), a row in format_grid_image and a generated
 * displayed image in format_grid/displayedsectionimage. The alt text is the section format option
 * sectionimagealttext.
 *
 * @package    local_aimcp
 * @copyright  2026 Kenneth
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gridimage {

    /** @var int Largest accepted raster image (bytes). */
    public const MAXBYTES = 5 * 1024 * 1024;

    /** @var int Largest accepted SVG document (bytes). */
    public const MAXSVGBYTES = 512 * 1024;

    /** @var int Default width (px) of the PNG made from an SVG. 1140 x 600 matches a 1.9:1 tile. */
    public const SVGWIDTH = 1140;

    /** @var array Accepted raster types => [mime type, file extension]. Same set as the Grid format accepts. */
    private const TYPES = [
        IMAGETYPE_PNG => ['image/png', 'png'],
        IMAGETYPE_JPEG => ['image/jpeg', 'jpg'],
        IMAGETYPE_GIF => ['image/gif', 'gif'],
        IMAGETYPE_WEBP => ['image/webp', 'webp'],
    ];

    /**
     * Check that the course uses the Grid format and return the format instance.
     *
     * @param \stdClass $course
     * @return \core_courseformat\base
     */
    public static function require_grid(\stdClass $course): \core_courseformat\base {
        $format = course_get_format($course);
        if ($format->get_format() !== 'grid' || !class_exists('\format_grid\toolbox')) {
            throw new \moodle_exception('notgridformat', 'local_aimcp', '', $format->get_format());
        }
        return $format;
    }

    /**
     * Turn an SVG document into PNG bytes with Imagick.
     *
     * Only self-contained SVG is accepted: no DOCTYPE/entities, scripts, event handlers, embedded or external
     * images, foreignObject or references outside the document.
     *
     * @param string $svg SVG markup.
     * @param int $width Width of the resulting PNG in pixels.
     * @return string PNG bytes.
     */
    public static function svg_to_png(string $svg, int $width = self::SVGWIDTH): string {
        if (!extension_loaded('imagick') || !class_exists('\Imagick')) {
            throw new \moodle_exception('noimagick', 'local_aimcp');
        }
        $svg = trim($svg);
        self::check_svg($svg);
        $width = max(200, min(2400, $width));

        try {
            // Measure the SVG at a known density, then render it again at the density that gives the wanted width.
            $probe = new \Imagick();
            $probe->setResolution(72, 72);
            $probe->setBackgroundColor(new \ImagickPixel('transparent'));
            $probe->readImageBlob($svg, 'image.svg');
            $basewidth = max(1, $probe->getImageWidth());
            $probe->clear();

            $density = 72 * $width / $basewidth;
            $image = new \Imagick();
            $image->setResolution($density, $density);
            $image->setBackgroundColor(new \ImagickPixel('transparent'));
            $image->readImageBlob($svg, 'image.svg');
            if ($image->getImageWidth() != $width) {
                $height = max(1, (int) round($image->getImageHeight() * $width / max(1, $image->getImageWidth())));
                $image->resizeImage($width, $height, \Imagick::FILTER_LANCZOS, 1);
            }
            $image->setImageFormat('png');
            $image->stripImage();
            $png = $image->getImageBlob();
            $image->clear();
        } catch (\ImagickException $e) {
            throw new \moodle_exception('svginvalid', 'local_aimcp', '', $e->getMessage());
        }
        if ($png === '') {
            throw new \moodle_exception('svginvalid', 'local_aimcp', '', 'empty result');
        }
        return $png;
    }

    /**
     * Reject SVG that is not a plain, self-contained drawing.
     *
     * @param string $svg
     */
    private static function check_svg(string $svg): void {
        if ($svg === '') {
            throw new \moodle_exception('svginvalid', 'local_aimcp', '', 'empty');
        }
        if (strlen($svg) > self::MAXSVGBYTES) {
            throw new \moodle_exception('imagetoolarge', 'local_aimcp', '', display_size(self::MAXSVGBYTES));
        }
        if (!preg_match('~^(<\?xml[^>]*\?>\s*)?(<!--.*?-->\s*)*<svg[\s>]~is', $svg)) {
            throw new \moodle_exception('svginvalid', 'local_aimcp', '', 'the document must start with <svg');
        }
        // Namespace declarations are the only place a URL may appear.
        $check = preg_replace('~\sxmlns(:[\w.-]+)?\s*=\s*("[^"]*"|\'[^\']*\')~i', ' ', $svg);
        $forbidden = '~<!DOCTYPE|<!ENTITY|<script|<foreignObject|<image[\s/>]|<feImage|<iframe|<object|<embed|<audio|' .
            '<video|<handler|<listener|@import|://|javascript:|\bfile:|\bdata:|\son[a-z]+\s*=~i';
        if (preg_match($forbidden, $check, $match)) {
            throw new \moodle_exception('svginvalid', 'local_aimcp', '', 'not allowed: ' . trim($match[0]));
        }
        if (preg_match_all('~(?:xlink:)?href\s*=\s*(?:"([^"]*)"|\'([^\']*)\')~i', $check, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $target = trim($m[1] !== '' ? $m[1] : ($m[2] ?? ''));
                if ($target === '' || $target[0] !== '#') {
                    throw new \moodle_exception('svginvalid', 'local_aimcp', '', 'only internal href="#id" references are allowed');
                }
            }
        }
        if (preg_match_all('~url\(\s*[\'"]?\s*([^\'")\s]*)~i', $check, $matches)) {
            foreach ($matches[1] as $target) {
                if ($target === '' || $target[0] !== '#') {
                    throw new \moodle_exception('svginvalid', 'local_aimcp', '', 'only internal url(#id) references are allowed');
                }
            }
        }
    }

    /**
     * Decode base64 image data (a data: URI prefix is allowed).
     *
     * @param string $data
     * @return string Image bytes.
     */
    public static function decode_base64(string $data): string {
        $data = trim($data);
        if (preg_match('~^data:[^,]*;base64,~i', $data)) {
            $data = substr($data, strpos($data, ',') + 1);
        }
        $data = preg_replace('~\s+~', '', $data);
        if (strlen($data) > self::MAXBYTES * 4 / 3 + 4) {
            throw new \moodle_exception('imagetoolarge', 'local_aimcp', '', display_size(self::MAXBYTES));
        }
        $bytes = base64_decode($data, true);
        if ($bytes === false || $bytes === '') {
            throw new \moodle_exception('imageinvalid', 'local_aimcp', '', 'imagedata is not valid base64');
        }
        return $bytes;
    }

    /**
     * Download an image from an http(s) URL. Moodle's curl security settings (blocked hosts and ports) apply.
     *
     * @param string $url
     * @return string Image bytes.
     */
    public static function download(string $url): string {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $url = clean_param(trim($url), PARAM_URL);
        if (!preg_match('~^https?://~i', $url)) {
            throw new \moodle_exception('imagedownloadfailed', 'local_aimcp', '', 'only http and https URLs are allowed');
        }
        $response = download_file_content($url, null, null, true, 30, 10);
        if (empty($response) || !empty($response->error) || (string) $response->status !== '200') {
            $reason = !empty($response->error) ? $response->error : ('HTTP status ' . ($response->status ?? '?'));
            throw new \moodle_exception('imagedownloadfailed', 'local_aimcp', '', $reason);
        }
        return (string) $response->results;
    }

    /**
     * Identify a raster image.
     *
     * @param string $bytes
     * @return array ['mime' => string, 'ext' => string, 'width' => int, 'height' => int]
     */
    public static function detect(string $bytes): array {
        if (strlen($bytes) > self::MAXBYTES) {
            throw new \moodle_exception('imagetoolarge', 'local_aimcp', '', display_size(self::MAXBYTES));
        }
        $info = @getimagesizefromstring($bytes);
        if (!$info || !isset(self::TYPES[$info[2]])) {
            throw new \moodle_exception('imageinvalid', 'local_aimcp', '', 'only PNG, JPEG, GIF and WebP images are supported');
        }
        [$mime, $ext] = self::TYPES[$info[2]];
        return ['mime' => $mime, 'ext' => $ext, 'width' => (int) $info[0], 'height' => (int) $info[1]];
    }

    /**
     * Store the image as the section's Grid image (replacing any existing one) and generate the displayed image.
     *
     * The file name gets a short content hash, so the browser does not keep showing an old image after a change.
     *
     * @param \stdClass $course
     * @param \core_courseformat\base $format
     * @param \section_info $section
     * @param string $bytes
     * @param array $type From detect().
     * @param string $filename Wanted base name (may be empty).
     * @return array [\stdClass $record from format_grid_image, string|null $warning]
     */
    public static function store(\stdClass $course, \core_courseformat\base $format, \section_info $section,
            string $bytes, array $type, string $filename): array {
        global $CFG, $DB, $USER;

        $context = \core\context\course::instance($course->id);
        $sectionid = (int) $section->id;

        $base = clean_param(pathinfo($filename, PATHINFO_FILENAME), PARAM_FILE);
        if ($base === '') {
            $base = 'section' . $section->section . '-grid';
        }
        $filename = $base . '-' . substr(sha1($bytes), 0, 8) . '.' . $type['ext'];

        $lock = self::lock($sectionid);
        $warning = null;
        try {
            $fs = get_file_storage();
            $fs->delete_area_files($context->id, 'format_grid', 'sectionimage', $sectionid);
            $file = $fs->create_file_from_string([
                'contextid' => $context->id,
                'component' => 'format_grid',
                'filearea' => 'sectionimage',
                'itemid' => $sectionid,
                'filepath' => '/',
                'filename' => $filename,
                'userid' => $USER->id,
                'author' => fullname($USER),
                'license' => $CFG->sitedefaultlicense ?? 'unknown',
                'mimetype' => $type['mime'],
            ], $bytes);

            $record = $DB->get_record('format_grid_image', ['courseid' => $course->id, 'sectionid' => $sectionid]);
            if ($record) {
                $record->image = $filename;
                $record->contenthash = $file->get_contenthash();
                $record->displayedimagestate = 0; // Not generated.
                $DB->update_record('format_grid_image', $record);
            } else {
                $record = (object) [
                    'sectionid' => $sectionid,
                    'courseid' => $course->id,
                    'image' => $filename,
                    'contenthash' => $file->get_contenthash(),
                    'displayedimagestate' => 0,
                ];
                $record->id = $DB->insert_record('format_grid_image', $record);
            }

            try {
                $record = \format_grid\toolbox::get_instance()->setup_displayed_image($record, $file, $course->id,
                    $sectionid, $format);
            } catch (\moodle_exception $e) {
                // The original is stored; the Grid format retries the displayed image when the course page is viewed.
                $warning = $e->getMessage();
            }
        } finally {
            if ($lock !== true) {
                $lock->release();
            }
        }
        return [$record, $warning];
    }

    /**
     * Remove the section's Grid image (original, displayed image and database row).
     *
     * @param \stdClass $course
     * @param \section_info $section
     * @return bool True if there was an image.
     */
    public static function delete(\stdClass $course, \section_info $section): bool {
        global $DB;

        $context = \core\context\course::instance($course->id);
        $sectionid = (int) $section->id;
        $fs = get_file_storage();
        $existed = $DB->record_exists('format_grid_image', ['courseid' => $course->id, 'sectionid' => $sectionid])
            || !$fs->is_area_empty($context->id, 'format_grid', 'sectionimage', $sectionid);

        \format_grid\toolbox::delete_image($sectionid, $course->id);
        // Clean up files that have no database row.
        $fs->delete_area_files($context->id, 'format_grid', 'sectionimage', $sectionid);
        $fs->delete_area_files($context->id, 'format_grid', 'displayedsectionimage', $sectionid);
        return $existed;
    }

    /**
     * Set the alt text of the section image.
     *
     * @param \core_courseformat\base $format
     * @param \section_info $section
     * @param string $alttext
     */
    public static function set_alttext(\core_courseformat\base $format, \section_info $section, string $alttext): void {
        $format->update_section_format_options(['id' => (int) $section->id, 'sectionimagealttext' => $alttext]);
    }

    /**
     * Describe the Grid image of one section.
     *
     * @param \stdClass $course
     * @param \core_courseformat\base $format
     * @param \section_info $section
     * @return array Matches section_structure().
     */
    public static function describe(\stdClass $course, \core_courseformat\base $format, \section_info $section): array {
        global $DB;

        $context = \core\context\course::instance($course->id);
        $record = $DB->get_record('format_grid_image', ['courseid' => $course->id, 'sectionid' => $section->id]);
        $options = $format->get_format_options($section);

        $imageurl = '';
        if ($record && (int) $record->displayedimagestate >= 1) {
            $webp = (get_config('format_grid', 'defaultdisplayedimagefiletype') == 2);
            $imageurl = \format_grid\toolbox::get_instance()->get_displayed_image_uri($record, $context->id,
                $section->id, $webp);
        }
        return [
            'sectionid' => (int) $section->id,
            'section' => (int) $section->section,
            'name' => get_section_name($course, $section),
            'delegated' => !empty($section->component),
            'hasimage' => !empty($record),
            'filename' => $record ? (string) $record->image : '',
            'alttext' => (string) ($options['sectionimagealttext'] ?? ''),
            'displayedimagestate' => $record ? (int) $record->displayedimagestate : 0,
            'imageurl' => $imageurl,
        ];
    }

    /**
     * Return structure for one section image.
     *
     * @return external_single_structure
     */
    public static function section_structure(): external_single_structure {
        return new external_single_structure([
            'sectionid' => new external_value(PARAM_INT, 'Section id'),
            'section' => new external_value(PARAM_INT, 'Section number'),
            'name' => new external_value(PARAM_TEXT, 'Displayed section name'),
            'delegated' => new external_value(PARAM_BOOL, 'True for a subsection (not shown as a tile)'),
            'hasimage' => new external_value(PARAM_BOOL, 'The section has a Grid image'),
            'filename' => new external_value(PARAM_FILE, 'Stored file name, empty if none'),
            'alttext' => new external_value(PARAM_TEXT, 'Alt text of the image'),
            'displayedimagestate' => new external_value(PARAM_INT,
                'Displayed image: 0 not generated yet, >= 1 generated, -1 could not be generated'),
            'imageurl' => new external_value(PARAM_URL, 'URL of the displayed tile image (needs a login), empty if none'),
        ]);
    }

    /**
     * Get the same lock as the Grid format uses when its section form changes an image.
     *
     * @param int $sectionid
     * @return \core\lock\lock|true
     */
    private static function lock(int $sectionid) {
        if (defined('BEHAT_SITE_RUNNING')) {
            return true;
        }
        $lock = \core\lock\lock_config::get_lock_factory('format_grid')->get_lock('sectionid' . $sectionid, 5);
        if (!$lock) {
            throw new \moodle_exception('cannotgetmanagesectionimagelock', 'format_grid');
        }
        return $lock;
    }
}
