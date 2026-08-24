---
paths:
  - 'app/Mcp/Tools/**'
---

# Tools

## The assistant tool surface is a reviewed snapshot
HiddenFromAssistant is a deny-list, so a new tool is exposed to the in-admin assistant the moment it is registered — nothing has to opt in. For a long time no class implemented the marker at all, which made the documented "everything except user management and integrations" invariant inert.

tests/Feature/SiteAssistantTest.php now pins it two ways: an explicit sorted list of the tool names the assistant may see, and a scan rejecting any visible tool whose source mentions App\Models\User, App\Models\Role or an integration credential key (ai_api_key, slack_webhook_url, mail_password, pexels_api_key, google_maps_api_key, google_analytics_credentials).

Adding a tool fails the snapshot on purpose. Decide before you edit the list: if it touches accounts, roles or credentials it must `implement HiddenFromAssistant` instead of being added. Do not widen the credential scan to App\Models\Settings — that is the general settings store and delete-page legitimately reads home_page_id from it to protect the homepage.
