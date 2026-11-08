# qtype_speakup - Spoken English practice/assessment question type

A Moodle question type where students give a **timed spoken response** to a
written or audio prompt. Responses can be graded by:

- **Pattern matching** - transcript compared against model answers / regular expressions.
- **AI holistic assessment** - transcript (and/or audio) sent to an AI engine with a rubric.
- **Manual only** - teacher listens and grades by hand.

Feedback can be **formative** (practice, no stakes) or **summative** (contributes to a grade).

## Status

Early development / alpha. Not yet installable end-to-end - see Roadmap below.

## Provenance and licensing

This plugin is GPL v3+, same as Moodle core. The audio-recording UI (browser
microphone capture via MediaRecorder, cross-device warnings) is based on, and
reuses code from, [qtype_recordrtc](https://github.com/moodleou/moodle-qtype_recordrtc)
by The Open University, also GPL v3+. Where files are copied or adapted from
that project, the original OU copyright header is retained alongside ours.
The question/grading logic, database schema, and AI-assessment integration
are original to this project.

## Architecture

- **Recording**: browser MediaRecorder API (`amd/src/`, based on qtype_recordrtc)
  captures audio, or audio+video, client-side depending on the question's
  `mediatype` setting, and uploads via the standard Moodle draft-file-area
  mechanism. Unlike our original plan, we are keeping recordrtc's video and
  screen-recording code rather than stripping it to audio-only, since a
  planned use case is AI assessment of non-verbal communication (facial
  expression, gesture) alongside spoken language.
- **Storage**: recordings are stored as Moodle files against the question
  attempt, same pattern as any file-upload question type.
- **Transcription + AI assessment**: run asynchronously via a Moodle adhoc
  task (`classes/task/assess_response.php`), never inline in the request that
  handles the student's submission. Results are written to
  `qtype_speakup_response` and picked up on next view/regrade. Video
  assessment (frames/clip sent to a multimodal model) is a distinct pipeline
  from audio transcription+text assessment and is not yet designed in detail.
- **Grading modes**: `SPEAKUP_GRADING_EXACT`, `SPEAKUP_GRADING_AI`,
  `SPEAKUP_GRADING_MANUAL`, set per question in `qtype_speakup_options`.
- **Media type**: `SPEAKUP_MEDIA_AUDIO` or `SPEAKUP_MEDIA_VIDEO`, also set per
  question in `qtype_speakup_options`.

## Image responses

With the response type "Image", students upload one or more images or PDFs
(up to the question's "Maximum number of files"), e.g. scans or photos of
their handwriting, or a drawing. Large photos are made smaller in the
browser before they are uploaded. For automated grading, a multimodal AI
provider transcribes the handwriting, page by page, and assesses it; the
target phrases and items are checked in that transcript.

To send several files in one request, a provider's request template uses
`{{FILE_PARTS}}`, which is replaced by its "file part template" once for
each file. The "Preset" menu on the provider form fills in working settings
for Gemini, ChatGPT and Claude, and for Gemini's and OpenAI's speech.

## Shared rubrics

A question's AI rubric can include all or part of its question text, e.g.
`{{questiontext.rubric}}` for the element with `class="rubric"`, in
English, or `{{questiontext.rubric:ja}}` in Japanese.

To write a rubric once for many questions, put it in another question,
e.g. a Description question at the start of the quiz, which students also
see, and give that question an ID number, e.g. `quickfire-rubric`. Then
each SpeakUp question's rubric can use
`{{quickfire-rubric/questiontext.rubric}}`: the question, then its text,
then the part with `class="rubric"`.

The question is looked for by its ID number in the SpeakUp question's own
category, then in the category above it, and so on to the top of the
question bank, so a rubric in a parent category serves all its
subcategories, and a subcategory can have its own. The latest "Ready"
version is used, so a rubric can be refined over time. The question form
says if no such question can be found; if it has gone when a response is
assessed, the assessment fails, and teachers are told why. In GIFT, give
the Description its ID number with `// [id:quickfire-rubric]` on the line
before it.

## Large files

Students' files are uploaded as they are, except that images bigger than
the upload limit are made smaller in the browser until they fit, and HEIC
photos are turned into JPEG. Audio, video and PDF files bigger than the
limit are refused, with a message saying how big they are and what to do.
Students can rotate images before submitting.

After the quiz is submitted, large files (by default, over 5 MB) are made
smaller in the background, by `\qtype_speakup\file_reducer`: in the
assessment task, before the files are sent to the AI, or, for questions
graded by hand, in the task `reduce_response_files`.

- Audio becomes mono MP3 (ffmpeg).
- Video becomes MP4 (H.264) at 720p (ffmpeg).
- Images become JPEG, at most 2000 pixels wide or tall (PHP's GD).
- PDFs have their images at 150 dpi (Ghostscript, from Moodle's "Path to
  ghostscript" setting).

A file is only replaced if the result is clearly smaller. If PHP cannot run
programs (`exec()`), or a tool is missing, files are kept as they are; the
plugin's settings page shows what is available. On Ubuntu or Debian:

    sudo apt install ffmpeg ghostscript

With "Replace the original files" unchecked, the originals are kept, and
smaller copies are made only for the AI, then deleted. Video questions with
"Send only the sound of videos to the AI" send only the sound (as MP3),
which also needs ffmpeg; teachers still see the whole video.

The tests for audio and video need ffmpeg at `/usr/bin/ffmpeg`, and are
skipped without it.

## GIFT format

SpeakUp questions can be imported and exported in GIFT. The settings go
inside the braces, one per line. The first line must be the audio prompt
transcript, `AUDIO: text` (or `AUDIO(voice=name): text`), or `SPEAKUP` for a
question without an audio prompt. Every other setting is optional: settings
that are left out get their usual defaults.

    ::Weekend::Listen and answer.{AUDIO: What did you do last weekend?}

    ::String instruments::[html]<p>Listen to the prompt and record your answer.</p>{
    AUDIO: Talk about a string instrument that you can play.
    TIMELIMIT=60
    MEDIA=audio
    RESPONSE=record
    LANGUAGE=en-US
    PAUSE=no
    RECORDAFTERPROMPT=yes
    MAXPROMPTPLAYS=1
    MAXRESPONSES=2
    SILENCESTOP=5
    PROMPTTRANSCRIPT=collapsed
    TRANSCRIPTPENALTY=30%
    GRADING=auto
    FEEDBACK=formative
    AI=Gemini, 100%
    RUBRIC=Score the answer out of 100.\nThen give two suggestions.
    TARGETITEM=(words, 50, 40%, x1)
    TARGETPHRASE=("I have played", 10%, /1, partial, nocase)
    SHOW=calculation:both, phrases:both, items:both, comment:both, transcript:both
    STATS=no; words, longwords, uniquewords, lexicaldensity
    #### <p>General feedback, as in standard GIFT.</p>
    }

- Settings can be given by their field names (e.g. `TIMELIMITINSECONDS`,
  `STTLANGUAGE`, `AIRUBRIC`) or by short names (e.g. `TIMELIMIT`, `LANGUAGE`,
  `RUBRIC`). Unrecognised settings and values are reported as warnings, and
  skipped.
- Yes/no settings take `yes` or `no`. `SHOW` takes `none`, `students`,
  `teachers` or `both`, for all five details at once or for each one.
  `PROMPTTRANSCRIPT` takes `hide`, `collapsed` or `expanded`.
- `AI` takes `no`, or the provider's name and/or the weighting, e.g.
  `AI=Gemini, 50%`. Providers are matched by name; credentials are never
  exported.
- `MEDIA` takes `audio`, `video` or `image`. Image responses (e.g. scans
  of handwriting) can have `MAXFILES=1` to `5`, and leave out the settings
  for recordings, such as `TIMELIMIT`. Video responses can have
  `AIAUDIOONLY=yes` (or `AUDIOONLY=yes`), to send only the sound to the AI.
- `TARGETITEM` is `(itemtype, target, percent%, xmultiplier)` and
  `TARGETPHRASE` is `("phrase", percent%, /divisor, full|partial,
  case|nocase)`. Repeat the line, or put several in one line, for more.
- As usual in GIFT, a blank line ends the question, so write line breaks as
  `\n`, and escape `{`, `}`, `=`, `#`, `~` and `:` in values with a
  backslash (only `{` and `}` are essential).
- GIFT cannot hold files, so the audio prompt file is not exported. When a
  question is imported (or saved) with a transcript but no audio prompt, the
  audio prompt is generated from the transcript in the background, by the
  speech provider set in the plugin's settings (Site administration >
  Plugins > Question types > SpeakUp).

## The Moodle app

In the Moodle app, students answer SpeakUp questions in a frame from the
site (`respond.php`), so that the recorder, uploader and audio prompt work
just as they do in a browser. The app shows the question text, sends the
response with the quiz, and shows the review.

Phones may not keep cookies in frames, so the frame has no session. The app
gets a single-use key for it from the web service
`qtype_speakup_get_response_key`, and when the frame is ready, sends it the
app's web service token (only to the site's address), which the frame uses
to upload, list and delete files in the response's draft area, and to play
the audio prompt.

This needs:

- the setting "Allow SpeakUp questions in the Moodle app" (on by default);
- web services for mobile devices;
- "Allow frame embedding" (Site administration > General > Security >
  HTTP security);
- Moodle app 4.2 or later.

The plugin's settings page shows which of these are met. If any is not,
SpeakUp tells the app it is not supported (see `db/mobile.php` and
`classes/output/mobile.php`), and the app offers to open the quiz in the
browser instead.

On phones, recording in the frame also needs the app to allow the
microphone (and camera) there. Where it does not, students can still
upload a recording, photo or file.

## Running the tests

PHPUnit tests are in `tests/`, one file for each class in `classes/`, plus
the question definition, question type, walkthroughs, backup and restore.
Requests to AI and speech providers get mocked responses, so no real API is
called. From Moodle's root directory:

    php admin/tool/phpunit/cli/init.php
    vendor/bin/phpunit --testsuite qtype_speakup_testsuite

Behat features are in `tests/behat/`. AI providers can be created with
`Given the following "qtype_speakup > providers" exist:`, and a question
can be given an audio prompt (a 1.5-second tone) with
`Given the following "qtype_speakup > audio prompts" exist:`.

The scenarios in `media.feature` record audio, so Firefox needs a fake
microphone, allowed without asking, even on the test site's http address,
and audio that plays without a click. Add this to the Behat profile in
Moodle's `config.php`, then run `php admin/tool/behat/cli/util.php --enable`:

    $CFG->behat_profiles = [
        'default' => [
            'browser' => 'firefox',
            'wd_host' => 'http://selenium:4444/wd/hub',
            'capabilities' => [
                'extra_capabilities' => [
                    'moz:firefoxOptions' => [
                        'prefs' => [
                            'media.navigator.streams.fake' => true,
                            'media.navigator.permission.disabled' => true,
                            'media.devices.insecure.enabled' => true,
                            'media.getusermedia.insecure.enabled' => true,
                            'media.autoplay.default' => 0,
                            'media.autoplay.blocking_policy' => 0,
                        ],
                    ],
                ],
            ],
        ],
    ];

Steps such as "I mute the SpeakUp microphone" and "the SpeakUp recorder
should be "recording"" are in `tests/behat/behat_qtype_speakup.php`.

    php admin/tool/behat/cli/init.php
    vendor/bin/behat --config <behat_dataroot>/behatrun/behat/behat.yml --tags=@qtype_speakup

## Roadmap

1. Port recordrtc's `avrecording.js` + templates for the single-response case.
2. Wire up a transcription provider (Whisper API or self-hosted whisper.cpp).
3. Define a pluggable AI-assessor interface so institutions can choose a backend.
4. Regrade-on-async-result plumbing (update question_usage once AI result lands).
5. Behat tests covering add/edit, attempt, and both grading paths.
6. Mobile app compatibility check (MediaRecorder in the Moodle app webview).
7. Privacy API completeness (export/delete transcript, audio, AI feedback).

## Installation (development)

    git clone https://github.com/gbateson/moodle-qtype_speakup.git question/type/speakup
    echo /question/type/speakup/ >> .git/info/exclude

Then run Moodle's upgrade process (Site administration > Notifications).

## Building the JavaScript

`amd/src/avrecording.js` is plain ES6 (import/export). Moodle does not
transpile this on the fly - it expects a pre-built AMD `define()`-wrapped
bundle checked into `amd/build/`. If you edit `amd/src/avrecording.js`,
rebuild it with:

    npm install
    npm run build

This regenerates `amd/build/avrecording.min.js` (+ sourcemap) via Rollup
(see `rollup.config.mjs`). This is a lightweight standalone build step, not
Moodle core's own Grunt/`.min.js` pipeline - it produces an equivalent
output without requiring a full Moodle git checkout with Grunt set up, but
if your team later adopts Moodle's own `grunt amd` workflow instead, that
works too; just make sure `amd/build/avrecording.min.js` stays committed
either way, since that is what Moodle actually loads at runtime.

## Authors

- Gordon Bateson
- [co-developer], live testing at a university in Japan
