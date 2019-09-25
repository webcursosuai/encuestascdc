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
 * Página de reporte encuestas de UAI Corporate.
 *
 * @package local
 * @subpackage encuestascdc
 * @author Jorge Villalón <jorge.villalon@uai.cl>
 * @copyright 2018 Universidad Adolfo Ibáñez
 */
require_once (dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once ('forms/questionnaire_form.php');
require_once ('lib.php');
require_once ('locallib.php');
// Id del curso
$courseid = required_param('id', PARAM_INT);
// Si ya se escogió encuesta, valida el curso
if(!$course = $DB->get_record('course', array('id'=>$courseid))) {
    print_error('Curso inválido');
}
$url = new moodle_url('/local/encuestascdc/index.php', array('id'=>$courseid));
// El usuario debe estar logueado
require_login($course);
// El usuario debe tener permiso asignado
$context = context_course::instance($courseid);
require_capability('local/encuestascdc:view', $context);
// Id de la encuesta
$qid = optional_param('qid', 0, PARAM_INT);
$mqid = isset($_REQUEST['mqid']) ? $_REQUEST['mqid'] : array();
$layout = optional_param('layout', null, PARAM_ALPHA);
$profesor1 = optional_param('profesor1', 'Profesor 1', PARAM_RAW_TRIMMED);
$profesor2 = optional_param('profesor2', 'Profesor 2', PARAM_RAW_TRIMMED);
$profesor3 = optional_param('profesor3', 'Profesor 3', PARAM_RAW_TRIMMED);
$coordinadora = optional_param('coordinadora', 'Coordinadora académica', PARAM_RAW_TRIMMED);
$empresa = optional_param('empresa', 'Empresa', PARAM_RAW_TRIMMED);
$programa = optional_param('programa', 'Programa', PARAM_RAW_TRIMMED);
$asignatura = optional_param('asignatura', 'Asignatura', PARAM_RAW_TRIMMED);
$destinatario = optional_param('type', 'program-director', PARAM_RAW_TRIMMED);
$tiporeporte = optional_param('reporttype', 'course', PARAM_RAW_TRIMMED);
$group = optional_param('group', 0, PARAM_INT);
// Configuración de página
$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_heading('Reporte de encuestas UAI Corporate');
$PAGE->set_pagelayout('course');
// Valida la categoría del curso
if(!$coursecategory = $DB->get_record('course_categories', array('id'=>$course->category))) {
    print_error('Curso inválido');
}
$categoriesids = explode('/',$coursecategory->path);
$categories = array();
foreach($categoriesids as $catid) {
    if($coursecat = $DB->get_record('course_categories', array('id'=>$catid))) {
        $categories[] = $coursecat->name;
    }
}
// Listado de profesores dentro del curso
$rolprofesor = $DB->get_record('role', array('shortname' => 'editingteacher'));
$profesores = get_role_users($rolprofesor->id, $context);
// Listado de profesores dentro del curso
$rolgestor = $DB->get_record('role', array('shortname' => 'manager'));
$gestores = get_role_users($rolgestor->id, $context, true);
$teachers = array();
if($destinatario !== 'program-director' && $destinatario !== 'teacher') {
    $teachers[1] = 'Profesor 1';
    $teachers[2] = 'Profesor 2';
    $teachers[3] = 'Profesor 3';
} else {
    $i=1;
    foreach($profesores as $profesor) {
        $teachers[$i] = $profesor->firstname . ' ' . $profesor->lastname;
        $i++;
    }
}
$managers = array();
$i=1;
foreach($gestores as $gestor) {
    $managers[$i] = $gestor->firstname . ' ' . $gestor->lastname;
    $i++;
}
$form = new local_encuestascdc_questionnaire_form(null,
    array('course'=>$courseid, 'teachers'=>$teachers, 'managers'=>$managers, 'categories'=>$categories), 'POST');
// Si no se ha seleccionado una encuesta aún, mostrar el formulario
if(!$form->get_data()) {
    // Header de la páginas
    echo $OUTPUT->header();
    $form->display();
    echo $OUTPUT->footer();
    die();
}
$PAGE->set_pagelayout('print');
// Header de la páginas
echo $OUTPUT->header();
echo '<link href="https://fonts.googleapis.com/css?family=Lato|Open+Sans|Ubuntu" rel="stylesheet">';
// Validación del objeto encuesta
if($qid > 0) {
    if(!$questionnaire = $DB->get_record('questionnaire', array('id'=>$qid))) {
        print_error('Encuesta inválida');
    }
    $questionnaires = array($questionnaire->id => $questionnaire);
} elseif(count($mqid) > 0) {
    list($insql, $inparams) = $DB->get_in_or_equal($mqid);
    $sql = "SELECT * FROM {bugtracker_issues} WHERE status $insql";
    if(!$questionnaires = $DB->get_records_sql('SELECT * FROM {questionnaire} q WHERE id ' . $insql, $inparams)) {
        print_error('Ids de encuestas inválidos');
    }
} else {
    print_error('Acceso no autorizado');
}
$enrolledusers = get_enrolled_users($context, 'mod/assignment:submit', $group);
$totalestudiantes = count($enrolledusers);
// Se incluye el layout escogido
if($layout) {
    $layout = clean_filename($layout);
    echo '<style>';
    include "css/questionnaire_$layout.css";
    echo '</style>';
}
$stats = encuestascdc_obtiene_estadisticas($questionnaires);
$teachers = encuestascdc_obtiene_profesores($stats, $profesor1, $profesor2, $profesor3);
list($statsbycourse_average, $statsbycourse_comments) = encuestascdc_obtiene_estadisticas_por_curso($stats);
list($statsbysection_average, $statsbysection_questions, $statsbysection_comments) = encuestascdc_obtiene_estadisticas_por_seccion($stats);
if($tiporeporte === 'course') {
    // Se obtienen los gráficos y las secciones de la encuesta
    $coursestats = $statsbycourse_average[0];

    if($destinatario === 'teacher') {
        if(count($teachers) > 0) {
            encuestascdc_dibuja_portada($questionnaire, $group, $profesor1, NULL, NULL, $asignatura, $empresa, $coursestats['RATIO'], $programa, $destinatario, $coordinadora, $coursestats['ENROLLEDSTUDENTS']);
            encuestascdc_dibujar_reporte($statsbysection_questions, $statsbysection_average, $statsbysection_comments, $profesor1, NULL, $coordinadora, $tiporeporte);
        }
        if(count($teachers) > 1) {
            encuestascdc_dibuja_portada($questionnaire, $group, NULL, $profesor2, NULL, $asignatura, $empresa, $coursestats['RATIO'], $programa, $destinatario, $coordinadora, $coursestats['ENROLLEDSTUDENTS']);
            encuestascdc_dibujar_reporte($statsbysection_questions, $statsbysection_average, $statsbysection_comments, NULL, $profesor2, $coordinadora, $tiporeporte);
        }
        if(count($teachers) > 2) {
            encuestascdc_dibuja_portada($questionnaire, $group, NULL, NULL, $profesor3, $asignatura, $empresa, $coursestats['RATIO'], $programa, $destinatario, $coordinadora, $coursestats['ENROLLEDSTUDENTS']);
            encuestascdc_dibujar_reporte($statsbysection_questions, $statsbysection_average, $statsbysection_comments, NULL, NULL, $coordinadora, $tiporeporte);
        }
    } else {
        encuestascdc_dibuja_portada($questionnaire, $group, $profesor1, $profesor2, $profesor3, $asignatura, $empresa, $coursestats['RATIO'], $programa, $destinatario, $coordinadora, $coursestats['ENROLLEDSTUDENTS']);
        encuestascdc_dibujar_reporte($statsbysection_questions, $statsbysection_average, $statsbysection_comments, $profesor1, $profesor2, $coordinadora, $tiporeporte);
    }
} elseif($tiporeporte === 'program') {
    echo '<div style=" resize: both; "><pre>' . print_r($teachers, true) . '</pre></div>';
    echo '<hr>';
    echo '<div style=" resize: both; "><pre>' . print_r($statsbycourse_average, true) . '</pre></div>';
    echo '<hr>';
    echo '<div style=" resize: both; "><pre>' . print_r($statsbycourse_comments, true) . '</pre></div>';
    echo '<hr>';
    echo '<div style=" resize: both; "><pre>' . print_r($statsbysection_average, true) . '</pre></div>';
    echo '<hr>';
    echo '<div style=" resize: both; "><pre>' . print_r($statsbysection_questions, true) . '</pre></div>';
    echo '<hr>';
    echo '<div style=" resize: both; "><pre>' . print_r($statsbysection_comments, true) . '</pre></div>';
    echo '<hr>';
    echo '<div style=" resize: both; "><pre>' . print_r($stats, true) . '</pre></div>';
    echo '<hr>';
} else {
    echo $OUTPUT->notification('ERROR! Tipo de reporte inválido', 'notifyproblem');
}
// Footer de la página
echo $OUTPUT->footer();