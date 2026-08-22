---
paths:
  - 'resources/views/layouts/auth/**'
---

# Auth

## Site custom CSS also loads on the auth pages — fence it
All four auth layouts render `<x-site.head>`, so `resources/css/site.css`, `themeCss()` **and** the site-wide `custom_css` setting apply to login/register/password/2FA pages too. The admin is unaffected (it loads `admin.css` only).

This bites hardest with `body { background-image: … }`: the auth layouts get their dark surface from `dark:bg-linear-to-b dark:from-neutral-950` (a background-image) over `bg-white`, so overriding `background-image` on `body` leaves a white page in dark mode and every `dark:text-white` label goes invisible.

Fence site-wide custom CSS with `body:has(main)` — `<main>` exists in `layouts/app.blade.php` and in none of the auth layouts. Prefer the design settings (theme, fonts, radius, nav hover, header/footer layout) over CSS whenever they can express the same thing.
