# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.7.0] - 2021-02-03
## Added
- Build multiple Docker architectures [#92](https://github.com/roy-bongers/certbot-transip-dns-01-validator/pull/92), thanks to [BasSmeets](https://github.com/BasSmeets)

## [2.6.0] - 2020-12-17
## Added
- Detect when an access token is revoked and then automatically request a new one

## [2.5.1] - 2020-12-16
## Fixed
- Fixes PHP 8 support
## Changed
- PHP-CS-Fixer now runs as separate GitHub action

## [2.5.0] - 2020-11-14
### Added
- Support for PHP 8.0
### Fixed
- Use correct link to composer [#69](https://github.com/roy-bongers/certbot-transip-dns-01-validator/pull/69), thanks to [kiwivogel](https://github.com/kiwivogel)
### Changed
- Use PHP-CS-Fixer instead of PHP_CodeSniffer
- Improve code style
### Removes
- PHP 7.2 support

## [2.4.3] - 2020-07-02
### Changed
- Update dependencies

## [2.4.2] - 2020-05-02
### Changed
- Modified shebang to support alternative php interpreter locations
- Update dependencies

## [2.4.1] - 2020-03-30
### Changed
- Update TransIP API to v6.0.4

## [2.4.0] - 2020-03-05
### Added
- New config option `transip_whitelist_only_token`

### Changed
- Updated Transip API to v6
- Updated required PHP version to >= 7.2


## [2.3.0] - 2020-02-22
### Added
- Classes are now loaded via dependency injection
- Prepared codebase to support additional providers
- `/docs` folder with description of how to add an additional provider
- Code comments
- Backwards compatibility for the original config file format

### Changed
- The config file moved from `config/transip.php` to `config/config.php`
- `login` and `private_key` config settings are now prefixed with `transip_` in order to
make it easier to add support for additional providers.

## [2.2.0] - 2020-02-16
### Added
- Dockerfile
- `ENV` variable support

### Changed
- Composer package updates

## [2.1.0] - 2020-01-18
### Added
- Unit tests
- GitHub Actions support
- Code style definition (PHPCS)

### Changed
- Update composer packages

### Fixed
- Missing sprintf() when throwing RuntimeException
- When waiting for nameservers there is now a timeout of 30 minutes to prevent an infinite loop

## [2.0.0] - 2019-12-04
### Added
- Config file

### Changed
- Complete rewrite of the code base
- Now using composer for installation

### Removed
- Outdated PurplePixie PhpDns library
- Requirement to manually download the TransIp API library

## [1.1.0] - 2019-09-12
### Changed
- Replaced deprecated constructors

## [1.0.0] - 2018-12-23
### Added
- Write output to log file for easier debugging.
- Define and apply coding standards (PSR-1 and PSR-2).

### Changed
 - Don't query already up-to-date nameservers.
 - In the cleanup hook only remove the TXT record with the challenge string received from Certbot. Used to remove all `_acme-challenge` TXT records.

[2.7.0]: https://github.com/roy-bongers/certbot-transip-dns-01-validator/compare/v2.6.0...v2.7.0
[2.6.0]: https://github.com/roy-bongers/certbot-transip-dns-01-validator/compare/v2.5.1...v2.6.0
[2.5.1]: https://github.com/roy-bongers/certbot-transip-dns-01-validator/compare/v2.5.0...v2.5.1
[2.5.0]: https://github.com/roy-bongers/certbot-transip-dns-01-validator/compare/v2.4.3...v2.5.0
[2.4.3]: https://github.com/roy-bongers/certbot-transip-dns-01-validator/compare/v2.4.2...v2.4.3
[2.4.2]: https://github.com/roy-bongers/certbot-transip-dns-01-validator/compare/v2.4.1...v2.4.2
[2.4.1]: https://github.com/roy-bongers/certbot-transip-dns-01-validator/compare/v2.4.0...v2.4.1
[2.4.0]: https://github.com/roy-bongers/certbot-transip-dns-01-validator/compare/v2.3.0...v2.4.0
[2.3.0]: https://github.com/roy-bongers/certbot-transip-dns-01-validator/compare/v2.2.0...v2.3.0
[2.2.0]: https://github.com/roy-bongers/certbot-transip-dns-01-validator/compare/v2.1.0...v2.2.0
[2.1.0]: https://github.com/roy-bongers/certbot-transip-dns-01-validator/compare/v2.0.0...v2.1.0
[2.0.0]: https://github.com/roy-bongers/certbot-transip-dns-01-validator/compare/v1.1.0...v2.0.0
[1.1.0]: https://github.com/roy-bongers/certbot-transip-dns-01-validator/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/roy-bongers/certbot-transip-dns-01-validator/releases/tag/v1.0.0
