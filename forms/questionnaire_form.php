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
 * Configuración de administración de UAI Corporate.
 *
 * @package local
 * @subpackage encuestascdc
 * @author Jorge Villalón <jorge.villalon@uai.cl>
 * @copyright 2018 Universidad Adolfo Ibáñez
 */

require_once ($CFG->libdir . '/formslib.php');
require_once ($CFG->libdir . '/coursecatlib.php');

class local_encuestascdc_questionnaire_form extends moodleform {

	public function definition() {
	    global $DB, $CFG;

		$mform = $this->_form;
		$instance = $this->_customdata;
		$courseid = $instance["course"];
		$moduleid = $instance["module"];
		
		if($courseid == 0) {
			$courses = $DB->get_records_sql_menu('SELECT c.id, c.fullname FROM {course} c INNER JOIN {questionnaire} q ON (q.course = c.id) GROUP BY c.id, c.fullname');
			$courses[0] = 'Seleccione un curso';
			$formselect = $mform->addElement('select', 'id', get_string('course'), $courses);
			$mform->setDefault('id', 0);
			$mform->setType('id', PARAM_INT);
			$this->add_action_buttons(false, 'Buscar encuestas');
			
		} else {
		
		$select = array();
		$select[] = get_string('selectquestionnaire', 'local_encuestascdc');
		
		if(!$questionnaires = $DB->get_records_sql('
        SELECT q.*,cs.name section
        FROM {questionnaire} q
        INNER JOIN {course_modules} cm ON (cm.instance = q.id AND cm.module = '.$moduleid.')
        INNER JOIN {course_sections} cs ON (cm.section = cs.id)
        WHERE q.course='.$courseid, array('course'=>$courseid))) {
			print_error('Curso sin encuestas');
		}
		
		foreach ($questionnaires as $questionnaire) {
		    $select[$questionnaire->id] = $questionnaire->section . '-' . $questionnaire->name . '-' . date('d M Y H:m', $questionnaire->opendate);
		}
		
		$formselect = $mform->addElement('select', 'qid', get_string('questionnaire', 'local_encuestascdc'), $select);
		$mform->addHelpButton('qid', 'questionnaire', 'local_encuestascdc');
		$mform->addElement('hidden', 'id', $courseid);
		$mform->setType('id', PARAM_INT);
		
		$files = scandir("css/");
		$suffix = ".css";
		$cleanfiles = array();
		$default = null;
		foreach($files as $filename) {
		    if (!is_dir($filename) && substr($filename, -4, 4) === $suffix) {
		        $show = str_replace(array('.css','questionnaire_'), '', $filename);
		        $cleanfiles[$show] = $show;
		        $default = $show;
		    }
		}

		$formselect = $mform->addElement('select', 'layout', get_string('questionnaire_report_layout', 'local_encuestascdc'), $cleanfiles);
		
		$formselect = $mform->addElement('text', 'profesor1', get_string('profesor', 'local_encuestascdc') . ' 1');
		$mform->setType('profesor1', PARAM_RAW_TRIMMED);
		
		$formselect = $mform->addElement('text', 'profesor2', get_string('profesor', 'local_encuestascdc') . ' 2');
		$mform->setType('profesor2', PARAM_RAW_TRIMMED);
		
		$formselect = $mform->addElement('text', 'coordinadora', get_string('coordinadora', 'local_encuestascdc'));
		$mform->setType('coordinadora', PARAM_RAW_TRIMMED);
		
		$this->add_action_buttons(false, get_string('search', 'local_encuestascdc'));
				}
		}

	public function validation($data, $files) {
		$errors = array();
		return $errors;
	}
}