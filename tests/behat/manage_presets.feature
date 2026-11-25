@qtype @qtype_speakup
Feature: Manage the presets that new SpeakUp questions start from
  As a teacher
  In order to keep my presets tidy
  I need to be able to rename, share, reorder and delete them

  Background:
    Given the following "users" exist:
      | username | firstname | lastname |
      | teacher  | Terry     | Teacher  |
      | other    | Olive     | Other    |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user    | course | role           |
      | teacher | C1     | editingteacher |
      | other   | C1     | editingteacher |
    And the following "qtype_speakup > presets" exist:
      | name           | shortname | contextlevel | reference | user    | timelimitinseconds | sortorder |
      | My drill       | drill     | User         | teacher   | teacher | 45                 | 1         |
      | Course warm-up | warmup    | Course       | C1        | other   | 20                 | 1         |
      | Course debate  | debate    | Course       | C1        | other   | 120                | 2         |
      | Olive's own    | olive     | User         | other     | other   | 30                 | 1         |

  Scenario: See the presets that can be used in a course
    When I am on the "C1" "qtype_speakup > presets" page logged in as "teacher"
    Then I should see "SpeakUp presets"
    And the following should exist in the "generaltable" table:
      | Name of the preset      | Short name of the preset | Who can use it                   | Owner         |
      | My drill                | drill                    | Only me                          | Terry Teacher |
      | Course warm-up          | warmup                   | Teachers in the course: Course 1 | Olive Other   |
      | Course debate           | debate                   | Teachers in the course: Course 1 | Olive Other   |
      | Instant spoken response | instant-response         | Everyone on this site            | Admin User    |
    And I should not see "Olive's own"
    # Teachers can change course presets, but not the site's.
    And "Edit the preset \"Course warm-up\"" "link" should exist
    And "Edit the preset \"Instant spoken response\"" "link" should not exist

  Scenario: Rename a preset and share it with the course
    When I am on the "C1" "qtype_speakup > presets" page logged in as "teacher"
    And I click on "Edit the preset \"My drill\"" "link"
    And I set the following fields to these values:
      | Name of the preset       | Daily drill                      |
      | Short name of the preset | warmup                           |
      | Who can use it           | Teachers in the course: Course 1 |
    And I press "Save changes"
    Then I should see "There is already a preset with this short name there: \"Course warm-up\""
    And I set the field "Short name of the preset" to "daily-drill"
    And I press "Save changes"
    And I should see "The preset \"Daily drill\" has been saved."
    And the following should exist in the "generaltable" table:
      | Name of the preset | Short name of the preset | Who can use it                   |
      | Daily drill        | daily-drill              | Teachers in the course: Course 1 |
    And I am on the "C1" "qtype_speakup > presets" page logged in as "other"
    And I should see "Daily drill"

  Scenario: Reorder and delete presets
    When I am on the "C1" "qtype_speakup > presets" page logged in as "teacher"
    And "Course warm-up" "text" should appear before "Course debate" "text"
    And I click on "Move down" "link" in the "Course warm-up" "table_row"
    Then "Course debate" "text" should appear before "Course warm-up" "text"
    And I click on "Delete the preset \"Course debate\"" "link"
    And I press "Continue"
    And I should see "The preset \"Course debate\" has been deleted."
    And I should not see "Course debate" in the "generaltable" "table"

  Scenario: Admins manage the site's presets in the admin settings
    When I log in as "admin"
    And I navigate to "Plugins > Question types > SpeakUp presets" in site administration
    Then I should see "Instant spoken response"
    And I should not see "Course warm-up"
    And I click on "Edit the preset \"Video monologue\"" "link"
    And I set the field "Name of the preset" to "Talk to the camera"
    And I press "Save changes"
    And I should see "Talk to the camera"
