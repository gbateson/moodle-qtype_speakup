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

namespace qtype_speakup\form;

use qtype_speakup\ai_provider;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Add/edit form for a single AI provider (site-wide, admin-only).
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider_form extends \moodleform {

    #[\Override]
    protected function definition() {
        $mform = $this->_form;
        $plugin = 'qtype_speakup';

        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);

        $name = 'name';
        $label = get_string($name, $plugin);
        $mform->addElement('text', $name, $label, ['size' => 40]);
        $mform->setType($name, PARAM_TEXT);
        $mform->addRule($name, null, 'required', null, 'client');

        $name = 'providertype';
        $label = get_string($name, $plugin);
        $options = [
            ai_provider::TYPE_CORE     => get_string('aiprovidertype_core', $plugin),
            ai_provider::TYPE_EXTERNAL => get_string('aiprovidertype_external', $plugin),
        ];
        $mform->addElement('select', $name, $label, $options);
        $mform->addHelpButton($name, $name, $plugin);
        $mform->setDefault($name, ai_provider::TYPE_EXTERNAL);

        $name = 'capability';
        $label = get_string($name, $plugin);
        $options = [
            ai_provider::CAPABILITY_TEXT       => get_string('aicapability_text', $plugin),
            ai_provider::CAPABILITY_MULTIMODAL => get_string('aicapability_multimodal', $plugin),
        ];
        $mform->addElement('select', $name, $label, $options);
        $mform->addHelpButton($name, $name, $plugin);
        $mform->setDefault($name, ai_provider::CAPABILITY_TEXT);
        // Core AI's generate_text action cannot accept files - always text-only.
        $mform->hideIf($name, 'providertype', 'eq', ai_provider::TYPE_CORE);

        $name = 'corename';
        $label = get_string($name, $plugin);
        $mform->addElement('text', $name, $label, ['size' => 40]);
        $mform->setType($name, PARAM_TEXT);
        $mform->addHelpButton($name, $name, $plugin);
        $mform->hideIf($name, 'providertype', 'neq', ai_provider::TYPE_CORE);

        $name = 'url';
        $label = get_string($name, $plugin);
        $mform->addElement('text', $name, $label, ['size' => 60]);
        // Not PARAM_URL: that cleaning rejects/strips the literal
        // "{{" "}}" characters in a {{MODEL}}/{{APIKEY}} template token
        // (curly braces are not valid URL characters), which some
        // providers (e.g. Gemini, which puts the model in the URL path)
        // genuinely need here. Validity is checked more loosely below
        // instead, after substituting dummy token values.
        $mform->setType($name, PARAM_RAW_TRIMMED);
        $mform->addHelpButton($name, $name, $plugin);
        $mform->hideIf($name, 'providertype', 'neq', ai_provider::TYPE_EXTERNAL);

        $name = 'username';
        $label = get_string($name, $plugin);
        $mform->addElement('text', $name, $label, ['size' => 40]);
        $mform->setType($name, PARAM_TEXT);
        $mform->hideIf($name, 'providertype', 'neq', ai_provider::TYPE_EXTERNAL);

        $name = 'apikey';
        $label = get_string($name, $plugin);
        $mform->addElement('passwordunmask', $name, $label, ['size' => 60]);
        $mform->setType($name, PARAM_RAW_TRIMMED);
        $mform->hideIf($name, 'providertype', 'neq', ai_provider::TYPE_EXTERNAL);

        $name = 'headers';
        $label = get_string($name, $plugin);
        $mform->addElement('textarea', $name, $label, ['rows' => 3]);
        $mform->setType($name, PARAM_RAW_TRIMMED);
        $mform->addHelpButton($name, $name, $plugin);
        $mform->hideIf($name, 'providertype', 'neq', ai_provider::TYPE_EXTERNAL);

        $name = 'model';
        $label = get_string($name, $plugin);
        $mform->addElement('text', $name, $label, ['size' => 40]);
        $mform->setType($name, PARAM_TEXT);
        $mform->hideIf($name, 'providertype', 'neq', ai_provider::TYPE_EXTERNAL);

        $name = 'returnpath';
        $label = get_string($name, $plugin);
        $mform->addElement('text', $name, $label, ['size' => 40]);
        $mform->setType($name, PARAM_TEXT);
        $mform->addHelpButton($name, $name, $plugin);
        $mform->hideIf($name, 'providertype', 'neq', ai_provider::TYPE_EXTERNAL);

        $name = 'requesttemplate';
        $label = get_string($name, $plugin);
        $mform->addElement('textarea', $name, $label, ['rows' => 8, 'style' => 'font-family: monospace;']);
        $mform->setType($name, PARAM_RAW_TRIMMED);
        $mform->addHelpButton($name, $name, $plugin);
        $mform->hideIf($name, 'providertype', 'neq', ai_provider::TYPE_EXTERNAL);

        $name = 'uploadstyle';
        $label = get_string($name, $plugin);
        $options = [
            ai_provider::UPLOAD_NONE           => get_string('uploadstyle_none', $plugin),
            ai_provider::UPLOAD_INLINE_BASE64   => get_string('uploadstyle_inline_base64', $plugin),
            ai_provider::UPLOAD_REFERENCE       => get_string('uploadstyle_upload_reference', $plugin),
        ];
        $mform->addElement('select', $name, $label, $options);
        $mform->addHelpButton($name, $name, $plugin);
        $mform->setDefault($name, ai_provider::UPLOAD_NONE);
        $mform->hideIf($name, 'providertype', 'neq', ai_provider::TYPE_EXTERNAL);
        $mform->hideIf($name, 'capability', 'neq', ai_provider::CAPABILITY_MULTIMODAL);

        $name = 'enabled';
        $label = get_string($name, $plugin);
        $mform->addElement('advcheckbox', $name, $label);
        $mform->setDefault($name, 1);

        $this->add_action_buttons();
    }

    #[\Override]
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if ($data['providertype'] === ai_provider::TYPE_EXTERNAL) {
            if (trim($data['url']) === '') {
                $errors['url'] = get_string('err_urlrequired', 'qtype_speakup');
            } else {
                // Check validity after substituting dummy values for
                // {{MODEL}}/{{APIKEY}}, since PARAM_URL cleaning (which
                // would otherwise do this check) can't be used here - see
                // the comment on the url field's setType() call above.
                $sampleurl = str_replace(['{{MODEL}}', '{{APIKEY}}'], ['sample-model', 'sample-key'], $data['url']);
                if (clean_param($sampleurl, PARAM_URL) === '') {
                    $errors['url'] = get_string('err_invalidurl', 'qtype_speakup');
                }
            }
            if (trim($data['headers']) !== '') {
                json_decode($data['headers']);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $errors['headers'] = get_string('err_invalidjson', 'qtype_speakup');
                }
            }
            if (trim($data['requesttemplate']) === '') {
                $errors['requesttemplate'] = get_string('err_requesttemplaterequired', 'qtype_speakup');
            } else {
                // Validate as JSON only after substituting placeholder
                // tokens with harmless dummy values, since the raw
                // template (e.g. {{TRANSCRIPT}} unquoted) is not valid
                // JSON by itself.
                $sample = str_replace(
                        ['{{RUBRIC}}', '{{TRANSCRIPT}}', '{{MODEL}}', '{{APIKEY}}', '{{AUDIO_BASE64}}', '{{AUDIO_MIMETYPE}}'],
                        ['sample rubric', 'sample transcript', 'sample-model', 'sample-key', 'AAAA', 'audio/webm'],
                        $data['requesttemplate']);
                json_decode($sample);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $errors['requesttemplate'] = get_string('err_invalidjson', 'qtype_speakup');
                }
            }
        } else if (trim($data['corename']) !== '') {
            // Sanity-check core provider names look like a frankenstyle
            // component (aiprovider_xxx), without hard-requiring one of
            // today's known providers - new ones may be added over time.
            if (!preg_match('/^aiprovider_[a-z0-9_]+$/', trim($data['corename']))) {
                $errors['corename'] = get_string('err_invalidcorename', 'qtype_speakup');
            }
        }

        return $errors;
    }
}
