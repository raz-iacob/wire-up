---
paths:
  - resources/views/components/site/blocks/partials/circuit.blade.php
---

# Partials

## The circuit hero effect is solved geometry, not free-hand SVG
Three invariants hold this partial together. Break one and it looks fine in a still but wrong in motion.

1. Every coordinate is a multiple of 40, because the faded grid behind the traces is a 40-unit `<pattern>`. Off-grid coordinates make the traces visibly drift off the grid they are supposed to be routed on.
2. Corners are `A24 24` arcs, so every straight segment must be at least 48 units or the arcs overrun each other and the corner inverts.
3. Terminator flare timing is derived, not chosen. Each trace's charge is a dash animating `--from` to `--to` over `--dur`; the dot at the end fires at `--arrive`, computed as `delay + dur * (16 + 992) / (16 + 1000 + extra)` where `extra` is the over-travel that buys the rest between runs. Change a path, a duration or a delay and `--arrive` has to be recomputed, or the dot flashes while the charge is still mid-run.

Speed is deliberately constant across traces: `--dur` came from each path's real `getTotalLength()` divided by 260 units/sec, plus rest. That is why the durations look arbitrary — they are not, and picking round numbers instead makes short stubs crawl and long routes race.

The markup was machine-generated from a point-list plus a chamfer/arc router. Hand-editing one path is fine if you honour the three rules above; reworking the layout is much easier by regenerating.

Note pint's Blade formatter needs two passes to converge on this file — the first run reports a fix and still fails `composer test:lint`. Run it again.
