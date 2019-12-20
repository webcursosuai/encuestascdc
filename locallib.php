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
function encuestascdc_grafico_encuesta_rank(array $questionnaires, $profesor1, $profesor2, $coordinadora, int $totalalumnos, int $groupid = 0) {
    global $DB, $OUTPUT, $CFG;
    
    $respuestasstats = encuestascdc_obtiene_estadisticas($questionnaires, $groupid);
    $respuestas = array();
    foreach($respuestasstats as $k => $v) {
        foreach($v as $k2 => $v2) {
            foreach($v2 as $k3 => $v3) {
                foreach($v3 as $k4 => $v4) {
                    $respuestas[] = $v4['respuesta'];
                }
            }
        }
    }

    // Arreglo con los nombres de secciones
    $secciones = array();
    // El html que se devuelve en el primer parámetro
    $fullhtml = '';
    // Html de preguntas abiertas
    $openhtml = '';
    // Variable con la última sección utilizada, para identificar cambio de sección
    $ultimaseccion = '';
    // Variable para contar preguntas cerradas dentro de una sección
    $preguntascerradasultimaseccion = 0;

    if(count($respuestas) == 0) {
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
function encuestascdc_obtiene_estadisticas(array $questionnaires, int $groupid = 0) {
    global $DB, $OUTPUT, $CFG;
    
    // Validación de instalación del módulo questionnaire
    if(!$module = $DB->get_record('modules', array('name'=>'questionnaire'))) {
        print_error('Módulo questionnaire no está instalado');
    }

    // Validación de tipo de respuesta rank
    if(!$questiontype = $DB->get_record('questionnaire_question_type', array('response_table'=>'response_rank'))) {
    	print_error('Tipo de pregunta rank no instalada');
    }
    
    // Validación de tipo de respuesta texto
    if(!$questiontypetext = $DB->get_record('questionnaire_question_type', array('response_table'=>'response_text', 'type'=>'Text Box'))) {
        print_error('Tipo de pregunta Text Box no instalada');
    }

    $totalalumnos = 0;
    $pluginversion = intval(get_config('mod_questionnaire', 'version'));
    $rankfield = $pluginversion > 2018050109 ? '' : 'value';
    $surveyfield = $pluginversion > 2018050109 ? 'survey_id' : 'surveyid';
    $responseonclause = $pluginversion > 2018050109 ? 'r.survey_id = s.id' : 'r.questionnaireid = qu.id';
    $groupsql = $groupid > 0 ? "LEFT JOIN {groups_members} gm ON (gm.groupid = :groupid AND gm.userid = r.userid)
WHERE gm.groupid is not null" : ""; 
    $groupsql2 = $groupid > 0 ? "LEFT JOIN {groups_members} gm ON (gm.groupid = :groupid2 AND gm.userid = r.userid)
WHERE gm.groupid is not null" : "";

    $questionnairesql = array();
    foreach($questionnaires as $questionnaire) {
        $questionnairesql[] = $questionnaire->id;
    }

    list($insql, $inparams) = $DB->get_in_or_equal($questionnairesql);
    // Query para respuestas
    $sql="
SELECT qu.id,
    c.id courseid,
	c.fullname,
	s.id surveyid, 
	s.title nombre, 
	q.name seccion, 
	q.content pregunta, 
	qc.content opcion, 
	q.length, 
	group_concat(rr.rank$rankfield order by r.userid separator '#') answers,
	group_concat(r.userid order by r.userid separator '#') respondents,
    q.position,
	qt.type
FROM
	{questionnaire} qu
	INNER JOIN {course} c ON (qu.course = c.id AND qu.id $insql)
	INNER JOIN {course_modules} cm on (cm.course = qu.course AND cm.module = ? AND cm.instance = qu.id)
	INNER JOIN {questionnaire_survey} s ON (s.id = qu.sid)
	INNER JOIN {questionnaire_question} q ON (q.$surveyfield = s.id and q.type_id = ? and q.deleted = 'n')
	INNER JOIN {questionnaire_quest_choice} qc ON (qc.question_id = q.id and q.type_id = ?)
    INNER JOIN {questionnaire_question_type} qt ON (q.type_id = qt.typeid)
	LEFT JOIN {questionnaire_response} r ON ($responseonclause)
	LEFT JOIN {questionnaire_response_rank} rr ON (rr.choice_id = qc.id and rr.question_id = q.id and rr.response_id = r.id)
    $groupsql
GROUP BY qu.id,c.id,s.id, q.id, qc.id
UNION ALL
SELECT qu.id,
    c.id courseid,
	c.fullname,
	s.id surveyid, 
	s.title nombre, 
	q.name seccion, 
	q.content pregunta,
    '' opcion,
    '' length,
    group_concat(rt.response order by r.userid separator '#') answers,
    group_concat(r.userid order by r.userid separator '#') answers,
    q.position,
    qt.type
FROM
	{questionnaire} qu
	INNER JOIN {course} c ON (qu.course = c.id AND qu.id $insql)
	INNER JOIN {course_modules} cm on (cm.course = qu.course AND cm.module = ? AND cm.instance = qu.id)
	INNER JOIN {questionnaire_survey} s ON (s.id = qu.sid)
	INNER JOIN {questionnaire_question} q ON (q.$surveyfield = s.id and q.type_id = ? and q.deleted = 'n')
    INNER JOIN {questionnaire_question_type} qt ON (q.type_id = qt.typeid)
    LEFT JOIN {questionnaire_response} r ON ($responseonclause)
    LEFT JOIN {questionnaire_response_text} rt ON (rt.response_id = r.id AND rt.question_id = q.id)
    $groupsql2
GROUP BY qu.id,c.id,s.id, q.id
ORDER BY position";
    
    $params = $inparams;
    $params[] = $module->id;
    $params[] = $questiontype->typeid;
    $params[] = $questiontype->typeid;
    for($i=0;$i<count($inparams);$i++) {
        $params[] = $inparams[$i];
    }
    $params[] = $module->id;
    $params[] = $questiontypetext->typeid;
    
    if($groupid > 0) {
        $params['groupid'] = $groupid;
        $params['groupid2'] = $groupid;
    }

    // Todas las respuestas
    $respuestas = $DB->get_recordset_sql($sql, $params);
    
    if(!$respuestas->valid()) {
        return false;
    }
    
    // Arreglo con los nombres de secciones
    $secciones = array();
    // Variable con la última sección utilizada, para identificar cambio de sección
    $ultimaseccion = '';
    // Variable para contar preguntas cerradas dentro de una sección
    $preguntascerradasultimaseccion = 0;

    $stats = array();
    $profesores = 0;
    $nuevaseccion = false;
    $estadisticas_seccion = null;
    // Revisamos cada conjunto de respuestas por pregunta
    foreach($respuestas as $respuesta)
    {
        if($respuesta->seccion === 'EVALUACIÓN DEL PROFESOR') {
            if(strpos($respuesta->pregunta, 'Profesor 1') > 0) {
                $respuesta->seccion .= '-P1';
            } elseif(strpos($respuesta->pregunta, 'Profesor 2') > 0) {
                $respuesta->seccion .= '-P2';
            } elseif(strpos($respuesta->pregunta, 'Profesor 3') > 0) {
                $respuesta->seccion .= '-P3';
            }
        }
        if(!isset($stats[$respuesta->courseid])) {
            $stats[$respuesta->courseid] = array();
        }
        
        if(!isset($stats[$respuesta->courseid][$respuesta->seccion])) {
            $stats[$respuesta->courseid][$respuesta->seccion] = array();
        }
        
        if(!isset($stats[$respuesta->courseid][$respuesta->seccion][$respuesta->type])) {
            $stats[$respuesta->courseid][$respuesta->seccion][$respuesta->type] = array();
        }
        
        $stat = encuestascdc_respuesta_stats($respuesta);
        
        $stats[$respuesta->courseid][$respuesta->seccion][$respuesta->type][] = array('stats'=>$stat, 'respuesta'=>$respuesta, 'group'=>$groupid);
    }
    // Se retorna el html de gráficos y a lista de secciones
    return $stats;
}

function encuestascdc_obtiene_profesores($stats, $profesor1, $profesor2, $profesor3) {
    $teachers = array();
    foreach($stats as $courseid => $statcourse) {
        foreach($statcourse as $seccion => $statstype) {
            if(substr($seccion, 0, 24) === 'EVALUACIÓN DEL PROFESOR') {
                $numprof = substr($seccion, -3);
                if(($numprof === '-P1' || $numprof === 'SOR') && !in_array($profesor1, $teachers)) {
                    $teachers[] = $profesor1;
                } elseif($numprof === '-P2' && !in_array($profesor2, $teachers)) {
                    $teachers[] = $profesor2;
                } elseif($numprof === '-P3' && !in_array($profesor3, $teachers)) {
                    $teachers[] = $profesor3;
                }
            }
        }
    }
    return $teachers;
}

function encuestascdc_obtiene_estadisticas_por_curso($stats) {
    $coursestats = array();
    $coursecomments = array();
    $group = 0;
    foreach($stats as $courseid => $statcourse) {
        $row = array();
        foreach($statcourse as $seccion => $statstype) {
            foreach($statstype as $type => $statdetail) {
                if($type === 'Rate (scale 1..5)') {
                    $seccionstats = encuestascdc_crea_estadistica();
                    foreach($statdetail as $detail) {
                        $seccionstats = encuestascdc_suma_estadisticas($seccionstats, $detail['stats']);
                        $group = $detail['group'];
                    }
                    $row['CURSO'] = $detail['respuesta']->fullname;
                    $row[$seccion] = $seccionstats->promedio;
                    $row['respondents'] = $seccionstats->respondents;
                } else {
                    if(!isset($coursecomments[$detail['respuesta']->fullname])) {
                        $coursecomments[$detail['respuesta']->fullname] = array();
                    }
                    foreach($statdetail as $detail) {
                        if(!isset($coursecomments[$detail['respuesta']->fullname][$detail['respuesta']->pregunta])) {
                            $coursecomments[$detail['respuesta']->fullname][$detail['respuesta']->pregunta] = array();
                        }
                        $coursecomments[$detail['respuesta']->fullname][$detail['respuesta']->pregunta] = array_merge($coursecomments[$detail['respuesta']->fullname][$detail['respuesta']->pregunta], explode('#',$detail['respuesta']->answers));
                        $group = $detail['group'];
                    }
                }
            }
        }
        $context = context_course::instance($courseid);
        $enrolledusers = get_enrolled_users($context, 'mod/assignment:submit', $group);
        $totalrespondents = count($row['respondents']);
        $totalstudents = count($enrolledusers);
        $ratio = $totalstudents > 0 ? round(($totalrespondents / $totalstudents) * 100, 1) : 0;
        $row['RATIO'] = $ratio;
        $row['ENROLLEDSTUDENTS'] = $totalstudents;
        $row['STUDENTS'] = $totalrespondents;

        $coursestats[] = $row;
    }
    return array($coursestats, $coursecomments);
}

function encuestascdc_obtiene_estadisticas_por_seccion($stats) {
    $seccionstats = array();
    $preguntas = array();
    $comments = array();
    foreach($stats as $courseid => $statcourse) {
        foreach($statcourse as $seccion => $statstype) {
            foreach($statstype as $type => $statdetail) {
                if($type === 'Rate (scale 1..5)') {
                    if(!isset($seccionstats[$seccion])) {
                        $seccionstats[$seccion] = encuestascdc_crea_estadistica();
                    }
                    if(!isset($preguntas[$seccion])) {
                        $preguntas[$seccion] = array();
                    }
                    foreach($statdetail as $detail) {
                        $seccionstats[$seccion] = encuestascdc_suma_estadisticas($seccionstats[$seccion], $detail['stats']);
                        $preguntas[$seccion][] = $detail['respuesta']->opcion;
                    }
                } else {
                    if(!isset($comments[$seccion])) {
                        $comments[$seccion] = array();
                    }
                    foreach($statdetail as $detail) {
                        if(!isset($comments[$seccion][$detail['respuesta']->pregunta])) {
                            $comments[$seccion][$detail['respuesta']->pregunta] = array();
                        }
                        $comments[$seccion][$detail['respuesta']->pregunta] = array_merge($comments[$seccion][$detail['respuesta']->pregunta], explode('#',$detail['respuesta']->answers));
                    }
                }
            }
        }
    }
    return array($seccionstats, $preguntas, $comments);
}

function encuestascdc_crea_estadistica() {
    $estadistica = new stdClass();
    $estadistica->min = 1;
    $estadistica->max = 0;
    $estadistica->promedio = 0;
    $estadistica->total = 0;
    $estadistica->totalna = 0;
    $estadistica->rank = 0;
    $estadistica->respondents = array();
    return $estadistica;
}

function encuestascdc_suma_estadisticas($stat1, $stat2) {
    $estadistica = new stdClass();
    $estadistica->min = min($stat1->min, $stat2->min);
    $estadistica->max = max($stat1->max, $stat2->max);
    $estadistica->promedio = (($stat1->promedio * $stat1->total) + ($stat2->promedio * $stat2->total)) / ($stat1->total + $stat2->total);
    $estadistica->total = $stat1->total + $stat2->total;
    $estadistica->totalna = $stat1->totalna + $stat2->totalna;
    foreach($stat2->respondents as $respondent) {
        if(!in_array($respondent, $stat1->respondents)) {
            $stat1->respondents[] = $respondent;
        }
    }
    $estadistica->rank = max($stat1->rank, $stat2->rank);
    $estadistica->respondents = $stat1->respondents;
    return $estadistica;
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
        <tr>
            <td>SIC: Así fue escrito.</td>
        </tr>
    </table>
</div>";
}
function encuestascdc_dibujar_reporte($statsbysection_questions, $statsbysection_average, $statsbysection_comments, $profesor1, $profesor2, $profesor3, $coordinadora, $reporttype) {
    foreach($statsbysection_questions as $section => $questions) {
        if(!$sectionstats = $statsbysection_average[$section]) {
            echo 'ERROR GRAVE: No hay stats para sección ' . $section;
            continue;
        }
        $sectioncomments = false;
        if(isset($statsbysection_comments[$section])) {
            $sectioncomments = $statsbysection_comments[$section];
        }
        $cleanquestions = array();
        foreach($questions as $q) {
            $question = substr($q, 3);
            $cleanquestions[] = $question;
        }
        $htmlcomments = '';
        if($sectioncomments) {
            $htmlcomments = encuestascdc_dibuja_comentarios($sectioncomments, $profesor1, $profesor2, $profesor3, $coordinadora);
        }
        $sectionstats->promedio = round($sectionstats->promedio, 1);
        if($sectionstats->rank === '4') {
            $scaletext = 'En una escala de 1 a 4, donde 1 es Bajo y 4 es Alto, indique su nivel de conformidad con las afirmaciones';
        } else {
            $scaletext = 'En una escala de 1 a 7, donde 1 es Muy Malo y 7 es Excelente, con qué nota evaluaría:';
        }
        $html = encuestascdc_dibuja_seccion($section, $scaletext, $profesor1, $profesor2, $profesor3, $coordinadora, $cleanquestions, $sectionstats, $htmlcomments, $reporttype);
        echo $html;
    }
    $cleanquestions = array();
    foreach($statsbysection_comments as $section => $comments) {
        if(isset($statsbysection_average[$section])) {
            continue;
        }
        $htmlcomments = encuestascdc_dibuja_comentarios($comments, $profesor1, $profesor2, $profesor3, $coordinadora);
        $scaletext = "";
        $html = encuestascdc_dibuja_seccion($section, $scaletext, $profesor1, $profesor2, $profesor3, $coordinadora, NULL, NULL, $htmlcomments, $reporttype);
        echo $html;
    }
    echo '<div class="endreport"></div>';
}
function encuestascdc_dibuja_comentarios($sectioncomments, $profesor1, $profesor2, $profesor3, $coordinadora) {
    $htmlcomments = '';
    foreach($sectioncomments as $question => $commentsarr) {
        $pregunta = $question;
        if(stripos($question, "Profesor 1") !== false) {
            $pregunta = str_replace("Profesor 1", $profesor1, $pregunta);
        } elseif(stripos($question, "Profesor 2") !== false) {
            $pregunta = str_replace("Profesor 2", $profesor2, $pregunta);
        } elseif(stripos($question, "Profesor 3") !== false) {
            $pregunta = str_replace("Profesor 3", $profesor3, $pregunta);
        } elseif(stripos($question, "Coordinadora") !== false) {
            $pregunta = str_replace("Coordinadora", $coordinadora, $pregunta);
        }
        $numanswers = count($commentsarr);
        $answers = "- " . implode(" (sic) \n- ", $commentsarr) . " (SIC)";
        $answers = strtoupper(str_replace(array('á','é','í','ó','ú','ñ'), array('Á','É','Í','Ó','Ú','Ñ'), $answers));
        $htmlcomments .= "
        <div class='row'>
            <div class='col-md-12'>
                <div class='preguntas'>
                    <ul>
                        <li>$pregunta</li>
                    </ul>
                </div>
            </div>
            <div class='col-md-12'>
                <textarea class='comentarios' rows=$numanswers disabled>$answers</textarea>
            </div>
            <div class='col-md-12'>
                SIC: Así fue escrito.
            </div>
        </div>";
    }
    return $htmlcomments;
}
function encuestascdc_dibuja_seccion($title, $subtitle, $profesor1, $profesor2, $profesor3, $coordinadora, $questions, $stats, $htmlcomments, $reporttype) {
    $htmlteacher = '';
    if(substr($title, 0, 24) === 'EVALUACIÓN DEL PROFESOR') {
        $numprof = substr($title, -3);
        $title = substr($title, 0, 24);
        if($numprof === '-P1' || $numprof === 'SOR') {
            $teacher = $profesor1;
            if($profesor1 == NULL) {
                return;
            }
        } else if($numprof === '-P2' || $numprof === 'SOR') {
            $teacher = $profesor2;
            if($profesor2 == NULL) {
                return;
            }
        } else {
            $teacher = $profesor3;
            if($profesor3 == NULL) {
                return;
            }
        }
        $htmlteacher = "
        <div class='row'>
            <div class='h5 col-md-12'>$teacher</div>
        </div>";

    }
    
    $htmlquestions = '';
    if($questions && $stats) {
        foreach($questions as $q) {
            $htmlquestions .= '<li>' . $q . '</li>';
        }
        $htmlquestions = "
        <div class='row row-questions'>
            <div class='preguntas col-md-9 col-sm-8'>
                <ul>
                    $htmlquestions
                </ul>
            </div>
            <div class='estadisticas-seccion col-md-3 col-sm-4'>
                <ul>
                    <li>Máximo: $stats->max</li>
                    <li>Mínimo: $stats->min</li>
                    <li>Promedio: $stats->promedio</li>
                </ul>
            </div>
        </div>";
    }
    echo "
    <div class='seccioncompleta break-before seccion'>
        <div class='row'>
            <div class='h4 col-md-6'>$title</div>
            <div class='escala col-md-6'>
                <div class='tituloescala'>$subtitle</div>
            </div>
        </div>
        $htmlteacher
        $htmlquestions
        $htmlcomments
    </div>";

}
function encuestascdc_respuesta_stats($respuesta) {
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
    
    $max = 0;
    $min = 0;
    foreach($values as $idx => $val) {
        if($val > 0) {
            $max = $idx;
            $min = $idx;
            break;
        }
    }
    foreach($values as $idx => $val) {
        if($idx > $max && $val > 0) {
            $max = $idx;
        }
        if($idx < $min && $val > 0) {
            $min = $idx;
        }
    }
    $respondents = explode('#', $respuesta->respondents);
    if(intval($respondents[0]) == 0) {
        $respondents = array();
    }
    $output = new stdClass();
    $output->values = $values;
    $output->promedio = $promedio;
    $output->min = $min;
    $output->max = $max;
    $output->rank = $respuesta->length;
    $output->total = $total;
    $output->totalna = $totalna;
    $output->respondents = $respondents;
    
    return $output;
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

     $stats = uol_respuesta_stats($respuesta);
     
     $values = $stats->values;
     $promedio = $stats->promedio;
     $min = $stats->min;
     $max = $stats->max;
     $total = $stats->total;
     $totalna = $stats->totalna;
    
    // Resumen de promedio y número respuestas
    $resumenhtml = '<div class="promedio">' . $promedio . '</div><div class="numrespuestas hyphenate">Nº respuestas: ' . $total . '</div>';
    $htmlpromedio = '<div class="promedio">' . $promedio . '</div>';
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
    $valuesna = '';
    if($totalna == 0) {
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

function encuestascdc_dibuja_portada($questionnaire, $group, $profesor1, $profesor2, $profesor3, $asignatura, $empresa, $tasa, $programa, $destinatario, $coordinadora, $totalestudiantes) {
    global $OUTPUT;
    
    // Se muestra la primera página con información del informe y general
    $portada = html_writer::start_div('primera-pagina');
    $portada .= html_writer::start_div('logos');
    $portada .= html_writer::div("<img width=396 height='auto' src='img/logo-uai-corporate-no-transparente2.png'>", "uai-corporate-logo");
    $portada .= html_writer::end_div();
    
    $portada .= $OUTPUT->heading('Encuesta de Satisfacción de Programas Corporativos', 1, array('class'=>'reporte_titulo'));

    $portada .= html_writer::div('Informe de resultados', 'subtitulo');
    
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
        <td class='portada-valor'>: $profesor2</td>
    </tr>
    ";
    $htmlprofesor3 = $profesor3 === '' ? '' : "<tr>
        <td class='portada-item'>Profesor 3</td>
        <td class='portada-valor'>: $profesor3</td>
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
    if($destinatario === 'program-director' || $destinatario === 'teacher') {
        if($destinatario === 'teacher') {
            $portada .= "
            <tr>    
                <td class='portada-item'>Profesor</td>
                <td class='portada-valor'>: $profesor1</td>
            </tr>";
        } else {
            $portada .= "
                <tr>    
                    <td class='portada-item'>Profesor 1</td>
                    <td class='portada-valor'>: $profesor1</td>
                </tr>
                $htmlprofesor2
                $htmlprofesor3";
        }
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
}