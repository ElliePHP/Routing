# PSR-11 Container Support

The ElliePHP Routing component now supports PSR-11 containers for automatic dependency injection.

## What Was Added

### 1. Container Integration
- Added `psr/container` dependency to `composer.json`
- Added `container` parameter to `Router::configure()`
- Added `container` parameter to `Routing` class constructor

### 2. Automatic Resolution
- **Controllers**: Resolved from container if available, otherwise instantiated directly
- **Middleware**: Resolved from container if available, otherwise instantiated directly
- **Fallback**: If a class is not in the container, it's created with `new ClassName()`

### 3. Files Modified
- `composer.json` - Added PSR-11 dependency
- `src/Router.php` - Added container configuration option
- `src/Core/Routing.php` - Added container support and `resolveController()` method
- `src/Core/MiddlewareAdapter.php` - Added container-based middleware resolution
- `README.md` - Added comprehensive documentation
- `examples/container-example.php` - Working example with DI

### 4. Tests
- `tests/ContainerTest.php` - Full test coverage for container features
- Tests use `Routing` class directly (not facade) to avoid static state pollution
- All existing tests still pass

## Usage

```php
use ElliePHP\Components\Routing\Router;

// Configure with your PSR-11 container
Router::configure([
    'container' => $container,
]);

// Controllers and middleware will be resolved from container
Router::get('/users/{id}', [UserController::class, 'show']);
```

## Benefits

1. **Dependency Injection**: Controllers and middleware can have dependencies injected
2. **Testability**: Easy to mock dependencies in tests
3. **Flexibility**: Works with any PSR-11 container (PHP-DI, Symfony, Laravel, etc.)
4. **Backward Compatible**: Works without a container (falls back to direct instantiation)
5. **No Breaking Changes**: Existing code continues to work

## Example

See `examples/container-example.php` for a complete working example with:
- Simple PSR-11 container implementation
- Service registration
- Controller with dependency injection
- Automatic resolution

## Testing

Run tests with:
```bash
composer test
```

All 43 tests pass, including 4 new container-specific tests.
