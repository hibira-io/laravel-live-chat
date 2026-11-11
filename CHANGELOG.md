# Changelog

## 0.1.0

- `<x-hibira-live-chat />` and `@hibiraLiveChat` print the Live Chat widget.
- The `hibira.live-chat` middleware (`InjectLiveChat`) adds it to every HTML
  page without editing views.
- Verified users: the signed-in user's ID is signed with the identity secret;
  `LiveChat::identity()` for Inertia and other SPAs.
- Hide for staff (`hideHibiraLiveChat` gate) or by request (`hideWhen`).
- `<x-hibira-live-chat-open>` buttons and `#hibira-live-chat` links open the
  chat; labels in en, ja, ko, zh_TW, zh_CN, th, vi.
- `php artisan hibira:live-chat` writes the `.env` keys and checks the site.
