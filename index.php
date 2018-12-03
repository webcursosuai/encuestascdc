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

// id del curso
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

// id de la encuesta
$qid = optional_param('qid', 0, PARAM_INT);
// layout a mostrar
$layout = optional_param('layout', null, PARAM_ALPHA);
// print
$print = optional_param('print', false, PARAM_BOOL);

// Validación de instalación del módulo questionnaire
if(!$module = $DB->get_record('modules', array('name'=>'questionnaire'))) {
    print_error('Módulo questionnaire no está instalado');
}

// Configuración de página
$PAGE->set_context($context);
$PAGE->set_url('/local/encuestascdc/index.php');
$PAGE->set_heading('Reporte de encuestas UAI Corporate');
$PAGE->set_pagelayout('course');

$form = new local_encuestascdc_questionnaire_form(null, array('course'=>$courseid, 'module'=>$module->id), 'POST');

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

// Parámetros necesarios para imprimir la encuesta
$profesor1 = required_param('profesor1', PARAM_RAW_TRIMMED);
$profesor2 = optional_param('profesor2', '', PARAM_RAW_TRIMMED);
$coordinadora = required_param('coordinadora', PARAM_RAW_TRIMMED);

// Valida la categoría del curso
if(!$coursecategory = $DB->get_record('course_categories', array('id'=>$course->category))) {
	print_error('Curso inválido');
}

// Listado de profesores dentro del curso
$rolprofesor = $DB->get_record('role', array('shortname' => 'teacher'));
$profesores = get_role_users($role->id, $context);

// Validación del objeto encuesta
if(!$questionnaire = $DB->get_record('questionnaire', array('id'=>$qid))) {
    print_error('Encuesta inválida');
}

$partesnombre = explode('-',$questionnaire->name);

if(count($partesnombre) != 3) {
    print_error('Nombre de encuesta inválida, debe contener dos guiones en el format A-B-C. Con B el nombre de la actividad, C el nombre del profesor y A bla bla.');
}

$nombrebla = $partesnombre[0];
$nombreactividad = $partesnombre[1];
$nombreprofesor = $partesnombre[2];

// Validación de tipo de respuesta rank
if(!$questiontype = $DB->get_record('questionnaire_question_type', array('response_table'=>'response_rank'))) {
	print_error('Tipo de pregunta rank no instalada');
}

// Validación de tipo de respuesta texto
if(!$questiontypetext = $DB->get_record('questionnaire_question_type', array('response_table'=>'response_text', 'type'=>'Text Box'))) {
    print_error('Tipo de pregunta Text Box no instalada');
}

// Validación del objeto coursemodule
if(!$coursemodule = $DB->get_record('course_modules', array('instance'=>$qid,'module'=>$module->id))) {
    print_error('Módulo de curso inválido');
}

// Validación del objeto course section
if(!$coursesection = $DB->get_record('course_sections', array('id'=>$coursemodule->section))) {
    print_error('Sección de curso inválida');
}

// Se incluye el layout escogido
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

// Se muestra la primera página con información del informe y general
echo html_writer::start_div('primera-pagina');
echo html_writer::start_div('logos');
echo "<div class='uai-corporate-logo'><img width=200 height=67 src='img/logo-uai-corporate-no-transparente.png'></div>";
echo "<div class='uai-logo'><img width=200 height=67 src='img/logo_uai_parche_negro.jpg'></div>";
echo html_writer::end_div();

echo $OUTPUT->heading('Encuesta de Satisfacción de Programas Corporativos', 1, array('class'=>'reporte_titulo'));
echo html_writer::div('Informe de resultados', 'subtitulo');

$fecharealizacion = date('d M Y', $questionnaire->opendate);
$htmlgrupo = strtoupper(substr($coursesection->name, 0)) === 'G' ?
"<tr>
    <td class='portada-item'>Grupo</td>
    <td class='portada-valor'>$coursesection->name</td>
</tr>
" : "";
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
    <td class='portada-valor'>$nombreactividad</td>
</tr>
$htmlgrupo
<tr>
    <td class='portada-item'>Fecha realización</td>
    <td class='portada-valor'>$fecharealizacion</td>
</tr>
</table>
";
// Listado de profesores
$profesoreshtml = '';
foreach($profesores as $profesor) {
    $profesoreshtml .= $OUTPUT->heading($profesor->firstname . ' ' . $profesor->lastname, 5);
}
echo html_writer::end_div();

// Se obtienen los gráficos y las secciones de la encuesta
list($grafico, $secciones) = uol_grafico_encuesta_rank($questionnaire->id, $module->id, $questiontype->typeid, $questiontypetext->id, $profesor1, $profesor2, $coordinadora);

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
    global $DB, $OUTPUT;
    
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
	group_concat(rr.rank separator '#') answers,
    q.position,
	qt.type
