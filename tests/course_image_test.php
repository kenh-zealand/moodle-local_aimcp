<?php
// This file is part of Moodle - http://moodle.org/
// Moodle is free software, distributed under the GNU GPL v3 or later.

namespace local_aimcp;

use local_aimcp\external\set_course_image;

/**
 * Tests for local_aimcp_set_course_image.
 *
 * @package local_aimcp
 * @copyright 2026 Kenneth
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_aimcp\external\set_course_image
 */
final class course_image_test extends \advanced_testcase {
    /** @var \stdClass Test course. */
    private \stdClass $course;

    /** Create a course and log in as admin. */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->course = $this->getDataGenerator()->create_course();
    }

    /**
     * Base64 of a PNG of the given size.
     *
     * @param int $width
     * @param int $height
     * @return string
     */
    private static function png(int $width, int $height): string {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 20, 80, 47));
        ob_start();
        imagepng($image);
        return base64_encode(ob_get_clean());
    }

    /**
     * Files in the course image area.
     *
     * @return \stored_file[]
     */
    private function files(): array {
        $context = \core\context\course::instance($this->course->id);
        return get_file_storage()->get_area_files($context->id, 'course', 'overviewfiles', false, 'filename', false);
    }

    /** Base64 data becomes the course image and is described correctly. */
    public function test_set_from_imagedata(): void {
        $result = set_course_image::execute($this->course->id, '', self::png(1200, 675), '', 'linux-forside');
        $result = \core_external\external_api::clean_returnvalue(set_course_image::execute_returns(), $result);
        self::assertTrue($result['hasimage']);
        self::assertSame('imagedata', $result['source']);
        self::assertSame(1200, $result['width']);
        self::assertSame(675, $result['height']);
        self::assertStringStartsWith('linux-forside-', $result['filename']);
        self::assertStringContainsString('/course/overviewfiles/', $result['imageurl']);
        self::assertEmpty($result['warnings']);
        self::assertCount(1, $this->files());
    }

    /** A new image replaces the old one, and a small image gives a warning. */
    public function test_replace_and_small_warning(): void {
        set_course_image::execute($this->course->id, '', self::png(1200, 675));
        $result = set_course_image::execute($this->course->id, '', self::png(300, 200));
        self::assertCount(1, $this->files());
        self::assertSame(300, $result['width']);
        self::assertSame('smallimage', $result['warnings'][0]['warningcode']);
    }

    /** The image can be removed again. */
    public function test_remove(): void {
        set_course_image::execute($this->course->id, '', self::png(1200, 675));
        $result = set_course_image::execute($this->course->id, '', '', '', '', 1200, true);
        self::assertFalse($result['hasimage']);
        self::assertSame('remove', $result['source']);
        self::assertCount(0, $this->files());
    }

    /** Exactly one source is required. */
    public function test_needs_one_source(): void {
        $this->expectException(\moodle_exception::class);
        set_course_image::execute($this->course->id, 'https://example.com/a.png', self::png(10, 10));
    }

    /** Data that is not an image is rejected and nothing is stored. */
    public function test_rejects_non_image(): void {
        try {
            set_course_image::execute($this->course->id, '', base64_encode('not an image'));
            $this->fail('Expected an exception');
        } catch (\moodle_exception $e) {
            self::assertSame('imageinvalid', $e->errorcode);
        }
        self::assertCount(0, $this->files());
    }

    /** A student cannot change the course image. */
    public function test_student_cannot_set(): void {
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $this->course->id, 'student');
        $this->setUser($student);
        $this->expectException(\required_capability_exception::class);
        set_course_image::execute($this->course->id, '', self::png(1200, 675));
    }
}
