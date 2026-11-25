# Contributing

Contributions are welcome and will be fully credited.

Please open an issue before you start on anything larger than a bug fix. The package is small on purpose, and a feature that makes it bigger may not be accepted.

## Pull requests

- Branch from `1.x` and open the pull request against `1.x`.
- Keep one pull request to one change.
- Add a test that fails without your change.
- Add a line to `CHANGELOG.md` under `Unreleased`.
- Update the README and the docs when behavior changes. The PHP blocks in those pages are run by `tests/Feature/DocsExamplesTest.php`, so a page and its output block have to change together. The classes a page uses but does not show are in `tests/Fixtures/Docs`.

## Running the tests

```bash
composer install
composer test
```

The suite runs on Orchestra Testbench against an in-memory SQLite database.

## Code style

```bash
composer format
composer analyse
```

## Security

If you've found a security issue, please don't open a public issue. See [SECURITY.md](SECURITY.md) instead.
