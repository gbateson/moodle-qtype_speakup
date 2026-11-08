@qtype @qtype_speakup @javascript
Feature: Audio prompts and recording in SpeakUp questions
  As a teacher
  In order to make sure students can respond to audio prompts
  I need the prompt, the transcript and the recorder to work

  # Recording needs a microphone. The Behat profile for Firefox in config.php
  # gives it a fake one (see Readme.md).

  Background:
    Given the following "users" exist:
      | username |
      | teacher  |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user    | course | role           |
      | teacher | C1     | editingteacher |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype   | name        | template | recordafterprompt | maxpromptplays | maxresponses | silencestop |
      | Test questions   | speakup | speakup-001 | basic    | 0                 | 0              | 0            | 0           |
      | Test questions   | speakup | speakup-002 | prompt   | 0                 | 2              | 0            | 0           |
      | Test questions   | speakup | speakup-003 | prompt   | 1                 | 0              | 1            | 0           |
      | Test questions   | speakup | speakup-004 | basic    | 0                 | 0              | 0            | 2           |
    And the following "questions" exist:
      | questioncategory | qtype   | name        | template | gradingmethod | responsemethod |
      | Test questions   | speakup | speakup-005 | manual   | manual        | upload         |
    And the following "qtype_speakup > audio prompts" exist:
      | question    |
      | speakup-002 |
      | speakup-003 |

  Scenario: Viewing the transcript asks for confirmation, because of the penalty
    When I am on the "speakup-002" "core_question > preview" page logged in as teacher
    Then I should see "Show transcript (penalty -30%)"
    And I should not see "Talk about a string instrument that you can play."
    And I click on "summary" "css_element" in the ".qtype_speakup-prompt-transcript" "css_element"
    And I should see "Viewing the transcript will reduce your grade by 30%. Are you sure you want to see it?" in the "Show the transcript?" "dialogue"
    And I click on "Cancel" "button" in the "Show the transcript?" "dialogue"
    And I should not see "Talk about a string instrument that you can play."
    And I click on "summary" "css_element" in the ".qtype_speakup-prompt-transcript" "css_element"
    And I click on "Show transcript" "button" in the "Show the transcript?" "dialogue"
    And I should see "Talk about a string instrument that you can play."

  Scenario: Students who are exempt from the penalty see the transcript without confirmation
    When I am on the "speakup-002" "core_question > preview" page logged in as admin
    Then I should not see "(penalty -30%)"
    And I click on "summary" "css_element" in the ".qtype_speakup-prompt-transcript" "css_element"
    And I should see "Talk about a string instrument that you can play."
    And "Show the transcript?" "dialogue" should not exist

  Scenario: The prompt can only be played as many times as allowed
    When I am on the "speakup-002" "core_question > preview" page logged in as teacher
    Then I should see "You can play the prompt 2 time(s)."
    And I play the SpeakUp audio prompt
    And I should not see "You have played the prompt as many times as allowed."
    And I play the SpeakUp audio prompt
    And I should see "You have played the prompt as many times as allowed."
    And the SpeakUp audio prompt should be locked

  Scenario: Record a response
    When I am on the "speakup-001" "core_question > preview" page logged in as teacher
    And I click on "button.qtype_speakup-main-button" "css_element"
    And the SpeakUp recorder should be "recording"
    And I wait "2" seconds
    And I click on "button.qtype_speakup-main-button" "css_element"
    And I wait until the SpeakUp recording is uploaded
    And I press "Submit and finish"
    Then I should not see "Not answered"
    And "source[src*='recording.mp3']" "css_element" should exist

  Scenario: Recording starts when the prompt finishes, and only one response is allowed
    When I am on the "speakup-003" "core_question > preview" page logged in as teacher
    Then I should see "Recording starts as soon as the prompt finishes."
    And the SpeakUp recorder should be "new"
    # Students listen first: the record button waits for the prompt.
    And I should see "Listen to the prompt first. Recording will start when it finishes."
    And "button.qtype_speakup-main-button.disabled[aria-disabled=true]" "css_element" should exist
    And I play the SpeakUp audio prompt
    And the SpeakUp recorder should be "recording"
    And I should not see "Listen to the prompt first."
    And "button.qtype_speakup-main-button.disabled" "css_element" should not exist
    And I wait "2" seconds
    And I click on "button.qtype_speakup-main-button" "css_element"
    And I wait until the SpeakUp recording is uploaded
    And I should see "You have given as many responses as allowed."
    And "button.qtype_speakup-main-button" "css_element" should not be visible

  Scenario: Recording stops after a silence, but only after the student has started speaking
    When I am on the "speakup-004" "core_question > preview" page logged in as teacher
    And I mute the SpeakUp microphone
    And I click on "button.qtype_speakup-main-button" "css_element"
    And the SpeakUp recorder should be "recording"
    # Silence before speaking does not stop the recording.
    And I wait "3" seconds
    And the SpeakUp recorder should be "recording"
    And I unmute the SpeakUp microphone
    And I wait "2" seconds
    And I mute the SpeakUp microphone
    Then the SpeakUp recorder should be "recorded"
    And I should see "Recording stopped after 2 seconds of silence."

  @_file_upload
  Scenario: Upload a file as the response
    When I am on the "speakup-005" "core_question > preview" page logged in as teacher
    And I upload "question/type/speakup/tests/fixtures/audioprompt.wav" as the SpeakUp response
    And I press "Submit and finish"
    Then I should not see "Not answered"
    And "source[src*='audioprompt.wav']" "css_element" should exist

  @_file_upload
  Scenario: A recording that is too big to upload is not uploaded, and the student is told what to do
    Given the following config values are set as admin:
      | maxbytes | 10240 |
    When I am on the "speakup-005" "core_question > preview" page logged in as teacher
    And I upload "question/type/speakup/tests/fixtures/audioprompt.wav" as the SpeakUp response
    Then I should see "This file is 23 KB, but the largest file you can upload is 10 KB."
    And I should see "Please upload a shorter recording"
    And "source[src*='audioprompt.wav']" "css_element" should not exist
    And ".qtype_speakup-upload-player audio[src]" "css_element" should not exist

  Scenario: Record an audio prompt on the question form
    When I am on the "speakup-001" "core_question > edit" page logged in as teacher
    And I click on "button.qtype_speakup-main-button" "css_element" in the "#qtype_speakup-audioprompt" "css_element"
    And the SpeakUp audio prompt recorder should be "recording"
    And I wait "2" seconds
    And I click on "button.qtype_speakup-main-button" "css_element" in the "#qtype_speakup-audioprompt" "css_element"
    And I wait until the SpeakUp audio prompt is uploaded
    And I press "id_submitbutton"
    And I choose "Edit question" action for "speakup-001" in the question bank
    Then the SpeakUp audio prompt recorder should be "recorded"
    And "#qtype_speakup-audioprompt audio[src*='audioprompt.mp3']" "css_element" should exist
    # The recorder can be used again straight away.
    And the "disabled" attribute of "#qtype_speakup-audioprompt button.qtype_speakup-main-button" "css_element" should not be set

  Scenario: Without "Start recording when the prompt finishes", students can record straight away
    When I am on the "speakup-002" "core_question > preview" page logged in as teacher
    Then I should not see "Listen to the prompt first."
    And "button.qtype_speakup-main-button.disabled" "css_element" should not exist
