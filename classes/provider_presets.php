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

namespace qtype_speakup;

/**
 * Ready-made settings for common AI providers, offered on the "Add a new
 * AI provider" form. Choosing one fills in the form; the administrator then
 * adds their own API key, and can change anything, e.g. the model.
 *
 * Models change often, so the model names here are only a starting point.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider_presets {

    /** The text of the prompt, as used in each provider's request: the rubric, then the transcript (if any). */
    const PROMPT = '{{RUBRIC}}\n\n{{TRANSCRIPT}}';

    /**
     * Get the presets.
     *
     * @return array preset name => form field => value. Each also has a
     *      "label", shown in the menu.
     */
    public static function get_presets(): array {
        return [
            'gemini' => [
                'label' => get_string('preset_gemini', 'qtype_speakup'),
                'name' => 'Gemini',
                'providertype' => ai_provider::TYPE_EXTERNAL,
                'capability' => ai_provider::CAPABILITY_MULTIMODAL,
                'url' => 'https://generativelanguage.googleapis.com/v1beta/models/{{MODEL}}:generateContent',
                'headers' => '{"x-goog-api-key": "{{APIKEY}}"}',
                'model' => 'gemini-2.5-flash',
                'returnpath' => 'candidates,0,content,parts,0,text',
                'requesttemplate' => self::json([
                    'contents' => [['parts' => ['{{FILE_PARTS}}', ['text' => self::PROMPT]]]],
                ]),
                // Gemini takes audio, video, images and PDFs in the same way.
                'filetemplate' => self::json(['inline_data' => ['mime_type' => '{{FILE_MIMETYPE}}', 'data' => '{{FILE_BASE64}}']]),
                'uploadstyle' => ai_provider::UPLOAD_INLINE_BASE64,
                'uploadprotocol' => '',
                'uploadurl' => '',
            ],
            'geminiupload' => [
                'label' => get_string('preset_geminiupload', 'qtype_speakup'),
                'name' => 'Gemini (large files)',
                'providertype' => ai_provider::TYPE_EXTERNAL,
                'capability' => ai_provider::CAPABILITY_MULTIMODAL,
                'url' => 'https://generativelanguage.googleapis.com/v1beta/models/{{MODEL}}:generateContent',
                'headers' => '{"x-goog-api-key": "{{APIKEY}}"}',
                'model' => 'gemini-2.5-flash',
                'returnpath' => 'candidates,0,content,parts,0,text',
                'requesttemplate' => self::json([
                    'contents' => [['parts' => ['{{FILE_PARTS}}', ['text' => self::PROMPT]]]],
                ]),
                'filetemplate' => self::json(['file_data' => ['mime_type' => '{{FILE_MIMETYPE}}', 'file_uri' => '{{FILE_URI}}']]),
                'uploadstyle' => ai_provider::UPLOAD_REFERENCE,
                'uploadprotocol' => \qtype_speakup\upload\uploader_factory::PROTOCOL_GEMINI,
                'uploadurl' => 'https://generativelanguage.googleapis.com/upload/v1beta/files?key={{APIKEY}}',
            ],
            'openai' => [
                'label' => get_string('preset_openai', 'qtype_speakup'),
                'name' => 'ChatGPT',
                'providertype' => ai_provider::TYPE_EXTERNAL,
                'capability' => ai_provider::CAPABILITY_MULTIMODAL,
                'url' => 'https://api.openai.com/v1/chat/completions',
                'headers' => '{"Authorization": "Bearer {{APIKEY}}"}',
                'model' => 'gpt-4o',
                'returnpath' => 'choices,0,message,content',
                'requesttemplate' => self::json([
                    'model' => '{{MODEL}}',
                    'messages' => [['role' => 'user', 'content' => ['{{FILE_PARTS}}', ['type' => 'text', 'text' => self::PROMPT]]]],
                ]),
                // Images and PDFs. (Audio needs one of OpenAI's audio models.)
                'filetemplate' => self::json([
                    'image/*' => ['type' => 'image_url', 'image_url' => ['url' => 'data:{{FILE_MIMETYPE}};base64,{{FILE_BASE64}}']],
                    'application/pdf' => ['type' => 'file', 'file' => ['filename' => '{{FILE_NAME}}',
                            'file_data' => 'data:application/pdf;base64,{{FILE_BASE64}}']],
                ]),
                'uploadstyle' => ai_provider::UPLOAD_INLINE_BASE64,
                'uploadprotocol' => '',
                'uploadurl' => '',
            ],
            'claude' => [
                'label' => get_string('preset_claude', 'qtype_speakup'),
                'name' => 'Claude',
                'providertype' => ai_provider::TYPE_EXTERNAL,
                'capability' => ai_provider::CAPABILITY_MULTIMODAL,
                'url' => 'https://api.anthropic.com/v1/messages',
                'headers' => '{"x-api-key": "{{APIKEY}}", "anthropic-version": "2023-06-01"}',
                'model' => 'claude-sonnet-5-5',
                'returnpath' => 'content,0,text',
                'requesttemplate' => self::json([
                    'model' => '{{MODEL}}',
                    'max_tokens' => 2000,
                    'messages' => [['role' => 'user', 'content' => ['{{FILE_PARTS}}', ['type' => 'text', 'text' => self::PROMPT]]]],
                ]),
                // Images and PDFs. (Claude does not take audio.)
                'filetemplate' => self::json([
                    'image/*' => ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => '{{FILE_MIMETYPE}}',
                            'data' => '{{FILE_BASE64}}']],
                    'application/pdf' => ['type' => 'document', 'source' => ['type' => 'base64',
                            'media_type' => 'application/pdf', 'data' => '{{FILE_BASE64}}']],
                ]),
                'uploadstyle' => ai_provider::UPLOAD_INLINE_BASE64,
                'uploadprotocol' => '',
                'uploadurl' => '',
            ],
            'geminitts' => [
                'label' => get_string('preset_geminitts', 'qtype_speakup'),
                'name' => 'Gemini speech',
                'providertype' => ai_provider::TYPE_EXTERNAL,
                'capability' => ai_provider::CAPABILITY_SPEECH,
                'url' => 'https://generativelanguage.googleapis.com/v1beta/models/{{MODEL}}:generateContent',
                'headers' => '{"x-goog-api-key": "{{APIKEY}}"}',
                'model' => 'gemini-2.5-flash-preview-tts',
                'returnpath' => 'candidates,0,content,parts,0,inlineData,data',
                'requesttemplate' => self::json([
                    'contents' => [['parts' => [['text' => '{{TEXT}}']]]],
                    'generationConfig' => [
                        'responseModalities' => ['AUDIO'],
                        'speechConfig' => ['voiceConfig' => ['prebuiltVoiceConfig' => ['voiceName' => '{{VOICE}}']]],
                    ],
                ]),
                'filetemplate' => '',
                'uploadstyle' => ai_provider::UPLOAD_NONE,
                'uploadprotocol' => '',
                'uploadurl' => '',
            ],
            'openaitts' => [
                'label' => get_string('preset_openaitts', 'qtype_speakup'),
                'name' => 'OpenAI speech',
                'providertype' => ai_provider::TYPE_EXTERNAL,
                'capability' => ai_provider::CAPABILITY_SPEECH,
                'url' => 'https://api.openai.com/v1/audio/speech',
                'headers' => '{"Authorization": "Bearer {{APIKEY}}"}',
                'model' => 'gpt-4o-mini-tts',
                'returnpath' => '',
                'requesttemplate' => self::json([
                    'model' => '{{MODEL}}',
                    'input' => '{{TEXT}}',
                    'voice' => '{{VOICE}}',
                    'response_format' => 'mp3',
                ]),
                'filetemplate' => '',
                'uploadstyle' => ai_provider::UPLOAD_NONE,
                'uploadprotocol' => '',
                'uploadurl' => '',
            ],
        ];
    }

    /**
     * Write a template as readable JSON. A list item of "{{FILE_PARTS}}"
     * becomes the token itself, not a JSON string, because it is replaced
     * by the file parts, each followed by a comma.
     *
     * @param array $template
     * @return string
     */
    protected static function json(array $template): string {
        $json = json_encode($template, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        // JSON_PRETTY_PRINT indents with 4 spaces; 2 is easier to read in a text area.
        $json = preg_replace_callback('/^( +)/m', fn($m) => str_repeat(' ', strlen($m[1]) / 2), $json);
        // "{{FILE_PARTS}}", -> {{FILE_PARTS}} (the comma comes with each part).
        $json = preg_replace('/"\{\{FILE_PARTS\}\}",?\s*/', '{{FILE_PARTS}} ', $json);
        // The prompt's "\n" were escaped twice: once in PROMPT, once by json_encode().
        return str_replace('\\\\n', '\\n', $json);
    }
}
