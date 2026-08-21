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
