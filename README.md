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

It also contains a first implementation for sensitive genealogical information:

- Administrators can select a tree and a release period after death.
- The assistant scans facts that may contain sensitive information:
  - ethnic origin (`FACT` with `TYPE Ethnic Origin`)
  - physical description (`DSCR`)
  - political party membership (`FACT` with `TYPE Political Party Membership`)
  - political affiliation (`EVEN` or `FACT` with `TYPE Political Affiliation`)
  - religious affiliation (`RELI`)
  - religious events (`EVEN` with `TYPE Religious Event`)
  - baptism and church ceremonies (`BAPM`, `CHR`, `CONF`)
  - trade union membership (`FACT` with `TYPE Trade Union Membership`)
  - DNA information (`FACT` with `TYPE Y-DNA Haplogroup` or `TYPE mtDNA Haplogroup`, `EVEN` with `TYPE DNA Test`)
  - cause of death (`DEAT` with `CAUS`)
- For people who are not known to have been dead for the configured number of years, `2 RESN CONFIDENTIAL` is added to matching facts when missing.
- For people who are known to have been dead for the configured number of years, an existing `2 RESN CONFIDENTIAL` on matching facts is removed.
- The action can be previewed before changes are applied.
- For causes of death, GEDCOM allows the restriction only on the `DEAT` fact, not on the `CAUS` substructure alone.

## Installation

Copy the folder `hh_privacy_assistant` into `webtrees/modules_v4` and enable the module in the webtrees control panel.

The module should be used together with `hh_legal_notice`, because the retention period is intentionally configured there and reused here.

## Version

Current version: `2.2.6.0`

## Translation

The module currently includes German and Dutch translation files.

Thanks to TheDutchJewel for the Dutch translation.

Some labels that already exist in webtrees core translations are reused from webtrees instead of being translated again in this module.

## License

GPL-3.0-or-later
