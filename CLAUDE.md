# Claude Code instructions

`AGENTS.md` holds this project's coding rules. Claude Code does not load it whole: `php artisan rxdesk:agent-rules` splits it into one rule per section under `.claude/rules/upstream/agents/`, and copies `.ai/rules` to `.claude/rules/upstream/ai/`. A rule with `paths:` loads the first time Claude reads or edits a matching file; the few without load at launch. `.claude/agent-rules.php` says where each section belongs.

- A rule loads only once its files are touched. Before planning a change, open the files in scope, or read the rule by name (`grep -n '^## ' AGENTS.md` lists every section).
- Never edit `AGENTS.md`, `.ai/rules` or `.claude/rules/upstream/` by hand. The first two come from upstream TryPost (Laravel Boost regenerates part of `AGENTS.md`), and the third is generated.
- After every upstream merge, run `php artisan rxdesk:agent-rules` and commit the result. It fails on a new `AGENTS.md` section until `.claude/agent-rules.php` places it. `tests/Feature/RxDesk/AgentRulesTest.php` fails while the generated rules are out of date.
- The "open `.ai/rules/index.md` first" step in `AGENTS.md` is covered by the copied `.ai/rules`. Still grep `.ai/rules` for keywords a path match misses.
