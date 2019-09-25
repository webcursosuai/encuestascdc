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
require_once ('forms/password_form.php');
require_once ('../../mod/questionnaire/lib.php');
require_once ('../../mod/questionnaire/locallib.php');

// Contexto página principal
$frontpagecontext = context_course::instance(SITEID);
// Editar la página principal solo lo pueden hacer gestores y administradores (esto permite filtrar a gestores)
require_capability('moodle/course:update', $frontpagecontext);
// Contexto de sistema
$context = context_system::instance ();

$pid = optional_param ( 'pid', 0, PARAM_INT );
$action = optional_param ( 'action', 'view', PARAM_ALPHA );

if($pid > 0 && $action == 'edit') {
	if(!$password = $DB->get_record('encuestascdc_passwords', array('id'=>$pid))) {
		print_error('Contraseña de encuesta inválida');
	}
}

// Page navigation and URL settings.
$PAGE->set_url ( $CFG->wwwroot . '/local/encuestascdc/passwords.php' );
$PAGE->set_context ( $context );
$PAGE->set_pagelayout ( 'admin' );
$PAGE->set_title ('Contraseñas para encuestas');
// Require jquery for modal.
$PAGE->requires->jquery ();
$PAGE->requires->jquery_plugin ( 'ui' );
$PAGE->requires->jquery_plugin ( 'ui-css' );

// The page header and heading
echo $OUTPUT->header ();
echo $OUTPUT->heading ('Contraseñas de encuestas');

if($action === 'view') {
	$passwords = $DB->get_records_sql("
		SELECT ep.*, 
			u.firstname, 
			u.lastname,
			cc.name AS categoryname
		FROM {encuestascdc_passwords} ep 
		INNER JOIN {user} u ON (u.id = ep.userid) 
		LEFT JOIN {course_categories} cc ON (cc.id = ep.categoryid) 
		ORDER BY ep.timecreated DESC");
	
	// Creating list.
	$table = new html_table();
	$table->head = array(
		'Categoría',
		'Creado por',
		'Fecha',
		'Duración',
		'Status',
		'Acciones'
	);
	
	$totalpasswords = 0;
	foreach($passwords as $passw) {
		$categoryurl = new moodle_url('/course/index.php', array('categoryid'=>$passw->categoryid));
		$editurl = new moodle_url('/local/encuestascdc/passwords.php', array('pid'=>$passw->id, 'action'=>'edit'));
		$deleteurl = new moodle_url('/local/encuestascdc/passwords.php', array('pid'=>$passw->id, 'action'=>'delete'));
		$personurl = new moodle_url('/user/profile.php', array('id'=>$passw->userid));
		$categoryname = $passw->categoryname;
		if($passw->categoryid == 0) {
			$categoryname = 'Todas';
		}
		$table->data [] = array(
			$OUTPUT->action_link($categoryurl, $categoryname),
			$OUTPUT->action_link($personurl, $passw->firstname . " " . $passw->lastname),
			date("d F Y h:i", $passw->timecreated),
			$passw->duration . " minutos",
			$passw->status,
			$OUTPUT->action_icon($editurl, new pix_icon('i/edit','Editar')) .
			$OUTPUT->action_icon($deleteurl, new pix_icon('i/delete','Editar'))
		);
		$totalpasswords++;
	}
	
	if($totalpasswords == 0) {
		echo $OUTPUT->notification('No hay constraseñas de encuestas aún');
	} else {
		echo html_writer::table($table);
	}
}

$mform = new local_encuestascdc_password_form (NULL, array('id'=>$pid, 'action'=>$action));

if ($mform->get_data ()) {
    $newpassword = $mform->get_data();
    if($newpassword->action === 'edit') {
	    $DB->update_record('encuestascdc_passwords', $newpassword);
    } else {
	    $newpassword->userid = $USER->id;
	    $newpassword->status = 0;
	    $newpassword->timecreated = time();
	    $newpassword->timemodified = time();
	    $newpassword->id = $DB->insert_record('encuestascdc_passwords', $newpassword);
    }
    echo $OUTPUT->notification('Transacción exitosa', 'notifysuccess');
} elseif($action === 'edit') {
	echo $OUTPUT->heading ('Editar contraseña', 4);
	$mform->set_data($password);
	$mform->display ();
} else {
	echo $OUTPUT->heading ('Agregar nueva contraseña', 4);
	$mform->display ();
}
echo $OUTPUT->footer ();
