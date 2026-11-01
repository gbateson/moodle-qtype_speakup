@qtype @qtype_speakup
Feature: Test exporting SpeakUp questions
  As a teacher
  In order to be able to reuse my SpeakUp questions
  I need to export them

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

  Scenario: Export SpeakUp questions to Moodle XML
    When I am on the "Course 1" "core_question > course question export" page logged in as teacher
    And I set the field "id_format_xml" to "1"
    And I press "Export questions to file"
    Then following "click here" should download a file that:
      | Has mimetype                 | text/xml                                          |
      | Contains text in xml element | speakup-001                                       |
      | Contains text in xml element | Talk about a string instrument that you can play. |
      | Contains text                | <targetphrase>                                    |
    And I log out

  Scenario: Export SpeakUp questions to GIFT
    When I am on the "Course 1" "core_question > course question export" page logged in as teacher
    And I set the field "id_format_gift" to "1"
    And I press "Export questions to file"
    Then following "click here" should download a file that:
      | Has mimetype  | text/plain                                                |
      | Contains text | ::speakup-001::                                           |
      | Contains text | AUDIO: Talk about a string instrument that you can play.  |
      | Contains text | TARGETPHRASE=                                             |
    And I log out
