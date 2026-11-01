@qtype @qtype_speakup
Feature: Test backing up and restoring SpeakUp questions
  As a teacher
  In order to reuse my courses
  I need SpeakUp questions to be backed up and restored

  Background:
    # Back up straight away, rather than in the background.
    Given the following config values are set as admin:
      | enableasyncbackup | 0 |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "qtype_speakup > providers" exist:
      | name   |
      | Gemini |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype   | name        | template |
      | Test questions   | speakup | speakup-001 | basic    |
      | Test questions   | speakup | speakup-002 | prompt   |

  @javascript
  Scenario: Backup and restore a course containing SpeakUp questions
    When I am on the "Course 1" course page logged in as admin
    And I backup "Course 1" course using this options:
      | Confirmation | Filename | test_backup.mbz |
    And I restore "test_backup.mbz" backup into a new course using this options:
      | Schema | Course name       | Course 2 |
      | Schema | Course short name | C2       |
    And I am on the "Course 2" "core_question > course question bank" page
    Then I should see "speakup-001"
    And I should see "speakup-002"
    And I choose "Edit question" action for "speakup-001" in the question bank
    And the following fields match these values:
      | Question name      | speakup-001 |
      | id_phrasematch_0   | guitar      |
      | id_itemcount_0     | 20          |
    And I press "Cancel"
    And I choose "Edit question" action for "speakup-002" in the question bank
    And the following fields match these values:
      | Transcript of the audio prompt     | Talk about a string instrument that you can play. |
      | Penalty for showing the transcript | 30%                                               |
