# webtrees module: Privacy and Security Assistant

This custom module helps webtrees administrators monitor privacy and security tasks.

The first version focuses on inactive user accounts:

- It reads the retention period from the `hh_legal_notice` setting `inactiveUserYears`.
- If the retention period is `0` (`never`), no accounts are reported as overdue.
- If a concrete period is configured, the module lists user accounts whose last activity is older than the configured number of years.
- If a user has never logged in, the registration timestamp is used as fallback.
- Administrator accounts are shown with a note, but are not treated differently.
- The module stores the timestamp and result count of the last scan.

The module does **not** delete accounts automatically. It is currently a monitoring and decision-support tool.

## Installation

Copy the folder `hh_privacy_assistant` into `webtrees/modules_v4` and enable the module in the webtrees control panel.

The module should be used together with `hh_legal_notice`, because the retention period is intentionally configured there and reused here.

## Version

Current version: `0.1.0`

## License

GPL-3.0-or-later
