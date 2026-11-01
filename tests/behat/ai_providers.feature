@qtype @qtype_speakup
Feature: Manage AI providers, including speech providers
  As an administrator
  In order to use AI assessment and generate audio prompts
  I need to set up AI providers

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
      | Test questions   | speakup | speakup-001 | prompt   |

  Scenario: Add a text provider and a speech provider
    When I log in as "admin"
    And I visit "/question/type/speakup/manageaiproviders.php"
    And I press "Add a new AI provider"
    And I set the following fields to these values:
      | Name                  | Test chat                                                      |
      | Provider type         | External AI provider                                            |
      | Capability            | Text only (transcript)                                         |
      | API URL               | https://api.example.com/v1/chat                                |
      | Response key path     | choices,0,message,content                                      |
      | Request body template | {"messages": [{"role": "user", "content": "{{TRANSCRIPT}}"}]} |
    And I press "Save changes"
    Then I should see "AI provider saved."
    And I should see "Test chat"
    And I press "Add a new AI provider"
    And I set the following fields to these values:
      | Name                  | Test voices                         |
      | Provider type         | External AI provider                 |
      | Capability            | Speech (text-to-speech)             |
      | API URL               | https://api.example.com/v1/speech   |
      | Request body template | {"voice": "{{VOICE}}"}              |
    And I press "Save changes"
    And I should see "A speech provider's request template must include the {{TEXT}} token."
    And I set the field "Request body template" to "{\"input\": \"{{TEXT}}\", \"voice\": \"{{VOICE}}\"}"
    And I press "Save changes"
    And I should see "Test voices"
    And I should see "Speech (text-to-speech)"
    And I navigate to "Plugins > Question types > SpeakUp - spoken response" in site administration
    And the "Speech provider" select box should contain "Test voices"
    And the "Speech provider" select box should not contain "Test chat"

  Scenario: Speech providers are not offered for assessing responses
    Given the following "qtype_speakup > providers" exist:
      | name        | capability |
      | Test chat   | text       |
      | Test voices | speech     |
    When I am on the "speakup-001" "core_question > edit" page logged in as teacher
    Then the "AI provider" select box should contain "Test chat"
    And the "AI provider" select box should not contain "Test voices"

  Scenario: The voice and a note about generating audio are only shown when a speech provider is set up
    When I am on the "speakup-001" "core_question > edit" page logged in as teacher
    Then I should not see "it will be generated from this transcript"
    And "Voice" "field" should not exist
    And the following "qtype_speakup > providers" exist:
      | name        | capability | tts |
      | Test voices | speech     | 1   |
    And I am on the "speakup-001" "core_question > edit" page
    And I should see "If there is no audio prompt when you save the question, it will be generated from this transcript."
    And I set the field "Voice" to "Kore"
    And I press "id_submitbutton"
    And I choose "Edit question" action for "speakup-001" in the question bank
    And the field "Voice" matches value "Kore"
