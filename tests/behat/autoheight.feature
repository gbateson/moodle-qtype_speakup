@qtype @qtype_speakup @javascript
Feature: Editors and text areas in the SpeakUp question form grow to fit their content
  As a teacher
  In order to see all of a long question text, rubric or transcript
  I need the editors and text areas to grow to fit their content

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

  Scenario: The TinyMCE editor grows and shrinks to fit the question text
    When I am on the "speakup-001" "core_question > edit" page logged in as teacher
    Then the "#fitem_id_questiontext .tox-tinymce" "css_element" should be shorter than 500 pixels
    And I set the field "Question text" to "<p>Line 1 of a long question text.</p><p>Line 2 of a long question text.</p><p>Line 3 of a long question text.</p><p>Line 4 of a long question text.</p><p>Line 5 of a long question text.</p><p>Line 6 of a long question text.</p><p>Line 7 of a long question text.</p><p>Line 8 of a long question text.</p><p>Line 9 of a long question text.</p><p>Line 10 of a long question text.</p><p>Line 11 of a long question text.</p><p>Line 12 of a long question text.</p><p>Line 13 of a long question text.</p><p>Line 14 of a long question text.</p><p>Line 15 of a long question text.</p><p>Line 16 of a long question text.</p><p>Line 17 of a long question text.</p><p>Line 18 of a long question text.</p><p>Line 19 of a long question text.</p><p>Line 20 of a long question text.</p><p>Line 21 of a long question text.</p><p>Line 22 of a long question text.</p><p>Line 23 of a long question text.</p><p>Line 24 of a long question text.</p><p>Line 25 of a long question text.</p><p>Line 26 of a long question text.</p><p>Line 27 of a long question text.</p><p>Line 28 of a long question text.</p><p>Line 29 of a long question text.</p><p>Line 30 of a long question text.</p><p>Line 31 of a long question text.</p><p>Line 32 of a long question text.</p><p>Line 33 of a long question text.</p><p>Line 34 of a long question text.</p><p>Line 35 of a long question text.</p><p>Line 36 of a long question text.</p><p>Line 37 of a long question text.</p><p>Line 38 of a long question text.</p><p>Line 39 of a long question text.</p><p>Line 40 of a long question text.</p>"
    And the "#fitem_id_questiontext .tox-tinymce" "css_element" should be taller than 800 pixels
    And I set the field "Question text" to "<p>A short question.</p>"
    And the "#fitem_id_questiontext .tox-tinymce" "css_element" should be shorter than 500 pixels

  Scenario: The Atto editor grows to fit the question text
    Given the following "user preferences" exist:
      | user    | preference | value |
      | teacher | htmleditor | atto  |
    When I am on the "speakup-001" "core_question > edit" page logged in as teacher
    Then the "#id_questiontexteditable" "css_element" should be shorter than 500 pixels
    And I set the field "Question text" to "<p>Line 1 of a long question text.</p><p>Line 2 of a long question text.</p><p>Line 3 of a long question text.</p><p>Line 4 of a long question text.</p><p>Line 5 of a long question text.</p><p>Line 6 of a long question text.</p><p>Line 7 of a long question text.</p><p>Line 8 of a long question text.</p><p>Line 9 of a long question text.</p><p>Line 10 of a long question text.</p><p>Line 11 of a long question text.</p><p>Line 12 of a long question text.</p><p>Line 13 of a long question text.</p><p>Line 14 of a long question text.</p><p>Line 15 of a long question text.</p><p>Line 16 of a long question text.</p><p>Line 17 of a long question text.</p><p>Line 18 of a long question text.</p><p>Line 19 of a long question text.</p><p>Line 20 of a long question text.</p><p>Line 21 of a long question text.</p><p>Line 22 of a long question text.</p><p>Line 23 of a long question text.</p><p>Line 24 of a long question text.</p><p>Line 25 of a long question text.</p><p>Line 26 of a long question text.</p><p>Line 27 of a long question text.</p><p>Line 28 of a long question text.</p><p>Line 29 of a long question text.</p><p>Line 30 of a long question text.</p><p>Line 31 of a long question text.</p><p>Line 32 of a long question text.</p><p>Line 33 of a long question text.</p><p>Line 34 of a long question text.</p><p>Line 35 of a long question text.</p><p>Line 36 of a long question text.</p><p>Line 37 of a long question text.</p><p>Line 38 of a long question text.</p><p>Line 39 of a long question text.</p><p>Line 40 of a long question text.</p>"
    And the "#id_questiontexteditable" "css_element" should be taller than 800 pixels

  Scenario: With the plain text editor, the question text box grows to fit the text
    Given the following "user preferences" exist:
      | user    | preference | value    |
      | teacher | htmleditor | textarea |
    When I am on the "speakup-001" "core_question > edit" page logged in as teacher
    Then the "#id_questiontext" "css_element" should be shorter than 500 pixels
    And I fill the "id_questiontext" text area with 40 lines of text
    And the "#id_questiontext" "css_element" should be taller than 500 pixels

  Scenario: The audio prompt transcript and the AI rubric grow and shrink to fit their text
    When I am on the "speakup-001" "core_question > edit" page logged in as teacher
    Then the "#id_audioprompttranscript" "css_element" should be shorter than 150 pixels
    And I fill the "id_audioprompttranscript" text area with 40 lines of text
    And the "#id_audioprompttranscript" "css_element" should be taller than 300 pixels
    And I set the field "Transcript of the audio prompt" to "Short."
    And the "#id_audioprompttranscript" "css_element" should be shorter than 150 pixels
    And I set the field "Use AI assessment" to "1"
    And I fill the "id_airubric" text area with 40 lines of text
    And the "#id_airubric" "css_element" should be taller than 300 pixels
