<?php
/**
 *
* @package local
* @subpackage encuestascdc
* @copyright 2017-onwards Universidad Adolfo Ibanez
* @author Jorge Villalon <jorge.villalon@uai.cl>
*/

require_once ($CFG->libdir . '/formslib.php');
require_once ($CFG->libdir . '/coursecatlib.php');

class local_encuestascdc_password_form extends moodleform {

	public function definition() {

		$mform = $this->_form;
		$instance = $this->_customdata;

		$mform->addElement('hidden', 'id', $instance['id']);
		$mform->setType('id', PARAM_INT);

		$mform->addElement('hidden', 'action', $instance['action']);
		$mform->setType('action', PARAM_ALPHA);

		$mform->addElement('passwordunmask', 'password', 'Contraseña de encuesta');
		$mform->setType('password', PARAM_RAW);
		
        $choices = coursecat::make_categories_list('moodle/category:manage');
        $choices[0] = get_string('all');
        ksort($choices);

		$mform->addElement('select', 'categoryid', 'Categoría de validez', $choices);
		$mform->setType('categoryid', PARAM_INT);
		
		$durations = Array(10=>"10 minutos", 20=>"20 minutos", 30=>"30 minutos");
		$mform->addElement('select', 'duration', 'Duración', $durations);
		$mform->setType('duration', PARAM_INT);
		
		$this->add_action_buttons();
	}

	public function validation($data, $files) {
		$errors = array();
		return $errors;
	}
}
