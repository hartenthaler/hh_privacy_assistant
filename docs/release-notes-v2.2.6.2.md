# hh_privacy_assistant 2.2.6.2

This release adds two new administrator diagnostics while remaining compatible with the currently released
webtrees 2.2 series.

## Highlights

- The assistant now recognizes the webtrees Core privacy policy and `hh_legal_notice` as alternative providers. It
  warns when neither is active and asks for review when both are active.
- A new information panel explains the webtrees Core version check, the transmitted technical data, the last local
  check status, and the available server-side mitigation. Reading the panel does not initiate another request.
- Legal jurisdiction, visitor language, and the inventory of active services are explicitly kept separate.
- The complete GPL-3.0 license text is now included as `LICENSE.md`.

## Deliberately deferred

The announced configurable Core privacy policy and per-analytics consent mechanism are not part of the current
webtrees release. The assistant does not report these unavailable settings as missing. Their final implementation
will be evaluated after the next webtrees version is published.

The proposal for an administrator-selectable Core update-check privacy mode is tracked in
[fisharebest/webtrees#5433](https://github.com/fisharebest/webtrees/issues/5433).
