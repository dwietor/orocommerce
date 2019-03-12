@regression
@ticket-BB-15117
@fixture-OroShoppingListBundle:ShoppingListFixtureWithCustomers.yml

Feature: Shopping list without permissions
  In order to check frontstore shopping list acl
  As a customer user
  I want to check user and guest cannot see shopping list buttons without permissions

  Scenario: Create different window session
    Given sessions active:
      | Admin | first_session  |
      | Buyer | second_session |
      | Guest | system_session |

  Scenario: Ensure that shopping list block didn't visible for guest by default
    Given I proceed as the Guest
    When I am on homepage
    Then I should not see "Shopping list"
    When I open product with sku "PSKU1" on the store frontend
    Then I should not see "Add to Shopping List"

  Scenario: Enable guest shopping lists
    Given I proceed as the Admin
    And I login as administrator
    Given I go to System/ Configuration
    When I follow "Commerce/Sales/Shopping List" on configuration sidebar
    Then the "Enable guest shopping list" checkbox should not be checked
    When uncheck "Use default" for "Enable guest shopping list" field
    And I check "Enable guest shopping list"
    And I save setting
    Then I should see "Configuration saved" flash message
    And the "Enable guest shopping list" checkbox should be checked

  Scenario: Ensure that shopping list block is visible for guest by default
    Given I proceed as the Guest
    When I reload the page
    Then I should see "Shopping list"
    When I open product with sku "PSKU1" on the store frontend
    Then I should see "Add to Shopping List"

  Scenario: Ensure that shopping list block is visible for user by default
    Given I proceed as the Buyer
    And I login as AmandaRCole@example.org buyer
    And I open shopping list widget
    And I should see "Shopping List 1" on shopping list widget
    And I click "Shopping List 1" on shopping list widget
    When I am on homepage
    Then I should see "Shopping list"
    When I open product with sku "PSKU1" on the store frontend
    Then I should see "Add to Shopping List"

  Scenario: Update buyer shopping list permissions
    Given I proceed as the Admin
    And I go to Customers/ Customer User Roles
    And I click edit "Buyer" in grid
    And select following permissions:
      | Shopping List | View:None | Create:None | Edit:None | Delete:None | Assign:None | Duplicate:None |
    When I save form
    Then I should see "Customer User Role has been saved" flash message

  Scenario: Check that buyer cannot see shopping list block and button
    Given I proceed as the Buyer
    When I am on homepage
    Then I should not see "Shopping list"
    When I open product with sku "PSKU1" on the store frontend
    Then I should not see "Add to Shopping List"
    When I click "NewCategory"
    Then I should not see "You do not have permission to perform this action." flash message

  Scenario: Set view shopping list permissions
    Given I proceed as the Admin
    And select following permissions:
      | Shopping List | View:User |
    When I save form
    Then I should see "Customer User Role has been saved" flash message
    And I proceed as the Buyer
    And I reload the page
    When I open shopping list widget
    Then I should see "Shopping List 1" on shopping list widget
    And I click "NewCategory"
    Then I should not see mass action checkbox in row with PSKU1 content for "Product Frontend Grid"

  Scenario: Set create shopping list permissions
    And I proceed as the Admin
    And select following permissions:
      | Shopping List | Create:User |
    When I save form
    Then I should see "Customer User Role has been saved" flash message
    And I proceed as the Buyer
    And I reload the page
    When I check PSKU1 record in "Product Frontend Grid" grid
    And I click "ProductFrontendMassActionButton"
    Then I should not see "Add to Shopping List 1" in the "ProductFrontendGridFloatingMenu" element
    And I should see "Create New Shopping List" in the "ProductFrontendGridFloatingMenu" element
    And I uncheck PSKU1 record in "Product Frontend Grid" grid

  Scenario: Set edit shopping list permissions
    Given I proceed as the Admin
    And select following permissions:
      | Shopping List | Edit:User |
    When I save form
    Then I should see "Customer User Role has been saved" flash message
    And I proceed as the Buyer
    And I reload the page
    When I check PSKU1 record in "Product Frontend Grid" grid
    And I click "ProductFrontendMassActionButton"
    Then I should see "Add to Shopping List 1" in the "ProductFrontendGridFloatingMenu" element
    And I should see "Create New Shopping List" in the "ProductFrontendGridFloatingMenu" element
    And I uncheck PSKU1 record in "Product Frontend Grid" grid

  Scenario: Set edit shopping list permissions
    Given I proceed as the Admin
    And select following permissions:
      | Shopping List | Edit:User |
    And I save and close form
    Then I should see "Customer User Role has been saved" flash message
    And I proceed as the Buyer
    When I open product with sku "PSKU1" on the store frontend
    Then I should see "Add to Shopping List"
