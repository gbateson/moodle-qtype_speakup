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
        $plugin = 'qtype_speakup';

        $name = 'timelimitinseconds';
        $label = get_string($name, $plugin);
        $mform->addElement('duration', $name, $label);
        $mform->addHelpButton($name, $name, $plugin);
        $mform->setDefault($name, 60);
        //$mform->addRule($name, null, 'required', null, 'client');

        $name = 'mediatype';
        $label = get_string($name, $plugin);
        $options = [
            SPEAKUP_MEDIA_AUDIO => get_string('mediatype_audio', $plugin),
            SPEAKUP_MEDIA_VIDEO => get_string('mediatype_video', $plugin),
        ];
        $mform->addElement('select', $name, $label, $options);
        $mform->addHelpButton($name, $name, $plugin);
        $mform->setDefault($name, SPEAKUP_MEDIA_AUDIO);

        $name = 'audioprompt';
        $label = get_string($name, $plugin);
        $options = [
            'subdirs' => 0,
            'maxfiles' => 1,
            'accepted_types' => ['audio'],
        ];
        $mform->addElement('filemanager', $name, $label, null, $options);
        $mform->addHelpButton($name, $name, $plugin);

        $name = 'gradingmethod';
        $label = get_string($name, $plugin);
        $options = [
            SPEAKUP_GRADING_EXACT  => get_string('gradingmethod_exact', $plugin),
            SPEAKUP_GRADING_AI     => get_string('gradingmethod_ai', $plugin),
            SPEAKUP_GRADING_MANUAL => get_string('gradingmethod_manual', $plugin),
        ];
        $mform->addElement('select', $name, $label, $options);
        $mform->addHelpButton($name, $name, $plugin);
        $mform->setDefault($name, SPEAKUP_GRADING_EXACT);

        $name = 'feedbackmode';
        $label = get_string($name, $plugin);
        $options = [
            SPEAKUP_FEEDBACK_FORMATIVE => get_string('feedbackmode_formative', $plugin),
            SPEAKUP_FEEDBACK_SUMMATIVE => get_string('feedbackmode_summative', $plugin),
        ];
        $mform->addElement('select', $name, $label, $options);
        $mform->addHelpButton($name, $name, $plugin);

        // Pattern-matching fields, shown only when gradingmethod = exact.
        $name = 'expectedanswers';
        $label = get_string($name, $plugin);
        $options = ['rows' => 6];
        $mform->addElement('textarea', $name, $label, $options);
        $mform->addHelpButton($name, $name, $plugin);
        $mform->hideIf($name, 'gradingmethod', 'neq', SPEAKUP_GRADING_EXACT);

        // AI rubric field, shown only when gradingmethod = ai.
        $name = 'airubric';
        $label = get_string($name, $plugin);
        $options = ['rows' => 6];
        $mform->addElement('textarea', $name, $label, $options);
        $mform->addHelpButton($name, $name, $plugin);
        $mform->hideIf($name, 'gradingmethod', 'neq', SPEAKUP_GRADING_AI);
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
