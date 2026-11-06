<?php
// Standalone parser smoke tests. Run: php tests/blueprint_standalone.php
// No Moodle installation required; Moodle integration tests are in course_blueprint_test.php.
require_once __DIR__ . '/../classes/local/course_blueprint.php';

use local_aimcp\local\course_blueprint;

$base = [
    'schema_version' => '1.0',
    'course' => ['fullname' => 'Test', 'shortname' => 'test1', 'format' => 'topics'],
    'sections' => [
        ['name' => 'Intro', 'activities' => [
            ['type' => 'page', 'name' => 'Lesson', 'content' => '<p>Text</p>'],
            ['type' => 'quiz', 'name' => 'Quiz', 'gift' => '::Q::One? {=Yes ~No}'],
        ]],
    ],
];
$cases = [
    ['Valid page and quiz', $base, true],
    ['Duplicate IDs', array_replace_recursive($base, [
        'sections' => [['id' => 'a', 'activities' => [
            ['id' => 'a', 'type' => 'page', 'name' => 'Lesson', 'content' => 'Text'],
        ]]],
    ]), false],
    ['Unknown type', array_replace_recursive($base, [
        'sections' => [['activities' => [['type' => 'bogus', 'name' => 'Lesson', 'content' => 'Text']]]],
    ]), false],
    ['Bad activity grade type', array_replace_recursive($base, [
        'sections' => [['activities' => [['type' => 'quiz', 'name' => 'Quiz', 'grade' => 'high']]]],
    ]), false],
    ['Wrong course format for Grid image', array_replace_recursive($base, [
        'sections' => [['image' => ['svg' => '<svg/>', 'alttext' => 'Blue illustration']]],
    ]), false],
    ['Invalid label title', array_replace_recursive($base, [
        'sections' => [['activities' => [['type' => 'label', 'content' => 'Hello', 'name' => 42]]]],
    ]), false],
    ['Oversized H5P grade', array_replace_recursive($base, [
        'sections' => [['activities' => [['type' => 'h5p', 'name' => 'H5P', 'draftitemid' => 1, 'grade' => 1001]]]],
    ]), false],
    ['Invalid book navstyle', array_replace_recursive($base, [
        'sections' => [['activities' => [['type' => 'book', 'name' => 'Book', 'navstyle' => 7]]]],
    ]), false],
    ['Unsupported schema version', array_replace_recursive($base, ['schema_version' => '2.0']), false],
];
foreach ($cases as [$name, $input, $expected]) {
    $result = course_blueprint::analyse(json_encode($input));
    if ($result['valid'] !== $expected) {
        fwrite(STDERR, "FAIL $name: " . implode('; ', $result['errors']) . "\n");
        exit(1);
    }
    echo "PASS $name\n";
}
