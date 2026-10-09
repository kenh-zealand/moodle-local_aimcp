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
 * Language strings for local_aimcp.
 *
 * @package    local_aimcp
 * @copyright  2026 Kenneth
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['externalcategory'] = 'Parent category for external users';
$string['externalcategory_desc'] = 'Users who are not authorised on the internal service get new courses in their own subcategory of this category. The subcategory is matched on category ID number or name = username, then ID number or name = the part of the user\'s e-mail before @. If none matches, this category itself is used.';
$string['giftimportfailed'] = 'GIFT import failed: {$a}';
$string['imagedownloadfailed'] = 'The image could not be downloaded: {$a}';
$string['imageinvalid'] = 'The image cannot be used: {$a}';
$string['imagesource'] = 'Give exactly one image source: svg, imagedata or imageurl. To change only the alt text, send alttext alone; the section must then already have an image.';
$string['imagetoolarge'] = 'The image is too large. The maximum is {$a}.';
$string['internalcategory'] = 'Default category for internal users';
$string['internalcategory_desc'] = 'New courses created through MCP by users authorised on the service "AI-assistenter – interne (MCP)" (and by site admins) go into this category, unless another category is given.';
$string['nodefaultcategory'] = 'No default category is configured for this user. Ask the administrator to set it under Site administration > Plugins > Local plugins > AI MCP content tools, or pass categoryid.';
$string['noimagick'] = 'SVG images need the PHP extension Imagick with SVG support on the server. Send imagedata or imageurl instead.';
$string['notgridformat'] = 'The course uses the format "{$a}". Section tile images need the Grid format (format_grid).';
$string['pluginname'] = 'AI MCP content tools';
$string['privacy:metadata'] = 'The AI MCP content tools plugin does not store any personal data.';
$string['svginvalid'] = 'The SVG cannot be used: {$a}';
