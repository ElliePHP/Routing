# Security Guidelines

## Critical Security Considerations

### 1. Debug Mode in Production

**NEVER enable debug mode in production environments.**

Debug mode exposes sensitive information including:
- Full file paths
- Stack traces
- Internal application structure
- Exception details

```php
// DANGEROUS in production
Router::configure([
    'debug_mode' => true,
]);

// CORRECT - Use environment variables
Router::configure([
    'debug_mode' => $_ENV['APP_ENV'] !== 'production',
]);
```

The router will now emit a warning if debug mode is enabled when `APP_ENV=production`.

### 2. Routes Directory Security

The routes directory is now validated to prevent path traversal attacks. Ensure your routes directory:

- Is an absolute path or relative to your application root
- Does not contain `..` path traversal sequences
- Has appropriate read permissions
- Is not writable by untrusted users

```php
// CORRECT
Router::configure([
    'routes_directory' => __DIR__ . '/routes',
]);

// DANGEROUS - could allow path traversal
Router::configure([
    'routes_directory' => $_GET['routes_path'], // Never use user input!
]);
```

### 3. Cache Security

Route cache files are now:
- Stored with unique, unpredictable filenames
- Created with restrictive permissions (0600)
- Serialized using JSON instead of PHP's `unserialize()`

Ensure your cache directory:
- Is not publicly accessible via web server
- Has appropriate permissions (typically 0700 or 0755)
- Is regularly cleaned of old cache files

```php
// CORRECT
Router::configure([
    'cache_directory' => __DIR__ . '/storage/cache',
    'cache_enabled' => true,
]);
```

### 4. CSRF Protection

This router does NOT include built-in CSRF protection. You must implement CSRF tokens for state-changing operations.

Example middleware:

```php
class CsrfMiddleware implements MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        if (in_array($request->getMethod(), ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            $token = $request->getHeaderLine('X-CSRF-Token');
            
            if (!$this->validateToken($token)) {
                throw new UnauthorizedException('Invalid CSRF token');
            }
        }
        
        return $handler->handle($request);
    }
}
```

### 5. Input Validation

Route parameters are passed directly to controllers without sanitization. Always validate and sanitize user input:

```php
Router::get('/users/{id}', function($request, $params) {
    // CORRECT - Validate input
    $id = filter_var($params['id'], FILTER_VALIDATE_INT);
    if ($id === false) {
        throw new InvalidArgumentException('Invalid user ID');
    }
    
    return ['user_id' => $id];
});
```

### 6. Rate Limiting

Implement rate limiting middleware to prevent brute force and DoS attacks:

```php
Router::group(['middleware' => [RateLimitMiddleware::class]], function() {
    Router::post('/login', [AuthController::class, 'login']);
});
```

### 7. Security Headers

Add security headers middleware to all routes:

```php
class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $response = $handler->handle($request);
        
        return $response
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('X-Frame-Options', 'DENY')
            ->withHeader('X-XSS-Protection', '1; mode=block')
            ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->withHeader('Content-Security-Policy', "default-src 'self'");
    }
}
```

## Reporting Security Vulnerabilities

If you discover a security vulnerability, please email bankuboy@proton.me instead of using the issue tracker.

## Security Checklist

- [ ] Debug mode is disabled in production
- [ ] Routes directory has proper permissions
- [ ] Cache directory is not publicly accessible
- [ ] CSRF protection is implemented for state-changing operations
- [ ] All user input is validated and sanitized
- [ ] Rate limiting is configured for sensitive endpoints
- [ ] Security headers middleware is applied
- [ ] Authentication middleware is properly implemented
- [ ] HTTPS is enforced in production
- [ ] Dependencies are regularly updated
