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
 * A prompt and an output format that SpeakUp adds to the library of the AI
 * bridge (tool_aibridge), so that AI can write SpeakUp questions, e.g. with
 * the question bank tool of the Vocabulary activity.
 *
 * The prompt links the format to the "speakup" question type, which is how a
 * question type offers itself to tools that generate questions.
 *
 * After changing them, increase the plugin version; the AI bridge updates its
 * library when it is upgraded, and SpeakUp's upgrade calls
 * \tool_aibridge\library::sync_component('qtype_speakup').
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// The templates are text for an AI, in which line breaks matter, so their lines are long.
// phpcs:disable moodle.Files.LineLength

$templates = [
    'speakup-format' => [
        'type' => 'format',
        'name' => 'GIFT format (SpeakUp speaking task)',
        'qformat' => 'gift',
        'body' => <<<'EOD'
::question-name::[html]<p>instructions-for-the-student</p>{
AUDIO: audio-prompt
TIMELIMIT=time-limit-in-seconds
RUBRIC=rubric-for-the-ai
TARGETPHRASE=("target-phrase", 20%, /1, partial, nocase)
#### <p>general-feedback</p>
}
EOD
,
        'instructions' => <<<'EOD'
Each question is a speaking task for the SpeakUp question type: the student listens to a short audio prompt, and then records a spoken answer, which an AI assesses.

The "instructions-for-the-student" item tells the student what to do, e.g. "Listen to the question, and answer it in two or three sentences."

The "audio-prompt" item is the text of the audio prompt, which is turned into speech. It is a question or a short situation for the student to respond to, and it uses the vocabulary item naturally. It is on one line.

The "time-limit-in-seconds" item is the longest time allowed for the answer: 30 for a short answer, 60 for a longer one.

The "rubric-for-the-ai" item tells the AI how to assess the student's answer: what a good answer contains, how to give a score out of 100, and that the feedback should be short and encouraging. It mentions the vocabulary item. It is on one line; write line breaks as \n.

The "target-phrase" item is a phrase, using the vocabulary item, that a good answer would contain. Give one TARGETPHRASE line for each target phrase, at most three.

The "general-feedback" item is a model answer, which the student sees after answering.

Do not use blank lines inside a question. In values, escape the characters { and } with a backslash.
EOD
,
        'examples' => <<<'EOD'
::Weekend plans::[html]<p>Listen to the question, and answer it in two or three sentences.</p>{
AUDIO: What are you planning to do this weekend?
TIMELIMIT=45
RUBRIC=Give a score out of 100 for how well the student describes their plans, using future forms such as "going to" and "will".\nThen give one short suggestion.
TARGETPHRASE=("going to", 20%, /1, partial, nocase)
#### <p>This weekend, I'm going to visit my grandparents. On Sunday, I will probably study for my test.</p>
}
EOD
,
    ],
    'speakup-speaking' => [
        'type' => 'prompt',
        'name' => 'SpeakUp: Speaking tasks',
        'description' => 'Speaking tasks, for the SpeakUp question type, that ask students to use one vocabulary item in a spoken answer.',
        'extends' => 'tool_aibridge:generate-questions',
        'body' => '{{block:task}}' . <<<'EOD'
Create {{number-of-questions}} speaking tasks in which students use the vocabulary item "{{vocabulary-item}}" in a spoken answer.
Use only CEFR level {{level-of-questions}} {{target-language}}.
EOD . '{{endblock}}' .
            '{{block:description}}' . <<<'EOD'
Each task asks a short, natural question, or describes a simple situation, that students can answer in a few sentences, in about 30 to 60 seconds. A good answer uses the vocabulary item, so the tasks make students use it, not only recognise it. The tasks are different from each other, e.g. one asks about the student's own life, one asks for an opinion, and one asks the student to describe or explain something.
EOD . '{{endblock}}',
        'formatlinks' => ['speakup' => 'speakup-format'],
    ],
];
