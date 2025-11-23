# ElliePHP Routing Examples

This directory contains working examples demonstrating various features of the ElliePHP Routing component.

## Running Examples

```bash
php examples/basic-usage.php
php examples/controller-example.php
php examples/middleware-example.php
php examples/fluent-api-example.php
php examples/domain-routing.php
php examples/non-facade-usage.php
php examples/complete-application.php
```

## Examples Overview

### basic-usage.php
Demonstrates the fundamentals:
- Simple route definition
- Route parameters
- Route groups
- Basic request handling

**Key concepts**: Closures, parameters, groups, JSON responses

### controller-example.php
Shows controller-based routing:
- Controller classes
- RESTful routes (GET, POST, PUT, DELETE)
- Parameter injection
- Alternative syntax

**Key concepts**: Controllers, REST, CRUD operations

### middleware-example.php
Explores middleware functionality:
- Creating PSR-15 middleware
- Applying middleware to routes
- Group middleware
- Closure middleware
- Middleware execution order
- Fluent syntax for middleware

**Key concepts**: PSR-15, middleware stack, request/response modification

### fluent-api-example.php
Demonstrates the fluent method chaining API:
- Fluent route configuration with chaining
- Fluent group configuration
- Starting groups with different methods (prefix, middleware, domain, name)
- Configuration order independence
- Multiple middleware calls (merging)
- Nested groups with fluent syntax
- Multi-tenant routing with fluent API
- Mixed array and fluent syntax usage
- Real-world API structure examples
- Progressive/conditional configuration

**Key concepts**: Method chaining, fluent API, expressive syntax, IDE autocomplete

### domain-routing.php
Demonstrates domain-based routing:
- Domain constraints on routes
- Subdomain routing (api.example.com, admin.example.com)
- Multi-tenant routing with domain parameters
- Domain groups
- Domain enforcement and whitelisting

**Key concepts**: Subdomains, multi-tenancy, domain parameters, SaaS applications

### non-facade-usage.php
Demonstrates direct usage without the static facade:
- Creating `Routing` instance directly
- Configuration options
- Instance methods
- Debug features

**Key concepts**: Non-static usage, dependency injection, instance configuration

### complete-application.php
A comprehensive example showing:
- Full application structure
- Multiple controllers
- Multiple middleware
- Nested route groups
- Logging and CORS
- API versioning
- Complete request/response cycle

**Key concepts**: Real-world application, best practices, production patterns

## What You'll Learn

### Basic Concepts
- Defining routes with closures and controllers
- Handling route parameters
- Returning JSON responses
- Working with PSR-7 requests and responses

### Intermediate Concepts
- Organizing routes with groups
- Applying middleware
- Using prefixes and nested groups
- Controller method injection

### Advanced Concepts
- Creating custom middleware
- Middleware execution order
- Non-facade usage for testing
- Debug mode and route inspection
- Building complete applications

## Next Steps

After exploring these examples:

1. Read the [complete documentation](../README.md)
2. Review the [test suite](../tests/) for more usage patterns
3. Check the [changelog](../CHANGELOG.md) for version history
4. See the [upgrade guide](../UPGRADE.md) for migration tips

## Tips

- Run examples with `php examples/filename.php`
- All examples use debug mode for detailed output
- Examples are self-contained and can be modified
- Check the output to understand execution flow
- Use these as templates for your own applications
