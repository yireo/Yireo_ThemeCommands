# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.4.0] - 26 November 2026
### Added
- `theme:list` shows whether a theme is active in a store, plus its type and parent ID
- `theme:list` option `--json` to output the list as JSON
- `theme:list` option `--type` to filter on Hyvä or Luma themes
- Support for PHP 8.5

### Changed
- BREAKING: `theme:create` only accepts theme names like `Vendor/theme_name`, `--application` must be `frontend` or `adminhtml` and the parent theme must exist
- BREAKING: `theme:create` no longer overwrites existing theme files unless `--force` is given
- BREAKING: `theme:change --reset` is now a flag without value, only works for the default scope, asks for confirmation and runs in a transaction
- `theme:change` only assigns frontend themes and rejects scope IDs of websites or store views that do not exist

### Removed
- Support for PHP 8.1

### Fixed
- Shell command injection and path traversal in `theme:create` via the theme name, `--application` or the custom skeleton folder
- Console output could be spoofed or hidden by formatter tags and terminal escape sequences in values from the database, theme.xml files or input
- `theme:list` looping endlessly on themes with cyclic parent themes
- Scope `websites` always rejected by `theme:change`
- Name and ID printed in swapped columns by `theme:design_config`
- Raw SQL with unescaped scope values in `theme:change` and `theme:design_config`
- Missing module dependencies on Magento_Indexer, Magento_Store and Magento_Theme
- PHP 8.6 deprecation of `trim()` without explicit characters

## [1.3.4] - 22 October 2025
### Fixed
- Wrong imported namespace of Composer, should be Symfony Console

## [1.3.3] - 08 October 2025
### Fixed
- Add new command "theme:path" to return full path for given theme

## [1.3.2] - 23 April 2025
### Fixed
- Fix PHP 8.4 deprecation

## [1.3.1] - 10 March 2025
### Fix
- Wrong regex when creating theme

## [1.3.0] - 1 July 2024
### Added
- Add `--reset` flag to `theme:change` command
- Add new command `theme:design_config`

## [1.2.1] - 6 February 2024
### Fixed
- namespace correction for InputOption #3 @meminuygur

## [1.2.0] - 3 February 2024
### Added
- New command to create a new theme

## [1.1.0] - 5 November 2023
### Added
- New command to list all themes

### Fixed
- Cleanup deps in change command

## [1.0.3] - 30 August 2023
### Fixed
- Fix issue with grid not being updated for global scope

## [1.0.2] - 20 June 2023
### Fixed
- Fix scope_id validation when using default scope #2 @lbanka

## [1.0.1] - 17 June 2023
### Added
- Allow to use scope codes #1 @lbanka

## [1.0.0] - 16 June 2023
- Initial release
