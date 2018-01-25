<?php
/**
 * Author: Ian McNamara <ian.mcnamara@wisc.edu>
 *         Teaching and Research Application Development
 * Copyright 2018 Board of Regents of the University of Wisconsin System
 */

function getWpLoadPath() {
    $path=$_SERVER['SCRIPT_FILENAME'];

    for ($i=0; $i<4; $i++)
        $path=dirname($path);

    return $path."/wp-load.php";
}

require_once getWpLoadPath();

LearningLockerInterface::get_h5p_statements(
    3,
    array(4),
    new DateTime("2017-11-01"), new DateTime("2018-01-23"));