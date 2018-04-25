@mod @mod_assign
Feature: Prevent or allow submission changes after the assignment due date
  In order to control when a student can change his/her submission
  As a teacher
  I need to prevent or allow student submission changes after the assignment due date

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category | groupmode |
      | Course 1 | C1        | 0        | 1         |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
      | student2 | Student   | 2        | student2@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
    And the following "activities" exist:
      | activity | course | idnumber | name                 | intro                       | duedate       | assignsubmission_onlinetext_enabled | submissiondrafts | preventlateresubmissions |
      | assign   | C1     | assign1  | Test assignment name | Test assignment description | ##yesterday## | 1                                   | 0                | 1                        |

  @javascript
  Scenario: Preventing changes after the due date and allowing them again
    Given I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test assignment name"
    When I press "Add submission"
    And I set the following fields to these values:
      | Online text | I'm the student submission |
    And I press "Save changes"
    Then I should not see "You can still make changes to your submission"
    And "Edit submission" "button" should not exist
    And I log out
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test assignment name"
    And I navigate to "Edit settings" in current page administration
    And I set the following fields to these values:
      | preventlateresubmissions | 0 |
    And I press "Save and display"
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test assignment name"
    And I should see "You can still make changes to your submission"
    When I press "Edit submission"
    And I set the following fields to these values:
      | Online text | I'm the student submission and he/she edited me |
    And I press "Save changes"
    Then I should see "I'm the student submission and he/she edited me"
    And I log out
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test assignment name"
    And I navigate to "Edit settings" in current page administration
    And I set the following fields to these values:
      | duedate[year] | 2050 |
      | preventlateresubmissions | 1 |
    And I press "Save and display"
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test assignment name"
    And I should see "You can still make changes to your submission"
    When I press "Edit submission"
    And I set the following fields to these values:
      | Online text | I'm the student submission edited again |
    And I press "Save changes"
    Then I should see "I'm the student submission edited again"
    And I log out

  @javascript
  Scenario: Preventing changes after the due date and allowing them for specific students by granting an extension
    Given I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test assignment name"
    When I press "Add submission"
    And I set the following fields to these values:
      | Online text | I'm student 1's submission |
    And I press "Save changes"
    Then I should not see "You can still make changes to your submission"
    And "Edit submission" "button" should not exist
    And I log out
    And I log in as "student2"
    And I am on "Course 1" course homepage
    And I follow "Test assignment name"
    When I press "Add submission"
    And I set the following fields to these values:
      | Online text | I'm student 2's submission |
    And I press "Save changes"
    Then I should not see "You can still make changes to your submission"
    And "Edit submission" "button" should not exist
    And I log out
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test assignment name"
    And I navigate to "View all submissions" in current page administration
    And I click on "Edit" "link" in the "Student 1" "table_row"
    And I follow "Grant extension"
    And I should see "Student 1 (student1@example.com)"
    And I set the following fields to these values:
      | extensionduedate[enabled] | 1 |
      | extensionduedate[year] | 2050 |
    And I press "Save changes"
    And I should see "Extension granted until:" in the "Student 1" "table_row"
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test assignment name"
    And I should see "You can still make changes to your submission"
    When I press "Edit submission"
    And I set the following fields to these values:
      | Online text | I'm student 1's submission and he/she edited me |
    And I press "Save changes"
    Then I should see "I'm student 1's submission and he/she edited me"
    And I log out
    And I log in as "student2"
    And I am on "Course 1" course homepage
    When I follow "Test assignment name"
    Then I should not see "You can still make changes to your submission"
    And "Edit submission" "button" should not exist
    And I log out
