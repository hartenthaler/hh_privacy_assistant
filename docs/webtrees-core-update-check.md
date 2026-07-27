# webtrees Core version check

This document describes the behavior verified in the currently released webtrees 2.2 series, using webtrees 2.2.5
as the concrete code baseline. It does not assume behavior from an unreleased webtrees commit.

## Current behavior

`Fisharebest\Webtrees\Services\UpgradeService` contacts:

`https://dev.webtrees.net/build/latest-version.txt`

The automatic request is limited to one attempt per 24 hours by the site preference
`LATEST_WT_VERSION_TIMESTAMP`. The query contains:

| Parameter | Value |
| --- | --- |
| `w` | installed webtrees version |
| `p` | PHP version |
| `s` | persistent site UUID stored as `SITE_UUID` |
| `d` | database driver |

The response, timestamp, and any connection error are cached in site preferences. A manual “check now” request
uses `isUpgradeAvailable(true)` and bypasses the 24-hour interval.

## Why a custom-module opt-out is not reliable

webtrees processes `CheckForNewVersion` before `BootModules`. The automatic Core request can therefore happen
before `hh_privacy_assistant::boot()` is called.

Two proposed approaches were evaluated:

1. Updating `LATEST_WT_VERSION_TIMESTAMP` in `boot()` can suppress later non-forced checks, but not a check that
   was already due on the current request. It also falsifies the displayed “last checked” time and does not stop a
   forced manual check.
2. Replacing `UpgradeService` in the dependency-injection container from `boot()` is too late. The earlier
   middleware already holds its `UpgradeService` instance, and other handlers may also retain instances resolved
   before the replacement.

A replacement from the module constructor would depend on undocumented module-discovery side effects before the
middleware is resolved. At that point the module name has not yet been assigned, disabled custom modules are also
being discovered, and a replacement would affect the control panel, manual checks, and the upgrade wizard. This is
not a stable extension point and is therefore not offered as an administrator option.

For these reasons the module does not offer a toggle that promises to disable the Core connection.

## Robust mitigation without patching Core

An administrator can block `dev.webtrees.net` at the server or network level, for example in an outbound firewall,
DNS policy, or proxy. This prevents the connection independently of the webtrees request order.

The trade-off is intentional and significant: both automatic checks and manual Core update checks will fail, and
administrators must monitor webtrees releases by another method. A native Core setting would provide the clearest
long-term solution.

An upstream feature request for three administrator-selectable modes—current behavior, complete suppression, and a
version-only request without the additional query data—is tracked in
[fisharebest/webtrees#5433](https://github.com/fisharebest/webtrees/issues/5433).

## What the assistant reports

The module reads existing local site preferences and displays:

- the installed webtrees version;
- the Core update-server URL;
- the last recorded check time;
- whether a persistent site identifier exists, without displaying its value;
- whether the last attempt recorded an error; and
- that no native opt-out is available in the verified Core version.

Displaying this information does not trigger another update request.
