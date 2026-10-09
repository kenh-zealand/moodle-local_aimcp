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

use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;

/**
 * Shared helpers for the AI MCP content tools.
 *
 * @package    local_aimcp
 * @copyright  2026 Kenneth
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {

    /**
     * Load a course, validate the context and check that the user may add/edit activities.
     *
     * @param int $courseid
     * @return array [stdClass $course, \core\context\course $context]
     */
    public static function require_course_editing(int $courseid): array {
        $course = get_course($courseid);
        $context = \core\context\course::instance($course->id);
        \core_external\external_api::validate_context($context);
        require_capability('moodle/course:manageactivities', $context);
        return [$course, $context];
    }

    /**
     * Load a course module, validate its context and check editing rights.
     *
     * @param int $cmid
     * @param string|null $modname Expected module name, or null for any.
     * @return array [stdClass $course, \cm_info $cm, \core\context\module $context]
     */
    public static function require_cm_editing(int $cmid, ?string $modname = null): array {
        [$course, $cm] = get_course_and_cm_from_cmid($cmid, $modname ?? '');
        $context = \core\context\module::instance($cm->id);
        \core_external\external_api::validate_context($context);
        require_capability('moodle/course:manageactivities', $context);
        return [$course, $cm, $context];
    }

    /**
     * Find the category new AI-created courses go into for a user.
     *
     * Internal users (authorised on the internal service, or site admins) get the configured internal category.
     * Everybody else gets their own subcategory of the configured external parent category, matched on
     * category idnumber or name = username, then idnumber or name = the part of the e-mail before @.
     * If none is found, the parent itself is used.
     *
     * @param int $userid
     * @return \core_course_category|null
     */
    public static function default_category(int $userid): ?\core_course_category {
        global $DB;
        $internalcatid = (int) get_config('local_aimcp', 'internalcategory');
        $externalcatid = (int) get_config('local_aimcp', 'externalcategory');

        $isinternal = is_siteadmin($userid);
        if (!$isinternal) {
            $serviceid = $DB->get_field('external_services', 'id', ['shortname' => 'aimcp_internal']);
            $isinternal = $serviceid && $DB->record_exists('external_services_users',
                ['externalserviceid' => $serviceid, 'userid' => $userid]);
        }
        if ($isinternal) {
            return $internalcatid ? \core_course_category::get($internalcatid, IGNORE_MISSING, true) : null;
        }
        if (!$externalcatid) {
            return null;
        }
        $user = $DB->get_record('user', ['id' => $userid], 'id, username, email');
        $keys = [];
        if ($user) {
            $keys[] = \core_text::strtolower(trim($user->username));
            if (strpos((string) $user->email, '@') !== false) {
                $keys[] = \core_text::strtolower(trim(strstr($user->email, '@', true)));
            }
        }
        $keys = array_values(array_unique(array_filter($keys, fn($k) => $k !== '')));
        $children = $DB->get_records('course_categories', ['parent' => $externalcatid], 'sortorder', 'id, name, idnumber');
        foreach ($keys as $key) {
            foreach (['idnumber', 'name'] as $field) {
                foreach ($children as $child) {
                    if (\core_text::strtolower(trim((string) $child->$field)) === $key) {
                        return \core_course_category::get($child->id, IGNORE_MISSING, true);
                    }
                }
            }
        }
        return \core_course_category::get($externalcatid, IGNORE_MISSING, true);
    }

    /**
     * Make sure the target section exists (normal sections are created if missing).
     *
     * @param stdClass $course
     * @param int $section
     */
    public static function ensure_section(\stdClass $course, int $section): void {
        $modinfo = get_fast_modinfo($course);
        if ($modinfo->get_section_info($section)) {
            return;
        }
        \core_courseformat\formatactions::section($course)->create_if_missing([$section]);
    }

    /**
     * Create a course module with the same defaults Moodle's own generators use.
     *
     * @param stdClass $course
     * @param string $modname
     * @param int $section
     * @param string $name
     * @param string $intro HTML
     * @param int $visible
     * @param array $extra Module specific fields.
     * @return stdClass moduleinfo returned by create_module() (has coursemodule and instance).
     */
    public static function create_module(\stdClass $course, string $modname, int $section, string $name,
            string $intro, int $visible, array $extra = []): \stdClass {
        global $CFG;
        require_once($CFG->dirroot . '/course/modlib.php');

        self::ensure_section($course, $section);

        $moduleinfo = (object) array_merge([
            'modulename' => $modname,
            'course' => $course->id,
            'section' => $section,
            'name' => $name,
            'visible' => $visible ? 1 : 0,
            'visibleoncoursepage' => 1,
            'cmidnumber' => '',
            'groupmode' => 0,
            'groupingid' => 0,
            'availability' => null,
            'completion' => 0,
            'completionview' => 0,
            'completionexpected' => 0,
            'completionpassgrade' => 0,
            'conditiongradegroup' => [],
            'conditionfieldgroup' => [],
            'conditioncompletiongroup' => [],
            'introeditor' => [
                'text' => $intro,
                'format' => FORMAT_HTML,
                'itemid' => file_get_unused_draft_itemid(),
            ],
        ], $extra);

        return create_module($moduleinfo);
    }

    /**
     * Standard return value for the create_* functions.
     *
     * @param stdClass $moduleinfo
     * @param array $warnings
     * @return array
     */
    public static function created_result(\stdClass $moduleinfo, array $warnings = []): array {
        $url = new \moodle_url('/mod/' . $moduleinfo->modulename . '/view.php', ['id' => $moduleinfo->coursemodule]);
        return [
            'cmid' => (int) $moduleinfo->coursemodule,
            'instanceid' => (int) $moduleinfo->instance,
            'name' => (string) $moduleinfo->name,
            'url' => $url->out(false),
            'warnings' => $warnings,
        ];
    }

    /**
     * Fields shared by the create_* return structures.
     *
     * @return array
     */
    public static function created_fields(): array {
        return [
            'cmid' => new external_value(PARAM_INT, 'Course module id of the new activity'),
            'instanceid' => new external_value(PARAM_INT, 'Instance id in the module table'),
            'name' => new external_value(PARAM_TEXT, 'Activity name'),
            'url' => new external_value(PARAM_URL, 'Link to the activity'),
            'warnings' => new external_warnings(),
        ];
    }

    /**
     * Standard return structure for the create_* functions.
     *
     * @return external_single_structure
     */
    public static function created_returns(): external_single_structure {
        return new external_single_structure(self::created_fields());
    }

    /**
     * Shared structure for a book chapter.
     *
     * @return external_single_structure
     */
    public static function chapter_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Chapter id'),
            'pagenum' => new external_value(PARAM_INT, 'Position in the book (1-based)'),
            'subchapter' => new external_value(PARAM_BOOL, 'True if this is a subchapter'),
            'title' => new external_value(PARAM_TEXT, 'Chapter title'),
            'content' => new external_value(PARAM_RAW, 'Chapter content (raw HTML; empty if not requested)'),
            'hidden' => new external_value(PARAM_BOOL, 'Hidden from students'),
            'timemodified' => new external_value(PARAM_INT, 'Last modified (unix timestamp)'),
        ]);
    }

    /**
     * Format a chapter record for output.
     *
     * @param stdClass $chapter
     * @param bool $includecontent
     * @return array
     */
    public static function export_chapter(\stdClass $chapter, bool $includecontent = true): array {
        return [
            'id' => (int) $chapter->id,
            'pagenum' => (int) $chapter->pagenum,
            'subchapter' => (bool) $chapter->subchapter,
            'title' => (string) $chapter->title,
            'content' => $includecontent ? (string) $chapter->content : '',
            'hidden' => (bool) $chapter->hidden,
            'timemodified' => (int) $chapter->timemodified,
        ];
    }

    /**
     * Renumber a book's chapters 1..n, make sure the first chapter is a main chapter and bump revision.
     *
     * @param int $bookid
     */
    public static function normalise_book(int $bookid): void {
        global $DB;
        $chapters = $DB->get_records('book_chapters', ['bookid' => $bookid], 'pagenum ASC, id ASC');
        $i = 0;
        foreach ($chapters as $chapter) {
            $i++;
            $changes = [];
            if ((int) $chapter->pagenum !== $i) {
                $changes['pagenum'] = $i;
            }
            if ($i === 1 && $chapter->subchapter) {
                $changes['subchapter'] = 0;
            }
            if ($changes) {
                $changes['id'] = $chapter->id;
                $DB->update_record('book_chapters', (object) $changes);
            }
        }
        $DB->execute('UPDATE {book} SET revision = revision + 1, timemodified = ? WHERE id = ?', [time(), $bookid]);
    }

    /**
     * Insert a chapter into a book at a given position (0 = append).
     *
     * @param stdClass $book
     * @param string $title
     * @param string $content
     * @param bool $subchapter
     * @param bool $hidden
     * @param int $pagenum
     * @return stdClass the chapter record
     */
    public static function insert_chapter(\stdClass $book, string $title, string $content, bool $subchapter,
            bool $hidden, int $pagenum = 0): \stdClass {
        global $DB;
        $max = (int) $DB->get_field_sql('SELECT MAX(pagenum) FROM {book_chapters} WHERE bookid = ?', [$book->id]);
        if ($pagenum <= 0 || $pagenum > $max) {
            $pagenum = $max + 1;
        } else {
            $DB->execute('UPDATE {book_chapters} SET pagenum = pagenum + 1 WHERE bookid = ? AND pagenum >= ?',
                [$book->id, $pagenum]);
        }
        $now = time();
        $chapter = (object) [
            'bookid' => $book->id,
            'pagenum' => $pagenum,
            'subchapter' => $subchapter ? 1 : 0,
            'title' => $title,
            'content' => $content,
            'contentformat' => FORMAT_HTML,
            'hidden' => $hidden ? 1 : 0,
            'timecreated' => $now,
            'timemodified' => $now,
            'importsrc' => '',
        ];
        $chapter->id = $DB->insert_record('book_chapters', $chapter);
        return $chapter;
    }

    /**
     * Fire the chapter event and return the record.
     *
     * @param string $eventclass short class name, e.g. chapter_created
     * @param stdClass $book
     * @param \context $context
     * @param stdClass $chapter
     */
    public static function chapter_event(string $eventclass, \stdClass $book, \context $context, \stdClass $chapter): void {
        $class = '\\mod_book\\event\\' . $eventclass;
        if (class_exists($class)) {
            $class::create_from_chapter($book, $context, $chapter)->trigger();
        }
    }
}
