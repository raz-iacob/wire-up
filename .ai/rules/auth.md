---
paths:
  - 'resources/views/layouts/auth/**'
---

# Auth

## The auth pages share the site head, minus site-wide custom CSS
All four auth layouts render `<x-site.head>`, so `resources/css/site.css`, `themeCss()`, the Google font links and the `head_scripts` setting all apply to login/register/password/2FA too. The admin is unaffected (it loads `admin.css` only).

The one thing deliberately withheld is the site-wide `custom_css` setting: the auth layouts pass `:site-custom-css="false"`. Keep it that way. The auth layouts get their dark surface from `dark:bg-linear-to-b dark:from-neutral-950` (a background-image) over `bg-white`, so an owner writing an unfenced `body { background-image: … }` used to leave a white page in dark mode with every `dark:text-white` label invisible — locking themselves out of their own login. `LoginTest` covers all four layouts against exactly that.

Anything genuinely shared between the site and the auth pages belongs in the theme tokens or `site.css`, not in the custom-CSS setting.
