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
 * Delete a book chapter.
 *
 * @package    local_aimcp
 * @copyright  2026 Kenneth
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class delete_book_chapter extends external_api {

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
        ]);
    }

    /**
     * Delete the chapter (and its subchapters if it is a main chapter).
     *
     * @param int $chapterid
     * @return array
     */
    public static function execute(int $chapterid): array {
        global $DB;
        $params = self::validate_parameters(self::execute_parameters(), compact('chapterid'));
        [$chapter, $book, , $context] = self::load_chapter($params['chapterid']);

        $todelete = [$chapter];
        if (!$chapter->subchapter) {
            $following = $DB->get_records_select('book_chapters', 'bookid = ? AND pagenum > ?',
                [$book->id, $chapter->pagenum], 'pagenum ASC');
            foreach ($following as $next) {
                if (!$next->subchapter) {
                    break;
                }
                $todelete[] = $next;
            }
        }
        $fs = get_file_storage();
        $ids = [];
        foreach ($todelete as $ch) {
            $fs->delete_area_files($context->id, 'mod_book', 'chapter', $ch->id);
            $DB->delete_records('book_chapters', ['id' => $ch->id]);
            helper::chapter_event('chapter_deleted', $book, $context, $ch);
            $ids[] = (int) $ch->id;
        }
        helper::normalise_book($book->id);
        return ['deletedids' => $ids];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'deletedids' => new external_multiple_structure(new external_value(PARAM_INT, 'Chapter id'),
                'Ids of deleted chapters (the chapter itself plus any subchapters)'),
        ]);
    }

}
