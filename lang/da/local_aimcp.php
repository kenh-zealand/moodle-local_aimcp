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
 * Danish language strings for local_aimcp.
 *
 * @package    local_aimcp
 * @copyright  2026 Kenneth
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['externalcategory'] = 'Overkategori for eksterne brugere';
$string['externalcategory_desc'] = 'Brugere, der ikke er godkendt på den interne service, får nye kurser i deres egen underkategori af denne kategori. Underkategorien findes ud fra kategori-id eller navn = brugernavn og derefter kategori-id eller navn = delen af brugerens e-mail før @. Findes ingen, bruges denne kategori.';
$string['giftimportfailed'] = 'GIFT-import fejlede: {$a}';
$string['imagedownloadfailed'] = 'Billedet kunne ikke hentes: {$a}';
$string['imageinvalid'] = 'Billedet kan ikke bruges: {$a}';
$string['imagesource'] = 'Angiv præcis én billedkilde: svg, imagedata eller imageurl. Vil du kun ændre alt-teksten, så send alttext alene. Sektionen skal da allerede have et billede.';
$string['imagetoolarge'] = 'Billedet er for stort. Grænsen er {$a}.';
$string['internalcategory'] = 'Standardkategori for interne brugere';
$string['internalcategory_desc'] = 'Nye kurser, som oprettes via MCP af brugere godkendt på servicen "AI-assistenter – interne (MCP)" (og af administratorer), lægges i denne kategori, medmindre en anden kategori angives.';
$string['nodefaultcategory'] = 'Der er ingen standardkategori for denne bruger. Bed administratoren om at vælge den under Administration > Plugins > Lokale plugins > AI MCP content tools, eller angiv categoryid.';
$string['noimagick'] = 'SVG-billeder kræver PHP-udvidelsen Imagick med SVG-understøttelse på serveren. Send imagedata eller imageurl i stedet.';
$string['notgridformat'] = 'Kurset bruger formatet "{$a}". Flisebilleder kræver Grid-formatet (format_grid).';
$string['pluginname'] = 'AI MCP content tools';
$string['privacy:metadata'] = 'Pluginet AI MCP content tools gemmer ingen persondata.';
$string['svginvalid'] = 'SVG-filen kan ikke bruges: {$a}';
