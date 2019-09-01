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
 * @copyright 2018 Universidad Adolfo Ibáñez
 * @author Jorge Villalón <jorge.villalon@uai.cl>
 */
 /**
 * Obtiene los gráficos de preguntas tipo rank de la encuesta
 * 
 * @param int $questionnaireid id de la encuesta
 * @param int $moduleid id del módulo questionnaire
 * @param int $typerankid id del tipo de pregunta rank
 * @param int $typetextid id del tipo de pregunta texto
 * @return string[]|string[][]
 */
function uol_grafico_encuesta_rank(int $questionnaireid, int $moduleid, int $typerankid, int $typetextid, String $profesor1, String $profesor2, String $coordinadora, int $groupid = 0) {
    global $DB, $OUTPUT, $CFG;
    $totalalumnos = 0;
    $rankfield = intval($CFG->version) < 2016120509 ? '' : 'value';
    $surveyfield = intval($CFG->version) < 2019052000 ? 'survey_id' : 'surveyid';
    $responseonclause = intval($CFG->version) < 2019052000 ? 'r.survey_id = s.id' : 'r.questionnaireid = qu.id';
    $groupsql = $groupid > 0 ? "LEFT JOIN {groups_members} gm ON (gm.groupid = :groupid AND gm.userid = r.userid)
WHERE gm.groupid is not null" : ""; 
    $groupsql2 = $groupid > 0 ? "LEFT JOIN {groups_members} gm ON (gm.groupid = :groupid2 AND gm.userid = r.userid)
WHERE gm.groupid is not null" : ""; 

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
	INNER JOIN {course_modules} cm on (cm.course = qu.course AND cm.module = :moduleid AND cm.instance = qu.id)
	INNER JOIN {questionnaire_survey} s ON (s.id = qu.sid)
	INNER JOIN {questionnaire_question} q ON (q.$surveyfield = s.id and q.type_id = :typerankid and q.deleted = 'n')
	INNER JOIN {questionnaire_quest_choice} qc ON (qc.question_id = q.id and q.type_id = :typerankid2)
    INNER JOIN {questionnaire_question_type} qt ON (q.type_id = qt.typeid)
	LEFT JOIN {questionnaire_response} r ON ($responseonclause)
	LEFT JOIN {questionnaire_response_rank} rr ON (rr.choice_id = qc.id and rr.question_id = q.id and rr.response_id = r.id)
    $groupsql
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
	INNER JOIN {course_modules} cm on (cm.course = qu.course AND cm.module = :moduleid2 AND cm.instance = qu.id)
	INNER JOIN {questionnaire_survey} s ON (s.id = qu.sid)
	INNER JOIN {questionnaire_question} q ON (q.$surveyfield = s.id and q.type_id = :typetextid and q.deleted = 'n')
    INNER JOIN {questionnaire_question_type} qt ON (q.type_id = qt.typeid)
    LEFT JOIN {questionnaire_response} r ON ($responseonclause)
    LEFT JOIN {questionnaire_response_text} rt ON (rt.response_id = r.id AND rt.question_id = q.id)
    $groupsql2
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
    if($groupid > 0) {
        $params['groupid'] = $groupid;
        $params['groupid2'] = $groupid;
    }
    // Todas las respuestas
    $respuestas = $DB->get_recordset_sql($sql, $params);
    // Arreglo con los nombres de secciones
    $secciones = Array();
    // El html que se devuelve en el primer parámetro
    $fullhtml = '';
    // Html de preguntas abiertas
    $openhtml = '';
    // Variable con la última sección utilizada, para identificar cambio de sección
    $ultimaseccion = '';
    // Variable para contar preguntas cerradas dentro de una sección
    $preguntascerradasultimaseccion = 0;

    if(!$respuestas->valid()) {
        return array("", array(), 0);
    }
    
    $profesores = 0;
    $nuevaseccion = false;
    $estadisticas_seccion = null;
    // Revisamos cada conjunto de respuestas por pregunta
    foreach($respuestas as $respuesta)
    {
    	// Si hay cambio de sección
        if($ultimaseccion !== $respuesta->seccion) {
            $nuevaseccion = true;
            // Clase para la escala de acuerdo al número de secciones
            $classescala = "escala-" . count($secciones);
        	// Se cierra div anterior (de sección)
            if($ultimaseccion !== '') {
                if($estadisticas_seccion == null) {
                    var_dump("Houston! We have a problem.");
                }
                $htmlstats = uol_tabla_estadisticas($estadisticas_seccion);
                if($preguntascerradasultimaseccion > 0) {
                    $fullhtml .= "</div><div class='promedios $classescala'>$htmlstats</div>";
                } else {
                    $fullhtml .= "</div>";
                }
                if($openhtml === '') {
                    $fullhtml .= "</div></div>";
                } else {
                    $fullhtml .= "</div><div class='´preguntas-abiertas'>$openhtml</div></div>";
                    $openhtml = '';
                }
            }
            $preguntascerradasultimaseccion=0;

            // Actualizamos última sección
            $ultimaseccion = $respuesta->seccion;
            // Agregamos a la lista de secciones
            $secciones[] = $ultimaseccion;
            // Estadísticas de la sección, al llamar con NULL se inicializa en 0.
            $estadisticas_seccion = uol_actualiza_estadisticas(null);
            
            // Se agregar un break vacío
            //$fullhtml .= "<div class='break-after'></div>";
            $fullhtml .= "<div class='multicol cols-2 seccioncompleta'>";
            
            // Partimos con un break antes del título y el título
            if($respuesta->type === "Rate (scale 1..5)") {
                if($respuesta->length == 4) {
                    $fullhtml .= "
                    <div class='encuesta break-before seccion'>
                        <div class='row'>
                            <div class='h4 col-md-6'>$respuesta->seccion</div>
                            <div class='escala $classescala col-md-6'>
                                <div class='tituloescala'>En una escala de 1 a 4, donde 1 es Bajo y 4 es Alto, indique su nivel de conformidad con las afirmaciones</div>
                            </div>
                        </div>
                    </div>";
                } elseif($respuesta->length == 7) {
                    $fullhtml .= "
                    <div class='encuesta break-before seccion'>
                        <div class='row'>
                            <div class='h4 col-md-6'>$respuesta->seccion</div>
                            <div class='escala $classescala col-md-6'>
                                <div class='tituloescala'>En una escala de 1 a 7, donde 1 es Muy Malo y 7 es Excelente, con qué nota evaluaría:</div>
                            </div>
                        </div>
                    </div>";
                } else {
                    $fullhtml .= '<div>Formato no definido</div>';
                }
            } else {
                $fullhtml .= "<div class='tituloseccion break-before seccion'>". $respuesta->seccion . "<br/>&nbsp;</div>";
            }
            if(stripos($respuesta->seccion, "PROFESOR") !== false) {
                $fullhtml .= "<h2 class='nombreprofesor'>$profesor1</h2>";
                $profesores++;
            } elseif(stripos($respuesta->seccion, "COORDINACI") !== false) {
                $fullhtml .= "<h2 class='nombreprofesor'>$coordinadora</h2>";
            }
            $fullhtml .= "<div class='resultados'><div class='preguntas'>";
        } elseif(stripos($respuesta->seccion, "PROFESOR") !== false && $profesores > 0 && substr($respuesta->opcion, 0, 2) === "a)") {
            $htmlstats = uol_tabla_estadisticas($estadisticas_seccion);
            if($preguntascerradasultimaseccion > 0) {
                $fullhtml .= "</div><div class='promedios $classescala'>$htmlstats</div>";
            } else {
                $fullhtml .= "</div>";
            }
            if($openhtml === '') {
                $fullhtml .= "</div></div>";
            } else {
                $fullhtml .= "</div><div class='´preguntas-abiertas'>$openhtml</div></div>";
                $openhtml = '';
            }
            $fullhtml .= "</div><div class='multicol cols-2 seccioncompleta'>";
            if($profesores == 1) {
                $fullhtml .= "<h2 class='nombreprofesor'>$profesor2</h2>";
                $profesores++;
            } else {
                $fullhtml .= "<h2 class='nombreprofesor'>$profesor3</h2>";
            }
            $fullhtml .= "<div class='resultados'><div class='preguntas'>";
        }
        if($respuesta->type === "Rate (scale 1..5)") {
            list($html, $estadisticas_nuevas) = uol_tabla_respuesta_rank($respuesta, $nuevaseccion);
            $estadisticas_seccion = uol_actualiza_estadisticas($estadisticas_nuevas, $estadisticas_seccion);
            $fullhtml .= $html;
            $preguntascerradasultimaseccion++;
        } elseif($respuesta->type === "Text Box") {
            $openhtml .=  uol_tabla_respuesta_text($respuesta, $profesor1, $profesor2, $coordinadora);
        }
        $partes = explode("#", $respuesta->answers);
        if(count($partes) > $totalalumnos) {
            $totalalumnos = count($partes);
        }
        $nuevaseccion = false;
    }
    $htmlstats = uol_tabla_estadisticas($estadisticas_seccion);
    if($preguntascerradasultimaseccion > 0) {
        $fullhtml .= "</div><div class='promedios $classescala'>$htmlstats</div>";
    } else {
        $fullhtml .= "</div>";
    }

    // Se retorna el html de gráficos y a lista de secciones
    return array($fullhtml ."</div><div class='´preguntas-abiertas'>$openhtml</div></div>", $secciones, $totalalumnos);
}

