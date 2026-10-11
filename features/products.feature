Feature: Managing catalog products
  In order to keep the shop catalog up to date
  As an API client
  I need to create, read, update and delete products linked to categories

  Background:
    Given the catalog is empty
    And a user "admin@example.com" with password "admin" and role "ROLE_ADMIN"
    And a user "reader@example.com" with password "reader" and role "ROLE_USER"
    And the following categories exist:
      | code    |
      | ELECTRO |
      | OFFICE  |

  Scenario: Anonymous clients cannot read the catalog
    Given I am not authenticated
    When I send a "GET" request to "/api/products"
    Then the response status code should be 401

  Scenario: Creating a product linked to two categories
    Given I am authenticated as "admin@example.com" with password "admin"
    When I send a "POST" request to "/api/products" with body:
      """
      {
        "name": "Mechanical keyboard",
        "price": { "amount": 34999, "currency": "PLN" },
        "categoryCodes": ["ELECTRO", "OFFICE"]
      }
      """
    Then the response status code should be 201
    And the JSON node "name" should be equal to "Mechanical keyboard"
    And the JSON node "price.amount" should be equal to "34999"
    And the JSON node "categories" should have 2 elements
    And the JSON node "createdAt" should be equal to the "updatedAt" node

  Scenario: Saving a product notifies every channel exactly once
    Given I am authenticated as "admin@example.com" with password "admin"
    And the notification queue is empty
    When I send a "POST" request to "/api/products" with body:
      """
      {
        "name": "Ergonomic chair",
        "price": { "amount": 129900, "currency": "PLN" },
        "categoryCodes": ["OFFICE"]
      }
      """
    Then the response status code should be 201
    And the notification queue should contain 2 messages
    And a notification should be queued for the "operation_log" channel with subject "Product created: Ergonomic chair"
    And a notification should be queued for the "email" channel with subject "Product created: Ergonomic chair"

  Scenario: A product cannot exist without a category
    Given I am authenticated as "admin@example.com" with password "admin"
    When I send a "POST" request to "/api/products" with body:
      """
      {
        "name": "Orphan",
        "price": { "amount": 1000, "currency": "PLN" },
        "categoryCodes": []
      }
      """
    Then the response status code should be 422
    And the response should report a violation on "categoryCodes"

  Scenario: Referencing an unknown category is rejected
    Given I am authenticated as "admin@example.com" with password "admin"
    When I send a "POST" request to "/api/products" with body:
      """
      {
        "name": "Ghost",
        "price": { "amount": 1000, "currency": "PLN" },
        "categoryCodes": ["NOPE"]
      }
      """
    Then the response status code should be 422
    And the response should report a violation on "categoryCodes"

  Scenario: A negative price is rejected
    Given I am authenticated as "admin@example.com" with password "admin"
    When I send a "POST" request to "/api/products" with body:
      """
      {
        "name": "Free lunch",
        "price": { "amount": -1, "currency": "PLN" },
        "categoryCodes": ["OFFICE"]
      }
      """
    Then the response status code should be 422
    And the response should report a violation on "price.amount"

  Scenario: A price that does not fit the storage is rejected
    Given I am authenticated as "admin@example.com" with password "admin"
    When I send a "POST" request to "/api/products" with body:
      """
      {
        "name": "Priceless",
        "price": { "amount": 4294967296, "currency": "PLN" },
        "categoryCodes": ["OFFICE"]
      }
      """
    Then the response status code should be 422
    And the response should report a violation on "price.amount"

  Scenario: Category codes are matched regardless of case and surrounding whitespace
    Given I am authenticated as "admin@example.com" with password "admin"
    When I send a "POST" request to "/api/products" with body:
      """
      {
        "name": "Desk lamp",
        "price": { "amount": 9900, "currency": "PLN" },
        "categoryCodes": [" electro ", "Office"]
      }
      """
    Then the response status code should be 201
    And the JSON node "categories" should have 2 elements

  Scenario: A malformed product identifier is not found
    Given I am authenticated as "reader@example.com" with password "reader"
    When I send a "GET" request to "/api/products/not-a-uuid"
    Then the response status code should be 404

  Scenario: Listing products is paginated and readable by any authenticated user
    Given the following products exist:
      | name                | amount | currency | categories       |
      | Mechanical keyboard | 34999  | PLN      | ELECTRO, OFFICE  |
      | Ergonomic chair     | 129900 | PLN      | OFFICE           |
    And I am authenticated as "reader@example.com" with password "reader"
    When I send a "GET" request to "/api/products?itemsPerPage=1"
    Then the response status code should be 200
    And the JSON node "totalItems" should be equal to "2"
    And the JSON node "member" should have 1 element

  Scenario: A plain user may not modify the catalog
    Given I am authenticated as "reader@example.com" with password "reader"
    When I send a "POST" request to "/api/products" with body:
      """
      {
        "name": "Not allowed",
        "price": { "amount": 100, "currency": "PLN" },
        "categoryCodes": ["OFFICE"]
      }
      """
    Then the response status code should be 403

  Scenario: Responses carry baseline hardening headers
    Given I am authenticated as "reader@example.com" with password "reader"
    When I send a "GET" request to "/api/products"
    Then the response status code should be 200
    And the response header "X-Content-Type-Options" should be "nosniff"
    And the response header "Referrer-Policy" should be "no-referrer"
    And the response header "X-RateLimit-Limit" should be "100000"
