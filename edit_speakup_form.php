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

        $name = 'responsemethod';
        $label = get_string($name, $plugin);
        $options = [
            SPEAKUP_RESPONSE_RECORD => get_string('responsemethod_record', $plugin),
            SPEAKUP_RESPONSE_UPLOAD => get_string('responsemethod_upload', $plugin),
        ];
        $mform->addElement('select', $name, $label, $options);
        $mform->addHelpButton($name, $name, $plugin);
        $mform->setDefault($name, SPEAKUP_RESPONSE_RECORD);

        // Pausing only applies to live recording.
        $mform->hideIf('allowpause', 'responsemethod', 'eq', SPEAKUP_RESPONSE_UPLOAD);

        // The language the student is expected to speak. Used for live
        // browser transcription, and as a hint to the AI when transcribing.
        $name = 'sttlanguage';
        $label = get_string($name, $plugin);
        $mform->addElement('text', $name, $label, ['size' => 10]);
        $mform->setType($name, PARAM_ALPHANUMEXT); // Restricts to e.g. en-US, ja-JP style tags.
        $mform->addHelpButton($name, $name, $plugin);
        $mform->setDefault($name, 'en-US');

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
            SPEAKUP_GRADING_AUTO => get_string('gradingmethod_auto', $plugin),
            SPEAKUP_GRADING_MANUAL => get_string('gradingmethod_manual', $plugin),
        ];
        $mform->addElement('select', $name, $label, $options);
        $mform->addHelpButton($name, $name, $plugin);
        $mform->setDefault($name, SPEAKUP_GRADING_AUTO);

        $name = 'feedbackmode';
        $label = get_string($name, $plugin);
        $options = [
            SPEAKUP_FEEDBACK_FORMATIVE => get_string('feedbackmode_formative', $plugin),
            SPEAKUP_FEEDBACK_SUMMATIVE => get_string('feedbackmode_summative', $plugin),
        ];
        $mform->addElement('select', $name, $label, $options);
        $mform->addHelpButton($name, $name, $plugin);

        // Target phrases, shown only for automated grading.
        // Modelled on qtype_essayautograde's target phrases: "If [phrase] is
        // used, award [percent] [divided by n] of the question grade."
        $name = 'phrasesheader';
        $mform->addElement('header', $name, get_string('expectedanswers', $plugin));
        $mform->addHelpButton($name, 'expectedanswers', $plugin);
        $mform->setExpanded($name, true);
        // If all elements in a section are hidden, using the "d-none" class,
        // we want to hide the section header, but this cannot be done using
        // hideIf() or by adding a CSS class. Instead, a separate CSS rule is
        // added to styles.css for each header that must be hidden when empty.

        $percentmenu = $this->get_percent_menu();
        $divisormenu = [];
        for ($i = 1; $i <= 20; $i++) {
            $divisormenu[$i] = ($i == 1) ? get_string('phrasedivisor1', $plugin) : get_string('phrasedivisorn', $plugin, $i);
        }
        $fullmatchmenu = [
            0 => get_string('phrasefullmatch0', $plugin),
            1 => get_string('phrasefullmatch1', $plugin),
        ];
        $casesensitivemenu = [
            0 => get_string('phrasecasesensitive0', $plugin),
            1 => get_string('phrasecasesensitive1', $plugin),
        ];

        $elements = [
            $mform->createElement('group', 'phrasegroup', get_string('phrasegroup', $plugin), [
                $mform->createElement('static', '', '', get_string('phrasetext1', $plugin)),
                $mform->createElement('text', 'phrasematch', '', ['size' => 30]),
                $mform->createElement('static', '', '', get_string('phrasetext2', $plugin)),
                $mform->createElement('select', 'phrasepercent', '', $percentmenu),
                $mform->createElement('select', 'phrasedivisor', '', $divisormenu),
                $mform->createElement('static', '', '', get_string('phrasetext3', $plugin)),
            ], ' ', false),
            $mform->createElement('group', 'phraseoptions', get_string('phraseoptions', $plugin), [
                $mform->createElement('select', 'phrasefullmatch', '', $fullmatchmenu),
                $mform->createElement('select', 'phrasecasesensitive', '', $casesensitivemenu),
            ], ' ', false),
        ];
        $options = [
            'phrasegroup' => ['helpbutton' => ['phrasegroup', $plugin]],
            'phrasematch' => ['type' => PARAM_RAW_TRIMMED],
            'phrasepercent' => ['type' => PARAM_INT],
            'phrasedivisor' => ['type' => PARAM_INT],
            'phrasefullmatch' => ['type' => PARAM_INT],
            'phrasecasesensitive' => ['type' => PARAM_INT],
        ];

        $saved = count($this->question->options->expectedanswers ?? []);
        $defaults = ['phrasepercent' => 0, 'phrasedivisor' => 1];
        $repeatno = $this->add_repeat_rows($mform, 'phrase', $elements, $options, $saved, $defaults);
        for ($i = 0; $i < $repeatno; $i++) {
            $mform->hideIf('phrasegroup[' . $i . ']', 'gradingmethod', 'neq', SPEAKUP_GRADING_AUTO);
            $mform->hideIf('phraseoptions[' . $i . ']', 'gradingmethod', 'neq', SPEAKUP_GRADING_AUTO);
        }
        $mform->hideIf('phraseaddgroup', 'gradingmethod', 'neq', SPEAKUP_GRADING_AUTO);

        // Uploaded files have no live transcript, so without AI assessment
        // an AI provider must transcribe them first. (With AI assessment,
        // the AI provider below does that.) This is saved in the same
        // aiproviderid field as the AI provider (see data_preprocessing()
        // and qtype_speakup::save_question_options()). It is a separate form
        // field because hideIf() can't show one field for "AI assessment,
        // or uploads without AI assessment".
        $name = 'transcriptionproviderid';
        $label = get_string($name, $plugin);
        $providermenu = \qtype_speakup\ai_provider::get_menu(true);
        if (empty($providermenu)) {
            $mform->addElement('static', $name, $label, get_string('noaiproviders', $plugin));
        } else {
            $mform->addElement('select', $name, $label, ['' => get_string('choosedots')] + $providermenu);
            $mform->setType($name, PARAM_INT);
        }
        $mform->addHelpButton($name, $name, $plugin);
        $mform->hideIf($name, 'gradingmethod', 'neq', SPEAKUP_GRADING_AUTO);
        $mform->hideIf($name, 'responsemethod', 'neq', SPEAKUP_RESPONSE_UPLOAD);
        $mform->hideIf($name, 'aiassessment', 'checked');

        // Target items, shown only for automated grading: "Proportionally
        // award up to [percent] of the question grade for [min] to [max]
        // [item]", e.g. words, unique words or speech rate.
        $name = 'itemsheader';
        $mform->addElement('header', $name, get_string($name, $plugin));
        $mform->addHelpButton($name, $name, $plugin);
        $mform->setExpanded($name, true);

        $itemmenu = [];
        foreach (\qtype_speakup\text_stats::get_item_types() as $type) {
            $itemmenu[$type] = get_string('item' . $type, $plugin);
        }
        $elements = [
            $mform->createElement('group', 'itemgroup', get_string('itemgroup', $plugin), [
                $mform->createElement('static', '', '', get_string('itemtext1', $plugin)),
                $mform->createElement('select', 'itempercent', '', $percentmenu),
                $mform->createElement('static', '', '', get_string('itemtext2', $plugin)),
                $mform->createElement('text', 'itemmin', '', ['size' => 4]),
                $mform->createElement('static', '', '', get_string('itemtext3', $plugin)),
                $mform->createElement('text', 'itemmax', '', ['size' => 4]),
                $mform->createElement('select', 'itemtype', '', $itemmenu),
            ], ' ', false),
        ];
        // The default item depends on the response language: characters
        // for languages written without spaces (e.g. Japanese), else words.
        $lang = $this->question->options->sttlanguage ?? 'en-US';
        $options = [
            'itemgroup' => ['helpbutton' => ['itemgroup', $plugin]],
            'itempercent' => ['type' => PARAM_INT],
            'itemmin' => ['type' => PARAM_RAW_TRIMMED],
            'itemmax' => ['type' => PARAM_RAW_TRIMMED],
            'itemtype' => ['type' => PARAM_ALPHA],
        ];
        $saved = count($this->question->options->targetitems ?? []);
        $defaults = ['itempercent' => 0, 'itemtype' => \qtype_speakup\text_stats::get_default_item_type($lang)];
        $repeatno = $this->add_repeat_rows($mform, 'item', $elements, $options, $saved, $defaults);
        for ($i = 0; $i < $repeatno; $i++) {
            $mform->hideIf('itemgroup[' . $i . ']', 'gradingmethod', 'neq', SPEAKUP_GRADING_AUTO);
        }
        $mform->hideIf('itemaddgroup', 'gradingmethod', 'neq', SPEAKUP_GRADING_AUTO);

        // AI assessment settings, shown only for automated grading.
        $name = 'aisettings';
        $mform->addElement('header', $name, get_string($name, $plugin));
        $mform->addHelpButton($name, $name, $plugin);
        $mform->setExpanded($name, true);
        // See styles.css for the CSS rule (#id_aisettings) that hides this
        // header when every field within it is hidden by hideIf() below.

        $name = 'aiassessment';
        $label = get_string($name, $plugin);
        $mform->addElement('advcheckbox', $name, $label);
        $mform->addHelpButton($name, $name, $plugin);
        $mform->setDefault($name, 0);
        $mform->hideIf($name, 'gradingmethod', 'neq', SPEAKUP_GRADING_AUTO);

        $name = 'aiweighting';
        $label = get_string($name, $plugin);
        $options = [];
        for ($i = 100; $i >= 0; $i--) {
            $options[$i] = $i . '%';
        }
        $mform->addElement('select', $name, $label, $options);
        $mform->addHelpButton($name, $name, $plugin);
        $mform->setDefault($name, 100);
        $mform->hideIf($name, 'gradingmethod', 'neq', SPEAKUP_GRADING_AUTO);
        $mform->hideIf($name, 'aiassessment', 'notchecked');

        $name = 'airubric';
        $label = get_string($name, $plugin);
        $options = ['rows' => 6];
        $mform->addElement('textarea', $name, $label, $options);
        $mform->addHelpButton($name, $name, $plugin);
        $mform->hideIf($name, 'gradingmethod', 'neq', SPEAKUP_GRADING_AUTO);
        $mform->hideIf($name, 'aiassessment', 'notchecked');

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
        $mform->hideIf($name, 'gradingmethod', 'neq', SPEAKUP_GRADING_AUTO);
        $mform->hideIf($name, 'aiassessment', 'notchecked');

        // Who sees each grading detail, for automated grading. As in
        // qtype_essayautograde: nobody, students only, teachers only, or both.
        $name = 'showheader';
        $mform->addElement('header', $name, get_string($name, $plugin));
        $mform->addHelpButton($name, $name, $plugin);
        $mform->setExpanded($name, false);

        $showmenu = [
            SPEAKUP_SHOW_NONE => get_string('no'),
            SPEAKUP_SHOW_STUDENTS_ONLY => get_string('showtostudentsonly', $plugin),
            SPEAKUP_SHOW_TEACHERS_ONLY => get_string('showtoteachersonly', $plugin),
            SPEAKUP_SHOW_TEACHERS_AND_STUDENTS => get_string('showtoteachersandstudents', $plugin),
        ];
        foreach (['showphrases', 'showitems', 'showcalculation', 'showcomment'] as $name) {
            $mform->addElement('select', $name, get_string($name, $plugin), $showmenu);
            $mform->addHelpButton($name, $name, $plugin);
            $mform->setDefault($name, SPEAKUP_SHOW_TEACHERS_AND_STUDENTS);
            $mform->hideIf($name, 'gradingmethod', 'neq', SPEAKUP_GRADING_AUTO);
        }
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

    /**
     * Get a menu of percentages, 100% down to 0%.
     *
     * @return array percent => label
     */
    protected function get_percent_menu(): array {
        $menu = [];
        for ($i = 100; $i >= 0; $i--) {
            $menu[$i] = $i . '%';
        }
        return $menu;
    }

    /**
     * Add repeated rows (e.g. target phrases), with an "Add [n more]"
     * button and menu, as in qtype_essayautograde.
     *
     * Element names follow the $type: e.g. for "phrase", the number of rows
     * is in "phrasecount", the button is "phraseadd", the menu is
     * "phraseaddcount", and both are in the group "phraseaddgroup". The
     * menu labels use the strings "addmore{$type}1" and "addmore{$type}s".
     *
     * Defaults are only set on new rows, i.e. those after the saved ones.
     * (repeat_elements()'s own "default" option would also overwrite the
     * values of saved rows.)
     *
     * @param MoodleQuickForm $mform
     * @param string $type e.g. "phrase"
     * @param array $elements the elements in each row.
     * @param array $options options for repeat_elements().
     * @param int $saved number of rows the question already has.
     * @param array $defaults element name => default value, for new rows.
     * @return int the number of rows added.
     */
    protected function add_repeat_rows($mform, string $type, array $elements, array $options,
            int $saved, array $defaults): int {
        $repeats = max(1, $saved);
        $plugin = 'qtype_speakup';
        $addname = $type . 'add';
        $addcountname = $type . 'addcount';
        $addcount = optional_param($addcountname, 1, PARAM_INT);

        $repeatno = $this->repeat_elements($elements, $repeats, $options,
                $type . 'count', $addname, $addcount, get_string('add'), true);

        // Replace repeat_elements()'s own "Add" button with a button and a
        // menu of how many rows to add.
        $mform->removeElement($addname);
        $menu = [];
        for ($i = 1; $i <= 10; $i++) {
            $menu[$i] = ($i == 1) ? get_string('addmore' . $type . '1', $plugin)
                                  : get_string('addmore' . $type . 's', $plugin, $i);
        }
        $mform->addGroup([
            $mform->createElement('submit', $addname, get_string('add')),
            $mform->createElement('select', $addcountname, '', $menu),
        ], $addname . 'group', '', ' ', false);
        $mform->registerNoSubmitButton($addname);
        $mform->setDefault($addcountname, $addcount);
        $mform->setType($addcountname, PARAM_INT);

        for ($i = $saved; $i < $repeatno; $i++) {
            foreach ($defaults as $name => $value) {
                $mform->setDefault($name . '[' . $i . ']', $value);
            }
        }

        return $repeatno;
    }

    #[\Override]
    public function data_preprocessing($question) {
        $question = parent::data_preprocessing($question);

        // The transcription provider is stored in aiproviderid.
        if (isset($question->options->aiproviderid)) {
            $question->transcriptionproviderid = $question->options->aiproviderid;
        }

        // Target phrases.
        $question->phrasematch = [];
        $question->phrasepercent = [];
        $question->phrasedivisor = [];
        $question->phrasefullmatch = [];
        $question->phrasecasesensitive = [];
        foreach ($question->options->expectedanswers ?? [] as $row) {
            $question->phrasematch[] = $row->answertext;
            $question->phrasepercent[] = (int) $row->percent;
            $question->phrasedivisor[] = (int) $row->divisor;
            $question->phrasefullmatch[] = (int) $row->fullmatch;
            $question->phrasecasesensitive[] = (int) $row->casesensitive;
        }

        // Target items. Numbers are shown without needless decimals, e.g.
        // 50 rather than 50.00.
        $question->itemtype = [];
        $question->itempercent = [];
        $question->itemmin = [];
        $question->itemmax = [];
        foreach ($question->options->targetitems ?? [] as $row) {
            $question->itemtype[] = $row->itemtype;
            $question->itempercent[] = (int) $row->percent;
            $question->itemmin[] = format_float((float) $row->mincount, 2, false, true);
            $question->itemmax[] = format_float((float) $row->maxcount, 2, false, true);
        }

        return $question;
    }

    #[\Override]
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (empty($data['timelimitinseconds']) || $data['timelimitinseconds'] <= 0) {
            $errors['timelimitinseconds'] = get_string('errornotimelimit', 'qtype_speakup');
        }

        $isautomated = ($data['gradingmethod'] === SPEAKUP_GRADING_AUTO);
        $usesai = $isautomated && !empty($data['aiassessment']);

        // Target items: a row is used if it awards a percent. It then needs
        // an upper number, and a lower number that is not above it. Items
        // that count sentences need a transcript with punctuation, which
        // only AI makes: the browser's live transcript has none.
        if ($isautomated && !empty($data['itemtype'])) {
            $isupload = (($data['responsemethod'] ?? SPEAKUP_RESPONSE_RECORD) === SPEAKUP_RESPONSE_UPLOAD);
            foreach ($data['itemtype'] as $key => $itemtype) {
                if (empty($data['itempercent'][$key])) {
                    continue;
                }
                $min = trim($data['itemmin'][$key] ?? '');
                $max = trim($data['itemmax'][$key] ?? '');
                if (($min !== '' && !is_numeric($min)) || !is_numeric($max) || (float) $max <= 0) {
                    $errors["itemgroup[$key]"] = get_string('err_itemmax', 'qtype_speakup');
                } else if ((float) $min > (float) $max) {
                    $errors["itemgroup[$key]"] = get_string('err_itemrange', 'qtype_speakup');
                } else if (\qtype_speakup\text_stats::needs_sentences($itemtype) && !$usesai && !$isupload) {
                    $errors["itemgroup[$key]"] = get_string('err_itemneedsai', 'qtype_speakup');
                }
            }
        }

        if ($isautomated && !empty($data['phrasematch'])) {
            foreach ($data['phrasematch'] as $key => $phrase) {
                $phrase = trim($phrase);
                if ($phrase !== '' && preg_match('#^/.*/[a-zA-Z]*$#', $phrase) && @preg_match($phrase, '') === false) {
                    $errors["phrasegroup[$key]"] = get_string('err_invalidregex', 'qtype_speakup', s($phrase));
                }
            }
        }

        if ($usesai) {
            $providermenu = \qtype_speakup\ai_provider::get_menu(true);
            if (!empty($providermenu) && empty($data['aiproviderid'])) {
                $errors['aiproviderid'] = get_string('err_aiproviderrequired', 'qtype_speakup');
            }
        }

        // An uploaded file has no live browser transcript. So, for
        // automated grading, it needs an AI provider that receives the file
        // itself and can transcribe it: with AI assessment, the AI
        // provider; without, the transcription provider.
        if ($isautomated && ($data['responsemethod'] ?? SPEAKUP_RESPONSE_RECORD) === SPEAKUP_RESPONSE_UPLOAD) {
            $field = ($usesai ? 'aiproviderid' : 'transcriptionproviderid');
            if (!isset($errors[$field])) {
                $providerid = (int) ($data[$field] ?? 0);
                if (empty(\qtype_speakup\ai_provider::get_menu(true))) {
                    $errors['responsemethod'] = get_string('err_uploadneedsprovider', 'qtype_speakup');
                } else if (empty($providerid)) {
                    $errors[$field] = get_string('err_transcriptionproviderrequired', 'qtype_speakup');
                } else {
                    $provider = \qtype_speakup\ai_provider::get($providerid);
                    if ($provider && $provider->capability !== \qtype_speakup\ai_provider::CAPABILITY_MULTIMODAL) {
                        $errors[$field] = get_string('err_uploadneedsmultimodal', 'qtype_speakup');
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
