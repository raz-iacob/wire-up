---
paths:
  - '**'
---

# General

## Agents see different tool output than you do
laravel/pao detects the CLAUDECODE / CLAUDE_CODE env vars and replaces human tool output with JSON. That JSON drops some content — a Rector "skipped rule is never registered" warning and stray stdout from tests were both invisible to an agent while plainly visible in a normal terminal.

If a human reports output an agent cannot reproduce, re-run without the flag:
env -u CLAUDECODE -u CLAUDE_CODE -u AI_AGENT vendor/bin/rector --dry-run

Also note `test` is shell-aliased to `composer test` here, so a bare `test -f somefile` in a script launches the full suite. Use [ -f somefile ] instead.

## No comments in PHP code
Never add comments to PHP code. No inline comments, no docblocks, no section dividers, no "// TODO" notes.

The only exception is PHPDoc that PHPStan/Larastan actually needs for generics and types it cannot infer — e.g. `@param Collection<int, Contact>`, `@return array<string, mixed>`, `@var` on ambiguous assignments. If PHPStan does not need it, it does not belong in the file. That includes prose sitting alongside a needed annotation inside the same docblock.

Code should be self-explanatory through descriptive names. This overrides the generic Laravel Boost guideline that suggests preferring PHPDoc blocks over inline comments.

## Always confirm commits before committing
Never run `git commit` or `git push` without confirming with the user first. Propose the exact commit message and wait for approval — do not commit as an unprompted "finishing touch" after making changes. A bare "commit it" approves THAT commit only; it is not standing permission for later ones.

Commit messages are one-line conventional commits (`feat:`, `fix:`, `refactor:`, `test:`, `chore:`). No body, no bullet lists, no co-author trailers.

Commit directly to `main`. Do not create a feature branch or a PR per feature — this deliberately overrides the default "branch first when on the default branch" behaviour. Branch or open a PR only when the user asks.

## Keep CHANGELOG.md current as you ship
Every user-visible change (feature, fix, or behaviour change) adds a bullet to an `## Unreleased` section at the top of CHANGELOG.md, in the same commit as the change. Internal-only work (refactors, tests, tooling, dependency bumps) does not.

At release: rename `## Unreleased` to `## vX.Y.Z — YYYY-MM-DD`, commit, tag `vX.Y.Z`, push with tags.

Format is load-bearing, not cosmetic. App\Services\UpdateService::parseChangelog() renders release notes on Settings → Updates by taking EVERY non-empty line of a section and stripping leading `- `. So use a flat bullet list only: no sub-headings, no blank-line-separated prose, no nested lists — a `### Foo` line would show up as a note item reading "### Foo". Only `## vX.Y.Z` headings are matched, which is why an `## Unreleased` section is invisible to installs (verified).

Never edit a section for an already-released tag: installs read CHANGELOG.md as it was AT that tag (`git show {tag}:CHANGELOG.md`), so edits on main cannot reach them and only cause drift.
