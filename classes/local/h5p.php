<?php
// This file is part of Moodle - http://moodle.org/
// Moodle is free software, distributed under the GNU GPL v3 or later.

namespace local_aimcp\local;

/**
 * Native H5P package preparation shared by the external tools.
 *
 * @package local_aimcp
 * @copyright 2026 Kenneth
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class h5p {
    /**
     * Make a private, validated draft from base64 or the current user's draft.
     * The caller must already have validated the destination and capabilities.
     *
     * @param string $packagedata Strict base64, or empty
     * @param int $draftitemid Current user's draft id, or zero
     * @param \stdClass $course Destination course
     * @return array Draft id and warnings
     */
    public static function prepare(string $packagedata, int $draftitemid, \stdClass $course): array {
        global $CFG, $USER;
        require_once($CFG->libdir . '/filelib.php');
        if (($packagedata === '') === ($draftitemid === 0) || $draftitemid < 0) {
            throw new \invalid_parameter_exception('Give exactly one of packagedata or draftitemid.');
        }
        $maxbytes = min(h5p_package::MAX_BYTES, get_max_upload_file_size($CFG->maxbytes, $course->maxbytes));
        $usercontext = \core\context\user::instance($USER->id);
        $fs = get_file_storage();
        $tempdir = make_request_directory();
        $source = $tempdir . '/source.h5p';
        if ($packagedata !== '') {
            if (strlen($packagedata) > (int) ceil($maxbytes / 3) * 4) {
                throw new \invalid_parameter_exception('H5P package exceeds the upload limit.');
            }
            $data = base64_decode($packagedata, true);
            if ($data === false || strlen($data) > $maxbytes) {
                throw new \invalid_parameter_exception('Invalid base64 or H5P package exceeds the upload limit.');
            }
            file_put_contents($source, $data);
            unset($data);
        } else {
            $files = $fs->get_area_files($usercontext->id, 'user', 'draft', $draftitemid, 'id', false);
            if (count($files) !== 1) {
                throw new \invalid_parameter_exception('The draft must contain exactly one H5P file owned by the caller.');
            }
            $file = reset($files);
            if ($file->is_external_file() || $file->get_filepath() !== '/'
                    || strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION)) !== 'h5p'
                    || $file->get_filesize() > $maxbytes) {
                throw new \invalid_parameter_exception('The draft must contain one local .h5p file within the upload limit.');
            }
            $file->copy_content_to($source);
        }
        $normalised = $tempdir . '/package.h5p';
        $info = h5p_package::inspect($source, $normalised);
        self::require_installed_libraries($info['manifest']);
        $draftid = file_get_unused_draft_itemid();
        $file = $fs->create_file_from_pathname([
            'contextid' => $usercontext->id, 'component' => 'user', 'filearea' => 'draft',
            'itemid' => $draftid, 'filepath' => '/', 'filename' => 'activity.h5p', 'userid' => $USER->id,
        ], $normalised);
        try {
            $factory = new \core_h5p\factory();
            if (!\core_h5p\api::is_valid_package($file, true, false, $factory)) {
                $messages = $factory->get_framework()->getMessages('error') ?: [];
                $details = array_map(static fn($message) => $message->message, $messages);
                throw new \invalid_parameter_exception('H5P validation failed: ' . implode(' ', $details));
            }
        } catch (\Throwable $e) {
            self::delete_draft($draftid);
            throw $e;
        }
        $warnings = [];
        if ($info['removed']) {
            $warnings[] = ['item' => 'package', 'warningcode' => 'librariesremoved',
                'message' => 'Bundled libraries were removed. Only installed Moodle libraries are used.'];
        }
        return [$draftid, $warnings];
    }

    /** Remove only the temporary draft created by this tool. */
    public static function delete_draft(int $draftid): void {
        global $USER;
        get_file_storage()->delete_area_files(\core\context\user::instance($USER->id)->id, 'user', 'draft', $draftid);
    }

    /** Check supported grading options; scales are deliberately not accepted. */
    public static function validate_settings(float $grade, int $grademethod): void {
        if (!is_finite($grade) || $grade < 0 || $grade > 1000 || $grademethod < 0 || $grademethod > 4) {
            throw new \invalid_parameter_exception('grade must be 0..1000 and grademethod must be 0..4.');
        }
    }

    /** Require exact installed, enabled major/minor versions, including the main library. */
    public static function require_installed_libraries(array $manifest): void {
        global $DB;
        $mainfound = false;
        foreach (['preloadedDependencies', 'dynamicDependencies', 'editorDependencies'] as $key) {
            $dependencies = $manifest[$key] ?? [];
            if (!is_array($dependencies)) {
                throw new \invalid_parameter_exception('Invalid H5P dependency list.');
            }
            foreach ($dependencies as $dependency) {
                if (!is_array($dependency) || !is_string($dependency['machineName'] ?? null)
                        || !self::is_version($dependency['majorVersion'] ?? null)
                        || !self::is_version($dependency['minorVersion'] ?? null)) {
                    throw new \invalid_parameter_exception('Invalid H5P dependency version.');
                }
                $library = $DB->get_record('h5p_libraries', ['machinename' => $dependency['machineName'],
                    'majorversion' => (int) $dependency['majorVersion'], 'minorversion' => (int) $dependency['minorVersion']]);
                if (!$library || !$library->enabled) {
                    throw new \invalid_parameter_exception('Install/enable the H5P library separately: ' .
                        $dependency['machineName'] . ' ' . $dependency['majorVersion'] . '.' . $dependency['minorVersion']);
                }
                if ($key === 'preloadedDependencies' && $dependency['machineName'] === $manifest['mainLibrary']
                        && $library->runnable) {
                    $mainfound = true;
                }
            }
        }
        if (!$mainfound) {
            throw new \invalid_parameter_exception('The main H5P library must be an installed, runnable preloaded dependency.');
        }
    }

    /** H5P exports use both integers and strings for version numbers. */
    private static function is_version($value): bool {
        return (is_int($value) || is_string($value)) && ctype_digit((string) $value)
            && strlen((string) $value) <= 9;
    }
}
