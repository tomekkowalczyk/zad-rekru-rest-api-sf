Feature: Managing catalog categories
  In order to classify products
  As an API client
  I need categories identified by a unique, short code

  Background:
    Given the catalog is empty
    And a user "admin@example.com" with password "admin" and role "ROLE_ADMIN"
    And I am authenticated as "admin@example.com" with password "admin"

  Scenario: Creating a category
    When I send a "POST" request to "/api/categories" with body:
      """
      { "code": "ELECTRO" }
      """
    Then the response status code should be 201
    And the JSON node "code" should be equal to "ELECTRO"

  Scenario: Category codes are unique
    Given the following categories exist:
      | code    |
      | ELECTRO |
    When I send a "POST" request to "/api/categories" with body:
      """
      { "code": "ELECTRO" }
      """
    Then the response status code should be 422
    And the response should report a violation on "code"

  Scenario: Category codes are limited to ten characters
    When I send a "POST" request to "/api/categories" with body:
      """
      { "code": "ABCDEFGHIJK" }
      """
    Then the response status code should be 422
    And the response should report a violation on "code"

  Scenario: Surrounding whitespace does not bypass the uniqueness check
    Given the following categories exist:
      | code    |
      | ELECTRO |
    When I send a "POST" request to "/api/categories" with body:
      """
      { "code": " ELECTRO " }
      """
    Then the response status code should be 422
    And the response should report a violation on "code"

  Scenario: Deleting an unused category
    Given the following categories exist:
      | id                                   | code    |
      | 0199d0c0-0000-7000-8000-000000000001 | ELECTRO |
    When I send a "DELETE" request to "/api/categories/0199d0c0-0000-7000-8000-000000000001"
    Then the response status code should be 204

  Scenario: A category still assigned to a product cannot be deleted
    Given the following categories exist:
      | id                                   | code    |
      | 0199d0c0-0000-7000-8000-000000000001 | ELECTRO |
    And the following products exist:
      | id                                   | name     | amount | currency | categories |
      | 0199d0c0-0000-7000-8000-0000000000a1 | Keyboard | 34999  | PLN      | ELECTRO    |
    When I send a "DELETE" request to "/api/categories/0199d0c0-0000-7000-8000-000000000001"
    Then the response status code should be 409
    When I send a "GET" request to "/api/products/0199d0c0-0000-7000-8000-0000000000a1"
    Then the response status code should be 200
    And the JSON node "categories" should have 1 element

  Scenario: A malformed identifier is not found
    When I send a "GET" request to "/api/categories/not-a-uuid"
    Then the response status code should be 404
