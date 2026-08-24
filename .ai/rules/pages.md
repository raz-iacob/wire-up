---
paths:
  - 'resources/views/pages/**'
---

# Pages

## Do not add #[Locked] to public Eloquent model props
An audit flagged ~20 SFCs holding an unlocked public model prop (User $user on the account page next to $password and $delete_password, Page $page in the editors, and so on) as a client-writable privilege-escalation risk. It is not one — Livewire blocks both vectors itself.

Verified on this codebase: set('user', $otherUser) is silently ignored and leaves the prop untouched, and set('user.email', ...) throws "Can't set model properties directly" from ModelSynth. A scalar set() on the same component works, so the no-op is Livewire's protection, not a broken probe.

So skip #[Locked] on model-typed props — it buys nothing. Real client-writable risk lives in scalar props that feed an authorization decision (a role id, a target user id), so scrutinise those instead.
