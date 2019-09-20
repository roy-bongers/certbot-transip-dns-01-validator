# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] - 
### Changed
- Complete rewrite of the code base
- Now using composer for installation
- No more need to manually download the TransIp API library

### Removed
- Outdated PurplePixie PhpDns library

## [1.0.1] - 2019-09-12
### Changed
- Replaced deprecated constructors

## [1.0.0] - 2018-12-23
### Added
- Write output to log file for easier debugging.
- Define and apply coding standards (PSR-1 and PSR-2).

### Changed
 - Don't query already up-to-date nameservers.
 - In the cleanup hook only remove the TXT record with the challenge string received from Certbot. Used to remove all `_acme-challenge` TXT records.