function uol_actualiza_estadisticas($estadisticas_nuevas, $estadisticas = NULL) {
    // Estadísticas de la sección
    $estadisticas_seccion = new stdClass();
    $estadisticas_seccion->min = 0;
    $estadisticas_seccion->max = 0;
    $estadisticas_seccion->numrespuestas = 0;
    $estadisticas_seccion->promedio = 0;

    if($estadisticas_nuevas == NULL) {
        return $estadisticas_seccion;
    }
    
    $estadisticas_seccion->min = $estadisticas->min == 0 ? $estadisticas_nuevas->promedio : min($estadisticas->min,$estadisticas_nuevas->min);
    $estadisticas_seccion->max = max($estadisticas->max,$estadisticas_nuevas->max);
    $estadisticas_seccion->numrespuestas = $estadisticas->numrespuestas + $estadisticas_nuevas->numrespuestas;
    $estadisticas_seccion->promedio = 
        ($estadisticas->promedio * $estadisticas->numrespuestas + 
        $estadisticas_nuevas->promedio * $estadisticas_nuevas->numrespuestas)
        / ($estadisticas->numrespuestas + $estadisticas_nuevas->numrespuestas);
        
    return $estadisticas_seccion;
}
function uol_tabla_estadisticas($estadisticas) {
    $promedio = round($estadisticas->promedio, 1);
    $html = "
    <div class='estadisticas-seccion'>
        <div class='maximo'><ul>
        <li>Máximo: $estadisticas->max</li>
        <li>Mínimo: $estadisticas->min</li>
        <li>Promedio: $promedio</li></ul>        </div>
    </div>
    ";
    return $html;
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
    $answers = "- " . implode(" (sic) \n- ", $answers) . " (SIC)";
    $answers = strtoupper(str_replace(array('á','é','í','ó','ú','ñ'), array('Á','É','Í','Ó','Ú','Ñ'), $answers));
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
            <td><textarea class='comentarios' name='text$respuesta->id' rows=$numanswers disabled>$answers</textarea></td>
        </tr>
    </table>
</div>";
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
    $min = 0;
    foreach($values as $idx => $val) {
        if($val > $max) {
            $max = $val;
        }
        if($val < $min) {
            $min = $val;
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
     $tablahtml = '&nbsp;';
    $titulografico = trim(str_ireplace(array('a)','b)','c)','d)','e)', 'f)', 'g)', 'h)', 'i)', 'j)'), '', $respuesta->opcion));
    $charthtml = '<table width="100%"><tr><td class="titulografico hyphenate">'.$titulografico.'</td></tr>'.
    '<tr class="trgrafico"><td class="tdgrafico">'. '</td><td>' .  $resumenhtml. '</td></tr></table>'; ### Se proyecta Chart
    $charthtml = html_writer::div($charthtml,'encuesta');
    $stats = new stdClass();
    $stats->min = $promedio;
    $stats->max = $promedio;
    $stats->numrespuestas = $total;
    $stats->promedio = $promedio;
    return array($charthtml, $stats);
}

function local_encuestascdc_util_mes_en_a_es($fecha, $corta = false) {
    if(!$corta) {
    $search = array('January','February','March','April','May','June','July','August','September','October','November','December');
    $replace = array('Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre');
    } else {
    $search = array('Jan','Apr','Aug','Dec');
    $replace = array('Ene','Abr','Ago','Dic');
    }
    $fecha=str_replace($search, $replace, $fecha);
    return $fecha;
}