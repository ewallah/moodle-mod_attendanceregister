@iplus @mod @mod_attendanceregister
Feature: attendance register offline sessions
  Background:
    Given the following "courses" exist:
      | fullname | idnumber  | shortname |
      | Course 1 | ENPRO     | ENPRO     |
    And the following "users" exist:
      | username  | firstname | lastname |
      | user1     | Username  | 1        |
      | teacher1  | Teacher   | 1        |
      | manager1  | Manager   | 1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | user1    | ENPRO  | student        |
      | teacher1 | ENPRO  | editingteacher |
    And the following "system role assigns" exist:
      | user     | course | role    |
      | manager1 | ENPRO  | manager |
    And the following "activities" exist:
      | activity           | name         | intro | course | idnumber   |
      | page               | page         | Testp | ENPRO  | page1      |
      | attendanceregister | attendance   | Testa | ENPRO  | attendance |
    And I log in as "manager1"

  Scenario: Offline sessions are default not visible
    Given I am on the "attendance" "attendanceregister activity" page logged in as "teacher1"
    Then I should see "Groups"
    And I should see "Show my sessions"

  @javascript
  Scenario: Teachers should be able to add offline sessions
    # TODO: enable tracking of teachers.
    Given I am on the "page1" "page activity" page logged in as user1
    And I log out
    And I am on the "attendance" "attendanceregister activity" page logged in as "teacher1"
    And I navigate to "Settings" in current page administration
    And I set the following fields to these values:
      | Enable Offline Sessions | 1 |
    And I press "Save and display"
    And I press "Show my sessions"
    And I set the following fields to these values:
      | Comments | Comment |
    And I press "Save changes"
    And I log out
    And I trigger cron
    When I am on the "attendance" "attendanceregister activity" page logged in as manager1
    # TODO: Why is the offline session of a teacher not calculated?
    Then I should see "0 min"

  @javascript
  Scenario: Students should be able to add offline sessions
    Given I am on the "attendance" "attendanceregister activity" page logged in as "teacher1"
    And I navigate to "Settings" in current page administration
    And I set the following fields to these values:
      | Enable Offline Sessions | 1 |
    And I press "Save and display"
    And I log out
    And I am on the "attendance" "attendanceregister activity" page logged in as user1
    And I set the following fields to these values:
      | Comments | Comment |
    And I press "Save changes"
    And I log out
    And I trigger cron
    When I am on the "attendance" "attendanceregister activity" page logged in as teacher1
    Then I should see "1 h, 0 min"
