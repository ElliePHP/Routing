# Contributing to ElliePHP Routing

Thank you for considering contributing to ElliePHP Routing! We welcome contributions from the community.

## How to Contribute

### Reporting Bugs

If you find a bug, please open an issue on GitHub with:
- A clear description of the problem
- Steps to reproduce the issue
- Expected vs actual behavior
- PHP version and environment details
- Code samples if applicable

### Suggesting Features

Feature requests are welcome! Please open an issue with:
- A clear description of the feature
- Use cases and benefits
- Potential implementation approach (optional)

### Pull Requests

1. **Fork the repository** and create your branch from `main`
2. **Write tests** for any new functionality
3. **Ensure all tests pass**: `composer test`
4. **Follow PSR-12 coding standards**
5. **Update documentation** if needed
6. **Write clear commit messages**

#### Development Setup

```bash
# Clone your fork
git clone https://github.com/your-username/routing.git
cd routing

# Install dependencies
composer install

# Run tests
composer test

# Run tests with coverage
composer test:coverage
```

#### Running Tests

```bash
# Run all tests
vendor/bin/phpunit

# Run with testdox output
vendor/bin/phpunit --testdox

# Run specific test
vendor/bin/phpunit tests/RouterTest.php
```

#### Code Style

We follow PSR-12 coding standards. Please ensure your code:
- Uses strict types: `declare(strict_types=1);`
- Has proper type hints for parameters and return types
- Includes docblocks for public methods
- Is properly formatted and readable

#### Testing Guidelines

- Write unit tests for new features
- Ensure existing tests still pass
- Aim for high code coverage
- Test edge cases and error conditions
- Use descriptive test method names

### Documentation

When adding features:
- Update the README.md with usage examples
- Add entries to CHANGELOG.md
- Create examples in the `examples/` directory if applicable
- Update UPGRADE.md for breaking changes

## Code of Conduct

### Our Standards

- Be respectful and inclusive
- Welcome newcomers and help them learn
- Focus on constructive feedback
- Accept responsibility for mistakes
- Prioritize the community's best interests

### Unacceptable Behavior

- Harassment or discriminatory language
- Trolling or insulting comments
- Personal or political attacks
- Publishing others' private information
- Other unprofessional conduct

## Questions?

Feel free to open an issue for questions or reach out to the maintainers.

## License

By contributing, you agree that your contributions will be licensed under the MIT License.
