@mod @mod_playerwords @javascript
Feature: PlayerWords attempt history and ranking
  As a student or teacher
  I want to see attempt history and ranking data
  So that I can track progress across rounds

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

  Scenario: A student sees only their own attempt history, never another student's
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student2 | Student   | Two      | student2@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student2 | C1      | student |
    And the following "activities" exist:
      | activity    | course | name        | min_length | max_length |
      | playerwords | C1     | Word Report | 5          | 5          |
    And the following PlayerWords words exist in activity "Word Report":
      | word  |
      | codar |
    And the following PlayerWords attempts exist in activity "Word Report":
      | user     | word  | score  |
      | student1 | codar | 70.00  |
      | student2 | codar | 45.00  |
    And I log in as "student1"
    And I am on the "Word Report" "playerwords activity" page
    And I click on "a.pw-toolbar-btn[title=\"Attempt history\"]" "css_element"
    Then I should see "My attempts"
    And I should see "70.00"
    And I should not see "45.00"

  Scenario: The teacher's all-students report paginates past 30 rows
    Given the following "activities" exist:
      | activity    | course | name          | min_length | max_length |
      | playerwords | C1     | Word Pagination | 5        | 5          |
    And the following PlayerWords words exist in activity "Word Pagination":
      | word  |
      | codar |
    And 31 PlayerWords attempts exist for "student1" with word "codar" in activity "Word Pagination"
    And I log in as "teacher1"
    And I am on the "Word Pagination" "playerwords activity" page
    And I click on "a.pw-toolbar-btn[title=\"Attempt history\"]" "css_element"
    Then I should see "Attempt history — All students"
    And "li[data-page-number=\"2\"] a.page-link" "css_element" should exist
    When I click on "li[data-page-number=\"2\"] a.page-link" "css_element"
    Then I should see "Attempt history — All students"

  Scenario: The teacher's all-students report sorts by clicking a column header
    Given the following "activities" exist:
      | activity    | course | name       | min_length | max_length |
      | playerwords | C1     | Word Sort  | 5          | 5          |
    And the following PlayerWords words exist in activity "Word Sort":
      | word  |
      | codar |
    And the following PlayerWords attempts exist in activity "Word Sort":
      | user     | word  | score  |
      | student1 | codar | 20.00  |
      | student1 | codar | 90.00  |
    And I log in as "teacher1"
    And I am on the "Word Sort" "playerwords activity" page
    And I click on "a.pw-toolbar-btn[title=\"Attempt history\"]" "css_element"
    When I click on "Score" "link"
    Then I should see "Score ▲" in the "table.mod-playerwords-myattempts-table thead" "css_element"
    And I should see "20.00" in the "table.mod-playerwords-myattempts-table tbody tr:nth-child(1)" "css_element"

  Scenario: The teacher's all-students report filters to a single student
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student2 | Student   | Two      | student2@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student2 | C1      | student |
    And the following "activities" exist:
      | activity    | course | name         | min_length | max_length |
      | playerwords | C1     | Word Filter  | 5          | 5          |
    And the following PlayerWords words exist in activity "Word Filter":
      | word  |
      | codar |
    And the following PlayerWords attempts exist in activity "Word Filter":
      | user     | word  | score  |
      | student1 | codar | 70.00  |
      | student2 | codar | 45.00  |
    And I log in as "teacher1"
    And I am on the "Word Filter" "playerwords activity" page
    And I click on "a.pw-toolbar-btn[title=\"Attempt history\"]" "css_element"
    And I should see "70.00"
    And I should see "45.00"
    When I set the field "playerwords-filter-student" to "Student One"
    And I click on "Filter" "button"
    Then I should see "70.00"
    And I should not see "45.00"

  Scenario: The ranking page shows the top 5 plus the current user's row when outside it
    Given the following "users" exist:
      | username | firstname | lastname | email                 |
      | player3  | Player    | Three    | player3@example.com   |
      | player4  | Player    | Four     | player4@example.com   |
      | player5  | Player    | Five     | player5@example.com   |
      | player6  | Player    | Six      | player6@example.com   |
      | player7  | Player    | Seven    | player7@example.com   |
    And the following "course enrolments" exist:
      | user    | course | role    |
      | player3 | C1     | student |
      | player4 | C1     | student |
      | player5 | C1     | student |
      | player6 | C1     | student |
      | player7 | C1     | student |
    And the following "activities" exist:
      | activity    | course | name          | min_length | max_length | show_ranking |
      | playerwords | C1     | Word Ranking  | 5          | 5          | 1            |
    And the following PlayerWords words exist in activity "Word Ranking":
      | word  |
      | codar |
    And the following PlayerWords attempts exist in activity "Word Ranking":
      | user     | word  | rankingpoints |
      | player3  | codar | 600.00        |
      | player4  | codar | 500.00        |
      | player5  | codar | 400.00        |
      | player6  | codar | 300.00        |
      | player7  | codar | 200.00        |
      | student1 | codar | 100.00        |
    And I log in as "student1"
    And I am on the "Word Ranking" "playerwords activity" page
    And I click on "a.pw-toolbar-btn[title=\"Top 5\"]" "css_element"
    Then I should see "Ranking"
    And I should see "Ties are broken by fewer attempts used on average"
    And "tr.pw-ranking-you" "css_element" should exist
