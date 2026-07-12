@mod @mod_playerwords @javascript
Feature: PlayerWords PlayerHUD integration
  As a student
  I want item costs and rewards to be enforced and shown accurately
  So that I always know what a round or a hint will cost me, and what I earn

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
    And I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add the "PlayerHUD" block
    And I log out

  Scenario: The lobby blocks starting a round until the student can afford the item cost
    Given a PlayerHUD item "Gold Key" exists in course "C1"
    And the following "activities" exist:
      | activity    | course | name          | min_length | max_length |
      | playerwords | C1     | Word HudLobby | 5          | 5          |
    And the PlayerWords activity "Word HudLobby" charges 2 PlayerHUD item "Gold Key" to start a round
    And the following PlayerWords words exist in activity "Word HudLobby":
      | word  |
      | codar |
    And I log in as "student1"
    And I am on the "Word HudLobby" "playerwords activity" page
    Then I should see "Costs 2× Gold Key (you have 0)."
    And the "#playerwords-start-round-button" element should be disabled
    When "student1" has 2 PlayerHUD item "Gold Key" in course "C1"
    And I reload the page
    Then I should see "Costs 2× Gold Key (you have 2)."
    And I click on "Start round" "button"
    And "#playerwords-round-play" "css_element" should exist

  Scenario: Revealing the hint requires confirmation and enough balance
    Given a PlayerHUD item "Magnifying Glass" exists in course "C1"
    And the following "activities" exist:
      | activity    | course | name         | min_length | max_length |
      | playerwords | C1     | Word HudHint | 5          | 5          |
    And the PlayerWords activity "Word HudHint" charges 1 PlayerHUD item "Magnifying Glass" to reveal the hint
    And the following PlayerWords words exist in activity "Word HudHint":
      | word  | hint                          |
      | codar | A tool for reading small text |
    And I log in as "student1"
    And I am on the "Word HudHint" "playerwords activity" page
    And I click on "Start round" "button"
    When I click on "#playerwords-hint-button" "css_element"
    Then I should see "Costs 1× Magnifying Glass (you have 0)."
    And the "[data-action=\"save\"]" element should be disabled
    When I click on "[data-action=\"cancel\"]" "css_element"
    And "student1" has 1 PlayerHUD item "Magnifying Glass" in course "C1"
    And I reload the page
    And I click on "#playerwords-hint-button" "css_element"
    And I click on "[data-action=\"save\"]" "css_element"
    Then I should see "A tool for reading small text"

  Scenario: A round starts and the hint reveals for free when the configured item no longer exists
    Given the following "activities" exist:
      | activity    | course | name         | min_length | max_length | hud_round_cost_item | hud_hint_cost_item |
      | playerwords | C1     | Word HudGone | 5          | 5          | 99999                | 99999               |
    And the following PlayerWords words exist in activity "Word HudGone":
      | word  | hint          |
      | codar | A simple hint |
    And I log in as "student1"
    And I am on the "Word HudGone" "playerwords activity" page
    And I should not see "Costs"
    And I click on "Start round" "button"
    And "#playerwords-round-play" "css_element" should exist
    When I click on "#playerwords-hint-button" "css_element"
    Then I should see "A simple hint"

  Scenario: Winning a round grants the configured PlayerHUD item
    Given a PlayerHUD item "Trophy" exists in course "C1"
    And the following "activities" exist:
      | activity    | course | name        | min_length | max_length |
      | playerwords | C1     | Word HudWin | 5          | 5          |
    And the PlayerWords activity "Word HudWin" grants 1 PlayerHUD item "Trophy" for winning a round
    And the following PlayerWords words exist in activity "Word HudWin":
      | word  |
      | codar |
    And I log in as "student1"
    And I am on the "Word HudWin" "playerwords activity" page
    And I click on "Start round" "button"
    And I set the field "Your guess" to "codar"
    And I click on "[data-key=\"ENTER\"]" "css_element"
    Then I should see "You received 1× Trophy!"
