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
 * Add a chapter or subchapter to a book.
 *
 * @package    local_aimcp
 * @copyright  2026 Kenneth
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class add_book_chapter extends external_api {

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
            'title' => new external_value(PARAM_TEXT, 'Chapter title'),
            'content' => new external_value(PARAM_RAW, 'Chapter content (HTML)'),
            'subchapter' => new external_value(PARAM_BOOL, 'Make this a subchapter', VALUE_DEFAULT, false),
            'hidden' => new external_value(PARAM_BOOL, 'Hide from students', VALUE_DEFAULT, false),
            'pagenum' => new external_value(PARAM_INT, 'Insert at this position (1 = first). 0 = append at the end.',
                VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Add the chapter.
     *
     * @param int $cmid
     * @param string $title
     * @param string $content
     * @param bool $subchapter
     * @param bool $hidden
     * @param int $pagenum
     * @return array
     */
    public static function execute(int $cmid, string $title, string $content, bool $subchapter = false,
            bool $hidden = false, int $pagenum = 0): array {
        global $DB;
        $params = self::validate_parameters(self::execute_parameters(),
            compact('cmid', 'title', 'content', 'subchapter', 'hidden', 'pagenum'));
        [, , $context, $book] = self::load_book($params['cmid'], 'mod/book:edit');
        $chapter = helper::insert_chapter($book, $params['title'], $params['content'], (bool) $params['subchapter'],
            (bool) $params['hidden'], $params['pagenum']);
        helper::normalise_book($book->id);
        $chapter = $DB->get_record('book_chapters', ['id' => $chapter->id], '*', MUST_EXIST);
        helper::chapter_event('chapter_created', $book, $context, $chapter);
        return helper::export_chapter($chapter, true);
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return helper::chapter_structure();
    }

}
