# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog][keepachangelog] and this project adheres to [Semantic Versioning][semver].

## v1.1.1

### Fixed

- The same uri can now respond to different http methods. Closes [#2]

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

## Issues

[#2]:https://github.com/tarampampam/guzzle-url-mock/issues/2
