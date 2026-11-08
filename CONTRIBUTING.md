# Contributing

Contributions are welcome and will be credited. Bug reports, failing examples, documentation fixes and small focused pull requests are all useful.

## Before you open a pull request

- **Open an issue first for anything larger than a bug fix or a typo.** Pipe Dream is deliberately small; a feature that makes it bigger may be turned down, and it is kinder to find that out before you write it.
- One pull request does one thing. Keep unrelated clean-ups out of it.
- Branch from `1.x` and open the pull request against `1.x`.

## Running the tests

```bash
composer install
composer test
```

The suite uses Orchestra Testbench and runs its database tests on in-memory SQLite, so you need no database set up. CI runs it on PHP 8.1 to 8.5 against Laravel 9 to 13.

Every PHP block in the README and in the docs pages (`docs/*.md`) is a complete file in `docs/examples/`. `tests/Feature/ReadmeExamplesTest.php` and `tests/Feature/DocsExamplesTest.php` run each one and check the output the page shows. **If you change behaviour, change the example and the page text together**, or those tests will tell you they have drifted.

## What a good pull request has

- A test that fails without your change and passes with it.
- A line under `## [Unreleased]` in `CHANGELOG.md` (Added, Changed, Fixed or Removed), written for someone who uses the package, not for someone who read the diff.
- Code in the style of the surrounding code: same naming, same comment density, same idiom.

## Reporting a security problem

Please do not open a public issue. Follow [SECURITY.md](SECURITY.md).

## Conduct

This project follows the [Code of Conduct](CODE_OF_CONDUCT.md). By taking part you agree to it.

## Licence

By contributing you agree that your contribution is licensed under the [MIT licence](LICENSE) that covers the project.
