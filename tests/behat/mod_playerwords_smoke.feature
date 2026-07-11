@mod @mod_playerwords @javascript
Feature: PlayerWords smoke test
  As a student
  I want to open a PlayerWords activity
  In order to start playing

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
    And the following "activities" exist:
      | activity     | course | name       | min_length | max_length |
      | playerwords  | C1     | Word Game  | 4          | 6          |
    And the following PlayerWords words exist in activity "Word Game":
      | word  | hint             |
      | codar | Write source code |

  Scenario: Student opens the lobby and can start a round
    When I log in as "student1"
    And I am on the "Word Game" "playerwords activity" page
    Then I should see "Start round"
    And "#playerwords-start-round-button" "css_element" should exist
