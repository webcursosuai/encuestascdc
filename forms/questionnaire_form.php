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

class local_encuestascdc_questionnaire_form extends moodleform
{

    public function definition()
    {
        global $DB, $CFG;
        
        $mform = $this->_form;
        $instance = $this->_customdata;
        $courseid = $instance["course"];
        $moduleid = $instance["module"];
        
        if ($courseid == 0) {
            $courses = $DB->get_records_sql_menu('SELECT c.id, c.fullname FROM {course} c INNER JOIN {questionnaire} q ON (q.course = c.id) GROUP BY c.id, c.fullname');
            $courses[0] = 'Seleccione un curso';
            $formselect = $mform->addElement('select', 'id', get_string('course'), $courses);
            $mform->setDefault('id', 0);
            $mform->setType('id', PARAM_INT);
            $this->add_action_buttons(false, 'Buscar encuestas');
        } else {
            if(!$course = $DB->get_record('course', array('id'=>$courseid))) {
                print_error("Curso inválido");
            }
            if(!$coursecat = $DB->get_record('course_categories', array('id'=>$course->category))) {
                print_error("Curso con categoría inválida");
            }
            
            $groups = $DB->get_records_sql_menu('
        SELECT id, name
        FROM {groups} g
        WHERE g.courseid=:course', array(
                'course' => $courseid
            ));

            if (! $questionnaires = $DB->get_records_sql('
        SELECT q.*,cs.name section
        FROM {questionnaire} q
        INNER JOIN {course_modules} cm ON (cm.instance = q.id AND cm.module = :module)
        INNER JOIN {course_sections} cs ON (cm.section = cs.id)
        WHERE q.course=:course', array(
                'course' => $courseid,
                'module' => $moduleid
            ))) {
                print_error('Curso sin encuestas');
            }

            $select = array(
                '' => get_string('selectreporttype', 'local_encuestascdc'),
                'course' => get_string('reportcourse', 'local_encuestascdc'),
                'program' =>  get_string('reportprogram', 'local_encuestascdc'));
            $formselect = $mform->addElement('select', 'reporttype', get_string('reporttype', 'local_encuestascdc'), $select);
            $mform->addHelpButton('reporttype', 'reporttype', 'local_encuestascdc');
            $mform->addRule('reporttype', 'Debe seleccionar un tipo de reporte', 'required');

            $select = array(
                '' => get_string('selecttype', 'local_encuestascdc'),
                'program-director' => get_string('program-director', 'local_encuestascdc'),
                'client' =>  get_string('client', 'local_encuestascdc'));
            $formselect = $mform->addElement('select', 'type', get_string('questionnaire_type', 'local_encuestascdc'), $select);
            $mform->addHelpButton('type', 'questionnaire_type', 'local_encuestascdc');
            $mform->addRule('type', 'Debe seleccionar destinatario', 'required');
            
            $select = array();
            $select[''] = get_string('selectquestionnaire', 'local_encuestascdc');
            foreach ($questionnaires as $questionnaire) {
                $select[$questionnaire->id] = $questionnaire->section . '-' . $questionnaire->name . '-' . date('d M Y H:m', $questionnaire->opendate);
            }
            
            $formselect = $mform->addElement('select', 'qid', get_string('questionnaire', 'local_encuestascdc'), $select);
            $mform->addHelpButton('qid', 'questionnaire', 'local_encuestascdc');
            $mform->addElement('hidden', 'id', $courseid);
            $mform->setType('id', PARAM_INT);
            $mform->addRule('qid', 'Debe seleccionar una encuesta', 'required');
            
            $files = scandir("css/");
            $suffix = ".css";
            $cleanfiles = array();
            $default = null;
            foreach ($files as $filename) {
                if (! is_dir($filename) && substr($filename, - 4, 4) === $suffix) {
                    $show = str_replace(array(
                        '.css',
                        'questionnaire_'
                    ), '', $filename);
                    $cleanfiles[$show] = $show;
                    $default = $show;
                }
            }
            $cleanfiles[''] = get_string('selectlayout', 'local_encuestascdc');
            
            $formselect = $mform->addElement('select', 'layout', get_string('questionnaire_report_layout', 'local_encuestascdc'), $cleanfiles);
            $mform->addHelpButton('layout', 'questionnaire_report_layout', 'local_encuestascdc');
            $mform->addRule('layout', 'Debe seleccionar una encuesta', 'required');
            $mform->setDefault('layout', '');

            if(count($groups) > 1) {
                $groups[''] = 'Incluir todos';
                $formselect = $mform->addElement('select', 'group', get_string('group', 'local_encuestascdc'), $groups);
                $mform->addHelpButton('group', 'group', 'local_encuestascdc');
                $mform->setDefault('group', '');
            }

            $formselect = $mform->addElement('text', 'profesor1', get_string('profesor', 'local_encuestascdc') . ' 1');
            $mform->setType('profesor1', PARAM_RAW_TRIMMED);
            $mform->hideIf('profesor1', 'type', 'neq', 'program-director');
            
            $formselect = $mform->addElement('text', 'profesor2', get_string('profesor', 'local_encuestascdc') . ' 2');
            $mform->setType('profesor2', PARAM_RAW_TRIMMED);
            $mform->hideIf('profesor2', 'type', 'neq', 'program-director');
            
            $formselect = $mform->addElement('text', 'profesor3', get_string('profesor', 'local_encuestascdc') . ' 3');
            $mform->setType('profesor3', PARAM_RAW_TRIMMED);
            $mform->hideIf('profesor3', 'type', 'neq', 'program-director');
            
            $formselect = $mform->addElement('text', 'coordinadora', get_string('coordinadora', 'local_encuestascdc'));
            $mform->setType('coordinadora', PARAM_RAW_TRIMMED);
            $mform->addRule('coordinadora', 'Debe indicar el nombre de la coordinadora', 'required');

            $formselect = $mform->addElement('text', 'empresa', get_string('empresa', 'local_encuestascdc'));
            $mform->setType('empresa', PARAM_RAW_TRIMMED);
            $mform->setDefault('empresa', $coursecat->name);
            $mform->addRule('empresa', 'Debe indicar el nombre de la empresa', 'required');
            
            $formselect = $mform->addElement('text', 'asignatura', get_string('asignatura', 'local_encuestascdc'));
            $mform->setType('asignatura', PARAM_RAW_TRIMMED);
            $mform->setDefault('asignatura', $course->fullname);
            $mform->addRule('asignatura', 'Debe indicar el nombre de la asignatura', 'required');
            
            $formselect = $mform->addElement('text', 'programa', get_string('programa', 'local_encuestascdc'));
            $mform->setType('programa', PARAM_RAW_TRIMMED);
            $mform->setDefault('programa', '');
            $mform->addRule('programa', 'Debe indicar el nombre del programa', 'required');
            
            $this->add_action_buttons(false, 'Ver reporte');
        }
    }

    public function validation($data, $files)
    {
        $errors = array();
        if($data['qid'] < 1) {
            $errors['qid'] = 'Debe seleccionar una encuesta';
        }
        return $errors;
    }
}