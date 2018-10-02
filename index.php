<?php
require_once (dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once ('forms/questionnaire_form.php');

require_login();

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$courseid = optional_param('id', 0, PARAM_INT);
$qid = optional_param('qid', 0, PARAM_INT);
$layout = optional_param('layout', null, PARAM_ALPHA);

$PAGE->set_context($context);
$PAGE->set_url('/lib/tests/other/cdcreport.php');
$PAGE->set_heading('Reporte encuesta');
$PAGE->set_pagelayout('print');

echo $OUTPUT->header();

if($qid == 0 || $courseid == 0) {
	echo $OUTPUT->heading('Reporte de encuestas UAI Corporate');
    $form = new local_uaio_questionnaire_form(null, array('course'=>$courseid), 'GET');
    $form->display();
    echo $OUTPUT->footer();
    die();
}

if(!$course = $DB->get_record('course', array('id'=>$courseid))) {
	print_error('Curso inválido');
}

$coursecontext = context_course::instance($course->id);

if(!$coursecategory = $DB->get_record('course_categories', array('id'=>$course->category))) {
	print_error('Curso inválido');
}

$profesores = get_enrolled_users($coursecontext, 'mod/assign:grade');


if(!$questionnaire = $DB->get_record('questionnaire', array('id'=>$qid))) {
    print_error('Encuesta inválida');
}
?>
<style>
<?php
if($layout) {
    $layout = clean_filename($layout);
    include "css/questionnaire_$layout.css";
}
?>
</style>
<?php
echo html_writer::start_div('primera-pagina');
echo "<div class='uai-corporate-logo'></div>";
echo $OUTPUT->heading('Encuesta de Satisfacción de Programas Corporativos', 1, array('class'=>'reporte_titulo'));
echo html_writer::div('Informe de resultados', 'subtitulo');

echo "
<table class='portada'>
<tr>
    <td class='portada-item'>Empresa</td>
    <td class='portada-valor'>$coursecategory->name</td>
</tr>
<tr>
    <td class='portada-item'>Programa</td>
    <td class='portada-valor'>$course->fullname</td>
</tr>
<tr>
    <td class='portada-item'>Asignatura-Actividad</td>
    <td class='portada-valor'>$coursecategory->name</td>
</tr>
<tr>
    <td class='portada-item'>Grupo</td>
    <td class='portada-valor'>$coursecategory->name</td>
</tr>
<tr>
    <td class='portada-item'>Fecha realización</td>
    <td class='portada-valor'>$coursecategory->name</td>
</tr>
</table>
";
$profesoreshtml = '';
foreach($profesores as $profesor) {
    $profesoreshtml .= $OUTPUT->heading($profesor->firstname . ' ' . $profesor->lastname, 5);
}
echo html_writer::end_div();

list($grafico, $secciones) = uol_grafico_encuesta_rank($questionnaire->id);

echo uol_tabla_contenidos($secciones, 1);

echo $grafico;

echo $OUTPUT->footer();

function uol_grafico_encuesta_rank(int $questonnaireid) {
    global $DB, $OUTPUT;
    
    $sql="
SELECT qu.id,c.fullname,s.id surveyid, s.title nombre, q.name seccion, q.content pregunta, qc.content opcion, q.length, group_concat(rr.rank separator '#') ranks
FROM
{questionnaire} qu
INNER JOIN {course} c ON (qu.course = c.id AND qu.id = $questonnaireid)
INNER JOIN {course_modules} cm on (cm.course = qu.course AND cm.module = 18 AND cm.instance = qu.id AND cm.visible = 1 AND cm.deletioninprogress = 0)
INNER JOIN {questionnaire_survey} s ON (s.id = qu.sid)
INNER JOIN {questionnaire_question} q ON (q.survey_id = s.id and q.type_id = 8 and q.deleted = 'n')
INNER JOIN {questionnaire_quest_choice} qc ON (qc.question_id = q.id and q.type_id = 8)
LEFT JOIN {questionnaire_response} r ON (r.survey_id = s.id)
LEFT JOIN {questionnaire_response_rank} rr ON (rr.choice_id = qc.id and rr.question_id = q.id and rr.response_id = r.id)
GROUP BY qu.id,c.id,s.id, q.id, qc.id
ORDER BY q.name, s.id, qc.content";
    
    $respuestas = $DB->get_recordset_sql($sql);
    $secciones = Array();
    $fullhtml = '';
    $ultimaseccion = '';
    foreach($respuestas as $respuesta)
    {
        if($ultimaseccion !== $respuesta->seccion) {
            if($ultimaseccion !== '') {
                $fullhtml .= "</div>";
            }
            $fullhtml .= "<div class='break-after'></div>";
            $fullhtml .= "<h1 class='break-before'>". $respuesta->seccion . "</h1>";
            $ultimaseccion = $respuesta->seccion;
            $secciones[] = $ultimaseccion;
            $classescala = "escala-" . count($secciones);
            if($respuesta->length == 4) {
                $fullhtml .= "<div class='escala $classescala'>Nivel de conformidad con las siguientes afirmaciones<br/><table width='100%'><tr><td width='25%'>1: Bajo</td><td width='25%'>2: Medio Bajo</td><td width='25%'>3: Medio Alto</td><td width='25%'>4: Alto</td></tr></table></div>";
            } else {
                $fullhtml .= "<div class='escala $classescala'>En una escala de 1 a 7, donde 1 es Muy Malo y 7 es Excelente</div>";
            }
            $fullhtml .= "<div class='multicol cols-2'>";
        }
        $ranks = explode('#', $respuesta->ranks);
        $values = array();
        $promedio = 0;
        $total = count($ranks);
        $totalna = 0;
        for($i=$respuesta->length-1;$i>=-1;$i--) {
            if($i<0) {
                $valuesna = 0;
            } else {
                $values[$i+1] = 0;
            }
            for($j=0;$j<count($ranks);$j++) {
                if($ranks[$j] == $i) {
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
        if($total - $totalna > 0) {
            $promedio = round($promedio / ($total - $totalna),1);
        }
        
        $resumenhtml = '<div class="promedio">' . $promedio . '</div><div class="numrespuestas hyphenate">Nº respuestas: ' . $total . '</div>';
        
        $classtabla = "cel-".$respuesta->length;
        $tablahtml = '<table class="datos '.$classtabla.'">';
        $tablahtml .= "<td>$valuesna</td>";
        foreach($values as $val) {
            $percent = $total > 0 ? round(($val / $total) * 100,1) : 0;
            $tablahtml .= "<td>$percent%</td>";
        }
        $tablahtml .= '</table>';
        
        ### Con esto saco frecuencias fácilmente
        $vals= array_values($values);
        $keys= array_keys($values);
        ### Preparo data para pasárselo al chart
        $chartSeries = new \core\chart_series('Estudiantes', $vals);
        $chartSeries->set_color('#f00');
        ### Creo una serie
        $chart = new \core\chart_bar();
        $chart->set_title(''); ### (CAMBIAR POR TÍTULO DE LA PREGUNTA)
        $chart->set_horizontal(true); ### Según lo visto por el PPT, eran horizontales
        $chart->add_series($chartSeries); ### Añado la serie (Es posible añadir varias)
        $chart->set_labels($keys); ### Labels dependiendo de las respuestas capturadas
        $xaxis= new \core\chart_axis();
        ### Frecuencias se miden sólo en enteros (duh)
        $xaxis->set_stepsize(1);
        $chart->set_xaxis($xaxis);
        $width = $respuesta->length == 4 ? 400 : 450;
        $titulografico = trim(str_ireplace(array('a)','b)','c)','d)','e)', 'f)', 'g)', 'h)', 'i)', 'j)'), '', $respuesta->opcion));
        $charthtml = '<table width="100%"><tr><td class="titulografico hyphenate">'.$titulografico.'</td><td>'.$tablahtml.'</td></tr><tr class="trgrafico"><td class="tdgrafico">'. $OUTPUT->render_chart($chart, false) . '</td><td>' .  $resumenhtml. '</td></tr></table>'; ### Se proyecta Chart
        $charthtml = html_writer::div($charthtml,'encuesta');
        $fullhtml .= $charthtml;
    }
    return array($fullhtml ."</div>", $secciones);
}

function uol_tabla_contenidos(array $secciones, int $activo) {
    global $OUTPUT;
    
    $output = '';
    $output .= html_writer::start_div('navegacion');
    $output .= $OUTPUT->heading('Contenido', 1, 'break-before');
    $output .= "<ul>";
    $i=0;
    foreach($secciones as $seccion) {
        $i++;
        $liclass = $i == $activo ? 'activo' : '';
        $output .= "<li class='$liclass'>$seccion</li>";
    }
    $output .= "</ul>";
    $output .= html_writer::end_div();
    return $output;
}
