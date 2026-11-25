@qtype @qtype_speakup
Feature: Start a new SpeakUp question from a preset
  As a teacher
  In order to set up a new question quickly
  I need to be able to start it from a preset

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

  Scenario: Start a new question from a preset without JavaScript
    When I am on the "Course 1" "core_question > course question bank" page logged in as teacher
    And I press "Create a new question ..."
    And I set the field "item_qtype_speakup" to "1"
    And I press "submitbutton"
    Then the field "id_preset" matches value "None (the usual settings)"
    And I set the field "id_preset" to "Instant spoken response (AU)"
    And I press "Use this preset"
    And the following fields match these values:
      | id_preset                    | Instant spoken response (AU) |
      | Question name                |                              |
      | id_timelimitinseconds_number | 30                           |
      | Number of responses allowed  | 1                            |
      | id_autorecord                | 1                            |
      | id_itemtarget_0              | 20                           |
      | id_itempercent_0             | 100%                         |
    And I set the following fields to these values:
      | Question name | speakup-preset              |
      | Question text | What did you do last night? |
    And I press "id_submitbutton"
    And I should see "speakup-preset"
    And I choose "Edit question" action for "speakup-preset" in the question bank
    # Presets are only for new questions.
    And "id_preset" "field" should not exist
    And the following fields match these values:
      | id_timelimitinseconds_number | 30   |
      | id_autorecord                | 1    |
      | id_itemtarget_0              | 20   |
      | id_itempercent_0             | 100% |

  @javascript
  Scenario: Choosing a preset is enough with JavaScript
    When I am on the "Course 1" "core_question > course question bank" page logged in as teacher
    And I press "Create a new question ..."
    And I set the field "item_qtype_speakup" to "1"
    And I press "submitbutton"
    Then "Use this preset" "button" should not be visible
    And I set the field "id_preset" to "Video monologue (AU)"
    And I wait until the page is ready
    And the following fields match these values:
      | id_preset                      | Video monologue (AU)   |
      | Response type                  | Audio + video (webcam) |
      | id_timelimitinseconds_number   | 3                      |
      | id_timelimitinseconds_timeunit | minutes                |
      | Use AI assessment              | 1                      |
      | AI weighting                   | 70%                    |
    And I set the field "id_preset" to "None (the usual settings)"
    And I wait until the page is ready
    And the following fields match these values:
      | id_preset         | None (the usual settings) |
      | Response type     | Audio only                |
      | Use AI assessment | 0                         |
