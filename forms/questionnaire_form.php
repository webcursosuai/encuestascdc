<?php
/**
 *
* @package local
* @subpackage uaio
* @copyright 2017 Universidad Adolfo Ibanez
* @author Jorge Villalon <jorge.villalon@uai.cl>
*/

require_once ($CFG->libdir . '/formslib.php');
require_once ($CFG->libdir . '/coursecatlib.php');

class local_encuestascdc_questionnaire_form extends moodleform {

	public function definition() {
	    global $DB, $CFG;

		$mform = $this->_form;
		$instance = $this->_customdata;
		$courseid = $instance["course"];

		if($courseid == 0) {
			$courses = $DB->get_records_sql_menu('SELECT c.id, c.fullname FROM {course} c INNER JOIN {questionnaire} q ON (q.course = c.id)');
			$courses[0] = 'Seleccione un curso';
			$formselect = $mform->addElement('select', 'id', get_string('course'), $courses);
			$mform->setDefault('id', 0);
			$mform->setType('id', PARAM_INT);
			$this->add_action_buttons(false, 'Buscar encuestas');
			
		} else {
		
		$select = array();
		$select[] = get_string('selectquestionnaire', 'local_encuestascdc');
		
		if(!$questionnaires = $DB->get_records('questionnaire', array('course'=>$courseid))) {
			print_error('Curso sin encuestas');
		}
		
		foreach ($questionnaires as $questionnaire) {
		    $select[$questionnaire->id] = $questionnaire->name;
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
		
		$this->add_action_buttons(false, get_string('search', 'local_encuestascdc'));
				}
		}

	public function validation($data, $files) {
		$errors = array();
		return $errors;
	}
}