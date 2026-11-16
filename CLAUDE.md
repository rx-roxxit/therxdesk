> **Start here:** The RX Desk's scope and decisions live in `idea.md`, and its vocabulary in `GLOSSARY.md`. Read both before planning or changing anything. `AGENTS.md` below describes upstream TryPost (much of it Cloud-only: billing, offers, PostHog). Use it as coding rules, not as the scope of this project.

@AGENTS.md

## The RX Desk overrides

The RX Desk is a fork of TryPost. Everything in `AGENTS.md` and `.ai/rules` applies, except where this section says otherwise. When the two disagree, this section wins.

### New copy is English only (owner decision November 2026)

This overrides the 16-locale rules in `AGENTS.md` ("Writing in-app copy", "Translated copy must fit its UI slot", "Emails"), but only for copy The RX Desk adds:

- Every new user-facing string (UI, errors, toasts, emails) is written in English only, in its own file, `lang/<locale>/rxdesk.php`. No RX Desk key goes into an upstream lang file.
- The file exists in all 16 locale folders with the same keys, and every locale holds the English text. `LocalizationParityTest` therefore stays green with no test change, the frontend never shows a missing key, and upstream merges never conflict, because upstream never touches `rxdesk.php`.
- Never translate these strings, and never add RX Desk keys to `tests/fixtures/single-line-copy.php`.
- Upstream strings stay as upstream ships them.
- Everything else about copy still applies: go through `__()` / `$t`, never write a literal, and build emails in Maizzle.

## Agent skills

### Issue tracker

Issues and specs live in GitHub Issues on rx-roxxit/therxdesk (public fork; never put secrets, server IPs or credentials in issues). See `docs/agents/issue-tracker.md`.

### Triage labels

Default five-role vocabulary (needs-triage, needs-info, ready-for-agent, ready-for-human, wontfix). See `docs/agents/triage-labels.md`.

### Domain docs

Single-context: one `GLOSSARY.md` + `docs/adr/` at the repo root. See `docs/agents/domain.md`.
