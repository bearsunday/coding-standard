# BEAR.Sunday Coding Standard

A [PHP_CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer) ruleset and custom sniffs for [BEAR.Sunday](https://bearsunday.github.io/) projects.

Extends [Doctrine Coding Standard](https://github.com/doctrine/coding-standard) with BEAR.Sunday-specific rules that enforce framework conventions around the Resource layer, dependency injection, and Ray.MediaQuery usage.

## Installation

Not yet published on Packagist. The package is currently registered under
`bear/conding-standard` in `composer.json` — a typo of the intended
`bearsunday/coding-standard` that hasn't been renamed yet. Use the name as
it actually is today; update this snippet if/when the package is renamed.

Add it as a VCS repository and require the `1.x` branch:

```json
{
    "repositories": {
        "bear/conding-standard": {
            "type": "vcs",
            "url": "https://github.com/bearsunday/coding-standard.git"
        }
    },
    "require-dev": {
        "bear/conding-standard": "1.x-dev"
    }
}
```

```bash
composer update bear/conding-standard --with-all-dependencies
```

The `--with-all-dependencies` flag is only needed the first time, to let
Composer raise `squizlabs/php_codesniffer`, `doctrine/coding-standard`, and
`slevomat/coding-standard` to the versions this package requires if your
project pins older ones.

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

### Scoping path-sensitive sniffs

Seven sniffs gate on a loose substring match against the file path —
`ReturnStatic`, `NoSuperglobals`, `NoAbstractResource`, `HeaderConstant`, and
`StatusCodeConstant` trigger on any path containing `/Resource/`; `NoNewService`
and `NoImplicitNow` trigger on `/Resource/`, `/Service/`, or `/Domain/`. This
also matches test-mirror directories such as `tests/Resource/ArticleTest.php`,
which is rarely what you want.

To scope a sniff to your actual source layout, add a narrower sibling
`<rule ref>` with an `include-pattern` — PHPCS applies it cumulatively even
though the sniff is already pulled in by the broader `<rule ref="BearSunday"/>`:

```xml
<rule ref="BearSunday.Resources.NoSuperglobals">
    <include-pattern>src/*Resource/*</include-pattern>
</rule>
```

Use `src/*Resource/*`, not `src/Resource/*` — the leading `*` after `src/`
tolerates nesting before the target segment, which a literal prefix pattern
silently misses. This matters most for `NoNewService`, whose three trigger
segments need their own patterns:

```xml
<rule ref="BearSunday.Di.NoNewService">
    <include-pattern>src/*Resource/*</include-pattern>
    <include-pattern>src/*Service/*</include-pattern>
    <include-pattern>src/*Domain/*</include-pattern>
</rule>
```

Without the leading `*`, `src/Service/*` would miss a real file like
`src/Datadog/Service/Foo.php`, where `Service` is nested under another
directory instead of appearing directly under `src/`.

### Disabling or exempting individual rules

Don't want a specific rule at all — for example a project with no plans to
adopt constructor-only DI enforcement? Exclude it by name inside the
`<rule ref="BearSunday"/>` block:

```xml
<rule ref="BearSunday">
    <exclude name="BearSunday.Di.NoNewService"/>
</rule>
```

This turns the rule off entirely; every other `BearSunday.*` sniff stays
active.

For a lighter touch — keep the rule, but exempt specific cases — most sniffs
expose their own properties instead (see each sniff's own section above for
the full list): `NoNewService` has `allowedClasses`/`allowedSuffixes`,
`HeaderConstant` has `allowedHeaders`. For example, to allow `new
StructuredData(...)` without disabling `NoNewService` for anything else:

```xml
<rule ref="BearSunday.Di.NoNewService">
    <properties>
        <property name="allowedClasses" type="array">
            <element value="StructuredData"/>
        </property>
    </properties>
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

**Auto-fixable** with `phpcbf`: inserts `: static` when the return type is
missing, or replaces an existing `self`/`ResourceObject`/other declared type
with `static`, preserving surrounding whitespace and brace placement.

The fix is purely syntactic — it rewrites the declared type without checking
what the method actually returns. Narrowing `: ResourceObject` (or a missing
type) to `: static` is only safe at runtime if the method returns `$this` (or
another same-class instance); a handler that returns a *different*
`ResourceObject` would start throwing a `TypeError` after the fix is applied.
Run your test suite after any bulk `phpcbf` pass using this sniff rather than
trusting the fixer alone.

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

### BearSunday.Resources.NoAbstractResource

**Trigger:** any `abstract class` declaration inside a file whose path contains
`/Resource/`.

**Rule:** Resource classes must be concrete. Shared abstract base classes should
live outside the Resource directory, for example `Support\Resource`.

Abstract methods are not flagged; this rule targets only abstract class
declarations.

```php
// Bad — inside Resource/
abstract class BasePageResource { ... }

// Good — inside Resource/
final class ArticlePageResource { ... }
```

### BearSunday.Resources.StatusCodeConstant

**Trigger:** `$this->code = <numeric literal>;` inside a file whose path
contains `/Resource/`.

**Rule:** use the matching `Koriym\HttpConstants\StatusCode` constant instead
of a bare HTTP status number. Codes without a constant in that class (this
package's `StatusCode` currently has no `422` or `429`, for example) are left
alone — flagging them would tell you to write an undefined constant.

**Auto-fixable** with `phpcbf`. The fixer always rewrites the literal to an
absolute (`\`-prefixed) class reference, so it never depends on a `use`
import being present in that file. If the resulting reference isn't already
shortened by a `use` import, the inherited
`SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly` rule adds one and
shortens the reference on the next `phpcbf` pass.

```php
// Bad
$this->code = 404;

// Good
$this->code = StatusCode::NOT_FOUND;
```

#### Configuration

Override the suggested class name via `statusCodeClass` in your `phpcs.xml`
if your project re-exports the constants under a different fully qualified
class name.

### BearSunday.Resources.HeaderConstant

**Trigger:** `$this->headers['<Name>'] = ...;` inside a file whose path
contains `/Resource/`.

**Rule:** use the matching `Koriym\HttpConstants\ResponseHeader` constant
instead of a string literal header name. Header names without a constant in
that class (application-specific headers like `X-Request-Id`) are left alone.

**Auto-fixable** with `phpcbf`, same as `StatusCodeConstant`: the fixer always
emits an absolute (`\`-prefixed) class reference, so it never depends on a
`use` import; the inherited `SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly`
rule adds the `use` import and shortens the reference on the next `phpcbf` pass.

```php
// Bad
$this->headers['Location'] = '/articles/1';

// Good
$this->headers[ResponseHeader::LOCATION] = '/articles/1';
```

#### Configuration

Keep specific header names as string literals via `allowedHeaders`, and
override the suggested class name via `headerClass`, in your `phpcs.xml`:

```xml
<rule ref="BearSunday.Resources.HeaderConstant">
    <properties>
        <property name="allowedHeaders" type="array">
            <element value="Location"/>
        </property>
    </properties>
</rule>
```

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
- `DateTime`, `DateTimeImmutable`, `DateTimeInterface`, `DateInterval`, `DateTimeZone`
- SPL classes: `SplDoublyLinkedList`, `SplFileInfo`, `SplFileObject`, `SplFixedArray`, `SplHeap`, `SplMaxHeap`, `SplMinHeap`, `SplObjectStorage`, `SplObserver`, `SplPriorityQueue`, `SplQueue`, `SplStack`, `SplSubject`, `SplTempFileObject`
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

### BearSunday.Di.NoImplicitNow

**Trigger:** `new DateTime()` / `new DateTimeImmutable()` with zero arguments,
or with a single `'now'` string argument, inside a file whose path contains
`/Resource/`, `/Service/`, or `/Domain/`.

**Rule:** reading the wall clock directly makes the class non-deterministic
and untestable at a fixed point in time. Inject the current time instead
(e.g. a `DateTimeInterface` parameter bound via `ray/identity-value-module`'s
`IdentityValueModule`).

`new DateTime($string)` / `new DateTimeImmutable($string)` with any other
explicit argument is unaffected — only the "current moment" forms are
flagged.

```php
// Bad — inside Domain/
public function isValid(): bool
{
    return $this->datePublished < new DateTimeImmutable();
}

// Good — current time injected as a DateTimeInterface
public function __construct(private readonly DateTimeInterface $now) {}

public function isValid(): bool
{
    return $this->datePublished < $this->now;
}
```

### BearSunday.AppMeta.NoAppDirWritablePath

**Trigger:** any `->appDir` property access (`$appMeta->appDir`,
`$this->appMeta->appDir`, or another binding name) concatenated with a
string literal that is exactly `/var/tmp` or starts with `/var/tmp/`.

**Rule:** `/var/tmp` paths must be derived from `$appMeta->tmpDir`, not built
by concatenating onto `$appMeta->appDir`.

BEAR.Sunday's [read-only deployment](https://bearsunday.github.io/manuals/1.0/ja/production.html#writable-paths)
model (Vercel, AWS Lambda, `docker run --read-only`,
`readOnlyRootFilesystem: true`) keeps `appDir` read-only and resolves
`tmpDir` under the writable system temp directory instead. Hard-coding a
`/var/tmp` path from `appDir` works on a writable filesystem but breaks that
deployment model. Other `appDir` concatenation — assets, config,
`/var/build` compiled artifacts shipped with the release — is unaffected.

```php
// Bad
$this->install(new QiqProdModule($this->appMeta->appDir . '/var/tmp/cache/qiq'));

// Good
$this->install(new QiqProdModule($this->appMeta->tmpDir . '/cache/qiq'));
```

## Development

```bash
composer install
vendor/bin/phpunit          # run sniff tests
vendor/bin/phpcs            # self-check (uses phpcs.xml)
```

## Reference

- [BEAR.Sunday コーディングガイド](https://bearsunday.github.io/manuals/1.0/ja/coding-guide.html)
- [MyVendor.Cms](https://github.com/bearsunday/MyVendor.Cms) — reference implementation
- [Doctrine Coding Standard](https://github.com/doctrine/coding-standard)
- [Slevomat Coding Standard](https://github.com/slevomat/coding-standard)

## License

MIT — see [LICENSE](LICENSE).
