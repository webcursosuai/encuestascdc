<?php
/**
 *
* @package local
* @subpackage encuestascdc
* @copyright 2017-onwards Universidad Adolfo Ibanez
* @author Jorge Villalon <jorge.villalon@uai.cl>
*/
require_once (dirname (dirname ( dirname ( __FILE__ ) ) ). '/config.php');
require_once ($CFG->libdir . '/adminlib.php');
require_once ($CFG->libdir . '/moodlelib.php');
require_once ('forms/login_form.php');
require_once ('../../mod/questionnaire/lib.php');
require_once ('../../mod/questionnaire/locallib.php');

$context = context_system::instance ();

$qid = optional_param ( 'qid', 0, PARAM_INT );
$uid = optional_param ( 'uid', 0, PARAM_INT );
$pwd = optional_param ( 'pwd', '', PARAM_RAW );

if($qid > 0 && $uid > 0) {
	if(!$cm = $DB->get_record('course_modules', array('id'=>$qid))) {
		print_error('Módulo de curso inválido');
	}
	if(!$user = $DB->get_record('user', array('id'=>$uid)))	{
		print_error('Estudiante inválido');
	}
	if(!$course = $DB->get_record('course', array('id'=>$cm->course)))	{
		print_error('Curso inválido');
	}
	if(!$coursecat = $DB->get_record('course_categories', array('id'=>$course->category))) {
		print_error('Categoría de cursos inválida');
	}
	$context = context_module::instance($cm->id);
	if(!has_capability('mod/questionnaire:submit', $context, $user)) {
		print_error('Estudiante no tiene permisos para contestar encuesta');
	}
	$result = authenticate_user_login ($user->username, $pwd);
	if(!$result) {
		$categoryids = substr(implode(',',explode('/',$coursecat->path)),1);
		$now = time();
		$passwords = $DB->get_records_sql("SELECT * FROM {encuestascdc_passwords} ep WHERE categoryid IN (:categoryids) AND timecreated < :now AND timecreated + (duration * 60) > :now2 AND status = 0 AND password = :password",
			array('categoryids'=>$categoryids, 'now'=>$now, 'now2'=>$now, 'password'=>$pwd));
		if(!$passwords) {
			print_error('Acceso no autorizado');
		}
	}
	complete_user_login($user);
	$url = new moodle_url('/mod/questionnaire/complete.php', array('id'=>$qid));
	redirect($url);
	die();
}

$url = new moodle_url('/local/encuestascdc/login.php');

// Page navigation and URL settings.
$PAGE->set_url ($url);
$PAGE->set_context ( $context );
$PAGE->set_pagelayout ( 'print' );
$PAGE->set_title ( get_string ( 'login', 'local_uaio' ) );
// Require jquery for modal.
$PAGE->requires->jquery ();
$PAGE->requires->jquery_plugin ( 'ui' );
$PAGE->requires->jquery_plugin ( 'ui-css' );

// The page header and heading
echo $OUTPUT->header ();
echo html_writer::img($CFG->wwwroot.'/local/encuestascdc/img/logo-uai-corporate-no-transparente2.png', 'UAI Corporate', array('class'=>'img-fluid'));
echo $OUTPUT->heading ('Resumen encuestas');

echo "
<style>
.questionnaire {
    border: 1px solid #999;
    border-radius: 5px;
    margin-bottom: 2em;
    padding: 1em;
}
.status, .status .singlebutton, .status button, input {
    width: 100%;
    text-align: center;
	border-radius: 3px;
}
.status button {
	background-color: #ff4c00;
	color: #fff;
	font-weight: bold;
	font-size: 1.5em;
	border: 0px;
}
.status i {
	font-size: 1.5em;
}
img {
	margin-bottom: 5px;
}
</style>";

$mform = new local_encuestascdc_login_form (NULL);

if ($mform->get_data ()) {
    $username = $mform->get_data()->username;
    $username = str_replace(".", "", $username);
    
	$usernamesindigito = explode("-", $mform->get_data ()->username)[0];
	$usernamesindigito = intval(str_replace(".", "", $usernamesindigito));
	
	$user = $DB->get_record ( 'user', array (
			'idnumber' => $username 
	),'*',IGNORE_MULTIPLE );

	if(!$user)
	{
	    echo $OUTPUT->notification('Estudiante no encontrado', 'notify-error');
	    echo $OUTPUT->single_button($url, 'Volver');
	} else {
		$html = array();
		$courses = enrol_get_users_courses($user->id);
		if(!$courses)
		{
		    echo $OUTPUT->notification('Estudiante no tiene cursos', 'notify-error');
		    echo $OUTPUT->single_button($url, 'Volver');
		} else {
			$questionnaires = get_all_instances_in_courses('questionnaire', $courses, $user->id);
			if(!$questionnaires)
			{
			    echo $OUTPUT->notification('Estudiante no tiene encuestas en sus cursos', 'notify-error');
			    echo $OUTPUT->single_button($url, 'Volver');
			} else {
				$teacherrole = $DB->get_record_sql('SELECT * FROM {role} WHERE archetype = :archetype ORDER BY id ASC LIMIT 1', array('archetype'=>'editingteacher'));
				foreach($questionnaires as $questionnaire) {
				    $url = new moodle_url("/local/encuestascdc/login.php", array("qid"=>$questionnaire->coursemodule, "uid"=>$user->id, "pwd"=>$pwd));
				    $htmlsummary = '';
				    $course = $courses[intval($questionnaire->course)];
				    $coursecontext = context_course::instance($course->id);
				    $teachers = get_users_from_role_on_context($teacherrole, $coursecontext);
				    $htmlsummary .= html_writer::start_tag('h5');
				    $htmlsummary .= $course->fullname;
				    $htmlsummary .= html_writer::end_tag('h5');
					$htmlsummary .= html_writer::start_tag('p');
				    foreach($teachers as $teacher) {
				    	$t = $DB->get_record('user', array('id'=>$teacher->userid));
					    $htmlsummary .= $t->firstname . " " . $t->lastname . "<br/>";
				    }
				    $htmlsummary .= $questionnaire->name;
				    $htmlsummary .= html_writer::end_tag('p');
				    $html = $htmlsummary;
			        if ($responses = questionnaire_get_user_responses($questionnaire->id, $user->id, false)) {
			            foreach ($responses as $response) {
			                if ($response->complete == 'y') {
			                    $html .= $OUTPUT->box('<i class="fa fa-check" aria-hidden="true"> Contestada</i>', 'alert-success status');
			                    break;
			                } else {
			                    $html .=  $OUTPUT->box($OUTPUT->single_button($url, 'Continuar'), 'status');
			                }
			            }
			        } else {
			            $html .=  $OUTPUT->box($OUTPUT->single_button($url, 'Contestar'), 'status');
			        }
				    echo $OUTPUT->box($html, 'questionnaire');
				}
			}
		}
	}
} else {
	$mform->display ();
}
echo $OUTPUT->footer ();
