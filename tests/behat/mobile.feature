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
    And I should see "The site uses HTTPS, with \"Secure cookies only\" on"
    # The test site does not use HTTPS.
    And I should see "Not all of these are met, so the Moodle app offers to open quizzes with SpeakUp questions in the browser."
    And the field "Allow SpeakUp questions in the Moodle app" matches value "1"

  @javascript
  Scenario: The response page shows the recorder, but not the question text, and records into the app's draft area
    When I log in as "student"
    And I am on the SpeakUp app response page for "speakup-001" in quiz "Quiz 1" attempted by "student"
    Then I should not see "Talk about a musical instrument."
    And I should see "Show transcript (penalty -30%)"
    And "input[type=hidden][name$='_recording'][value='987654321']" "css_element" should exist
    And I click on "button.qtype_speakup-main-button" "css_element"
    And the SpeakUp recorder should be "recording"
    And I wait "2" seconds
    And I click on "button.qtype_speakup-main-button" "css_element"
    And I wait until the SpeakUp recording is uploaded

  Scenario: Students cannot open the response page for someone else's attempt
    When I log in as "student2"
    And I am on the SpeakUp app response page for "speakup-001" in quiz "Quiz 1" attempted by "student"
    Then I should see "This question cannot be answered here"
