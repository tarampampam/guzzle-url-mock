# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog][keepachangelog] and this project adheres to [Semantic Versioning][semver].

## UNRELEASED

### Changed

- Http method names in the stack from lowercase to uppercase

### Fixed

- Uppercase method names finding. Closes [#8](https://github.com/tarampampam/guzzle-url-mock/issues/8)

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
