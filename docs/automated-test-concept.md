# Automated Test Concept

This document describes a practical path toward automated tests for the sensitive-data protection feature.

The module currently relies on manual testing in a webtrees installation. The fixture in
[`../test-data/sensitive-data-patterns.ged`](../test-data/sensitive-data-patterns.ged) is the first building block for repeatable tests.

## Goal

The sensitive-data assistant should reliably:

* find every supported sensitive GEDCOM pattern
* add `2 RESN CONFIDENTIAL` when the person is alive or has not been dead long enough
* remove `2 RESN CONFIDENTIAL` when the person has been dead longer than the configured release period
* leave already-correct facts unchanged
* preview the changes before applying them

## Fixture

`test-data/sensitive-data-patterns.ged` contains invented data only.

It covers:

* ethnic origin
* physical description
* political party membership
* political affiliation
* religious affiliation
* religious events
* baptism/christening/confirmation
* trade union membership
* Y-DNA and mtDNA haplogroups
* DNA tests
* cause of death
* arrest
* indictment
* criminal conviction
* criminal offence
* imprisonment
* pardon
* deportation

The persons are deliberately mixed:

* living people without `RESN CONFIDENTIAL`: protection should be added
* recently deceased people without `RESN CONFIDENTIAL`: protection should be added
* long-deceased people with `RESN CONFIDENTIAL`: protection should be removed
* long-deceased people without `RESN CONFIDENTIAL`: no change should be needed
* recently deceased people with `RESN CONFIDENTIAL`: no change should be needed

With a release period of 30 years and a current date after 2026-01-01, the fixture should produce:

* 25 matching sensitive facts
* 17 facts where `RESN CONFIDENTIAL` should be added
* 6 facts where `RESN CONFIDENTIAL` should be removed
* 2 matching facts that should remain unchanged

## Manual Baseline Test

Before automation, the fixture can be used manually:

1. Create a dedicated local test tree in webtrees.
2. Import `test-data/sensitive-data-patterns.ged`.
3. Open the Privacy and Security Assistant module settings.
4. Select the test tree.
5. Set "Release after death" to `30`.
6. Click "Preview".
7. Check the result table against the expected numbers above.
8. Click "Apply changes" only in this disposable test tree.
9. Run "Preview" again.

After applying once, the second preview should show the same matching facts, but no pending add/remove actions.

## Automated Test Levels

### 1. Pattern Unit Tests

The smallest useful test is a pure pattern test.

The current implementation keeps the pattern list and matching logic inside `PrivacyAssistantModule`.
For easy testing, this logic should eventually be extracted into a small service, for example:

* `SensitiveFactPattern`
* `SensitiveFactMatcher`
* `SensitiveFactProtectionPlanner`

Then a PHPUnit test can feed small GEDCOM fact snippets into the matcher and assert the expected label:

* `1 RELI protestant` -> `Religious affiliation`
* `1 EVEN\n2 TYPE Verurteilung` -> `Criminal conviction`
* `1 EVEN\n2 TYPE Conviction` -> `Criminal conviction`
* `1 EVEN\n2 TYPE Deportation` -> `Deportation`
* unrelated facts -> no match

This test level does not need a database and is fast.

### 2. Protection Planning Tests

The next level tests the decision logic:

* living person + sensitive fact without restriction -> add
* recently deceased person + sensitive fact without restriction -> add
* long-deceased person + sensitive fact with restriction -> remove
* long-deceased person + sensitive fact without restriction -> unchanged
* recently deceased person + sensitive fact with restriction -> unchanged

This should also be extracted into testable code that returns a planned action without touching the database.

### 3. Integration Test With GEDCOM Fixture

The full integration test imports `test-data/sensitive-data-patterns.ged` into a temporary webtrees test database.

The test then runs the assistant against the imported tree with release period `30` and asserts:

* number of matching facts
* number of planned add actions
* number of planned remove actions
* number of unchanged matching facts
* GEDCOM after applying changes

This is slower than unit tests, but it verifies that the module works with real webtrees `Individual` and `Fact` objects.

### 4. Browser Smoke Test

As an optional final layer, a browser test can open the module configuration page, select the fixture tree, click "Preview", and check that the result table appears.

This is useful for catching template and JavaScript/DataTables regressions, but it should not be the only test because browser tests are slower and more fragile.

## Suggested First Automation Step

The first realistic implementation step is not the full browser test.

Start by extracting the pattern list and matching rules from `PrivacyAssistantModule` into a service class. Then add PHPUnit tests for that service. This gives fast feedback and makes the most important rules visible in code.

After that, add the GEDCOM fixture as an integration test input.
