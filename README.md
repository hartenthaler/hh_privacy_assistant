# webtrees module: Privacy and Security Assistant

[![License: GPL v3](https://img.shields.io/badge/License-GPL%20v3-blue.svg)](http://www.gnu.org/licenses/gpl-3.0)

![webtrees major version](https://img.shields.io/badge/webtrees-v2.2.x-green)

![Latest Release](https://img.shields.io/github/v/release/hartenthaler/hh_privacy_assistant)

This [webtrees](https://www.webtrees.net) module helps administrators monitor privacy and security tasks on a webtrees site.

Current module version: **2.2.6.1**.

> [!IMPORTANT]
> This module does not provide legal advice.
> Administrators remain responsible for deciding which privacy and security measures are appropriate for their website.

## Contents

This README contains the following main sections:

* [Purpose](#purpose)
* [Scope](#scope)
* [Requirements](#requirements)
* [Installation](#installation)
* [Translation](#translation)
* [Credits](#credits)
* [Privacy, telemetry, and tracking](#privacy)
* [Support](#support)
* [License](#license)

<a name="purpose"></a>
## Purpose

The Privacy and Security Assistant supports recurring checks that are easy to forget in daily administration.

It currently focuses on these areas:

* monitoring inactive user accounts against the retention period documented in the privacy policy
* reviewing sensitive genealogical information and applying or removing GEDCOM privacy restrictions where appropriate
* checking whether self-registration and the acceptable use agreement are configured consistently

The module is a monitoring and decision-support tool. It does **not** delete user accounts automatically.
GEDCOM changes for sensitive information are shown as a preview and are applied only after confirmation.

<a name="scope"></a>
## Scope

### Shared Settings from hh_legal_notice

The assistant reads privacy-policy values from `hh_legal_notice` whenever that module is installed and enabled:

* `inactiveUserYears` for inactive user account retention
* `sensitiveDataYears` for the sensitive-data protection period after death

If these values cannot be imported, the assistant shows an administrator warning and continues with local fallback values.

### Inactive User Accounts

The assistant reads the retention period from the `hh_legal_notice` setting `inactiveUserYears`.

If the retention period is `0` (`never`), no accounts are reported as overdue.
If a concrete period is configured, the module lists user accounts whose last activity is older than the configured number of years.
If a user has never logged in, the registration timestamp is used as fallback.

The overview shows inactive accounts together with the available account data and the number of inactive days.
Administrator accounts are shown with a note, but are not treated differently.
The module stores the timestamp and result count of the last scan.

### Sensitive Genealogical Information

Administrators can select a tree and a release period after death.
The default release period is read from the `hh_legal_notice` setting `sensitiveDataYears`.
The assistant scans facts that may contain sensitive information, for example:

* ethnic origin (`FACT` with `TYPE Ethnic Origin`)
* physical description (`DSCR`)
* political party membership (`FACT` with `TYPE Political Party Membership`)
* political affiliation (`EVEN` or `FACT` with `TYPE Political Affiliation`)
* religious affiliation (`RELI`)
* religious events (`EVEN` with `TYPE Religious Event`)
* baptism and church ceremonies (`BAPM`, `CHR`, `CONF`)
* trade union membership (`FACT` with `TYPE Trade Union Membership`)
* DNA information (`FACT` with `TYPE Y-DNA Haplogroup` or `TYPE mtDNA Haplogroup`, `EVEN` with `TYPE DNA Test`)
* cause of death (`DEAT` with `CAUS`)
* criminal-law events (`EVEN` with `TYPE` values such as `Arrest`, `Indictment`, `Conviction`, `Criminal Offense`, `Criminal Offence`, `Imprisonment`, `Pardon`, or `Deportation`; German equivalents such as `Verhaftung`, `Anklage`, `Verurteilung`, `Straftat`, `Inhaftierung`, and `Begnadigung` are recognized as well)

For people who are not known to have been dead for the configured number of years,
`2 RESN CONFIDENTIAL` is added to matching facts when missing.
For people who are known to have been dead for the configured number of years,
an existing `2 RESN CONFIDENTIAL` on matching facts is removed.

The action can be previewed before changes are applied.
For causes of death, GEDCOM allows the restriction only on the `DEAT` fact, not on the `CAUS` substructure alone.

Sample GEDCOM data for all supported sensitive-data patterns is available in [`test-data/sensitive-data-patterns.ged`](test-data/sensitive-data-patterns.ged).
The automated test approach is described in [`docs/automated-test-concept.md`](docs/automated-test-concept.md).

### Self-registration and Acceptable Use Agreement

The assistant checks and can update the global webtrees settings for self-registration:

* whether visitors can request a new user account
* whether the acceptable use agreement is shown on the "Request a new user account" page

If self-registration is enabled but the acceptable use agreement is not shown, the assistant displays a warning.
The assistant also shows the actual acceptable-use notice text from webtrees so administrators can review what new users see.
It also states that webtrees currently shows this agreement as notice text only and does not store a separate,
audit-proof acceptance record for each registered user.

<a name="requirements"></a>
## Requirements

This module requires **webtrees** version 2.2.

The module is designed to be used together with `hh_legal_notice`.
The inactive-account retention period and sensitive-data protection period are intentionally configured in `hh_legal_notice` and reused here.

<a name="installation"></a>
## Installation

Copy the folder `hh_privacy_assistant` into `webtrees/modules_v4` and enable the module in the webtrees control panel.

After installation, open the module configuration page in the webtrees control panel.
Use the inactive-account section to review overdue accounts and the sensitive-information section to preview and apply GEDCOM privacy restrictions.

<a name="translation"></a>
## Translation

The module currently includes German and Dutch translation files.

Thanks to TheDutchJewel for the Dutch translation.

Some labels that already exist in webtrees core translations are reused from webtrees instead of being translated again in this module.

<a name="credits"></a>
## Credits

Developed by Hermann Hartenthaler with support from OpenAI Codex and JetBrains PhpStorm.

<a name="privacy"></a>
## Privacy, telemetry, and tracking

This module does not collect analytics data, does not track users, and does not send data to the module author.

When the **webtrees** control panel is opened, the module checks whether a newer version is available.
This version check requests only the module's public latest-version URL on `github.com`.

The module reads existing webtrees user-account data and GEDCOM data locally in the webtrees installation.
It does not transmit this data to external services.

<a name="support"></a>
## Support

**Issues**: for ideas, questions, or bugs, please create an [issue](https://github.com/hartenthaler/hh_privacy_assistant/issues).

**Forum**: general webtrees support can be found at the [webtrees forum](https://www.webtrees.net/).

<a name="license"></a>
## License

* Copyright (C) 2026 Hermann Hartenthaler
* Derived from **webtrees** - Copyright 2026 webtrees development team.

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
