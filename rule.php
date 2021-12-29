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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Implementaton of the quizaccess_proctorcheck plugin.
 *
 * @package    quizaccess
 * @subpackage proctorcheck
 * @copyright  2021 Farhan Karmali
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quiz/accessrule/accessrulebase.php');


/**
 * A rule implementing the proctorcheck check.
 *
 * @copyright  2021 Farhan Karmali
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quizaccess_proctorcheck extends quiz_access_rule_base {

    public static function make(quiz $quizobj, $timenow, $canignoretimelimits) {
        if (empty($quizobj->get_quiz()->remoteproctoringrequired)) {
            return null;
        }

        return new self($quizobj, $timenow);
    }

    public function description() {
        return get_string('requirepasswordmessage', 'quizaccess_proctorcheck');
    }

    public function is_preflight_check_required($attemptid) {
        return empty($attemptid);
    }

    public function add_preflight_check_form_fields(mod_quiz_preflight_check_form $quizform,
            MoodleQuickForm $mform, $attemptid) {

        $mform->addElement('header', 'proctorcheckheader', get_string('oncampusproctoring', 'quizaccess_proctorcheck'));
        $mform->addElement('static', 'proctorcheckheadermessage', '', get_string('proctorintro', 'quizaccess_proctorcheck'));
        $table = new html_table();
        $curl = new curl();
        $response = $curl->get('http://moodle.test/mod/quiz/accessrule/proctorcheck/mockendpoint.php');
        $response = json_decode($response);
        $table->data[] = [' ', 'Status'];
        $table->data[] = ['Webcam', $response->webcam ? '&#9989;' : '&#10060;'];
        $table->data[] = ['Sound On', $response->sound ? '&#9989;' : '&#10060;'];
        $table->data[] = ['Screensharing On', $response->screensharing ? '&#9989;' : '&#10060;'];
        $mform->addElement('html', html_writer::table($table));
        $mform->addElement('hidden', 'settingscheck');
        $mform->setType('settingscheck', PARAM_BOOL);
    }

    public function validate_preflight_check($data, $files, $errors, $attemptid) {
        $curl = new curl();
        $response = $curl->get('http://moodle.test/mod/quiz/accessrule/proctorcheck/mockendpoint.php');
        $response = json_decode($response);

        if (!$response->webcam) {
            $errors['proctorcheckheadermessage'] = 'Webcam access is required';
        }
        if (!$response->sound) {
            $errors['proctorcheckheadermessage'] = 'Sound access is required';
        }
        if (!$response->screensharing) {
            $errors['proctorcheckheadermessage'] = 'Screensharing access is required';
        }

        return $errors;
    }

    public function current_attempt_finished() {
        global $SESSION;
        // Clear the flag in the session that says that the user has already
        // entered the password for this quiz.
        if (!empty($SESSION->passwordcheckedquizzes[$this->quiz->id])) {
            unset($SESSION->passwordcheckedquizzes[$this->quiz->id]);
        }
    }

    public static function add_settings_form_fields(mod_quiz_mod_form $quizform, MoodleQuickForm $mform) {
        $mform->addElement('select', 'remoteproctoringrequired',
            get_string('remoteproctoringrequired', 'quizaccess_proctorcheck'),
            [
                0 => get_string('notrequired', 'quizaccess_proctorcheck'),
                1 => get_string('proctoringrequiredoption', 'quizaccess_proctorcheck')
            ]);
        $mform->addHelpButton('remoteproctoringrequired', 'remoteproctoringrequired', 'quizaccess_proctorcheck');
    }

    public static function save_settings($quiz) {
        global $DB;
        if (empty($quiz->remoteproctoringrequired)) {
            $DB->delete_records('quizaccess_proctorcheck', ['quizid' => $quiz->id]);
        } else {
            if (!$DB->record_exists('quizaccess_proctorcheck', ['quizid' => $quiz->id])) {
                $record = new stdClass();
                $record->quizid = $quiz->id;
                $record->proctoringrequired = 1;
                $DB->insert_record('quizaccess_proctorcheck', $record);
            }
        }
    }

    public static function delete_settings($quiz) {
        global $DB;
        $DB->delete_records('quizaccess_proctorcheck', ['quizid' => $quiz->id]);
    }

    public static function get_settings_sql($quizid) {
        return [
            'proctoringrequired AS remoteproctoringrequired',
            'LEFT JOIN {quizaccess_proctorcheck} proctoring ON proctoring.quizid = quiz.id', []
        ];
    }
}
