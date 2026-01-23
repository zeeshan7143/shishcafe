# Changelog

All notable changes to the Debug Log Config Tool plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] - 2023-11-15

### Added
- WP-CLI style command structure in terminal (e.g., `wp core version` instead of `wp-version`)
- Database commands with WP-CLI syntax (`wp db query`, `wp db tables`, etc.)
- Terminal settings page to enable/disable terminal and database features
- Super admin restriction for database commands
- Support for SQL queries with proper security measures
- Enhanced developer profile in support page
- Improved UI for support and notification pages
- Command auto-completion for WP-CLI style commands
- Support for colon syntax in commands (e.g., `wp:db:query`)
- Environment detection to hide development features in production

### Changed
- Reorganized terminal commands into logical categories
- Updated help command to show commands by category
- Improved error messages for better debugging
- Enhanced security for terminal commands
- Updated UI components with modern design elements

### Fixed
- Issue with SQL query execution where quotes were included in the query
- Terminal command parsing for complex arguments
- Case sensitivity issues in command validation
- Security improvements for database access

## [1.2.0] - 2023-10-01

### Added
- Debug Terminal feature for executing WordPress commands
- Auto-refresh functionality for real-time log monitoring
- Stack trace viewer for PHP error analysis
- Safe Mode for disabling problematic plugins
- Email notifications for debug log events

### Changed
- Improved UI for log viewing
- Enhanced filtering options for log entries
- Updated settings page with more configuration options

### Fixed
- Various bug fixes and performance improvements

## [1.1.0] - 2023-08-15

### Added
- Support for custom log file locations
- Advanced filtering options
- Export functionality for log data

### Changed
- Improved error handling
- Enhanced UI for better user experience

## [1.0.0] - 2023-07-01

### Added
- Initial release
- Basic log viewing functionality
- Simple filtering options
- Configuration settings
