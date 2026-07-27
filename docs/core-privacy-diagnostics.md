# Core privacy-policy diagnostics

This document separates checks that are safe with the currently released webtrees version from checks that require
the next webtrees release. The unreleased implementation is a design reference only and is not treated as an API.

## Checks available now

The assistant can safely determine whether these privacy-policy providers are installed and enabled:

- the webtrees Core module `privacy-policy`;
- the custom module `hh_legal_notice`.

The providers are alternatives. The assistant reports:

- a warning when neither provider is active;
- a review request when both providers are active, because duplicate links or conflicting statements may result;
- no provider warning when exactly one is active.

The diagnostic keeps three dimensions separate:

1. the legal jurisdiction selected by the administrator;
2. the visitor’s display language, which only controls the output language; and
3. the inventory of functions and external services that are actually enabled.

The diagnostic evaluates technical configuration and identifiable gaps. It is not legal advice and cannot certify
that a privacy policy is complete for a particular website.

## Deferred until the next webtrees release

Only after a new webtrees version containing the announced functionality has been published may the assistant:

- detect the final Core jurisdiction setting and its supported values;
- verify completion of the fields required by the selected jurisdiction;
- inspect the final per-analytics consent mechanism and its cookies;
- verify that a consent choice remains reachable when `hh_legal_notice` replaces the Core page or footer;
- account for the final resolution of fisharebest/webtrees#5432; and
- map Core and `hh_legal_notice` settings without silently overwriting either configuration.

These checks must be based on feature detection or a stable published interface. Version-number guesses and private
Core methods are not an acceptable dependency.
