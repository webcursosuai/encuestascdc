<?php
/**
 *
* @package local
* @subpackage uaio
* @copyright 2017 Universidad Adolfo Ibanez
* @author Jorge Villalon <jorge.villalon@uai.cl>
*/

require_once ($CFG->libdir . '/formslib.php');

class local_encuestascdc_login_form extends moodleform {

	public function definition() {

		$mform = $this->_form;
		$instance = $this->_customdata;

		$mform->addElement('text', 'username', 'RUT o Pasaporte');
		$mform->addHelpButton('username', 'rut', 'local_uaio');
		$mform->setType('username', PARAM_RAW);

		$mform->addElement('password', 'pwd', 'Contraseña');
		$mform->setType('pwd', PARAM_RAW);

		$this->add_action_buttons(false, get_string('login', 'local_uaio'));
	}

	public function validation($data, $files) {
		$errors = array();
		return $errors;
	}
}
