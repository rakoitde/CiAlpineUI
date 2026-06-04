# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- `src/Exceptions/CiAlpineUiException.php` — base exception class for all library errors.
- `autoload-dev` PSR-4 mapping for `tests/` in `composer.json`.
- `.gitattributes` to exclude development files from dist archives.
- `declare(strict_types=1)` to all PHP source and test files.
- PHPDoc on all public methods and properties in `src/`.

### Changed
- `composer.json` `analyze` script consolidated to a single `&&`-chained command.
- `.php-cs-fixer.dist.php` now includes `tests/` and enforces `declare_strict_types`.
- `CiAlpineUiComponentTestCell`: `public $canAccess` typed as `bool`, all action methods
  annotated with `void` return type.
- `CiAlpineUiControllerTest` namespace corrected from `Cells` to `Controllers`.

## [0.1.0] - 2024-10-25

### Added
- Initial release: `CiAlpineUiComponent` base cell, `CiAlpineUiController`, Alpine.js `$cell`
  magic (`CiAlpineUiJs` view), `make:uicomponent` spark generator, optional component-name
  encryption.
