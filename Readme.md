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
