@qtype @qtype_speakup
Feature: Test importing SpeakUp questions
  As a teacher
  In order to reuse, and quickly create, SpeakUp questions
  I need to import them

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

  @javascript @_file_upload
  Scenario: Import SpeakUp questions from GIFT
    When I am on the "Course 1" "core_question > course question import" page logged in as teacher
    And I set the field "id_format_gift" to "1"
    And I upload "question/type/speakup/tests/fixtures/speakup.gift.txt" file to "Import" filemanager
    And I press "id_submitbutton"
    Then I should see "Parsing questions from import file."
    And I should see "This setting was not recognised, and was skipped: COLOUR"
    And I should see "Importing 3 questions from file"
    And I press "Continue"
    And I should see "speakup-gift-001"
    And I should see "speakup-gift-002"
    And I should see "speakup-gift-003"
    And I choose "Edit question" action for "speakup-gift-002" in the question bank
    And the following fields match these values:
      | Question name                            | speakup-gift-002                                   |
      | Transcript of the audio prompt           | Talk about a string instrument that you can play. |
      | id_timelimitinseconds_number             | 90                                                 |
      | Start recording when the prompt finishes | 1                                                  |
      | Number of times the prompt can be played | 2                                                  |
      | Number of responses allowed              | 1                                                  |
      | Penalty for showing the transcript       | 30%                                                |
      | id_phrasematch_0                         | I have played                                      |
      | id_itemcount_0                           | 50                                                 |
    And I press "Cancel"
    And I choose "Edit question" action for "speakup-gift-003" in the question bank
    And the field "Grading method" matches value "Manual"
