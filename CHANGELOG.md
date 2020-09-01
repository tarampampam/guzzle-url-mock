# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog][keepachangelog] and this project adheres to [Semantic Versioning][semver].

## v1.3.0

### Changed

- Minimal required PHP version now is `7.2` [#PR10]
- GitHub actions as main CI [#PR10]
- Dev-dependency `phpstan/phpstan` updated [#PR10]

### Added

- Dependency `ext-mbstring` [#PR10]
- `declare(strict_types=1);` into each PHP file [#PR10]
- Type definitions in methods parameters and return values, where it possible [#PR10]

### Fixed

- Autoload paths in `composer.json` (eg.: `src` &rarr; `src/`) [#PR10]

[#PR10]:https://github.com/tarampampam/guzzle-url-mock/pull/10

## v1.2.1

### Fixed

- Any case method names finding [#8](https://github.com/tarampampam/guzzle-url-mock/issues/8)

## v1.2.0

### Changed

- Maximal `guzzlehttp/guzzle` package version now is `~7.0`

## v1.1.2

### Fixed

- The same uri can now respond to different http methods by [@BurningDog](https://github.com/BurningDog). Closes [#2](https://github.com/tarampampam/guzzle-url-mock/issues/2)

## v1.1.1

### Changed

- Updated code annotations in handler
- Docker environment dor package developing
- CS fixer settings

### Added

- GitHub actions settings

## v1.1.0

### Added

- Parameter `$to_top` for `onUriRegexpRequested` method (it allows "override" previously declared patterns)

## v1.0.2

### Changed

- Exception message "There is no action for requested URI" now contains request method name

## v1.0.1

### Changed

- Exception message "There is no action for requested URI" now contains requested URI

## v1.0.0

### Added

- First release

[keepachangelog]:https://keepachangelog.com/en/1.0.0/
[semver]:https://semver.org/spec/v2.0.0.html
