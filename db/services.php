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

/**
 * External functions and services for local_aimcp.
 *
 * @package    local_aimcp
 * @copyright  2026 Kenneth
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_aimcp_add_gift_questions' => [
        'classname' => 'local_aimcp\external\add_gift_questions',
        'description' => 'Import questions in Moodle GIFT format into an existing quiz and add them to it.',
        'type' => 'write',
        'capabilities' => 'moodle/question:add',
    ],
    'local_aimcp_create_assign' => [
        'classname' => 'local_aimcp\external\create_assign',
        'description' => 'Create an Assignment with online text and/or file submissions.',
        'type' => 'write',
        'capabilities' => 'moodle/course:manageactivities',
    ],
    'local_aimcp_create_forum' => [
        'classname' => 'local_aimcp\external\create_forum',
        'description' => 'Create a Forum activity.',
        'type' => 'write',
        'capabilities' => 'moodle/course:manageactivities',
    ],
    'local_aimcp_create_label' => [
        'classname' => 'local_aimcp\external\create_label',
        'description' => 'Create a Text and media area (label) shown directly on the course page.',
        'type' => 'write',
        'capabilities' => 'moodle/course:manageactivities',
    ],
    'local_aimcp_create_page' => [
        'classname' => 'local_aimcp\external\create_page',
        'description' => 'Create a Page activity (HTML content) in a course section.',
        'type' => 'write',
        'capabilities' => 'moodle/course:manageactivities',
    ],
    'local_aimcp_create_quiz' => [
        'classname' => 'local_aimcp\external\create_quiz',
        'description' => 'Create a Quiz, optionally with questions written in Moodle GIFT format.',
        'type' => 'write',
        'capabilities' => 'moodle/course:manageactivities',
    ],
    'local_aimcp_create_subsection' => [
        'classname' => 'local_aimcp\external\create_subsection',
        'description' => 'Create a subsection (mod_subsection) inside a normal course section. Returns sectionnum, which can be used as section for the other create functions.',
        'type' => 'write',
        'capabilities' => 'moodle/course:manageactivities',
    ],
    'local_aimcp_create_url' => [
        'classname' => 'local_aimcp\external\create_url',
        'description' => 'Create a URL resource linking to an external web page.',
        'type' => 'write',
        'capabilities' => 'moodle/course:manageactivities',
    ],
    'local_aimcp_delete_subsection' => [
        'classname' => 'local_aimcp\external\delete_subsection',
        'description' => 'Delete a subsection by its cmid. By default its activities are moved to the parent section first.',
        'type' => 'write',
        'capabilities' => 'moodle/course:manageactivities',
    ],
    'local_aimcp_set_completion' => [
        'classname' => 'local_aimcp\external\set_completion',
        'description' => 'Set activity completion (none/manual/auto with view, grade, pass grade, submit, forum posts, quiz attempts) on one or more existing activities, so they count in progress/status bars.',
        'type' => 'write',
        'capabilities' => 'moodle/course:manageactivities',
    ],
    'local_aimcp_update_activity' => [
        'classname' => 'local_aimcp\external\update_activity',
        'description' => 'Change name, description or page content of an existing activity.',
        'type' => 'write',
        'capabilities' => 'moodle/course:manageactivities',
    ],
    'local_aimcp_update_section' => [
        'classname' => 'local_aimcp\external\update_section',
        'description' => 'Set name, summary (HTML) and/or visibility of a course section; creates it if missing.',
        'type' => 'write',
        'capabilities' => 'moodle/course:update',
    ],
    'local_aimcp_add_book_chapter' => [
        'classname' => 'local_aimcp\external\add_book_chapter',
        'description' => 'Add a chapter or subchapter to an existing book (by cmid). Appends at the end unless pagenum is given.',
        'type' => 'write',
        'capabilities' => 'mod/book:edit',
    ],
    'local_aimcp_create_book' => [
        'classname' => 'local_aimcp\external\create_book',
        'description' => 'Create a Book activity in a course section, optionally with an ordered list of chapters/subchapters.',
        'type' => 'write',
        'capabilities' => 'moodle/course:manageactivities',
    ],
    'local_aimcp_delete_book_chapter' => [
        'classname' => 'local_aimcp\external\delete_book_chapter',
        'description' => 'Delete a book chapter by chapterid. Deleting a main chapter also deletes its subchapters. To delete the whole book use core_course_delete_modules.',
        'type' => 'write',
        'capabilities' => 'mod/book:edit',
    ],
    'local_aimcp_get_book_chapters' => [
        'classname' => 'local_aimcp\external\get_book_chapters',
        'description' => 'Read a Book activity by cmid: settings and chapters (id, title, position, subchapter, hidden, HTML content). Use chapterid to get one chapter, includecontent=false for table of contents only.',
        'type' => 'read',
        'capabilities' => 'mod/book:read',
    ],
    'local_aimcp_update_book_chapter' => [
        'classname' => 'local_aimcp\external\update_book_chapter',
        'description' => 'Update a book chapter by chapterid. Only the fields sent are changed (title, content, subchapter, hidden).',
        'type' => 'write',
        'capabilities' => 'mod/book:edit',
    ],
    'local_aimcp_create_course' => [
        'classname' => 'local_aimcp\\external\\create_course',
        'description' => 'Create a new course. Leave categoryid at 0: the course is then placed in the calling user\'s default AI category (internal users: the internal AI category; external users: their own subcategory). Use local_aimcp_get_default_category to see where that is.',
        'type' => 'write',
        'capabilities' => 'moodle/course:create',
    ],
    'local_aimcp_get_default_category' => [
        'classname' => 'local_aimcp\\external\\get_default_category',
        'description' => 'Show which category new courses will be created in for the calling user, and whether the user may create courses there.',
        'type' => 'read',
        'capabilities' => '',
    ],
    'local_aimcp_set_section_image' => [
        'classname' => 'local_aimcp\external\set_section_image',
        'description' => 'Upload or replace the tile image of a section in a Grid-format course, and/or set its alt text. Give exactly one source: svg (SVG markup, recommended for AI; rendered to a 1140 px wide PNG on the server, use viewBox="0 0 1140 600" and no text in the image), imagedata (base64 PNG/JPEG/GIF/WebP) or imageurl (public http/https image). Send only alttext to change the alt text of an existing image.',
        'type' => 'write',
        'capabilities' => 'moodle/course:update',
        'ajax' => true,
    ],
    'local_aimcp_delete_section_image' => [
        'classname' => 'local_aimcp\external\delete_section_image',
        'description' => 'Delete the tile image of a section in a Grid-format course (and by default its alt text).',
        'type' => 'write',
        'capabilities' => 'moodle/course:update',
        'ajax' => true,
    ],
    'local_aimcp_get_section_images' => [
        'classname' => 'local_aimcp\external\get_section_images',
        'description' => 'List the sections of a Grid-format course with tile image status: whether each has an image, file name, alt text and image URL. Use it before and after setting images.',
        'type' => 'read',
        'capabilities' => 'moodle/course:manageactivities',
        'ajax' => true,
    ],
];

// Service names must be unique across the site. Delete any manually created service with the same name
// before installing (Site administration > Server > Web services > External services).
$services = [
    'AI-assistenter – interne (MCP)' => [
        'shortname' => 'aimcp_internal',
        'functions' => [
            'local_aimcp_add_gift_questions',
            'local_aimcp_create_assign',
            'local_aimcp_create_forum',
            'local_aimcp_create_label',
            'local_aimcp_create_page',
            'local_aimcp_create_quiz',
            'local_aimcp_create_subsection',
            'local_aimcp_create_url',
            'local_aimcp_delete_subsection',
            'local_aimcp_set_completion',
            'local_aimcp_update_activity',
            'local_aimcp_update_section',
            'local_aimcp_add_book_chapter',
            'local_aimcp_create_book',
            'local_aimcp_delete_book_chapter',
            'local_aimcp_get_book_chapters',
            'local_aimcp_update_book_chapter',
            'local_aimcp_set_section_image',
            'local_aimcp_delete_section_image',
            'local_aimcp_get_section_images',
            'core_webservice_get_site_info',
            'core_course_get_categories',
            'core_course_get_courses',
            'core_course_get_courses_by_field',
            'core_course_search_courses',
            'core_course_get_contents',
            'core_course_get_course_module',
            'core_course_get_course_module_by_instance',
            'mod_page_get_pages_by_courses',
            'mod_book_get_books_by_courses',
            'mod_quiz_get_quizzes_by_courses',
            'mod_assign_get_assignments',
            'mod_forum_get_forums_by_courses',
            'mod_url_get_urls_by_courses',
            'mod_label_get_labels_by_courses',
            'local_aimcp_create_course',
            'local_aimcp_get_default_category',
            'core_course_update_courses',
            'core_course_duplicate_course',
            'core_course_delete_modules',
            'core_courseformat_update_course',
            'core_course_create_categories',
            'core_course_update_categories',
            'core_course_delete_categories',
            'core_course_delete_courses',
            'enrol_manual_enrol_users',
            'enrol_manual_unenrol_users',
            'core_enrol_get_enrolled_users',
            'core_user_get_users_by_field',
            'core_files_upload',
            'core_files_get_files',
        ],
        'restrictedusers' => 1,
        'enabled' => 1,
        'downloadfiles' => 1,
        'uploadfiles' => 1,
    ],
    'AI-assistenter – eksterne (MCP)' => [
        'shortname' => 'aimcp_external',
        'functions' => [
            'local_aimcp_add_gift_questions',
            'local_aimcp_create_assign',
            'local_aimcp_create_forum',
            'local_aimcp_create_label',
            'local_aimcp_create_page',
            'local_aimcp_create_quiz',
            'local_aimcp_create_subsection',
            'local_aimcp_create_url',
            'local_aimcp_delete_subsection',
            'local_aimcp_set_completion',
            'local_aimcp_update_activity',
            'local_aimcp_update_section',
            'local_aimcp_add_book_chapter',
            'local_aimcp_create_book',
            'local_aimcp_delete_book_chapter',
            'local_aimcp_get_book_chapters',
            'local_aimcp_update_book_chapter',
            'local_aimcp_set_section_image',
            'local_aimcp_delete_section_image',
            'local_aimcp_get_section_images',
            'core_webservice_get_site_info',
            'core_course_get_categories',
            'core_course_get_courses',
            'core_course_get_courses_by_field',
            'core_course_search_courses',
            'core_course_get_contents',
            'core_course_get_course_module',
            'core_course_get_course_module_by_instance',
            'mod_page_get_pages_by_courses',
            'mod_book_get_books_by_courses',
            'mod_quiz_get_quizzes_by_courses',
            'mod_assign_get_assignments',
            'mod_forum_get_forums_by_courses',
            'mod_url_get_urls_by_courses',
            'mod_label_get_labels_by_courses',
            'local_aimcp_create_course',
            'local_aimcp_get_default_category',
            'core_course_update_courses',
            'core_course_duplicate_course',
            'core_course_delete_modules',
        ],
        'restrictedusers' => 1,
        'enabled' => 1,
        'downloadfiles' => 1,
        'uploadfiles' => 0,
    ],
];
