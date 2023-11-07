# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.0.0] - 2023-11-07

### Added

- Supports clear queue by @lizhineng in https://github.com/dew-serverless/laravel-mns-driver/pull/8
- Supports customize HTTP client by @lizhineng in https://github.com/dew-serverless/laravel-mns-driver/pull/10
- Introduce PHPStan in the workflow by @lizhineng in https://github.com/dew-serverless/laravel-mns-driver/pull/6

### Changed

- **[BREAKING CHANGES]** Throws exception when result was failed by @lizhineng in https://github.com/dew-serverless/laravel-mns-driver/pull/7
- Improve readability of test cases by @lizhineng in https://github.com/dew-serverless/laravel-mns-driver/pull/9

### Fixed

- Fix connector registration on service provider by @lizhineng in https://github.com/dew-serverless/laravel-mns-driver/pull/4
- Fix connector not passing the correct queue instance by @lizhineng in https://github.com/dew-serverless/laravel-mns-driver/pull/5
- Fix PHPStan rules by @lizhineng in https://github.com/dew-serverless/laravel-mns-driver/pull/7

## [1.0.0] - 2023-11-05

### Added

- First release.
- Hopes the package could help you easily integrating ACS MNS with your Laravel Queue.

[unreleased]: https://github.com/dew-serverless/laravel-mns-driver/compare/v1.0.0...HEAD
[2.0.0]: https://github.com/dew-serverless/laravel-mns-driver/compare/v1.0.0...v2.0.0
[1.0.0]: https://github.com/dew-serverless/laravel-mns-driver/releases/tag/v1.0.0
