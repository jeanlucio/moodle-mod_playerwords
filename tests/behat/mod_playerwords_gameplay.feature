@mod @mod_playerwords @javascript
Feature: PlayerWords core gameplay loop
  As a student
  I want to play rounds of PlayerWords
  So that I can practise course vocabulary

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

  Scenario: Student wins a round on the first try and the timer badge disappears
    Given the following "activities" exist:
      | activity    | course | name     | min_length | max_length | max_attempts | timer_minutes |
      | playerwords | C1     | Word Win | 5          | 5          | 6             | 1              |
    And the following PlayerWords words exist in activity "Word Win":
      | word  | hint             |
      | codar | Write source code |
    And I log in as "student1"
    And I am on the "Word Win" "playerwords activity" page
    And I click on "Start round" "button"
    And "#playerwords-timer-wrapper" "css_element" should be visible
    When I set the field "Your guess" to "codar"
    And I click on "[data-key=\"ENTER\"]" "css_element"
    Then I should see "Genius!"
    And I should see "The word was:"
    And I should see "codar"
    And "#playerwords-timer-wrapper" "css_element" should not be visible

  Scenario: Student loses a round after exhausting all attempts
    Given the following "activities" exist:
      | activity    | course | name      | min_length | max_length | max_attempts |
      | playerwords | C1     | Word Lose | 5          | 5          | 1             |
    And the following PlayerWords words exist in activity "Word Lose":
      | word  |
      | codar |
    And I log in as "student1"
    And I am on the "Word Lose" "playerwords activity" page
    And I click on "Start round" "button"
    When I set the field "Your guess" to "campo"
    And I click on "[data-key=\"ENTER\"]" "css_element"
    Then I should see "Better luck next time."

  Scenario: Student forfeits an active round with a confirmation dialog
    Given the following "activities" exist:
      | activity    | course | name         | min_length | max_length | max_attempts |
      | playerwords | C1     | Word Forfeit | 5          | 5          | 6             |
    And the following PlayerWords words exist in activity "Word Forfeit":
      | word  |
      | codar |
    And I log in as "student1"
    And I am on the "Word Forfeit" "playerwords activity" page
    And I click on "Start round" "button"
    When I click on "#playerwords-forfeit-button" "css_element"
    And I click on "Yes" "button"
    Then I should see "You gave up."

  Scenario: Student's round ends automatically when the timer runs out
    Given the following "activities" exist:
      | activity    | course | name        | min_length | max_length | max_attempts |
      | playerwords | C1     | Word Timer  | 5          | 5          | 6             |
    And the following PlayerWords words exist in activity "Word Timer":
      | word  |
      | codar |
    And the PlayerWords activity "Word Timer" has "timer_seconds" set to "2" seconds
    And I log in as "student1"
    And I am on the "Word Timer" "playerwords activity" page
    And I click on "Start round" "button"
    When I wait until "#playerwords-round-result" "css_element" exists
    Then I should see "Time is up!"

  Scenario: Reaching the round limit hides the new-round action instead of offering a dead end
    Given the following "activities" exist:
      | activity    | course | name        | min_length | max_length | max_attempts | max_rounds |
      | playerwords | C1     | Word Limit  | 5          | 5          | 6             | 1          |
    And the following PlayerWords words exist in activity "Word Limit":
      | word  |
      | codar |
    And I log in as "student1"
    And I am on the "Word Limit" "playerwords activity" page
    And I click on "Start round" "button"
    And I set the field "Your guess" to "codar"
    And I click on "[data-key=\"ENTER\"]" "css_element"
    Then I should see "Genius!"
    And I should see "Rounds played: 1 / 1."
    And "#playerwords-new-round-button" "css_element" should not exist

  Scenario: A configured cooldown shows a countdown instead of the new-round button
    Given the following "activities" exist:
      | activity    | course | name          | min_length | max_length | max_attempts |
      | playerwords | C1     | Word Cooldown | 5          | 5          | 6             |
    And the following PlayerWords words exist in activity "Word Cooldown":
      | word  |
      | codar |
    And the PlayerWords activity "Word Cooldown" has "cooldown_seconds" set to "99999" seconds
    And I log in as "student1"
    And I am on the "Word Cooldown" "playerwords activity" page
    And I click on "Start round" "button"
    And I set the field "Your guess" to "codar"
    And I click on "[data-key=\"ENTER\"]" "css_element"
    Then I should see "Next round in"
    And "#playerwords-cooldown-countdown" "css_element" should exist
    And "#playerwords-new-round-button" "css_element" should not exist
