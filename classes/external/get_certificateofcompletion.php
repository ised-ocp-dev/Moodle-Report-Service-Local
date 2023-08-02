<?php

namespace local_reportservice\external;

defined('MOODLE_INTERNAL') || die();

use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use completion_info;
use completion_completion;
use StdClass;


class get_certificateofcompletion extends external_api {

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'id of course', VALUE_REQUIRED),
            'cmid' => new external_value(PARAM_INT, 'course module id of certificate', VALUE_REQUIRED),
        ]);
    }

    /**
     * Get certificate completion
     * @param text $courseid id of course
     * @param text $cmid of certifiate 
     * @return array of course completion for all enrolled users
     */
    public static function execute(int $courseid, int $cmid): array {
        global $DB,$CFG,$USER;

        // Validate parameters.
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid'    => $tourid,
            'cmid'      => $cmid,
        ]);
        $course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
        
        // Retrieve customcert data for the course
        $cm = get_coursemodule_from_id('customcert', $cmid, 0, false, MUST_EXIST);
        $customcert = $DB->get_record('customcert', array('id' => $cm->instance), '*', MUST_EXIST);
        $issues = $DB->get_records('customcert_issues', array('customcertid' => $customcert->id));

        // Get all users who have a custom cert
        $parsedData = array();
        $userids = array();
        foreach($issues as $issue) {
            $userids[] = $issue->userid;
            $issue->awardedon = userdate($issue->timecreated, '%Y-%m-%d, %H:%M');
            $parsedData[$issue->userid] = $issue;
        }
        $useridssql = implode(", ", $userids);

        // Query user table to get more details on those with custom certs
        $rs = $DB->get_recordset_sql("
                SELECT
                    u.id, u.firstname, u.lastname, u.email
                FROM
                    {user} u
                WHERE
                    u.id IN ($useridssql)");

        // Construct the JSON response
        $certsofcompletion = array();
        foreach($rs as $user) {
            $item = new StdClass;
            $item->name = $user->firstname . ' ' . $user->lastname;
            $item->email = $user->email;
            $item->awardedon = $parsedData[$user->id]->awardedon;
            $certsofcompletion[] = $item;
        }
        $rs->close();
        return $certsofcompletion;
    }


    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function execute_returns() {
        return new external_multiple_structure(
            new external_single_structure([
                'name' => new external_value(PARAM_TEXT, 'name of user'),
                'email' => new external_value(PARAM_TEXT, 'email of user'),
                'awardedon' => new external_value(PARAM_TEXT, 'date certificate was awarded to user')
            ])
        );
    }
}