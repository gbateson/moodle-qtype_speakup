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
 * SpeakUp question editing form.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class qtype_speakup_edit_form extends question_edit_form {

    #[\Override]
    protected function definition_inner($mform) {
        $mform->addElement('duration', 'timelimitinseconds', get_string('timelimit', 'qtype_speakup'));
        $mform->addHelpButton('timelimitinseconds', 'timelimit', 'qtype_speakup');
        $mform->setDefault('timelimitinseconds', 60);
        $mform->addRule('timelimitinseconds', null, 'required', null, 'client');

        $mform->addElement('filemanager', 'audioprompt', get_string('audioprompt', 'qtype_speakup'), null, [
            'subdirs' => 0,
            'maxfiles' => 1,
            'accepted_types' => ['audio'],
        ]);
        $mform->addHelpButton('audioprompt', 'audioprompt', 'qtype_speakup');

        $mform->addElement('select', 'gradingmethod', get_string('gradingmethod', 'qtype_speakup'), [
            SPEAKUP_GRADING_EXACT  => get_string('gradingmethod_exact', 'qtype_speakup'),
            SPEAKUP_GRADING_AI     => get_string('gradingmethod_ai', 'qtype_speakup'),
            SPEAKUP_GRADING_MANUAL => get_string('gradingmethod_manual', 'qtype_speakup'),
        ]);
        $mform->addHelpButton('gradingmethod', 'gradingmethod', 'qtype_speakup');

        $mform->addElement('select', 'feedbackmode', get_string('feedbackmode', 'qtype_speakup'), [
            SPEAKUP_FEEDBACK_FORMATIVE => get_string('feedbackmode_formative', 'qtype_speakup'),
            SPEAKUP_FEEDBACK_SUMMATIVE => get_string('feedbackmode_summative', 'qtype_speakup'),
        ]);
        $mform->addHelpButton('feedbackmode', 'feedbackmode', 'qtype_speakup');

        // Pattern-matching fields, shown only when gradingmethod = exact.
        $mform->addElement('textarea', 'expectedanswers', get_string('expectedanswers', 'qtype_speakup'),
                ['rows' => 6, 'class' => 'form-control']);
        $mform->addHelpButton('expectedanswers', 'expectedanswers', 'qtype_speakup');
        $mform->hideIf('expectedanswers', 'gradingmethod', 'neq', SPEAKUP_GRADING_EXACT);
        $mform->hideIf('expectedanswers[helpicon]', 'gradingmethod', 'neq', SPEAKUP_GRADING_EXACT);

        // AI rubric field, shown only when gradingmethod = ai.
        $mform->addElement('textarea', 'airubric', get_string('airubric', 'qtype_speakup'),
                ['rows' => 6, 'class' => 'form-control']);
        $mform->addHelpButton('airubric', 'airubric', 'qtype_speakup');
        $mform->hideIf('airubric', 'gradingmethod', 'neq', SPEAKUP_GRADING_AI);
        $mform->hideIf('airubric[helpicon]', 'gradingmethod', 'neq', SPEAKUP_GRADING_AI);
    }

    #[\Override]
    public function data_preprocessing($question) {
        $question = parent::data_preprocessing($question);

        if (!empty($question->options->expectedanswers)) {
            $lines = array_map(function ($row) {
                return $row->answertext;
            }, $question->options->expectedanswers);
            $question->expectedanswers = implode("\n", $lines);
        }

        return $question;
    }

    #[\Override]
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (empty($data['timelimitinseconds']) || $data['timelimitinseconds'] <= 0) {
            $errors['timelimitinseconds'] = get_string('errornotimelimit', 'qtype_speakup');
        }

        if ($data['gradingmethod'] === SPEAKUP_GRADING_EXACT && trim($data['expectedanswers']) !== '') {
            $lines = preg_split('/\r\n|\r|\n/', $data['expectedanswers']);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                if (preg_match('#^/.*/[a-zA-Z]*$#', $line)) {
                    if (@preg_match($line, '') === false) {
                        $errors['expectedanswers'] = get_string('err_invalidregex', 'qtype_speakup', s($line));
                        break;
                    }
                }
            }
        }

        return $errors;
    }

    #[\Override]
    public function qtype() {
        return 'speakup';
    }
}
