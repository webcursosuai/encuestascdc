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

// El usuario debe estar logueado
require_login($course);

// El usuario debe tener permiso asignado
$context = context_course::instance($courseid);
require_capability('local/encuestascdc:view', $context);

// Id de la encuesta
$qid = optional_param('qid', 0, PARAM_INT);
$layout = optional_param('layout', null, PARAM_ALPHA);
$profesor1 = optional_param('profesor1', 'Profesor 1', PARAM_RAW_TRIMMED);
$profesor2 = optional_param('profesor2', 'Profesor 2', PARAM_RAW_TRIMMED);
$profesor3 = optional_param('profesor3', 'Profesor 3', PARAM_RAW_TRIMMED);
$coordinadora = optional_param('coordinadora', 'Coordinadora académica', PARAM_RAW_TRIMMED);
$empresa = optional_param('empresa', 'Empresa', PARAM_RAW_TRIMMED);
$programa = optional_param('programa', 'Programa', PARAM_RAW_TRIMMED);
$asignatura = optional_param('asignatura', 'Asignatura', PARAM_RAW_TRIMMED);
$destinatario = optional_param('type', 'program-director', PARAM_RAW_TRIMMED);
$group = optional_param('group', 0, PARAM_INT);

// Validación de instalación del módulo questionnaire
if(!$module = $DB->get_record('modules', array('name'=>'questionnaire'))) {
    print_error('Módulo questionnaire no está instalado');
}

// Configuración de página
$PAGE->set_context($context);
$PAGE->set_url('/local/encuestascdc/index.php');
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
if($destinatario !== 'program-director') {
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

// Validación de tipo de respuesta rank
if(!$questiontype = $DB->get_record('questionnaire_question_type', array('response_table'=>'response_rank'))) {
	print_error('Tipo de pregunta rank no instalada');
}

// Validación de tipo de respuesta texto
if(!$questiontypetext = $DB->get_record('questionnaire_question_type', array('response_table'=>'response_text', 'type'=>'Text Box'))) {
    print_error('Tipo de pregunta Text Box no instalada');
}

$form = new local_encuestascdc_questionnaire_form(null, 
    array('course'=>$courseid, 'module'=>$module->id, 'teachers'=>$teachers, 'managers'=>$managers, 'categories'=>$categories), 'POST');

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
if(!$questionnaire = $DB->get_record('questionnaire', array('id'=>$qid))) {
    print_error('Encuesta inválida');
}

// Validación del objeto coursemodule
if(!$coursemodule = $DB->get_record('course_modules', array('instance'=>$qid,'module'=>$module->id))) {
    print_error('Módulo de curso inválido');
}

// Validación del objeto course section
if(!$coursesection = $DB->get_record('course_sections', array('id'=>$coursemodule->section))) {
    print_error('Sección de curso inválida');
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

// Se muestra la primera página con información del informe y general
echo html_writer::start_div('primera-pagina');
echo html_writer::start_div('logos');
echo "<div class='uai-corporate-logo'><img width=396 height='auto' src='img/logo-uai-corporate-no-transparente2.png'></div>";
echo html_writer::end_div();

echo $OUTPUT->heading('Encuesta de Satisfacción de Programas Corporativos', 1, array('class'=>'reporte_titulo'));

// Se obtienen los gráficos y las secciones de la encuesta
list($grafico, $secciones, $totalalumnos) = uol_grafico_encuesta_rank($questionnaire->id, $module->id, $questiontype->typeid, $questiontypetext->id, $profesor1, $profesor2, $coordinadora, $group);
$tasa = $totalestudiantes > 0 ? round(($totalalumnos / $totalestudiantes) * 100, 0) : 0;
$portada = html_writer::div('Informe de resultados', 'subtitulo');

$fecharealizacion = local_encuestascdc_util_mes_en_a_es(date('d F Y', $questionnaire->opendate));

$htmlgrupo = '';
if($group > 0) {
    if(!$groupobj = $DB->get_record('groups', array('id'=>$group))) {
        print_error('Invalid group');
    }
    
    $htmlgrupo = "<tr>
        <td class='portada-item'>Grupo</td>
        <td class='portada-valor'>: $groupobj->name</td>
    </tr>";    
}

$htmlprofesor2 = $profesor2 === '' ? '' : "<tr>
    <td class='portada-item'>Profesor 2</td>
    <td class='portada-valor'>$profesor2</td>
</tr>
";
$htmlprofesor3 = $profesor3 === '' ? '' : "<tr>
    <td class='portada-item'>Profesor 3</td>
    <td class='portada-valor'>$profesor3</td>
</tr>
";
$portada .= "
<table class='portada'>
<tr>
    <td class='portada-item'>Empresa</td>
    <td class='portada-valor'>: $empresa</td>
</tr>
<tr>
    <td class='portada-item'>Programa</td>
    <td class='portada-valor'>: $programa</td>
</tr>
<tr>
    <td class='portada-item'>Asignatura-Actividad</td>
    <td class='portada-valor'>: $asignatura</td>
</tr>
$htmlgrupo
<tr>
    <td class='portada-item'>Fecha realización</td>
    <td class='portada-valor'>: $fecharealizacion</td>
</tr>";
if($destinatario === 'program-director') {
    $portada .= "
    <tr>    
        <td class='portada-item'>Profesor 1</td>
        <td class='portada-valor'>: $profesor1</td>
    </tr>
    $htmlprofesor2
    $htmlprofesor3";
}
$portada .= "
<tr>
    <td class='portada-item'>Coordinadora</td>
    <td class='portada-valor'>: $coordinadora</td>
</tr>
<tr>
    <td class='portada-item'>Número de alumnos</td>
    <td class='portada-valor'>: $totalestudiantes</td>
</tr>
<tr>
    <td class='portada-item'>Tasa de respuesta</td>
    <td class='portada-valor'>: $tasa%</td>
</tr>
</table>
";
$portada .= html_writer::end_div();

echo $portada;

// Se muestra la tabla de contenidos con las secciones
echo uol_tabla_contenidos($secciones, 1);

// Se muestran los gráficos
echo $grafico;

// Footer de la página
echo $OUTPUT->footer();