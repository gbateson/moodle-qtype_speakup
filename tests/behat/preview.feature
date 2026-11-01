@qtype @qtype_speakup
Feature: Preview a SpeakUp question
  As a teacher
  In order to check my SpeakUp questions will work for students
  I need to preview them

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
      | questioncategory | qtype   | name        | template |
      | Test questions   | speakup | speakup-001 | basic    |
      | Test questions   | speakup | speakup-002 | prompt   |

  Scenario: Preview a SpeakUp question
    When I am on the "speakup-001" "core_question > preview" page logged in as teacher
    Then I should see "Talk about a musical instrument."
    And I should see "Start recording"
    And I should see "No recording submitted."

  Scenario: Preview a SpeakUp question with limits on the prompt and the responses
    When I am on the "speakup-002" "core_question > preview" page logged in as teacher
    Then I should see "Recording starts as soon as the prompt finishes."
    And I should see "You can play the prompt 2 time(s)."
    And I should see "You can give 1 response(s)."

  Scenario: Submitting a preview without a recording
    When I am on the "speakup-001" "core_question > preview" page logged in as teacher
    And I press "Submit and finish"
    Then I should see "Not answered"
