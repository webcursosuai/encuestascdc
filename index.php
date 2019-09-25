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

// Se muestra la tabla de contenidos con las secciones
echo uol_tabla_contenidos($secciones, 1);

?>
<style>
<!--

-->
</style>
<?php
// Se muestran los gráficos
echo $grafico;

// Footer de la página
echo $OUTPUT->footer();

/**
 * Obtiene los gráficos de preguntas tipo rank de la encuesta
 * 
 * @param int $questionnaireid id de la encuesta
 * @param int $moduleid id del módulo questionnaire
 * @param int $typerankid id del tipo de pregunta rank
 * @param int $typetextid id del tipo de pregunta texto
 * @return string[]|string[][]
 */
function uol_grafico_encuesta_rank(int $questionnaireid, int $moduleid, int $typerankid, int $typetextid, String $profesor1, String $profesor2, String $coordinadora) {
    global $DB, $OUTPUT, $CFG;
    
    $totalalumnos = 0;
    $rankfield = $CFG->version < 2016120509 ? '' : 'value';
    // Query para respuestas
    $sql="
SELECT qu.id,
	c.fullname,
	s.id surveyid, 
	s.title nombre, 
	q.name seccion, 
	q.content pregunta, 
	qc.content opcion, 
	q.length, 
	group_concat(rr.rank$rankfield separator '#') answers,
    q.position,
	qt.type
FROM
	{questionnaire} qu
	INNER JOIN {course} c ON (qu.course = c.id AND qu.id = :questionnaireid)
	INNER JOIN {course_modules} cm on (cm.course = qu.course AND cm.module = :moduleid AND cm.instance = qu.id AND cm.visible = 1)
	INNER JOIN {questionnaire_survey} s ON (s.id = qu.sid)
	INNER JOIN {questionnaire_question} q ON (q.surveyid = s.id and q.type_id = :typerankid and q.deleted = 'n')
	INNER JOIN {questionnaire_quest_choice} qc ON (qc.question_id = q.id and q.type_id = :typerankid2)
    INNER JOIN {questionnaire_question_type} qt ON (q.type_id = qt.typeid)
	LEFT JOIN {questionnaire_response} r ON (r.questionnaireid = s.id)
	LEFT JOIN {questionnaire_response_rank} rr ON (rr.choice_id = qc.id and rr.question_id = q.id and rr.response_id = r.id)
GROUP BY qu.id,c.id,s.id, q.id, qc.id
UNION ALL
SELECT qu.id,
	c.fullname,
	s.id surveyid, 
	s.title nombre, 
	q.name seccion, 
	q.content pregunta,
    '' opcion,
    '' length,
    group_concat(rt.response separator '#') answers,
    q.position,
    qt.type
FROM
	{questionnaire} qu
	INNER JOIN {course} c ON (qu.course = c.id AND qu.id = :questionnaireid2)
	INNER JOIN {course_modules} cm on (cm.course = qu.course AND cm.module = :moduleid2 AND cm.instance = qu.id AND cm.visible = 1)
	INNER JOIN {questionnaire_survey} s ON (s.id = qu.sid)
	INNER JOIN {questionnaire_question} q ON (q.surveyid = s.id and q.type_id = :typetextid and q.deleted = 'n')
    INNER JOIN {questionnaire_question_type} qt ON (q.type_id = qt.typeid)
    LEFT JOIN {questionnaire_response} r ON (r.questionnaireid = s.id)
    LEFT JOIN {questionnaire_response_text} rt ON (rt.response_id = r.id AND rt.question_id = q.id)
GROUP BY qu.id,c.id,s.id, q.id
ORDER BY position";
    
    $params = array(
        'questionnaireid' => $questionnaireid,
        'moduleid' => $moduleid,
        'typerankid' => $typerankid,
        'typerankid2' => $typerankid,
        'questionnaireid2' => $questionnaireid,
        'moduleid2' => $moduleid,
        'typetextid' => $typetextid
    );
    // Todas las respuestas
    $respuestas = $DB->get_recordset_sql($sql, $params);
    // Arreglo con los nombres de secciones
    $secciones = Array();
    // El html que se devuelve en el primer parámetro
    $fullhtml = '';
    // Variable con la última sección utilizada, para identificar cambio de sección
    $ultimaseccion = '';
    
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

function uol_tabla_respuesta_rank($respuesta, $header = false) {
    $gradient = array(
        1 => "EF494F",
        2 => "E96946",
        3 => "E38E44",
        4 => "DDB142",
        5 => "D7D23F",
        6 => "B1D13D",
        7 => "88CB3B",
        8 => "60C539",
        9 => "3BBF37",
        10 => "35B951",
        11 => "33B26F"
    );
    
    
    // Todas las respuestas, indicando qué rank escogió de entre 0 y length - 1
    $ranks = explode('#', $respuesta->answers);
    // Totales de respuestas por cada rank
    $values = array();
    // Promedio acumulado
    $promedio = 0;
    // Total de respuestas
    $total = count($ranks);
    // Total de respuestas NA (para no considerar en el promedio)
    $totalna = 0;
    // Por cada rank posible (de 0 a length - 1)
    for($i=-1;$i<$respuesta->length;$i++) {
        // Inicializamos valores
        // Si es -1 es porque es NA (NS/NC No sabe, no contesta)
        if($i<0) {
            $valuesna = 0;
        } else {
            $values[$i+1] = 0;
        }
        // Cuenta cuántos valores de dicho rank hay. Recorre todas las respuestas
        for($j=0;$j<count($ranks);$j++) {
            // Si la respuesta corresponde al rank
            if($ranks[$j] == $i) {
                // Suma a valores NA o al valor
                if($i<0) {
                    $valuesna++;
                    $totalna++;
                } else {
                    $values[$i+1]++;
                    $promedio += $i+1;
                }
            }
        }
    }
    // Calculamos promedio si es viable, de lo contrario queda en 0
    if($total - $totalna > 0) {
        $promedio = round($promedio / ($total - $totalna),1);
    }
    
    // Resumen de promedio y número respuestas
    $resumenhtml = '<div class="promedio">' . $promedio . '</div><div class="numrespuestas hyphenate">Nº respuestas: ' . $total . '</div>';
    $htmlpromedio = '<div class="promedio">' . $promedio . '</div>';
    $max = 0;
    foreach($values as $idx => $val) {
        if($val > $max) {
            $max = $val;
        }
    }
    // HTML y clase CSS para tabla de datos
    $classtabla = "cel-".$respuesta->length;
    $tablahtml = '<table class="datos '.$classtabla.'"><tr>';
    if($header) {
        if($respuesta->length == 7) {
            $tablahtml .= "<tr><td width='10%'>NS/NC</td><td width='10%'>1</td><td width='10%'>2</td><td width='10%'>3</td><td width='10%'>4</td><td width='10%'>5</td><td width='10%'>6</td><td width='10%'>7</td><td width='20%'>Prom.</td></tr>";
        } else {
            $tablahtml .= "<tr><tr><td width='16%'>NS/NC</td><td width='16%'>Bajo</td><td width='16%'>Medio Bajo</td><td width='16%'>Medio Alto</td><td width='16%'>Alto</td><td width='20%'>Promedio</td></tr>";
        }
    }
    $classinterno = '';
    if($valuesna == 0) {
        $valuesna = '-';
        $classinterno = 'cero';
    }
    $tablahtml .= "<td><div class=\"circulo\"><div class=\"numero\">$valuesna</div></div></td>";
    $nivel = 1;
    foreach($values as $idx => $val) {
        $percent = $max > 0 ? round(($val / $max) * 13,0) + 7 : 0;
        $indexgradient = 1 + (10/$respuesta->length) * ($nivel - 1);
        $fill = "#" . $gradient[$indexgradient];
        $classinterno = '';
        if($val == 0) {
            $val = '-';
            $classinterno = 'cero';
            $fill = '#fff';
        }
        // $tablahtml .= "<td><div class=\"circulo\"><div class=\"circulo-interno nivel$nivel-$respuesta->length $classinterno\" style=\"width:".$percent."px; height:".$percent."px;\"><div class=\"numero\">$val</div></div></div></td>";
        $tablahtml .= "<td><svg width='40' height='40'><circle cx='20' cy='20' r='$percent' stroke='none' fill='$fill' />
<text font-size='12'
      fill='black'
      font-family='Verdana'
      text-anchor='middle'
      alignment-baseline='baseline'
      x='20'
      y='25'>$val</text></svg></td>";
        $nivel++;
    }
    $tablahtml .= '<td style="width:20%" class="promedio">'.$promedio.'</td></tr></table>';
    
    // Crea chart
    /*        ### Con esto saco frecuencias fácilmente
     $vals = array_values($values);
     $labels = array_keys($values);
     ### Preparo data para pasárselo al chart
     $chartSeries = new \core\chart_series('Estudiantes', $vals);
     $chartSeries->set_color('#f00');
     ### Creo una serie
     $chart = new \core\chart_bar();
     $chart->set_title('');
     $chart->set_horizontal(true);
     $chart->add_series($chartSeries);
     $chart->set_labels($labels);
     $xaxis= new \core\chart_axis();
     ### Frecuencias se miden sólo en enteros (duh)
     $xaxis->set_stepsize(1);
     $chart->set_xaxis($xaxis);
     $width = $respuesta->length == 4 ? 400 : 450; */
    $titulografico = trim(str_ireplace(array('a)','b)','c)','d)','e)', 'f)', 'g)', 'h)', 'i)', 'j)'), '', $respuesta->opcion));
    $charthtml = '<table width="100%"><tr><td class="titulografico hyphenate">'.$titulografico.'</td><td>'.$tablahtml.'</td></tr>'.
    '<tr class="trgrafico"><td class="tdgrafico">'. '</td><td>' .  $resumenhtml. '</td></tr></table>'; ### Se proyecta Chart
    $charthtml = html_writer::div($charthtml,'encuesta') . '<hr>';
    return $charthtml;
}
