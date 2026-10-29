<?php
// This file is part of Moodle - http://moodle.org/
// Moodle is free software, distributed under the GNU GPL v3 or later.

namespace local_aimcp;

use local_aimcp\external\create_complete_course;
use local_aimcp\external\validate_course_blueprint;
use local_aimcp\local\course_blueprint;

/**
 * Moodle integration tests for blueprint validation and safe course creation.
 *
 * Run inside Moodle: vendor/bin/phpunit --testsuite local_aimcp_testsuite
 *
 * @package local_aimcp
 * @copyright 2026 Kenneth
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class course_blueprint_test extends \advanced_testcase {
    /** Isolated category used by default AI course placement. */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $category = $this->getDataGenerator()->create_category();
        set_config('internalcategory', $category->id, 'local_aimcp');
    }

    /** Build a small blueprint. */
    private function example(bool $visible = false): string {
        return json_encode([
            'schema_version' => '1.0',
            'course' => [
                'fullname' => 'Blueprint course test',
                'shortname' => 'aimcp-blueprint-test',
                'format' => 'topics',
                'visible' => $visible,
                'enablecompletion' => true,
            ],
            'sections' => [
                ['name' => 'Introduction', 'summary' => '<p>Welcome</p>', 'activities' => [
                    ['id' => 'welcome', 'type' => 'page', 'name' => 'Welcome', 'content' => '<p>Hello</p>',
                        'completion' => ['tracking' => 'manual']],
                    ['id' => 'reading', 'type' => 'book', 'name' => 'Reading', 'chapters' => [
                        ['title' => 'Chapter one', 'content' => '<p>Text</p>'],
                    ]],
                ]],
            ],
        ], JSON_THROW_ON_ERROR);
    }

    /** Structural analysis does not need to touch the database. */
    public function test_parser_accepts_known_types_and_rejects_unknown_fields(): void {
        $valid = course_blueprint::analyse($this->example());
        self::assertTrue($valid['valid']);
        self::assertSame(1, $valid['counts']['sections']);
        self::assertSame(2, $valid['counts']['activities']);
        $invalid = str_replace('"type":"page"', '"type":"unsupported"', $this->example());
        self::assertFalse(course_blueprint::analyse($invalid)['valid']);
    }

    /** The validation endpoint reads the exact payload and returns its digest. */
    public function test_validate_does_not_create_a_course(): void {
        global $DB;
        $before = $DB->count_records('course');
        $result = validate_course_blueprint::execute($this->example());
        $result = \core_external\external_api::clean_returnvalue(
            validate_course_blueprint::execute_returns(), $result);
        self::assertTrue($result['valid']);
        self::assertSame(2, $result['activities']);
        self::assertSame($before, $DB->count_records('course'));
    }

    /** A complete build may be published only after the final step. */
    public function test_create_course_with_page_and_book(): void {
        global $DB;
        $blueprint = $this->example(true);
        $hash = hash('sha256', $blueprint);
        $result = create_complete_course::execute($blueprint, $hash, true);
        $result = \core_external\external_api::clean_returnvalue(
            create_complete_course::execute_returns(), $result);
        self::assertSame('created', $result['status']);
        self::assertSame(1, $result['createdsections']);
        self::assertSame(2, $result['createdactivities']);
        self::assertTrue($result['published']);
        self::assertCount(2, $result['items']);
        self::assertEquals(1, $DB->get_field('course', 'visible', ['id' => $result['courseid']]));
    }

    /** A duplicate request is refused instead of making another course. */
    public function test_duplicate_shortname_is_rejected(): void {
        $first = create_complete_course::execute($this->example());
        self::assertSame('created', $first['status']);
        $again = create_complete_course::execute($this->example());
        self::assertSame('rejected', $again['status']);
        self::assertSame(0, $again['courseid']);
    }

    /** Hash mismatch is a hard preflight failure; nothing may be written. */
    public function test_different_validated_hash_is_rejected(): void {
        global $DB;
        $before = $DB->count_records('course');
        $result = create_complete_course::execute($this->example(), str_repeat('0', 64));
        self::assertSame('rejected', $result['status']);
        self::assertSame(0, $result['courseid']);
        self::assertSame($before, $DB->count_records('course'));
    }

    /** A runtime failure is never silently rolled back or published. */
    public function test_runtime_failure_retains_hidden_partial_course(): void {
        global $DB;
        $data = json_decode($this->example(true), true);
        $data['sections'][0]['activities'][] = [
            'type' => 'h5p', 'name' => 'Missing package', 'draftitemid' => 999999999,
        ];
        $result = create_complete_course::execute(json_encode($data));
        self::assertSame('partial', $result['status']);
        self::assertGreaterThan(0, $result['courseid']);
        self::assertSame(2, $result['createdactivities']);
        self::assertEquals(0, $DB->get_field('course', 'visible', ['id' => $result['courseid']]));
    }

    /** Students may not use the bulk builder to bypass course-creation capabilities. */
    public function test_user_without_course_create_is_rejected(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $result = validate_course_blueprint::execute($this->example());
        self::assertFalse($result['valid']);
    }
}
