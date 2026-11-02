@qtype @qtype_speakup
Feature: SpeakUp questions in the Moodle app
  As a student
  In order to answer SpeakUp questions in the Moodle app
  I need the response page that the app shows in a frame to work

  Background:
    Given the following "users" exist:
      | username |
      | student  |
      | student2 |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student  | C1     | student |
      | student2 | C1     | student |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype   | name        | template | recordafterprompt | maxpromptplays | maxresponses |
      | Test questions   | speakup | speakup-001 | prompt   | 0                 | 0              | 0            |
    And the following "activities" exist:
      | activity | name   | course | idnumber |
      | quiz     | Quiz 1 | C1     | quiz1    |
    And quiz "Quiz 1" contains the following questions:
      | question    | page |
      | speakup-001 | 1    |
    And the following "qtype_speakup > audio prompts" exist:
      | question    |
      | speakup-001 |
    And user "student" has started an attempt at quiz "Quiz 1"

  Scenario: The settings page shows whether SpeakUp works in the Moodle app
    When I log in as "admin"
    And I navigate to "Plugins > Question types > SpeakUp - spoken response" in site administration
    Then I should see "Moodle app"
    And I should see "\"Allow frame embedding\" is on"
    And I should see "Web services for mobile devices are enabled"
    # The test site does not allow frame embedding, or the app.
    And I should see "Not all of these are met, so the Moodle app offers to open quizzes with SpeakUp questions in the browser."
    And the field "Allow SpeakUp questions in the Moodle app" matches value "1"

  @javascript
  Scenario: The response page needs no login, shows the recorder but not the question text, and records with the app's token
    When I open the SpeakUp app response page for "speakup-001" in quiz "Quiz 1" attempted by "student" with a key for "student"
    Then I should not see "Talk about a musical instrument."
    And I should see "Show transcript (penalty -30%)"
    And "input[type=hidden][name$='_recording'][value='987654321']" "css_element" should exist
    And the Moodle app gives the SpeakUp response page a token for "student"
    And I click on "button.qtype_speakup-main-button" "css_element"
    And the SpeakUp recorder should be "recording"
    And I wait "2" seconds
    And I click on "button.qtype_speakup-main-button" "css_element"
    And I wait until the SpeakUp recording is uploaded

  Scenario: The key can only be used once
    When I open the SpeakUp app response page for "speakup-001" in quiz "Quiz 1" attempted by "student" with a key for "student"
    And I should see "Show transcript"
    And I reload the page
    Then I should see "This page can only be opened from the Moodle app."

  Scenario: Students cannot open the response page for someone else's attempt
    When I open the SpeakUp app response page for "speakup-001" in quiz "Quiz 1" attempted by "student" with a key for "student2"
    Then I should see "This question cannot be answered here"

  @javascript
  Scenario: In a frame, the response page reports its height, without growing after recording
    When I open the SpeakUp app response page for "speakup-001" in quiz "Quiz 1" attempted by "student" in a frame
    Then the SpeakUp app frame should keep the same height
    And I switch to "qtype-speakup-test-frame" iframe
    And the Moodle app gives the SpeakUp response page a token for "student"
    And I click on "button.qtype_speakup-main-button" "css_element"
    And the SpeakUp recorder should be "recording"
    And I wait "2" seconds
    And I click on "button.qtype_speakup-main-button" "css_element"
    And I wait until the SpeakUp recording is uploaded
    And I switch to the main frame
    And the SpeakUp app frame should keep the same height
