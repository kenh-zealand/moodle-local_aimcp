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
 * Admin settings for local_aimcp.
 *
 * @package    local_aimcp
 * @copyright  2026 Kenneth
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_aimcp', new lang_string('pluginname', 'local_aimcp'));
    $ADMIN->add('localplugins', $settings);

    if ($ADMIN->fulltree) {
        $options = [0 => new lang_string('none')] + core_course_category::make_categories_list();
        $settings->add(new admin_setting_configselect('local_aimcp/internalcategory',
            new lang_string('internalcategory', 'local_aimcp'),
            new lang_string('internalcategory_desc', 'local_aimcp'), 0, $options));
        $settings->add(new admin_setting_configselect('local_aimcp/externalcategory',
            new lang_string('externalcategory', 'local_aimcp'),
            new lang_string('externalcategory_desc', 'local_aimcp'), 0, $options));
    }
}
