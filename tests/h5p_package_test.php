<?php
// This file is part of Moodle - http://moodle.org/
// Moodle is free software, distributed under the GNU GPL v3 or later.

namespace local_aimcp;

use local_aimcp\local\h5p_package;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Real ZIP tests for library removal, content preservation and unsafe input.
 * @package local_aimcp
 * @copyright 2026 Kenneth
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class h5p_package_test extends TestCase {
    /** @var array Temporary files owned by this test. */
    private array $paths = [];

    /** Allocate a unique test file. */
    private function temp(): string {
        $path = tempnam(sys_get_temp_dir(), 'aimcp-h5p-');
        $this->paths[] = $path;
        return $path;
    }

    /** Make a real package, optionally overriding or adding entries. */
    private function package(array $entries = []): string {
        $path = $this->temp();
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $defaults = ['h5p.json' => '{"mainLibrary":"H5P.Test","title":"Test"}',
            'content/content.json' => '{"text":"Dansk æøå","image":{"path":"images/a.png"}}',
            'content/images/a.png' => "\x89PNG\r\n\x1a\n",
            'H5P.Test-1.0/library.json' => '{"machineName":"H5P.Test"}',
            'H5P.Test-1.0/script.js' => 'alert("not installed")'];
        foreach (array_merge($defaults, $entries) as $name => $data) {
            if ($data !== null) {
                $zip->addFromString($name, $data);
            }
        }
        $zip->close();
        return $path;
    }

    /** Delete only files allocated by this test. */
    protected function tearDown(): void {
        foreach ($this->paths as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }
        parent::tearDown();
    }

    /** Libraries are stripped but JSON and media are byte-for-byte preserved. */
    public function test_normalisation_preserves_content_and_removes_libraries(): void {
        $source = $this->package();
        $destination = $this->temp();
        $originalhash = hash_file('sha256', $source);
        $info = h5p_package::inspect($source, $destination);
        self::assertSame(2, $info['removed']);
        self::assertSame('H5P.Test', $info['manifest']['mainLibrary']);
        self::assertSame($originalhash, hash_file('sha256', $source));
        $normalised = new \ZipArchive();
        $normalised->open($destination);
        self::assertSame(3, $normalised->numFiles);
        self::assertSame($info['content'], $normalised->getFromName('content/content.json'));
        self::assertSame("\x89PNG\r\n\x1a\n", $normalised->getFromName('content/images/a.png'));
        self::assertFalse($normalised->getFromName('H5P.Test-1.0/script.js'));
        $normalised->close();
    }

    /** Malformed archives never produce a usable normalised package. */
    #[DataProvider('invalid_entries')]
    public function test_rejects_invalid_entries(array $entries): void {
        $source = $this->package($entries);
        $this->expectException(\invalid_parameter_exception::class);
        h5p_package::inspect($source);
    }

    /** Unsafe names and malformed H5P objects. */
    public static function invalid_entries(): array {
        return [
            'traversal' => [['content/../../outside.txt' => 'bad']],
            'absolute' => [['/outside.txt' => 'bad']],
            'windows path' => [['content\\outside.txt' => 'bad']],
            'drive path' => [['C:/outside.txt' => 'bad']],
            'missing manifest' => [['h5p.json' => null]],
            'missing content' => [['content/content.json' => null]],
            'invalid json' => [['content/content.json' => '{']],
            'content array' => [['content/content.json' => '[]']],
            'invalid mainlibrary' => [['h5p.json' => '{"mainLibrary":123}']],
        ];
    }

    /** Non-archives are rejected. */
    public function test_rejects_non_zip(): void {
        $source = $this->temp();
        file_put_contents($source, 'not a ZIP');
        $this->expectException(\invalid_parameter_exception::class);
        h5p_package::inspect($source);
    }

    /** Entry count is bounded even for tiny entries. */
    public function test_rejects_excessive_entry_count(): void {
        $entries = [];
        for ($i = 0; $i < h5p_package::MAX_ENTRIES; $i++) {
            $entries['content/data/' . $i . '.txt'] = '';
        }
        $source = $this->package($entries);
        $this->expectException(\invalid_parameter_exception::class);
        h5p_package::inspect($source);
    }

    /** A highly compressed oversized payload is rejected before reading its bytes. */
    public function test_rejects_expansion_bomb(): void {
        $large = $this->temp();
        $handle = fopen($large, 'wb');
        ftruncate($handle, h5p_package::MAX_EXPANDED_BYTES + 1);
        fclose($handle);
        $source = $this->package();
        $zip = new \ZipArchive();
        $zip->open($source);
        $zip->addFile($large, 'content/large.txt');
        $zip->close();
        self::assertLessThan(h5p_package::MAX_BYTES, filesize($source));
        $this->expectException(\invalid_parameter_exception::class);
        h5p_package::inspect($source);
    }
}
