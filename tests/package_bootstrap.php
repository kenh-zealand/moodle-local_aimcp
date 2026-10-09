<?php
// This file is part of Moodle - http://moodle.org/
// Moodle is free software, distributed under the GNU GPL v3 or later.

/**
 * Minimal bootstrap for package-only tests outside a Moodle installation.
 * @package local_aimcp
 * @copyright 2026 Kenneth
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
if (PHP_SAPI !== 'cli') {
    die();
}
if (!class_exists('invalid_parameter_exception')) {
    class invalid_parameter_exception extends \InvalidArgumentException {
    }
}
require_once(__DIR__ . '/../classes/local/h5p_package.php');
