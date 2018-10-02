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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * Configuración de administración de UAI Online.
 *
 * @package local
 * @subpackage encuestascdc
 * @author Jorge Villalón <jorge.villalon@uai.cl>
 * @copyright 2018 Universidad Adolfo Ibáñez
 */
defined('MOODLE_INTERNAL') || die();
global $PAGE, $CFG;
require_once ($CFG->libdir . '/coursecatlib.php');

if (! during_initial_install()) { // do not use during installation
    $frontpagecontext = context_course::instance(SITEID);
    
    if ($hassiteconfig || has_capability('moodle/course:update', $frontpagecontext)) { // needs this condition or there is error on login page
        // Agregando menu de administración UAI CDC
        $ADMIN->add('root', new admin_category('cdc', 'UAI Corporate'));
        $ADMIN->add('cdc', new admin_externalpage('surveysreport', 'Reporte de encuestas', new moodle_url('/local/encuestascdc/index.php'), 'moodle/course:update', false, $frontpagecontext));
    }
}