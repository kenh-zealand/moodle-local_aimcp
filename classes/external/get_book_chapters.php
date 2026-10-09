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
 * Read a Book activity and its chapters.
 *
 * @package    local_aimcp
 * @copyright  2026 Kenneth
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_book_chapters extends external_api {

    /**
     * Load book, cm and context and check the capability.
     *
     * @param int $cmid
     * @param string $capability
     * @return array [course, cm, context, book]
     */
    protected static function load_book(int $cmid, string $capability): array {
        global $DB;
        [$course, $cm] = get_course_and_cm_from_cmid($cmid, 'book');
        $context = \core\context\module::instance($cm->id);
        self::validate_context($context);
        require_capability($capability, $context);
        $book = $DB->get_record('book', ['id' => $cm->instance], '*', MUST_EXIST);
        return [$course, $cm, $context, $book];
    }

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id of the book'),
            'chapterid' => new external_value(PARAM_INT, 'Only return this chapter (0 = all chapters)',
                VALUE_DEFAULT, 0),
            'includecontent' => new external_value(PARAM_BOOL,
                'Include chapter HTML content. Set false to get only the table of contents.', VALUE_DEFAULT, true),
        ]);
    }

    /**
     * Read the book.
     *
     * @param int $cmid
     * @param int $chapterid
     * @param bool $includecontent
     * @return array
     */
    public static function execute(int $cmid, int $chapterid = 0, bool $includecontent = true): array {
        global $DB;
        $params = self::validate_parameters(self::execute_parameters(), compact('cmid', 'chapterid', 'includecontent'));
        [$course, $cm, $context, $book] = self::load_book($params['cmid'], 'mod/book:read');
        $canviewhidden = has_capability('mod/book:viewhiddenchapters', $context);

        $conditions = ['bookid' => $book->id];
        if ($params['chapterid']) {
            $conditions['id'] = $params['chapterid'];
        }
        $chapters = [];
        foreach ($DB->get_records('book_chapters', $conditions, 'pagenum ASC') as $chapter) {
            if ($chapter->hidden && !$canviewhidden) {
                continue;
            }
            $chapters[] = helper::export_chapter($chapter, (bool) $params['includecontent']);
        }
        return [
            'bookid' => (int) $book->id,
            'cmid' => (int) $cm->id,
            'courseid' => (int) $course->id,
            'name' => (string) $book->name,
            'intro' => (string) $book->intro,
            'numbering' => (int) $book->numbering,
            'navstyle' => (int) $book->navstyle,
            'customtitles' => (bool) $book->customtitles,
            'chapters' => $chapters,
        ];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'bookid' => new external_value(PARAM_INT, 'Book instance id'),
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'name' => new external_value(PARAM_TEXT, 'Book name'),
            'intro' => new external_value(PARAM_RAW, 'Book description (HTML)'),
            'numbering' => new external_value(PARAM_INT, 'Chapter formatting: 0 none, 1 numbers, 2 bullets, 3 indented'),
            'navstyle' => new external_value(PARAM_INT, 'Navigation: 0 TOC only, 1 images, 2 text'),
            'customtitles' => new external_value(PARAM_BOOL, 'Custom titles'),
            'chapters' => new external_multiple_structure(helper::chapter_structure(), 'Chapters in page order'),
        ]);
    }

}