FROM
	{questionnaire} qu
	INNER JOIN {course} c ON (qu.course = c.id AND qu.id = :questionnaireid)
	INNER JOIN {course_modules} cm on (cm.course = qu.course AND cm.module = :moduleid AND cm.instance = qu.id AND cm.visible = 1)
	INNER JOIN {questionnaire_survey} s ON (s.id = qu.sid)
	INNER JOIN {questionnaire_question} q ON (q.survey_id = s.id and q.type_id = :typerankid and q.deleted = 'n')
	INNER JOIN {questionnaire_quest_choice} qc ON (qc.question_id = q.id and q.type_id = :typerankid2)
    INNER JOIN {questionnaire_question_type} qt ON (q.type_id = qt.typeid)
	LEFT JOIN {questionnaire_response} r ON (r.survey_id = s.id)
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
	INNER JOIN {questionnaire_question} q ON (q.survey_id = s.id and q.type_id = :typetextid and q.deleted = 'n')
    INNER JOIN {questionnaire_question_type} qt ON (q.type_id = qt.typeid)
    LEFT JOIN {questionnaire_response} r ON (r.survey_id = s.id)
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
    
    $profesores = 0;
    // Revisamos cada conjunto de respuestas por pregunta
    foreach($respuestas as $respuesta)
    {
    	// Si hay cambio de sección
        if($ultimaseccion !== $respuesta->seccion) {
        	// Se cierra div anterior (de sección)
            if($ultimaseccion !== '') {
                $fullhtml .= "</div>";
            }
            // Se agregar un break vacío
            $fullhtml .= "<div class='break-after'></div>";
            $fullhtml .= "<div class='multicol cols-2 seccioncompleta'>";
            
            // Partimos con un break antes del título y el título
            if($respuesta->type === "Rate (scale 1..5)") {
                if($respuesta->length == 4) {
                    $fullhtml .= "<div class='encuesta break-before seccion'>
<table width='100%'><tr><td class='tituloseccion titulografico hyphenate'>$respuesta->seccion</td><td><div class='escala $classescala'><div class='tituloescala'>Nivel de conformidad con las siguientes afirmaciones</div><table width='100%'><tr><td width='20%'>NS/NC</td><td width='20%'>Bajo</td><td width='20%'>Medio Bajo</td><td width='20%'>Medio Alto</td><td width='20%'>Alto</td></tr></table></div></td></tr><tr class='trgrafico'><td class='tdgrafico'>'. '</td><td></td></tr></table>
                </div>";
                } elseif($respuesta->length == 7) {
                    $fullhtml .= "<div class='encuesta break-before seccion'>
<table width='100%'><tr><td class='tituloseccion titulografico hyphenate'>$respuesta->seccion</td><td><div class='escala $classescala'><div class='tituloescala'>En una escala de 1 a 7, donde 1 es Muy Malo y 7 es Excelente</div><table width='100%'><tr><td width='12.5%'>NS/NC</td><td width='12.5%'>Muy Malo</td><td width='12.5%'>Malo</td><td width='12.5%'>Medio Malo</td><td width='12.5%'>Medio</td><td width='12.5%'>Bueno</td><td width='12.5%'>Muy Bueno</td><td width='12.5%'>Excelente</td></tr></table></div></td></tr><tr class='trgrafico'><td class='tdgrafico'>'. '</td><td></td></tr></table>
                </div>";
                } else {
                    $fullhtml .= '<div>WTF</div>';
                }
            } else {
                $fullhtml .= "<div class='tituloseccion break-before seccion'>". $respuesta->seccion . "</div>";
            }
            if(stripos($respuesta->seccion, "PROFESOR") !== false) {
                $fullhtml .= "<h2 class='nombreprofesor'>$profesor1</h2>";
                $profesores++;
            } elseif(stripos($respuesta->seccion, "COORDINACI") !== false) {
                $fullhtml .= "<h2 class='nombreprofesor'>$coordinadora</h2>";
            }
            // Actualizamos última sección
            $ultimaseccion = $respuesta->seccion;
            // Agregamos a la lista de secciones
            $secciones[] = $ultimaseccion;
            // Clase para la escala de acuerdo al número de secciones
            $classescala = "escala-" . count($secciones);
        } elseif(stripos($respuesta->seccion, "PROFESOR") !== false && $profesores > 0 && substr($respuesta->opcion, 0, 2) === "a)") {
            $fullhtml .= "</div><div class='multicol cols-2 seccioncompleta'>";
            $fullhtml .= "<div class='encuesta break-before seccion'>
<table width='100%'><tr><td class='tituloseccion titulografico hyphenate'>$respuesta->seccion</td><td><div class='escala $classescala'><div class='tituloescala'>Nivel de conformidad con las siguientes afirmaciones</div><table width='100%'><tr><td width='20%'>NS/NC</td><td width='20%'>Bajo</td><td width='20%'>Medio Bajo</td><td width='20%'>Medio Alto</td><td width='20%'>Alto</td></tr></table></div></td></tr><tr class='trgrafico'><td class='tdgrafico'>'. '</td><td></td></tr></table>
                </div>";
            $fullhtml .= "<h2 class='nombreprofesor'>$profesor2</h2>";
        }
        if($respuesta->type === "Rate (scale 1..5)") {
            $fullhtml .= uol_tabla_respuesta_rank($respuesta);
        } elseif($respuesta->type === "Text Box") {
            $fullhtml .= uol_tabla_respuesta_text($respuesta, $profesor1, $profesor2, $coordinadora);
        }
    }
    // Se retorna el html de gráficos y a lista de secciones
    return array($fullhtml ."</div>", $secciones);
}

