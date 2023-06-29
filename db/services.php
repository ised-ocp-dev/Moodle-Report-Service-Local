<?php

defined('MOODLE_INTERNAL') || die();
$functions = [
    // The name of your web service function
    'local_reportservice_get_coursecompletion' => [
        // The name of the namespaced class that the function is located in.
        'classname' => 'local_reportservice\external\get_coursecompletion',
        // The name of the external function name.
        'methodname'  => 'execute',
        // A brief, human-readable, description of the web service function.
        'description' => 'Get a course\'s completion report',
        'type' => 'read',
        'ajax' => true,
    ],
];

$services = [
    'Report Service' => [
        'functions' => [
            'local_reportservice_get_coursecompletion'
        ],
        'restrictedusers' => 0,
        'enabled' => 1,
        'shortname'=>'local_rs'
    ],
];