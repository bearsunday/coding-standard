# BEAR.Sunday Coding Standard

A [PHP_CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer) ruleset and custom sniffs for [BEAR.Sunday](https://bearsunday.github.io/) projects.

Extends [Doctrine Coding Standard](https://github.com/doctrine/coding-standard) with BEAR.Sunday-specific rules that enforce framework conventions around the Resource layer, dependency injection, and Ray.MediaQuery usage.

## Installation

```bash
composer require --dev bearsunday/coding-standard
```

The `dealerdirect/phpcodesniffer-composer-installer` plugin auto-registers the standard. After install, `BearSunday` is available as a PHPCS standard name.

## Usage

### Option A — extend in your `phpcs.xml`

```xml
<?xml version="1.0"?>
<ruleset name="MyProject">
    <rule ref="BearSunday"/>
    <file>src</file>
    <file>tests</file>
</ruleset>
```

### Option B — pass on the CLI

```bash
vendor/bin/phpcs --standard=BearSunday src/
```

## Notes for consumers

When a class extends a parent whose property is untyped, for example `BEAR\Resource\ResourceObject::$body` is mixed, the Slevomat sniff `SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint` demands a native type but phpstan rejects subclass-only native types when the parent has none (`property.extraNativeType`). Consuming projects should restrict the rule via `<include-pattern>` to paths where it is safe, typically not `src/Resource/*`.

```xml
<rule ref="SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint">
    <include-pattern>src/Module/*</include-pattern>
    <include-pattern>tests/*</include-pattern>
</rule>
```

## What's included

### Doctrine Coding Standard (base)

All Doctrine rules are inherited. The single excluded rule is
`SlevomatCodingStandard.Attributes.AttributeAndTargetSpacing`, which allows
inline parameter attributes like `#[Input] Dto $input` — the BEAR.Sunday
preferred form for DTO injection.

### Generic.PHP.ForbiddenFunctions

Forbids: `var_dump`, `print_r`, `dd`, `error_log`, `dump`.
Use Xdebug tracing / profiling for debugging.

### BearSunday.Resources.ReturnStatic

**Trigger:** any method named `onGet`, `onPost`, `onPut`, `onPatch`, `onDelete`,
`onHead`, or `onOptions` inside a file whose path contains `/Resource/`.

**Rule:** the declared return type must be exactly `static`.

`ResourceObject` subclasses form fluent chains. Returning `self` breaks
inheritance; omitting the type drops static analysis coverage.

```php
// Bad
public function onGet(int $id): self { ... }
public function onPost(string $slug)  { ... }  // no return type
public function onDelete(int $id): ResourceObject { ... }

// Good
public function onGet(int $id): static { ... }
```

### BearSunday.Resources.NoSuperglobals

**Trigger:** any `T_VARIABLE` token inside a file whose path contains `/Resource/`.

**Rule:** `$_GET`, `$_POST`, `$_FILES`, `$_COOKIE`, `$_SESSION`, `$_REQUEST`,
`$_SERVER`, `$_ENV` are forbidden.

BEAR.Sunday injects request data via parameter attributes. Direct superglobal
access bypasses the framework's request lifecycle and AOP interceptor chains.

```php
// Bad — inside a Resource
$id   = $_GET['id'];
$body = $_POST['body'];

// Good — declare as method parameters
public function onGet(#[QueryParam('id')] int $id): static { ... }
```

Alternatives mentioned in the error message: `#[QueryParam]`, `#[CookieParam]`, `#[UploadFiles]`.

### BearSunday.DbQuery.RedundantType

**Trigger:** `#[DbQuery]` attributes that include a `type:` named argument.

**Rule:** the `type:` argument is redundant when the method's return type already
implies the fetch mode:

| Return type | Implied fetch mode | Redundant `type:` value |
|---|---|---|
| `?ClassName`, `ClassName\|null` | `getRow()` | `'row'` |
| `array` | `getRowList()` | `'row_list'` |

Severity: WARNING (auto-fixable stub — full fix in v0.2).

```php
// Bad
#[DbQuery('article_item', type: 'row')]
public function item(int $id): ?Article;

// Good — return type makes the fetch mode self-evident
#[DbQuery('article_item')]
public function item(int $id): ?Article;
```

### BearSunday.Di.NoNewService

**Trigger:** `new ClassName` expressions inside files whose path contains
`/Resource/`, `/Service/`, or `/Domain/`.

**Excluded paths:** `/Module/`, `/Provider/`, `/Factory/` (composition roots).

**Rule:** service-layer classes must receive dependencies via Ray.Di constructor
injection, not direct `new`.

**Allow-list (no error):**
- Class names ending in `Input`, `Dto`, `Entity`, `ValueObject`, `Exception`
- `DateTime*`, `DateInterval`, `DateTimeZone`
- Class names starting with `Spl`
- `throw new ...` expressions
- Classes listed in `allowedClasses`

```php
// Bad — inside Service/
public function getService(): OtherService
{
    return new OtherService();   // error
}

// Good — use constructor injection
public function __construct(
    private readonly OtherService $service,
) {}
```

#### Configuration

Extend the allow-list via `allowedClasses` or `allowedSuffixes` in your `phpcs.xml`:

```xml
<rule ref="BearSunday.Di.NoNewService">
    <properties>
        <property name="allowedClasses" type="array">
            <element value="LegacyFactory"/>
            <element value="Vendor\Package\Clock"/>
        </property>
        <property name="allowedSuffixes" type="array">
            <element value="Request"/>
            <element value="Response"/>
        </property>
    </properties>
</rule>
```

`allowedClasses` accepts short class names or fully qualified class names without
the leading namespace separator.

## Development

```bash
composer install
vendor/bin/phpunit          # run sniff tests
vendor/bin/phpcs            # self-check (uses phpcs.xml)
```

## Reference

- [BEAR.Package docs/coding-conventions.md](https://github.com/bearsunday/BEAR.Package/blob/1.x/docs/coding-conventions.md)
- [MyVendor.Cms](https://github.com/bearsunday/MyVendor.Cms) — reference implementation
- [Doctrine Coding Standard](https://github.com/doctrine/coding-standard)
- [Slevomat Coding Standard](https://github.com/slevomat/coding-standard)

## License

MIT — see [LICENSE](LICENSE).