/**
 * Crea una tabla con contenidos dada una lista de secciones. Puede marcar una sección como la activa.
 * 
 * @param array $secciones
 * @param int $activo
 * @return string
 */
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

function uol_tabla_respuesta_text($respuesta, $profesor1, $profesor2, $coordinadora) {
    $answers = explode('#',$respuesta->answers);
    $numanswers = count($answers);
    $answers = implode("\n", $answers);
    $pregunta = $respuesta->pregunta;
    if(stripos($respuesta->pregunta, "Profesor 1") !== false) {
        $pregunta = str_replace("Profesor 1", $profesor1, $pregunta);
    } elseif(stripos($respuesta->pregunta, "Profesor 2") !== false) {
        $pregunta = str_replace("Profesor 2", $profesor2, $pregunta);
    } elseif(stripos($respuesta->pregunta, "Coordinadora") !== false) {
        $pregunta = str_replace("Coordinadora", $coordinadora, $pregunta);
    }
    // Todas las respuestas, indicando qué rank escogió de entre 0 y length - 1
    return "
<div class='encuesta'>
    <table width='100%'>
        <tr>
            <td class='titulografico'>$pregunta</td>
        </tr>
        <tr>
            <td><textarea class='comentarios' name='text$respuesta->id' rows=$numanswers>$answers</textarea></td>
        </tr>
    </table>
</div>";
}

function uol_tabla_respuesta_rank($respuesta) {
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
    for($i=$respuesta->length-1;$i>=-1;$i--) {
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

    $max = 0;
    foreach($values as $idx => $val) {
        if($val > $max) {
            $max = $val;
        }
    }
    // HTML y clase CSS para tabla de datos
    $classtabla = "cel-".$respuesta->length;
    $tablahtml = '<table class="datos '.$classtabla.'"><tr>';
    $percent = $max > 0 ? round(($valuesna / $max) * 33,0) + 17 : 0;
    $classinterno = '';
    if($valuesna == 0) {
        $valuesna = '-';
        $classinterno = 'cero';
    }
    $tablahtml .= "<td><div class=\"circulo\"><div class=\"circulo-interno nivel0 $classinterno\" style=\"width:".$percent."px; height:".$percent."px;\"><div class=\"numero\">$valuesna</div></div></div></td>";
    $nivel = 1;
    foreach($values as $idx => $val) {
        $percent = $max > 0 ? round(($val / $max) * 16,0) + 9 : 0;
        $indexgradient = 1 + (10/$respuesta->length) * ($nivel - 1);
        $fill = "#" . $gradient[$indexgradient];
        $classinterno = '';
        if($val == 0) {
            $val = '-';
            $classinterno = 'cero';
            $fill = '#fff';
        }
        // $tablahtml .= "<td><div class=\"circulo\"><div class=\"circulo-interno nivel$nivel-$respuesta->length $classinterno\" style=\"width:".$percent."px; height:".$percent."px;\"><div class=\"numero\">$val</div></div></div></td>";
        $tablahtml .= "<td><svg width='50' height='50'><circle cx='25' cy='25' r='$percent' stroke='none' fill='$fill' />
<text font-size='12'
      fill='black'
      font-family='Verdana'
      text-anchor='middle'
      alignment-baseline='baseline'
      x='25'
      y='30'>$val</text></svg></td>";
        $nivel++;
    }
    $tablahtml .= '</tr></table>';
    
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
    $charthtml = '<table width="100%"><tr><td class="titulografico hyphenate">'.$titulografico.'</td><td>'.$tablahtml.'</td></tr><tr class="trgrafico"><td class="tdgrafico">'. '</td><td>' .  $resumenhtml. '</td></tr></table>'; ### Se proyecta Chart
    $charthtml = html_writer::div($charthtml,'encuesta');
    return $charthtml;
}