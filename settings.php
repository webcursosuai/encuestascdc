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
 * @copyright 2017-onwards Universidad Adolfo Ibáñez
 * @author Jorge Villalón <jorge.villalon@uai.cl>
 */
defined('MOODLE_INTERNAL') || die();
global $PAGE, $CFG;
require_once ($CFG->libdir . '/coursecatlib.php');

if (! during_initial_install()) { // do not use during installation
    $frontpagecontext = context_course::instance(SITEID);
    
    if ($hassiteconfig || has_capability('moodle/course:update', $frontpagecontext)) { // needs this condition or there is error on login page
        if ($hassiteconfig) {
            $settings = new admin_settingpage('local_encuestascdc', 'UAI Corporate');
            $ADMIN->add('localplugins', $settings);
            $settings->add(new admin_setting_configcheckbox('encuestascdc_enablepasswords', 'Habilitar contraseñas de encuestas', 'Al habilitar contraseñas de encuestas, los gestores y administradores pueden crear contraseñas para que los usuarios ingresen ignorando su clave de Moodle ', '', PARAM_URL));
        }
        // Agregando menu de administración UAI Online
        $ADMIN->add('root', new admin_category('cdc', 'UAI Corporate'));
        $ADMIN->add('cdc', new admin_externalpage('encuestascdc_passwords', 'Contraseñas de encuestas', new moodle_url('/local/encuestascdc/passwords.php'), 'moodle/course:update', false, $frontpagecontext));
        $ADMIN->add('cdc', new admin_externalpage('encuestascdc_qrlogin', 'QR para ingreso', new moodle_url('/local/encuestascdc/qrcode.php'), 'moodle/course:update', false, $frontpagecontext));
    }
}