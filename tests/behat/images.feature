@qtype @qtype_speakup @javascript
Feature: Image responses, e.g. scans of handwriting
  As a teacher
  In order to assess students' handwriting and drawings
  I need students to be able to upload images and PDFs

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
      | questioncategory | qtype   | name        | template | gradingmethod | maxfiles |
      | Test questions   | speakup | speakup-001 | basic    | auto          | 1        |
      | Test questions   | speakup | speakup-002 | image    | manual        | 2        |

  Scenario: The settings for recordings are hidden for image responses
    When I am on the "speakup-001" "core_question > edit" page logged in as teacher
    Then I should see "Response time limit"
    And I should see "Stop recording after a silence of"
    And I should not see "Maximum number of files"
    And I set the field "Response type" to "Image (e.g. a handwriting scan)"
    And I should not see "Response time limit"
    And I should not see "Response method"
    And I should not see "Allow pausing"
    And I should not see "Start recording when the prompt finishes"
    And I should not see "Stop recording after a silence of"
    And I should not see "Number of responses allowed"
    And I should see "Maximum number of files"
    # The audio prompt is still available, e.g. "draw what is described".
    And I should see "Transcript of the audio prompt"
    And the field "Response method" matches value "Upload"

  Scenario: Students upload pages, which are shown in order, and can be removed
    When I am on the "speakup-002" "core_question > preview" page logged in as teacher
    Then I should see "You can upload up to 2 files, e.g. one for each page."
    And I should see "No file uploaded."
    And I upload "question/type/speakup/tests/fixtures/handwriting.png" as a SpeakUp page
    And I should see "Page 1"
    And "img.qtype_speakup-image-thumb" "css_element" should exist
    And I should see "Upload another page"
    And I upload "question/type/speakup/tests/fixtures/handwriting.pdf" as a SpeakUp page
    And I should see "Page 2"
    And I should see "handwriting.pdf"
    # No more pages than allowed.
    And ".qtype_speakup-image-button" "css_element" should not be visible
    And I click on "Remove page 1" "button"
    And I should see "Page removed."
    And I should not see "Page 2"
    And I should see "handwriting.pdf"
    And ".qtype_speakup-image-button" "css_element" should be visible
    And I upload "question/type/speakup/tests/fixtures/handwriting.png" as a SpeakUp page
    And I press "Submit and finish"
    And I should see "Page 1"
    And I should see "Page 2"
    And "a.qtype_speakup-image-pdf" "css_element" should exist
    And "img.qtype_speakup-image-thumb" "css_element" should exist

  @_file_upload
  Scenario: Students can rotate a page
    When I am on the "speakup-002" "core_question > preview" page logged in as teacher
    And I upload "question/type/speakup/tests/fixtures/handwriting.png" as a SpeakUp page
    Then the SpeakUp page 1 picture should be 400 by 200 pixels
    And I click on "Rotate page 1 right" "button"
    And I should see "The page was rotated."
    And the SpeakUp page 1 picture should be 200 by 400 pixels
    And I click on "Rotate page 1 left" "button"
    And I should see "The page was rotated."
    And the SpeakUp page 1 picture should be 400 by 200 pixels
    # The rotated page is the one that is saved.
    And I click on "Rotate page 1 right" "button"
    And I should see "The page was rotated."
    And I press "Submit and finish"
    And the SpeakUp page 1 picture should be 200 by 400 pixels

  @_file_upload
  Scenario: A photo that is too big to upload is made smaller, and one that is small enough is not
    Given the following config values are set as admin:
      | maxbytes | 204800 |
    When I am on the "speakup-002" "core_question > preview" page logged in as teacher
    And I upload "question/type/speakup/tests/fixtures/handwriting.png" as a SpeakUp page
    Then "img.qtype_speakup-image-thumb[src*='01-handwriting.png']" "css_element" should exist
    And I upload a 1200 by 900 pixel photo as a SpeakUp page
    And I should see "\"photo.png\" was too big to upload"
    And I should see "so it was made smaller"
    And "img.qtype_speakup-image-thumb[src*='02-photo.jpg']" "css_element" should exist
    And the SpeakUp page 2 picture should be 1200 by 900 pixels

  @_file_upload
  Scenario: A PDF that is too big to upload is not uploaded, and the student is told what to do
    Given the following config values are set as admin:
      | maxbytes | 100 |
    When I am on the "speakup-002" "core_question > preview" page logged in as teacher
    And I upload "question/type/speakup/tests/fixtures/handwriting.pdf" as a SpeakUp page
    Then I should see "\"handwriting.pdf\" is 441 bytes" in the ".qtype_speakup-image-message" "css_element"
    And I should see "e.g. by scanning at a lower resolution"
    And "a.qtype_speakup-image-pdf" "css_element" should not exist

  Scenario: Choosing a preset fills in the AI provider form
    When I log in as "admin"
    And I visit "/question/type/speakup/manageaiproviders.php"
    And I press "Add a new AI provider"
    And I set the field "Preset" to "Claude (Anthropic)"
    Then the following fields match these values:
      | Name              | Claude                               |
      | Capability        | Multimodal (audio, video and image files)|
      | API URL           | https://api.anthropic.com/v1/messages |
      | Response key path | content,0,text                       |
    And I set the field "API key" to "my-secret-key"
    And I press "Save changes"
    And I should see "AI provider saved."
    And I should see "Claude"
