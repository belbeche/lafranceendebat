# Contributing

[Français](CONTRIBUTING.md) | **English**

Thank you. A few simple rules, so that the debate stays peaceful even in the code.

## Before you start
1. Read the [code of conduct](CODE_OF_CONDUCT.en.md).
2. Open an issue to discuss any large change before writing code.
3. **Security vulnerabilities**: never a public issue, see [SECURITY.en.md](SECURITY.en.md).

## Welcome
Bug fixes, accessibility (RGAA / WCAG), translations, tests, documentation, performance, new official sources per topic, clearer help texts.

## Needs a discussion first
- Any change to **rankings or algorithms**: it must be justified, documented in `docs-publics/ALGORITHMES.md`, and never favour an opinion, a party or a payment.
- Any **new processing of personal data**: purpose, retention and legal basis go in `docs-publics/DONNEES_PERSONNELLES.md`.
- Read `docs-publics/ARCHITECTURE.md` and `docs-publics/CONVENTIONS_DE_CODE.md` first: they explain where to add what and how to document it.
- Anything touching moderation or payments.

## Non-negotiable rules
- No third-party trackers, no tracking pixels in emails, no ads.
- No secret, token, password or real data in a commit (even for a test).
- User-facing text is in plain French, without jargon. Code, comments and technical documentation may be in English or French.
- One change = one test. Scenarios live in `tests/e2e/`.

## Proposing a change
1. Fork, then create a branch: `git checkout -b my-feature`.
2. Code, then run the relevant tests (see the README).
3. Clear commit messages in the imperative: "Add filter by theme".
4. Open a pull request describing **the problem solved**, **what changes for the user** and **the impact on data** (if any).

## Code style
PSR-12, strict types where possible. Comments explain the *why*, not the *how*, and say where the code is meant to be extended. Algorithm classes start with a comment a non-programmer can read.
