<?php
// This file is part of Moodle - http://moodle.org/
// Moodle is free software, distributed under the GNU GPL v3 or later.

namespace local_aimcp\local;

/**
 * Inspect packages without extracting paths supplied by the client.
 *
 * @package local_aimcp
 * @copyright 2026 Kenneth
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class h5p_package {
    /** @var int Maximum compressed package size (20 MiB). */
    public const MAX_BYTES = 20971520;
    /** @var int Maximum uncompressed size (100 MiB). */
    public const MAX_EXPANDED_BYTES = 104857600;
    /** @var int Maximum archive entries. */
    public const MAX_ENTRIES = 2000;

    /**
     * Read manifest/content and optionally copy only content into a new package.
     * Bundled executable libraries never reach Moodle's library installer.
     *
     * @param string $source Local archive pathname
     * @param string|null $destination Normalised archive pathname
     * @return array Manifest, content JSON and number of removed library files
     */
    public static function inspect(string $source, ?string $destination = null): array {
        if (filesize($source) > self::MAX_BYTES) {
            throw new \invalid_parameter_exception('H5P package exceeds 20 MiB.');
        }
        $zip = new \ZipArchive();
        if ($zip->open($source) !== true) {
            throw new \invalid_parameter_exception('The H5P package is not a readable ZIP archive.');
        }
        $output = null;
        try {
            if ($zip->numFiles > self::MAX_ENTRIES) {
                throw new \invalid_parameter_exception('Too many files in the H5P package.');
            }
            $names = [];
            $expanded = 0;
            $removed = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->statIndex($i);
                $name = $entry['name'];
                if ($name === '' || strpos($name, '\\') !== false || $name[0] === '/'
                        || preg_match('~(^|/)\.\.?(/|$)|[\x00-\x1f:]~', $name) || isset($names[$name])) {
                    throw new \invalid_parameter_exception('Unsafe or duplicate H5P archive path.');
                }
                $names[$name] = true;
                $expanded += $entry['size'];
                if ($expanded > self::MAX_EXPANDED_BYTES) {
                    throw new \invalid_parameter_exception('H5P package exceeds 100 MiB when expanded.');
                }
                if ($name !== 'h5p.json' && !str_starts_with($name, 'content/') && !str_ends_with($name, '/')) {
                    $removed++;
                }
            }
            $manifestjson = $zip->getFromName('h5p.json');
            $contentjson = $zip->getFromName('content/content.json');
            if ($manifestjson === false || $contentjson === false) {
                throw new \invalid_parameter_exception('h5p.json and content/content.json are required.');
            }
            try {
                $manifest = json_decode($manifestjson, true, 512, JSON_THROW_ON_ERROR);
                $content = json_decode($contentjson, false, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                throw new \invalid_parameter_exception('Invalid JSON in H5P package.');
            }
            if (!is_array($manifest) || !is_string($manifest['mainLibrary'] ?? null) || !is_object($content)) {
                throw new \invalid_parameter_exception('Invalid H5P manifest or content object.');
            }
            if ($destination !== null) {
                $output = new \ZipArchive();
                if ($output->open($destination, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                    throw new \invalid_parameter_exception('Cannot prepare H5P archive.');
                }
                foreach ($names as $name => $unused) {
                    if (($name === 'h5p.json' || str_starts_with($name, 'content/')) && !str_ends_with($name, '/')) {
                        $data = $zip->getFromName($name);
                        if ($data === false || !$output->addFromString($name, $data)) {
                            throw new \invalid_parameter_exception('Cannot read H5P archive entry.');
                        }
                    }
                }
            }
            return ['manifest' => $manifest, 'content' => $contentjson, 'removed' => $removed];
        } finally {
            $zip->close();
            if ($output !== null && !$output->close()) {
                throw new \invalid_parameter_exception('Cannot finish H5P archive.');
            }
        }
    }
}
