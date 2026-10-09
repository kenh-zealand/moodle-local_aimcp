<?php
// This file is part of Moodle - http://moodle.org/
// Moodle is free software, distributed under the GNU GPL v3 or later.

namespace local_aimcp;

use local_aimcp\external\create_h5p;
use local_aimcp\external\get_h5p;
use local_aimcp\external\get_h5p_libraries;
use local_aimcp\external\update_h5p;

/**
 * Native Moodle integration tests; run in a Moodle PHPUnit installation.
 * @package local_aimcp
 * @copyright 2026 Kenneth
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class h5p_external_test extends \advanced_testcase {
    /** @var \stdClass Test course. */
    private \stdClass $course;
    /** @var string Base64 of Moodle's official H5P fixture. */
    private string $package;

    /** Install fixture libraries into the isolated PHPUnit database. */
    protected function setUp(): void {
        global $CFG, $USER;
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->course = $this->getDataGenerator()->create_course();
        $fixture = $CFG->dirroot . '/h5p/tests/fixtures/ipsums.h5p';
        $this->package = base64_encode(file_get_contents($fixture));
        $file = get_file_storage()->create_file_from_pathname([
            'contextid' => \core\context\user::instance($USER->id)->id, 'component' => 'user', 'filearea' => 'draft',
            'itemid' => file_get_unused_draft_itemid(), 'filepath' => '/', 'filename' => 'fixture.h5p', 'userid' => $USER->id,
        ], $fixture);
        $factory = new \core_h5p\factory();
        self::assertNotFalse(\core_h5p\helper::save_h5p($factory, $file, (object) [], false));
    }

    /** Creation is native, readable and uses a number for section placement. */
    public function test_create_and_read(): void {
        $created = create_h5p::execute($this->course->id, 2, 'H5P test', $this->package);
        $result = get_h5p::execute($created['cmid']);
        $result = \core_external\external_api::clean_returnvalue(get_h5p::execute_returns(), $result);
        self::assertSame(2, $result['section']);
        self::assertSame('H5P test', $result['name']);
        self::assertSame(0.0, $result['grade']);
        self::assertFalse($result['enabletracking']);
        self::assertNotEmpty($result['content']);
        self::assertStringContainsString('/mod/h5pactivity/', $result['url']);
        self::assertNotEmpty(get_h5p_libraries::execute($this->course->id)['libraries']);
    }

    /** A student cannot create an activity even with a valid package. */
    public function test_student_cannot_create(): void {
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $this->course->id, 'student');
        $this->setUser($student);
        $this->expectException(\required_capability_exception::class);
        create_h5p::execute($this->course->id, 0, 'Denied', $this->package);
    }

    /** Normal editing teachers can author with installed libraries without admin rights. */
    public function test_editing_teacher_can_create(): void {
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $this->course->id, 'editingteacher');
        $this->setUser($teacher);
        $created = create_h5p::execute($this->course->id, 0, 'Teacher activity', $this->package);
        self::assertSame('Teacher activity', get_h5p::execute($created['cmid'])['name']);
    }

    /** Consuming one's own draft preserves its original bytes for reuse. */
    public function test_own_draft_is_preserved(): void {
        global $CFG, $USER;
        $draftid = file_get_unused_draft_itemid();
        $file = get_file_storage()->create_file_from_pathname([
            'contextid' => \core\context\user::instance($USER->id)->id, 'component' => 'user', 'filearea' => 'draft',
            'itemid' => $draftid, 'filepath' => '/', 'filename' => 'original.h5p', 'userid' => $USER->id,
        ], $CFG->dirroot . '/h5p/tests/fixtures/ipsums.h5p');
        $hash = $file->get_contenthash();
        $created = create_h5p::execute($this->course->id, 0, 'Draft activity', '', $draftid);
        self::assertGreaterThan(0, $created['cmid']);
        $original = get_file_storage()->get_file($file->get_contextid(), 'user', 'draft', $draftid, '/', 'original.h5p');
        self::assertNotFalse($original);
        self::assertSame($hash, $original->get_contenthash());
    }

    /** Editing rights without deployment rights are insufficient. */
    public function test_teacher_without_deploy_cannot_create(): void {
        global $DB;
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $this->course->id, 'editingteacher');
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        assign_capability('moodle/h5p:deploy', CAP_PROHIBIT, $roleid,
            \core\context\course::instance($this->course->id)->id);
        $this->setUser($teacher);
        $this->expectException(\required_capability_exception::class);
        create_h5p::execute($this->course->id, 0, 'Denied', $this->package);
    }

    /** Invalid source must leave course modules untouched. */
    public function test_invalid_package_does_not_create_activity(): void {
        global $DB;
        $count = $DB->count_records('course_modules', ['course' => $this->course->id]);
        try {
            create_h5p::execute($this->course->id, 0, 'Invalid', base64_encode('invalid archive'));
            self::fail('Invalid package accepted');
        } catch (\invalid_parameter_exception $e) {
            self::assertSame($count, $DB->count_records('course_modules', ['course' => $this->course->id]));
        }
    }

    /** A disabled required library stays disabled and creation fails, even for admin. */
    public function test_disabled_library_is_not_reenabled(): void {
        global $DB;
        $DB->set_field('h5p_libraries', 'enabled', 0);
        $this->expectException(\invalid_parameter_exception::class);
        create_h5p::execute($this->course->id, 0, 'Disabled', $this->package);
    }

    /** Cross-user draft IDs never expose or consume another user's file. */
    public function test_cannot_use_other_users_draft(): void {
        global $CFG;
        $other = $this->getDataGenerator()->create_user();
        $draftid = file_get_unused_draft_itemid();
        get_file_storage()->create_file_from_pathname([
            'contextid' => \core\context\user::instance($other->id)->id, 'component' => 'user', 'filearea' => 'draft',
            'itemid' => $draftid, 'filepath' => '/', 'filename' => 'other.h5p', 'userid' => $other->id,
        ], $CFG->dirroot . '/h5p/tests/fixtures/ipsums.h5p');
        $this->expectException(\invalid_parameter_exception::class);
        create_h5p::execute($this->course->id, 0, 'Other draft', '', $draftid);
    }

    /** Invalid replacements leave the original stored bytes and title intact. */
    public function test_invalid_update_preserves_original(): void {
        $created = create_h5p::execute($this->course->id, 1, 'Original', $this->package);
        $original = get_h5p::execute($created['cmid']);
        try {
            update_h5p::execute($created['cmid'], base64_encode('broken'), 0, 'Must not change');
            self::fail('Invalid replacement accepted');
        } catch (\invalid_parameter_exception $e) {
            $after = get_h5p::execute($created['cmid']);
            self::assertSame($original['contenthash'], $after['contenthash']);
            self::assertSame('Original', $after['name']);
        }
    }

    /** Replacement keeps attempts and grading settings; only requested metadata changes. */
    public function test_update_preserves_attempts_and_settings(): void {
        global $DB;
        $created = create_h5p::execute($this->course->id, 1, 'Original', $this->package, 0, 'Original intro', true, true, 80, 3);
        $activity = $DB->get_record('h5pactivity', ['id' => $created['instanceid']], '*', MUST_EXIST);
        $activity->cmid = $created['cmid'];
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_h5pactivity');
        $generator->create_content($activity, ['cmid' => $created['cmid']]);
        $attempts = $DB->get_records('h5pactivity_attempts', ['h5pactivityid' => $activity->id]);
        update_h5p::execute($created['cmid'], $this->package, 0, 'Updated');
        $after = get_h5p::execute($created['cmid']);
        self::assertSame('Updated', $after['name']);
        self::assertSame('Original intro', $after['intro']);
        self::assertSame(80.0, $after['grade']);
        self::assertSame(3, $after['grademethod']);
        self::assertTrue($after['enabletracking']);
        self::assertEquals($attempts, $DB->get_records('h5pactivity_attempts', ['h5pactivityid' => $activity->id]));
    }
}
