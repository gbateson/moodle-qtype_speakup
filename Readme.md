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

## Authors

- Gordon Bateson
- [co-developer], live testing at a university in Japan
