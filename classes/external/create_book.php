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
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use local_aimcp\local\helper;

/**
 * Create a Book activity with optional chapters.
 *
 * @package    local_aimcp
 * @copyright  2026 Kenneth
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_book extends external_api {

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'section' => new external_value(PARAM_INT, 'Section number (0 = general section at the top)'),
            'name' => new external_value(PARAM_TEXT, 'Book title'),
            'intro' => new external_value(PARAM_RAW, 'Short description (HTML)', VALUE_DEFAULT, ''),
            'numbering' => new external_value(PARAM_INT,
                'Chapter formatting: 0 none, 1 numbers, 2 bullets, 3 indented', VALUE_DEFAULT, 1),
            'navstyle' => new external_value(PARAM_INT,
                'Navigation style: 0 TOC only, 1 images, 2 text (chapter titles)', VALUE_DEFAULT, 2),
            'customtitles' => new external_value(PARAM_BOOL,
                'Custom titles (chapter title is not shown automatically above the content)', VALUE_DEFAULT, false),
            'visible' => new external_value(PARAM_BOOL, 'Visible to students', VALUE_DEFAULT, true),
            'chapters' => new external_multiple_structure(new external_single_structure([
                'title' => new external_value(PARAM_TEXT, 'Chapter title'),
                'content' => new external_value(PARAM_RAW, 'Chapter content (HTML)'),
                'subchapter' => new external_value(PARAM_BOOL, 'Make this a subchapter of the previous main chapter',
                    VALUE_DEFAULT, false),
                'hidden' => new external_value(PARAM_BOOL, 'Hide from students', VALUE_DEFAULT, false),
            ]), 'Chapters to create, in order', VALUE_DEFAULT, []),
        ]);
    }

    /**
     * Create the book.
     *
     * @param int $courseid
     * @param int $section
     * @param string $name
     * @param string $intro
     * @param int $numbering
     * @param int $navstyle
     * @param bool $customtitles
     * @param bool $visible
     * @param array $chapters
     * @return array
     */
    public static function execute(int $courseid, int $section, string $name, string $intro = '',
            int $numbering = 1, int $navstyle = 2, bool $customtitles = false, bool $visible = true,
            array $chapters = []): array {
        global $DB;
        $params = self::validate_parameters(self::execute_parameters(), compact('courseid', 'section', 'name',
            'intro', 'numbering', 'navstyle', 'customtitles', 'visible', 'chapters'));
        [$course] = helper::require_course_editing($params['courseid']);

        $mi = helper::create_module($course, 'book', $params['section'], $params['name'], $params['intro'],
            (int) $params['visible'], [
                'numbering' => min(3, max(0, $params['numbering'])),
                'navstyle' => min(2, max(0, $params['navstyle'])),
                'customtitles' => $params['customtitles'] ? 1 : 0,
                'revision' => 0,
            ]);

        $book = $DB->get_record('book', ['id' => $mi->instance], '*', MUST_EXIST);
        $context = \core\context\module::instance($mi->coursemodule);
        foreach ($params['chapters'] as $ch) {
            $chapter = helper::insert_chapter($book, $ch['title'], $ch['content'], (bool) $ch['subchapter'],
                (bool) $ch['hidden']);
            helper::chapter_event('chapter_created', $book, $context, $chapter);
        }
        helper::normalise_book($book->id);

        $out = [];
        foreach ($DB->get_records('book_chapters', ['bookid' => $book->id], 'pagenum ASC') as $chapter) {
            $out[] = helper::export_chapter($chapter, false);
        }
        return ['cmid' => (int) $mi->coursemodule, 'bookid' => (int) $book->id, 'chapters' => $out];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cmid' => new external_value(PARAM_INT, 'Course module id of the new book'),
            'bookid' => new external_value(PARAM_INT, 'Book instance id'),
            'chapters' => new external_multiple_structure(helper::chapter_structure(),
                'Created chapters (content omitted)'),
        ]);
    }

}
