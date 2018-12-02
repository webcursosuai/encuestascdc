<?php

function local_encuestascdc_extend_settings_navigation($settingsnav, $context) {
    global $CFG, $PAGE;
    
    // Only add this settings item on non-site course pages.
    if (!$PAGE->course or $PAGE->course->id == 1) {
        return;
    }
    
    // Only let users with the appropriate capability see this settings item.
    if (!has_capability('local/encuestascdc:view', context_course::instance($PAGE->course->id))) {
        return;
    }
    
    if ($settingnode = $settingsnav->find('courseadmin', navigation_node::TYPE_COURSE)) {
        $strfoo = get_string('questionnairereport', 'local_encuestascdc');
        $url = new moodle_url('/local/encuestascdc/index.php', array('id' => $PAGE->course->id));
        $foonode = navigation_node::create(
            $strfoo,
            $url,
            navigation_node::NODETYPE_LEAF,
            'encuestascdc',
            'encuestascdc',
            new pix_icon('i/report', $strfoo)
            );
        if ($PAGE->url->compare($url, URL_MATCH_BASE)) {
            $foonode->make_active();
        }
        $settingnode->add_node($foonode);
    }
}