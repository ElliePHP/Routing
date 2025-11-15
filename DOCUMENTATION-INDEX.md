# ElliePHP Routing Documentation Index

## Main Documentation

### [README.md](README.md)
Complete documentation covering all features:
- Installation & Quick Start
- Route Definition (GET, POST, PUT, DELETE, PATCH)
- Controllers & Handlers
- Route Groups & Nesting
- Domain Routing & Multi-Tenant Applications
- **Global Middleware** (Section: Middleware → Global Middleware)
- Route-Specific Middleware
- Configuration Options
- Performance & Caching
- Debug Features
- Testing
- Complete Examples

**Key Sections:**
- Line 733-830: Global Middleware documentation
- Line 869-906: Configuration Options (includes global_middleware)
- Line 1000-1080: Performance & Caching

## Quick References

### [QUICK-START-GLOBAL-MIDDLEWARE.md](QUICK-START-GLOBAL-MIDDLEWARE.md)
1-minute setup guide for global middleware:
- Quick setup example
- What is global middleware?
- Execution order
- Example middleware implementations
- Framework integration
- Best practices
- Testing

### [GLOBAL-MIDDLEWARE-GUIDE.md](GLOBAL-MIDDLEWARE-GUIDE.md)
Comprehensive guide to global middleware:
- Overview & configuration
- Execution order details
- Common use cases with code examples
- Framework integration patterns
- Benefits & best practices
- Testing strategies
- Performance impact
- Migration guide

## Code Examples

### [example-global-middleware.php](example-global-middleware.php)
Working example demonstrating:
- RequestIdMiddleware
- LoggingMiddleware
- CorsMiddleware
- AuthMiddleware (route-specific)
- Execution order visualization
- Testing different scenarios

### [example-http-application.php](example-http-application.php)
Framework kernel pattern example:
- HttpApplication class
- Global middleware configuration
- Route loading
- Request handling
- Response emission

### [index.php](index.php)
Basic usage example:
- Router configuration
- Route definition
- PSR-7 request/response handling
- Both array and ResponseInterface returns

## Additional Examples

### [example-custom-formatter.php](RadioAPIErrorFormatter-fixed.php)
Custom error formatter implementation showing:
- RouteNotFoundException handling
- RouterException handling
- Debug vs production modes
- Safe error message exposure

## Testing Files

### [test-error-formatting.php](test-error-formatting.php)
Tests error formatting behavior:
- Debug mode ON/OFF
- RouteNotFoundException handling
- Server error handling

### [test-custom-formatter.php](test-custom-formatter.php)
Tests custom error formatter:
- Custom formatter implementation
- Debug mode behavior
- Status code handling

## Feature Documentation

### Global Middleware

**Where to find it:**
1. **README.md** - Lines 733-830 (complete documentation)
2. **QUICK-START-GLOBAL-MIDDLEWARE.md** - Quick reference
3. **GLOBAL-MIDDLEWARE-GUIDE.md** - Comprehensive guide
4. **example-global-middleware.php** - Working code example
5. **example-http-application.php** - Framework integration

**What's covered:**
- ✅ Configuration syntax
- ✅ Execution order (global → route → handler)
- ✅ Framework integration patterns
- ✅ Common use cases (logging, CORS, request ID, security)
- ✅ Best practices
- ✅ Performance impact
- ✅ Testing strategies
- ✅ Migration guide

### Performance Optimizations

**Where to find it:**
- **README.md** - Lines 1000-1080 (Performance & Caching section)

**What's covered:**
- ✅ Route caching
- ✅ Dispatcher caching per domain
- ✅ Reflection metadata caching
- ✅ Domain regex caching
- ✅ Smart cache validation (5-second trust window)
- ✅ Route hash invalidation
- ✅ Performance benchmarks
- ✅ Memory usage
- ✅ Production recommendations

### Domain Routing

**Where to find it:**
- **README.md** - Lines 300-650 (Domain Routing section)

**What's covered:**
- ✅ Basic domain constraints
- ✅ Domain groups
- ✅ Domain parameters (multi-tenant)
- ✅ Multiple domain parameters
- ✅ Domain configuration
- ✅ Complete multi-tenant example

### Error Handling

**Where to find it:**
- **README.md** - Configuration section
- **src/Core/ErrorFormatter.php** - Implementation
- **RadioAPIErrorFormatter-fixed.php** - Custom formatter example

**What's covered:**
- ✅ RouteNotFoundException handling
- ✅ RouterException handling
- ✅ Debug vs production modes
- ✅ Custom error formatters
- ✅ Safe error message exposure

## Quick Navigation

| Feature | Quick Start | Full Docs | Example Code |
|---------|------------|-----------|--------------|
| **Global Middleware** | [Quick Start](QUICK-START-GLOBAL-MIDDLEWARE.md) | [README](README.md#global-middleware) / [Guide](GLOBAL-MIDDLEWARE-GUIDE.md) | [example-global-middleware.php](example-global-middleware.php) |
| **Domain Routing** | [README](README.md#domain-routing) | [README](README.md#domain-routing) | [README Examples](README.md#multi-tenant-application-example) |
| **Performance** | [README](README.md#enable-caching) | [README](README.md#performance--caching) | [README](README.md#production-recommendations) |
| **Route Groups** | [README](README.md#basic-groups) | [README](README.md#route-groups) | [README Examples](README.md#nested-groups) |
| **Controllers** | [README](README.md#basic-controller) | [README](README.md#controllers) | [README Examples](README.md#registering-controller-routes) |
| **Middleware** | [README](README.md#creating-middleware) | [README](README.md#middleware) | [example-global-middleware.php](example-global-middleware.php) |

## Getting Started

1. **New to the router?** Start with [README.md](README.md#quick-start)
2. **Need global middleware?** Check [QUICK-START-GLOBAL-MIDDLEWARE.md](QUICK-START-GLOBAL-MIDDLEWARE.md)
3. **Building multi-tenant app?** See [README.md - Domain Routing](README.md#domain-routing)
4. **Optimizing for production?** Read [README.md - Performance](README.md#performance--caching)
5. **Framework integration?** See [example-http-application.php](example-http-application.php)

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for contribution guidelines.

## License

MIT License - See [LICENSE](LICENSE)
