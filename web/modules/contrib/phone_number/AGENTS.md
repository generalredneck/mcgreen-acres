# Agent Guidelines for phone_number Drupal Module

## Project Overview

This is a Drupal 10/11 module that provides a validated phone number field using the `giggsey/libphonenumber-for-php` library. The module provides field types, widgets, formatters, and validation constraints for phone numbers.

## Build/Lint/Test Commands

### Prerequisites

- Docker + DDEV installed
- Run `ddev start` before testing

### First-time Setup (one-time)
```bash
ddev config --project-type=drupal --docroot=web --php-version=8.3 --corepack-enable
ddev add-on get ddev/ddev-drupal-contrib
ddev start
ddevposer
ddev symlink-project
```

### Running Tests
```bash
ddev phpunit                    # Run all tests
ddev phpunit --filter TestName # Run single test class
```

### Changing Drupal Core Version
```bash
ddev core-version ^11          # Switch to Drupal 11
```

### Static Analysis
```bash
ddev phpstan    # PHPStan (level 1, see phpstan.neon)
ddev phpcs      # PHP CodeSniffer
ddev phpcbf     # Fix coding standards
```

### JavaScript and CSS Linting
```bash
ddev eslint     # Run ESLint on JavaScript files
ddev stylelint  # Run Stylelint on CSS files
```

**Note:** This project prefers fixing actual code issues over adding lint ignore comments (e.g., `eslint-disable`). Only add ignore comments as a last resort when fixing would break functionality.

### Spell Checking

