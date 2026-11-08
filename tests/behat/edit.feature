@qtype @qtype_speakup
Feature: Test editing a SpeakUp question
  As a teacher
  In order to be able to update my SpeakUp question
  I need to edit it

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

  Scenario: The saved target phrases and items are shown, and can be changed
    When I am on the "speakup-001" "core_question > edit" page logged in as teacher
    Then the following fields match these values:
      | Question name      | speakup-001     |
      | Grading method     | Automated       |
      | id_phrasematch_0   | guitar          |
      | id_phrasepercent_0 | 40%             |
      | id_phrasematch_1   | I have played   |
      | id_phrasematch_2   | /\bviolins?\b/i |
      | id_phrasedivisor_2 | 2               |
      | id_itemcount_0     | 20              |
      | id_itempercent_0   | 30%             |
    And I set the following fields to these values:
      | Question name      | speakup-001 edited |
      | id_phrasematch_0   | violin             |
    And I press "id_submitbutton"
    And I should see "speakup-001 edited"
    And I choose "Edit question" action for "speakup-001 edited" in the question bank
    And the field "id_phrasematch_0" matches value "violin"

  Scenario: Extra rows of target phrases can be added
    When I am on the "speakup-001" "core_question > edit" page logged in as teacher
    And I set the field "id_phraseaddcount" to "2 more target phrases"
    And I press "id_phraseadd"
    Then "id_phrasematch_4" "field" should exist
    And "id_phrasematch_5" "field" should not exist

  Scenario: The audio prompt settings are saved
    When I am on the "speakup-002" "core_question > edit" page logged in as teacher
    Then the following fields match these values:
      | Transcript of the audio prompt           | Talk about a string instrument that you can play. |
      | Show audio prompt transcript             | Yes, collapsed                                   |
      | Penalty for showing the transcript       | 30%                                               |
      | Start recording when the prompt finishes | 1                                                 |
      | Number of times the prompt can be played | 2                                                 |
      | Number of responses allowed              | 1                                                 |
    And I set the following fields to these values:
      | Number of times the prompt can be played | Unlimited |
      | Stop recording after a silence of        | 5 seconds |
    And I press "id_submitbutton"
    And I choose "Edit question" action for "speakup-002" in the question bank
    And the following fields match these values:
      | Number of times the prompt can be played | Unlimited |
      | Stop recording after a silence of        | 5 seconds |

  @javascript
  Scenario: The transcript penalty is only offered for a collapsed transcript and automated grading
    When I am on the "speakup-002" "core_question > edit" page logged in as teacher
    And I expand all fieldsets
    Then I should see "Penalty for showing the transcript"
    And I set the field "Show audio prompt transcript" to "Yes, expanded"
    And I should not see "Penalty for showing the transcript"
    And I set the field "Show audio prompt transcript" to "Yes, collapsed"
    And I should see "Penalty for showing the transcript"
    And I set the field "Grading method" to "Manual"
    And I should not see "Penalty for showing the transcript"

  @javascript
  Scenario: The headings show the most the target items and phrases can award
    When I am on the "speakup-001" "core_question > edit" page logged in as teacher
    Then I should see "Up to 30%" in the "#id_itemsheader" "css_element"
    # Phrases: 40% + 30% + 20% divided by 2.
    And I should see "Up to 80%" in the "#id_phrasesheader" "css_element"
    And I set the field "id_phrasepercent_0" to "100%"
    And I should see "Up to 140%" in the "#id_phrasesheader" "css_element"
    And I set the field "id_phrasematch_1" to ""
    And I should see "Up to 110%" in the "#id_phrasesheader" "css_element"
    And I set the field "id_itemmultiplier_0" to "multiplied by 2"
    And I should see "Up to 60%" in the "#id_itemsheader" "css_element"

  @javascript
  Scenario: Phrase boxes grow to fit their text
    When I am on the "speakup-001" "core_question > edit" page logged in as teacher
    Then the "id_phrasematch_1" field should be wider than the "id_phrasematch_0" field
    And I set the field "id_phrasematch_0" to "a much longer target phrase than the others"
    And the "id_phrasematch_0" field should be wider than the "id_phrasematch_1" field

  @javascript
  Scenario: After adding a target phrase, the new phrase box has the focus
    When I am on the "speakup-001" "core_question > edit" page logged in as teacher
    And I press "id_phraseadd"
    Then the focused element is "id_phrasematch_3" "field"
    And I press "id_itemadd"
    And the focused element is "id_itemcount_1" "field"

  @javascript
  Scenario: The audio prompt can be played on the question form
    Given the following "qtype_speakup > audio prompts" exist:
      | question    |
      | speakup-002 |
    When I am on the "speakup-002" "core_question > edit" page logged in as teacher
    Then ".qtype_speakup-audioprompt-editor audio" "css_element" should be visible
    And the ".qtype_speakup-audioprompt-editor audio" "css_element" should be wider than 200 pixels
    And "Delete" "button" in the "#fitem_id_audiopromptwidget" "css_element" should be visible
