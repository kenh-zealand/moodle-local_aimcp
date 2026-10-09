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
 * Update a book chapter.
 *
 * @package    local_aimcp
 * @copyright  2026 Kenneth
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class update_book_chapter extends external_api {

    /**
     * Load chapter + book and check edit capability.
     *
     * @param int $chapterid
     * @return array [chapter, book, cm, context]
     */
    protected static function load_chapter(int $chapterid): array {
        global $DB;
        $chapter = $DB->get_record('book_chapters', ['id' => $chapterid], '*', MUST_EXIST);
        $book = $DB->get_record('book', ['id' => $chapter->bookid], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('book', $book->id, $book->course, false, MUST_EXIST);
        $context = \core\context\module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/book:edit', $context);
        return [$chapter, $book, $cm, $context];
    }

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'chapterid' => new external_value(PARAM_INT, 'Chapter id'),
            'title' => new external_value(PARAM_TEXT, 'New title (omit to keep)', VALUE_DEFAULT, null),
            'content' => new external_value(PARAM_RAW,
                'New content as HTML (omit to keep). Replaces the whole chapter body.', VALUE_DEFAULT, null),
            'subchapter' => new external_value(PARAM_BOOL, 'Change main/subchapter (omit to keep)', VALUE_DEFAULT, null),
            'hidden' => new external_value(PARAM_BOOL, 'Hide/show (omit to keep). Hiding or showing a main chapter ' .
                'applies to its subchapters too.', VALUE_DEFAULT, null),
        ]);
    }

    /**
     * Update the chapter.
     *
     * @param int $chapterid
     * @param string|null $title
     * @param string|null $content
     * @param bool|null $subchapter
     * @param bool|null $hidden
     * @return array
     */
    public static function execute(int $chapterid, ?string $title = null, ?string $content = null,
            ?bool $subchapter = null, ?bool $hidden = null): array {
        global $DB;
        $params = self::validate_parameters(self::execute_parameters(),
            compact('chapterid', 'title', 'content', 'subchapter', 'hidden'));
        [$chapter, $book, , $context] = self::load_chapter($params['chapterid']);

        $update = ['id' => $chapter->id];
        if ($params['title'] !== null) {
            $update['title'] = $params['title'];
        }
        if ($params['content'] !== null) {
            $update['content'] = $params['content'];
            $update['contentformat'] = FORMAT_HTML;
        }
        if ($params['subchapter'] !== null) {
            $update['subchapter'] = $params['subchapter'] ? 1 : 0;
        }
        if ($params['hidden'] !== null) {
            $update['hidden'] = $params['hidden'] ? 1 : 0;
        }
        $update['timemodified'] = time();
        $DB->update_record('book_chapters', (object) $update);

        // Like the Book UI: hiding/showing a main chapter applies to its subchapters.
        $current = $DB->get_record('book_chapters', ['id' => $chapter->id], '*', MUST_EXIST);
        if ($params['hidden'] !== null && !$current->subchapter) {
            $following = $DB->get_records_select('book_chapters', 'bookid = ? AND pagenum > ?',
                [$book->id, $current->pagenum], 'pagenum ASC');
            foreach ($following as $next) {
                if (!$next->subchapter) {
                    break;
                }
                $DB->update_record('book_chapters', (object) ['id' => $next->id,
                    'hidden' => $current->hidden, 'timemodified' => time()]);
            }
        }
        helper::normalise_book($book->id);
        $current = $DB->get_record('book_chapters', ['id' => $chapter->id], '*', MUST_EXIST);
        helper::chapter_event('chapter_updated', $book, $context, $current);
        return helper::export_chapter($current, true);
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
