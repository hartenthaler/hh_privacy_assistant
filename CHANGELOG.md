# Changelog

All notable changes to `hh_privacy_assistant` are documented in this file.

## Next release

- Updated Dutch translations; thanks to TheDutchJewel.

## 2.2.6.2 - 2026-07-27

### Added

- Diagnose which privacy-policy provider is installed and active, treating the webtrees Core privacy policy and
  `hh_legal_notice` as alternatives.
- Report the local status of the webtrees Core version check without triggering another external request.
- Document the Core update-server request, its transmitted parameters, and robust server-side mitigation.
- Document the checks that must remain deferred until the next webtrees release.
- Add the complete GNU General Public License version 3 as `LICENSE.md`.

### Changed

- Detect `hh_legal_notice` through the loaded module inventory instead of relying only on database rows.
- Distinguish the administrator-selected legal jurisdiction from the visitor's display language and from the
  inventory of active functions and external services.
- Add the upstream Core opt-out proposal fisharebest/webtrees#5433 to the documentation.

### Compatibility

- Do not assume the unreleased configurable Core privacy policy or per-analytics consent API.
- Keep all existing checks compatible with the currently released webtrees 2.2 series.
