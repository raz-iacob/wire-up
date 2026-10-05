---
paths:
  - 'database/factories/**'
---

# Factories

## Factory definitions must describe every column and stay deterministic
Model::preventAccessingMissingAttributes() is on, and a factory-created model only carries the columns its definition inserted. A column left out of the definition is missing from the in-memory model, so reading it throws — but only after Livewire re-hydrates the model and clears wasRecentlyCreated, so it fails intermittently under --parallel instead of every time (UserFactory lacked invited_at). Add every new column to its factory, even as null.

Keep definitions deterministic. An attribute derived from a random draw must follow an overridden value — use a closure reading $attributes, as MediaFactory does for type. Never randomly emit values that break downstream assertions: MediaFactory once returned a YouTube URL whose '?' percent-encoded inside image URLs. Derived unique columns need a real collision guard too — RecordTypeFactory's slug_prefix comes from a unique word but its pluralised slug can still collide.
