@qtype @qtype_speakup
Feature: Test creating a SpeakUp question
  As a teacher
  In order to test my students' speaking
  I need to be able to create a SpeakUp question

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

  Scenario: Create a manually graded SpeakUp question
    When I am on the "Course 1" "core_question > course question bank" page logged in as teacher
    And I add a "SpeakUp - spoken response" question filling the form with:
      | Question name                  | speakup-001                  |
      | Question text                  | Tell me about your hometown. |
      | General feedback               | Thank you.                   |
      | Grading method                 | Manual                       |
      | id_timelimitinseconds_number   | 90                           |
      | Transcript of the audio prompt | Where do you come from?      |
    Then I should see "speakup-001"
    And I choose "Edit question" action for "speakup-001" in the question bank
    And the following fields match these values:
      | Question name                  | speakup-001             |
      | Grading method                 | Manual                  |
      | id_timelimitinseconds_number   | 90                      |
      | Transcript of the audio prompt | Where do you come from? |

  Scenario: Create an automatically graded SpeakUp question with a target phrase and a target item
    When I am on the "Course 1" "core_question > course question bank" page logged in as teacher
    And I press "Create a new question ..."
    And I set the field "item_qtype_speakup" to "1"
    And I press "submitbutton"
    And I set the following fields to these values:
      | Question name  | speakup-002                      |
      | Question text  | Talk about a musical instrument. |
      | Grading method | Automated                        |
    # New questions have no rows of target phrases or items until "Add" is pressed.
    And I press "id_phraseadd"
    And I press "id_itemadd"
    And I set the following fields to these values:
      | id_phrasematch_0   | guitar |
      | id_phrasepercent_0 | 50%    |
      | id_itemcount_0     | 20     |
      | id_itempercent_0   | 30%    |
    And I press "id_submitbutton"
    Then I should see "speakup-002"
    And I choose "Edit question" action for "speakup-002" in the question bank
    And the following fields match these values:
      | Question name      | speakup-002 |
      | id_phrasematch_0   | guitar      |
      | id_phrasepercent_0 | 50%         |
      | id_itemcount_0     | 20          |
      | id_itempercent_0   | 30%         |

  Scenario: The question form needs a time limit
    When I am on the "Course 1" "core_question > course question bank" page logged in as teacher
    And I press "Create a new question ..."
    And I set the field "item_qtype_speakup" to "1"
    And I press "submitbutton"
    And I set the following fields to these values:
      | Question name                | speakup-003    |
      | Question text                | Say something. |
      | id_timelimitinseconds_number | 0              |
    And I press "id_submitbutton"
    Then I should see "You must set a time limit greater than zero."
