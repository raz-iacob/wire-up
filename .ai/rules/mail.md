---
paths:
  - 'app/Mail/**'
---

# Mail

## Synchronous mailables need an ArchTest exception
Pest's `laravel` arch preset requires every class in app/Mail to implement ShouldQueue, so a deliberately synchronous mailable fails with "Expecting ... to implement 'Illuminate\Contracts\Queue\ShouldQueue'".

Do not queue it to silence that. Anything reporting an outcome back to the user in the same request (App\Mail\IntegrationTestMail, sent by the Settings → Integrations Test buttons) must send now: queued, the UI would toast "sent" while the job dies later in failed_jobs against a stale worker. Add the class to the `ignoring(...)` list on `arch()->preset()->laravel()` in tests/Unit/ArchTest.php instead — same precedent as the security preset's ignores.