This project uses the cspell command from [jameswilson/ddev-drupal-contrib](https://github.com/jameswilson/ddev-drupal-contrib/tree/cspell) to run spell checking locally.

```bash
# First-time: Fetch the cspell command from jameswilson/ddev-drupal-contrib
curl -OL https://raw.githubusercontent.com/jameswilson/ddev-drupal-contrib/cspell/commands/web/cspell
chmod +x cspell
mv cspell .ddev/commands/web/

# First-time only: Install yarn dependencies in Drupal core
ddev exec "cd /var/www/html/web/core && yarn install"

# Run cspell (like GitLab CI)
ddev cspell
```

For complete ddev-drupal-contrib documentation, see: https://github.com/ddev/ddev-drupal-contrib

## CI/CD Pipeline

This project uses [DrupalCI GitLab templates](https://git.drupalcode.org/project/gitlab_templates/) for CI. The pipeline runs on merge requests and pushes.

### Pipeline Stages

| Stage | Description |
|-------|-------------|
| PHP Syntax | Validates PHP file syntax |
| CodeSniffer | Drupal coding standards (see `phpcs.xml.dist`) |
| PHPStan | Static analysis (see `phpstan.neon`) |
| PHPUnit | Functional tests (BrowserTestBase) |

### Running CI Checks Locally

| CI Stage | Local Command |
|----------|---------------|
| CodeSniffer | `ddev phpcs` |
| CodeSniffer (fix) | `ddev phpcbf` |
| PHPStan | `ddev phpstan` |
| PHPUnit | `ddev phpunit` |

**Note:** Ensure DDEV is running (`ddev start`) before running local tests.

## Code Quality

This project follows Drupal coding standards:
- 2-space indentation (per .editorconfig)
- Use `Drupal::service()` or dependency injection for services

## Code Style Guidelines

### General PHP Conventions

- Use `<?php` without closing tag
- Always use `declare(strict_types=1)` at the top of PHP files (after the namespace)
- Use return type declarations
- Max line length: 80 characters

### Naming Conventions

- **Classes**: `UpperCamelCase` (e.g., `PhoneNumberUtil`, `PhoneNumberWidget`)
- **Methods**: `lowerCamelCase` (e.g., `getPhoneNumber()`, `settingsForm()`)
- **Properties**: `$camelCase` (e.g., `$phoneNumberUtil`, `$libUtil`)
- **Constants**: `UPPER_SNAKE_CASE`
- **Plugin IDs**: `lowercase_with_underscores`

### Imports and Namespaces

- Group imports: PHP internal, vendor, Drupal, local
- Use `use` statements for classes used more than once
- Prefer fully qualified class names for single-use classes in documentation

```php
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\phone_number\PhoneNumberUtilInterface;
use libphonenumber\PhoneNumberUtil as LibPhoneNumberUtil;
```

### DocBlocks

All classes, methods, and properties should have docblocks:

```php
/**
 * The Phone Number field utility.
 */
class PhoneNumberUtil implements PhoneNumberUtilInterface {

  /**
   * The PhoneNumberUtil object.
   *
   * @var \libphonenumber\PhoneNumberUtil
   */
  protected LibPhoneNumberUtil $libUtil;
}
```

### Drupal Patterns

**Dependency Injection** (use constructor property promotion):
```php
public function __construct(
  protected ConfigFactoryInterface $configFactory,
  protected EntityFieldManagerInterface $fieldManager,
) {}
```

**Drupal Plugin System** (use PHP attributes):
```php
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the phone number widget.
 */
#[Widget(
  id: "phone_number_default",
  label: new TranslatableMarkup("Phone Number"),
  field_types: ["phone_number"],
)]
class PhoneNumberWidget extends WidgetBase {
  // Plugin implementation.
}
```

**Using String Translation**:
```php
use Drupal\Core\StringTranslation\StringTranslationTrait;

// In class:
$this->t('Error message');

// Or directly:
$t = \Drupal::service('string_translation');
$t->translate('Message');
```

### Error Handling

- Throw specific exceptions for different error types
- Use custom exception classes in `src/Exception/`
- Catch library exceptions and convert to module exceptions

```php
try {
  $phoneNumber = $this->libUtil->parse($number, $country);
}
catch (NumberParseException $e) {
  throw new ParseException('Invalid number', 0, $e);
}
```

### Form API

- Use `#type`, `#title`, `#options`, `#default_value`, `#description`
- Always set `#required` appropriately
- Use validation callbacks for complex validation

### Field API

- Implement `FieldItemInterface` for field types
- Implement `WidgetInterface` for widgets
- Implement `FormatterInterface` for formatters
- Use PHP attributes for plugin discovery

### Testing

- Tests go in `tests/src/`
- Use `PhoneNumberCreationTrait` for creating phone number fields
- Functional tests extend `BrowserTestBase`
- FunctionalJavascript tests extend `JavascriptTestBase`

```php
use Drupal\Tests\phone_number\Traits\PhoneNumberCreationTrait;

class PhoneNumberFieldWidgetSettingsTest extends BrowserTestBase {
  use PhoneNumberCreationTrait;

  protected function setUp(): void {
    parent::setUp();
    $this->createPhoneNumberField();
  }
}
```

### File Structure

```
src/
  Plugin/
    Field/
      FieldType/     # Field type plugins
      FieldWidget/   # Widget plugins
      FieldFormatter/# Formatter plugins
    Validation/
      Constraint/    # Constraint validators
    WebformElement/ # Webform integration
  Exception/         # Custom exception classes
  Element/           # Form elements
  Feeds/Target/      # Feeds integration
tests/
  src/
    Functional/     # Browser tests
    FunctionalJavascript/ # JS tests
    Traits/         # Test traits
```

### Type Hints and Return Types

Use type hints where possible:
```php
public function getPhoneNumber(?string $number, ?string $country = NULL): ?\libphonenumber\PhoneNumber;
```

### Working with libphonenumber

```php
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberType;

// Parse a number
$phoneNumber = $libUtil->parse('+14155551234', 'US');

// Format a number
$formatted = $libUtil->format($phoneNumber, PhoneNumberFormat::E164);

// Get number type
$type = $libUtil->getNumberType($phoneNumber);

// Validate
$isValid = $libUtil->isValidNumber($phoneNumber);
```

## Common Tasks

### Creating a new field type
1. Create `src/Plugin/Field/FieldType/MyFieldType.php`
2. Add `#[FieldType]` attribute
3. Implement `FieldItemInterface`

### Creating a new widget
1. Create `src/Plugin/Field/FieldWidget/MyWidget.php`
2. Add `#[Widget]` attribute
3. Extend `WidgetBase`

### Adding a validation constraint
1. Create `src/Plugin/Validation/Constraint/MyConstraint.php`
2. Create `src/Plugin/Validation/Constraint/MyConstraintValidator.php`
3. Add `#[Constraint]` attribute