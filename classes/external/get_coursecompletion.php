<?php

namespace local_reportservice\external;

defined('MOODLE_INTERNAL') || die();

require_once("{$CFG->libdir}/completionlib.php");

use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use completion_info;
use completion_completion;
use StdClass;


class get_coursecompletion extends external_api {

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'id of course', VALUE_REQUIRED)
        ]);
    }

    /**
     * Get course completion
     * @param text $courseid id of course 
     * @return array of course completion for all enrolled users
     */
    public static function execute(int $courseid): array {
        global $DB,$CFG,$USER;

        // Validate parameters.
        $params = self::validate_parameters(self::execute_parameters(), ['courseid' => $courseid]);

        $course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
        
        // Retrieve course_module data for all modules in the course
        $modinfo = get_fast_modinfo($course);

        // Get criteria for course
        $completion = new completion_info($course);
        if (!$completion->has_criteria()) {
            throw new invalid_parameter_exception('No course completion criteria set for the specified course');
        }

        // Get criteria and put in correct order
        $criteria = array();

        // Course completions of "Condition: Course grade"
        foreach ($completion->get_criteria(COMPLETION_CRITERIA_TYPE_COURSE) as $criterion) {
            $criteria[] = $criterion;
        }
        // Course completions of "Condition: Activity completion"
        foreach ($completion->get_criteria(COMPLETION_CRITERIA_TYPE_ACTIVITY) as $criterion) {
            $criteria[] = $criterion;
        }
        foreach ($completion->get_criteria() as $criterion) {
            if (!in_array($criterion->criteriatype, array(
                    COMPLETION_CRITERIA_TYPE_COURSE, COMPLETION_CRITERIA_TYPE_ACTIVITY))) {
                $criteria[] = $criterion;
            }
        }
    
        // Get user data
        $progress = array();
        $progress = $completion->get_progress_all(
                '',
                array(),
                0,
                'u.firstname ASC'
        );

        $userid_to_coursecomplete = array();
        $userids = array();
        foreach($progress as $user_progress) {
            $userids[] = $user_progress->id;

            $params = array(
                'userid'    => $user_progress->id,
                'course'    => $course->id
            );
            $ccompletion = new completion_completion($params);

            if ($ccompletion->is_complete()) {
                $userid_to_coursecomplete[$user_progress->id] = userdate($ccompletion->timecompleted, '%Y-%m-%d, %H:%M');
            } else {
                $userid_to_coursecomplete[$user_progress->id] = '';
            }
        }
        $useridssql = implode(", ", $userids);

        // Query user table to get more details
        $rs = $DB->get_recordset_sql("
                SELECT
                    u.id, u.firstname, u.lastname, u.email
                FROM
                    {user} u
                WHERE
                    u.id IN ($useridssql)");

        $all_users_ccompletion = array();
        foreach ($rs as $user) {
            $item = new StdClass;
            $item->name = $user->firstname . ' ' . $user->lastname;
            $item->email = $user->email;
            $item->coursecomplete = $userid_to_coursecomplete[$user->id];
            $all_users_ccompletion[] = $item;
        }
        $rs->close();
        return $all_users_ccompletion;
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
                'coursecomplete' => new external_value(PARAM_TEXT, 'date user completed course')
            ])
        );
    }
}