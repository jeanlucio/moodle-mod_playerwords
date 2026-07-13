@mod @mod_playerwords @javascript
Feature: PlayerWords toolbar and modals
  As a student or teacher
  I want the toolbar to only show actions that are actually available to me
  So that the activity page stays uncluttered and accurate

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
    And "teacher1" has already seen the playerwords intro
    And "student1" has already seen the playerwords intro

  Scenario: The manage-words icon only appears for whoever can manage the activity
    Given the following "activities" exist:
      | activity    | course | name          | min_length | max_length |
      | playerwords | C1     | Word Toolbar  | 5          | 5          |
    When I log in as "teacher1"
    And I am on the "Word Toolbar" "playerwords activity" page
    Then "a.pw-toolbar-btn[title=\"Manage words\"]" "css_element" should exist
    When I log in as "student1"
    And I am on the "Word Toolbar" "playerwords activity" page
    Then "a.pw-toolbar-btn[title=\"Manage words\"]" "css_element" should not exist

  Scenario: The inactive-words warning appears only for whoever can manage the activity
    Given the following "activities" exist:
      | activity    | course | name          | min_length | max_length |
      | playerwords | C1     | Word Inactive | 5          | 5          |
    And the following PlayerWords words exist in activity "Word Inactive":
      | word  |
      | rodar |
      | boca  |
    When I log in as "teacher1"
    And I am on the "Word Inactive" "playerwords activity" page
    Then I should see "Inactive words in the pool"
    And I should see "Outside the current length range"
    And I should see "boca"
    When I log in as "student1"
    And I am on the "Word Inactive" "playerwords activity" page
    Then I should not see "Inactive words in the pool"

  Scenario: The ranking icon only appears when the activity has ranking enabled
    Given the following "activities" exist:
      | activity    | course | name              | min_length | max_length | show_ranking |
      | playerwords | C1     | Word Ranking On   | 5          | 5          | 1            |
      | playerwords | C1     | Word Ranking Off  | 5          | 5          | 0            |
    And I log in as "student1"
    And I am on the "Word Ranking On" "playerwords activity" page
    Then "a.pw-toolbar-btn[title=\"Top 5\"]" "css_element" should exist
    When I am on the "Word Ranking Off" "playerwords activity" page
    Then "a.pw-toolbar-btn[title=\"Top 5\"]" "css_element" should not exist

  Scenario: The forfeit icon only appears while a round is active
    Given the following "activities" exist:
      | activity    | course | name         | min_length | max_length |
      | playerwords | C1     | Word Forfeit2 | 5          | 5          |
    And the following PlayerWords words exist in activity "Word Forfeit2":
      | word  |
      | codar |
    And I log in as "student1"
    And I am on the "Word Forfeit2" "playerwords activity" page
    Then "#playerwords-forfeit-button" "css_element" should not be visible
    When I click on "Start round" "button"
    Then "#playerwords-forfeit-button" "css_element" should be visible
    When I set the field "Your guess" to "codar"
    And I click on "[data-key=\"ENTER\"]" "css_element"
    Then "#playerwords-forfeit-button" "css_element" should not be visible

  Scenario: The help modal shows the optional paragraphs when they are all relevant
    Given the following "activities" exist:
      | activity    | course | name         | min_length | max_length | show_ranking | grade | hud_round_cost_item |
      | playerwords | C1     | Word HelpFull | 5          | 5          | 1            | 100   | 1                    |
    And I log in as "student1"
    And I am on the "Word HelpFull" "playerwords activity" page
    When I click on "#playerwords-help-button" "css_element"
    Then I should see "How to play"
    And I should see "This activity may require PlayerHUD items"
    And I should see "Grading method for this activity"
    And I should see "The ranking adds up your points"

  Scenario: The help modal hides the optional paragraphs when none of them are relevant
    Given the following "activities" exist:
      | activity    | course | name            | min_length | max_length | show_ranking | grade |
      | playerwords | C1     | Word HelpMinimal | 5          | 5          | 0            | 0     |
    And I log in as "student1"
    And I am on the "Word HelpMinimal" "playerwords activity" page
    When I click on "#playerwords-help-button" "css_element"
    Then I should see "How to play"
    And I should not see "This activity may require PlayerHUD items"
    And I should not see "Grading method for this activity"
    And I should not see "The ranking adds up your points"

  Scenario: The how-to-play modal opens automatically on a player's very first visit, once ever
    Given the following "users" exist:
      | username | firstname | lastname | email                 |
      | student2 | Student   | Two      | student2@example.com  |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student2 | C1     | student |
    And the following "activities" exist:
      | activity    | course | name           | min_length | max_length |
      | playerwords | C1     | Word AutoIntro | 5          | 5          |
    When I log in as "student2"
    And I am on the "Word AutoIntro" "playerwords activity" page
    Then I should see "How to play"
    And I should see "You can review these instructions anytime by clicking the help icon at the top of the game."
    When I am on the "Word AutoIntro" "playerwords activity" page
    Then I should not see "How to play"
    When I click on "#playerwords-help-button" "css_element"
    Then I should see "How to play"

  Scenario: Cancelling the forfeit confirmation leaves the round untouched
    Given the following "activities" exist:
      | activity    | course | name             | min_length | max_length |
      | playerwords | C1     | Word ForfeitCancel | 5        | 5          |
    And the following PlayerWords words exist in activity "Word ForfeitCancel":
      | word  |
      | codar |
    And I log in as "student1"
    And I am on the "Word ForfeitCancel" "playerwords activity" page
    And I click on "Start round" "button"
    When I click on "#playerwords-forfeit-button" "css_element"
    Then I should see "Are you sure you want to give up?"
    And I wait "1" seconds
    When I click on "[data-action=\"cancel\"]" "css_element"
    Then "#playerwords-round-play" "css_element" should exist
    When I set the field "Your guess" to "codar"
    And I click on "[data-key=\"ENTER\"]" "css_element"
    Then I should see "Genius!"
