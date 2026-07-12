@mod @mod_playerwords @javascript
Feature: PlayerWords teacher-facing settings behaviour
  As a teacher
  I want the settings form to protect data that is already in use
  So that changing a setting later cannot silently corrupt past results

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                 |
      | teacher1 | Teacher   | One      | teacher1@example.com  |
      | student1 | Student   | One      | student1@example.com  |
    And the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1        | topics |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |

  Scenario: The scoring mode and attempt-count settings freeze once a real grade exists
    Given the following "activities" exist:
      | activity    | course | name        | min_length | max_length | max_attempts |
      | playerwords | C1     | Word Freeze | 5          | 5          | 6             |
    And the following PlayerWords words exist in activity "Word Freeze":
      | word  |
      | codar |
    And I log in as "student1"
    And I am on the "Word Freeze" "playerwords activity" page
    And I click on "Start round" "button"
    And I set the field "Your guess" to "codar"
    And I click on "[data-key=\"ENTER\"]" "css_element"
    And I should see "Genius!"
    And I log in as "teacher1"
    And I am on the "Word Freeze" "playerwords activity editing" page
    And I click on "Expand all" "link"
    Then I should see "Because this activity has already recorded a real grade"
    And the "#id_max_attempts" element should be disabled
    And the "#id_gradescoringmode" element should be disabled
    And the "#id_rankingscoringmode" element should be disabled

  Scenario: Adding a manual word that already exists in the pool is rejected
    Given the following "activities" exist:
      | activity    | course | name        | min_length | max_length |
      | playerwords | C1     | Word Manage | 5          | 5          |
    And the following PlayerWords words exist in activity "Word Manage":
      | word  |
      | codar |
    And I log in as "teacher1"
    And I am on the "Word Manage" "playerwords activity" page
    And I click on "a.pw-toolbar-btn[title=\"Manage words\"]" "css_element"
    When I set the field "playerwords-manualword" to "codar"
    And I click on "Add word" "button"
    Then I should see "This word already exists in this activity's word pool."

  Scenario: A PlayerHUD item that no longer exists stays selected instead of resetting silently
    Given the following "activities" exist:
      | activity    | course | name     | min_length | max_length | hud_round_cost_item |
      | playerwords | C1     | Word Hud | 5          | 5          | 99999                |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add the "PlayerHUD" block
    And I am on the "Word Hud" "playerwords activity editing" page
    And I click on "Expand all" "link"
    Then I should see "Deleted item (please reconfigure)"
    When I press "Save and return to course"
    And I am on the "Word Hud" "playerwords activity editing" page
    And I click on "Expand all" "link"
    Then I should see "Deleted item (please reconfigure)"
