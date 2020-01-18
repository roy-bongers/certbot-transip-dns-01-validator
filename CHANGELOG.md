# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
