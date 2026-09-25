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

        // Set form id for precise CSS targeting.
        // Id will be set to 'speakup_edit_form'.
        $this->set_form_id($mform);

        $name = 'timelimitinseconds';
        $label = get_string($name, $plugin);
        $mform->addElement('duration', $name, $label);
        $mform->addHelpButton($name, $name, $plugin);
        $mform->setDefault($name, 60);
        //$mform->addRule($name, null, 'required', null, 'client');

        $name = 'allowpause';
        $label = get_string($name, $plugin);
        $mform->addElement('advcheckbox', $name, $label);
        $mform->addHelpButton($name, $name, $plugin);
        $mform->setDefault($name, 0);

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

        // Pattern-matching phrases, shown only when gradingmethod = exact.
        // Weighted-phrase model based on qtype_essayautograde's target
        // phrases: each phrase contributes up to its own weight% of the
        // grade if matched (case-insensitive substring match by default,
        // or a full regex if written as /pattern/flags), and matched
        // phrases add up, capped at 100%.
        $name = 'phrasesheader';
        $mform->addElement('header', $name, get_string('expectedanswers', $plugin));
        $mform->addHelpButton($name, 'expectedanswers', $plugin);
        $mform->setExpanded($name, true);
        // If all elements in a section are hidden, using the "d-none" class,
        // we want to hide the section header, but this cannot be done using
        // hideIf() or by adding a CSS class. Instead, a separate CSS rule is
        // added to styles.css for each header that must be hidden when empty.

        $repeatarray = [
            $mform->createElement('text', 'answertext', get_string('answertext', $plugin), ['size' => 40]),
            $mform->createElement('text', 'weight', get_string('weight', $plugin), ['size' => 5]),
            $mform->createElement('advcheckbox', 'casesensitive', get_string('casesensitive', $plugin)),
        ];
        $repeatedoptions = [
            'answertext' => ['type' => PARAM_RAW_TRIMMED],
            'weight' => ['type' => PARAM_FLOAT],
            'casesensitive' => ['type' => PARAM_BOOL],
        ];

        // The initial number of rows must be at least as many as the
        // question already has saved phrases, or existing phrases beyond
        // the third would be silently present in the form's data but not
        // rendered as visible rows - only appearing once "Add more" adds
        // enough new rows to reach them, which looks like duplicated/
        // misplaced data rather than what it actually is (data that was
        // there all along, just not yet displayed).
        $countphrases = 0;
        if (!empty($this->question->options->expectedanswers)) {
            $countphrases = count($this->question->options->expectedanswers);
        }
        $repeatsatstart = max($countphrases, 3);

        $repeatno = $this->repeat_elements($repeatarray, $repeatsatstart, $repeatedoptions,
                'answercount', 'answeradd', 2, get_string('addmorephrases', $plugin), true);
        for ($i = 0; $i < $repeatno; $i++) {
            $mform->hideIf('answertext[' . $i . ']', 'gradingmethod', 'neq', SPEAKUP_GRADING_EXACT);
            $mform->hideIf('weight[' . $i . ']', 'gradingmethod', 'neq', SPEAKUP_GRADING_EXACT);
            $mform->hideIf('casesensitive[' . $i . ']', 'gradingmethod', 'neq', SPEAKUP_GRADING_EXACT);
        }
        // Hide the "Add 2 more phrases" button too, when phrases aren't in use.
        $mform->hideIf('answeradd', 'gradingmethod', 'neq', SPEAKUP_GRADING_EXACT);

        $name = 'sttlanguage';
        $label = get_string($name, $plugin);
        $mform->addElement('text', $name, $label, ['size' => 10]);
        $mform->setType($name, PARAM_ALPHANUMEXT); // Restricts to e.g. en-US, ja-JP style tags.
        $mform->addHelpButton($name, $name, $plugin);
        $mform->setDefault($name, 'en-US');
        $mform->hideIf($name, 'gradingmethod', 'neq', SPEAKUP_GRADING_EXACT);

        // AI rubric field, shown only when gradingmethod = ai.
        $name = 'aisettings';
        $mform->addElement('header', $name, get_string($name, $plugin));
        $mform->addHelpButton($name, $name, $plugin);
        $mform->setExpanded($name, true);
        // See styles.css for the CSS rule (#id_aisettings) that hides this
        // header when every field within it is hidden by hideIf() below.

        $name = 'airubric';
        $label = get_string($name, $plugin);
        $options = ['rows' => 6];
        $mform->addElement('textarea', $name, $label, $options);
        $mform->addHelpButton($name, $name, $plugin);
        $mform->hideIf($name, 'gradingmethod', 'neq', SPEAKUP_GRADING_AI);

        $name = 'aiproviderid';
        $label = get_string($name, $plugin);
        $providermenu = \qtype_speakup\ai_provider::get_menu(true);
        if (empty($providermenu)) {
            // No providers configured yet - show a disabled placeholder
            // rather than an empty select, with a pointer to where an
            // admin sets these up (Manage AI providers, under Site
            // administration > Plugins > Question types).
            $mform->addElement('static', $name, $label, get_string('noaiproviders', $plugin));
        } else {
            $mform->addElement('select', $name, $label, ['' => get_string('choosedots')] + $providermenu);
            $mform->setType($name, PARAM_INT);
        }
        $mform->addHelpButton($name, $name, $plugin);
        $mform->hideIf($name, 'gradingmethod', 'neq', SPEAKUP_GRADING_AI);
    }

    /**
     * Set the id of this form. This is useful for CSS styling.
     * Default ID is class name with backslashes converted to
     * underscore minus initial underscore and plugin type.
     *
     * @param object $mform representing the Moodle form
     * @param string $id (optional, default='') the new form id
     * @return mixed default value of setting
     */
    public function set_form_id($mform, $id='') {
        if ($id == '') {
            $id = str_replace('\\', '_', get_called_class());
            $id = substr($id, strpos($id, '_', 1) + 1);
        }
        $attributes = $mform->getAttributes();
        $attributes['id'] = $id;
        $mform->setAttributes($attributes);
    }

    #[\Override]
    public function data_preprocessing($question) {
        $question = parent::data_preprocessing($question);

        if (!empty($question->options->expectedanswers)) {
            $question->answertext = [];
            $question->weight = [];
            $question->casesensitive = [];
            foreach ($question->options->expectedanswers as $row) {
                $question->answertext[] = $row->answertext;
                $question->weight[] = round((float) $row->weight, 2);
                $question->casesensitive[] = $row->casesensitive;
            }
        } else {
            // Brand new question, nothing saved yet - give the initial
            // blank rows a sensible starting weight, via the same
            // set_data() mechanism used above for already-saved phrases,
            // rather than repeat_elements()'s own per-row 'default' option
            // (which was found to conflict with already-saved weights
            // being correctly displayed/saved - every weight was ending up
            // as 100, the DB column's own schema default, regardless of
            // what was actually entered).
            $question->weight = array_fill(0, 3, 100);
        }

        return $question;
    }

    #[\Override]
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (empty($data['timelimitinseconds']) || $data['timelimitinseconds'] <= 0) {
            $errors['timelimitinseconds'] = get_string('errornotimelimit', 'qtype_speakup');
        }

        if ($data['gradingmethod'] === SPEAKUP_GRADING_EXACT && !empty($data['answertext'])) {
            foreach ($data['answertext'] as $key => $answertext) {
                $answertext = trim($answertext);
                if ($answertext === '') {
                    continue;
                }
                if (preg_match('#^/.*/[a-zA-Z]*$#', $answertext)) {
                    if (@preg_match($answertext, '') === false) {
                        $errors["answertext[$key]"] = get_string('err_invalidregex', 'qtype_speakup', s($answertext));
                    }
                }
                if (isset($data['weight'][$key])
                        && ($data['weight'][$key] < 0 || $data['weight'][$key] > 100)) {
                    $errors["weight[$key]"] = get_string('err_invalidweight', 'qtype_speakup');
                }
            }
        }

        if ($data['gradingmethod'] === SPEAKUP_GRADING_AI) {
            $providermenu = \qtype_speakup\ai_provider::get_menu(true);
            if (!empty($providermenu) && empty($data['aiproviderid'])) {
                $errors['aiproviderid'] = get_string('err_aiproviderrequired', 'qtype_speakup');
            }
        }

        return $errors;
    }

    #[\Override]
    public function qtype() {
        return 'speakup';
    }
}
