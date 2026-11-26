# Contributing

This project is a command line tool that writes a single-page markdown API
document from the DocBlock comments in PHP source code. Contributions are
welcome; this guide lists what the CI checks on every pull request, so a change
can pass them locally first.

## Development setup

You need PHP 8.3 or newer with the `tokenizer` extension, and Composer.

```shell
git clone https://github.com/ivuorinen/markdowndocs.git
cd markdowndocs
composer install
```

`composer install` ends by running `composer normalize`, which rewrites
`composer.json` into its normalized form; commit that change if it makes one.

## Code style

The code follows the PSR-2 ruleset (`phpcs.xml`), and psalm checks types
(`psalm.xml`). CI fails on any phpcs error and on any psalm error.

```shell
composer lint       # phpcs
composer lint-fix   # phpcbf: fixes what phpcs can fix itself
composer psalm      # psalm
```

The fixtures under `test/Acme/` are exempt from phpcs: their layout is what the
tests reflect over.

## Testing

```shell
composer test                                   # the whole suite
vendor/bin/phpunit --filter testNameOrClass     # one test or test class
composer test-coverage                          # with coverage (needs Xdebug or PCOV)
```

PHPUnit fails on any warning, notice or deprecation raised by `src/`.

`docs.md` is generated from `src/` by this tool, and CI checks it is current. After
changing anything public in `src/`, regenerate it:

```shell
composer docs        # rewrite docs.md
composer docs-check  # what CI runs: fails when docs.md is stale
```

When changing which PHP versions are supported, run
`composer check-php-support`: it names every file that states the supported
versions and fails until they agree.

## Commit convention

Pull request titles must follow [Conventional Commits](https://www.conventionalcommits.org/)
(`feat: ...`, `fix(scope): ...`, `chore(deps): ...`); the "Lint PR title" check
enforces it. Pull requests are squash-merged, so the title becomes the commit
message on `main`. Mark a breaking change with `!` after the type, as in
`feat!: ...`.

## Pull-request process

- Every pull request needs one approving review; the code owner is listed in
  `.github/CODEOWNERS`.
- These checks must pass before merging: "PHP 8.3 (locked deps)", "PHP 8.4
  (locked deps)", "PHP 8.5 (locked deps)", "PHP 8.5 (highest deps)", "PHP 8.3
  (lowest deps)" and "Lint PR title". The PHP jobs run `composer validate`,
  `composer check-php-support`, `composer audit`, the test suite, phpcs, psalm
  and, on the locked jobs, `composer docs-check`.
- The lowest and highest jobs install the oldest and newest dependency versions
  `composer.json` allows, so a change must work across that whole range.

## License

The project is MIT-licensed (`LICENSE`), and contributions are accepted under
the same license.
