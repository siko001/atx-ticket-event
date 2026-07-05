# ATX Custom Blocks — example / reference

A **standalone example plugin** showing how to build your own Gutenberg blocks and
displays on top of **ATX Digital Ticketing Connect**, using only its public API —
so your customisations survive plugin updates and event re-syncs.

Point your AI (or yourself) at this folder as a working reference.

## What it demonstrates

- A server-rendered Gutenberg block `atx-example/featured-parallax` (a featured
  event as a parallax hero) with an event picker, height and overlay controls.
- Reading event data with `atx_ticketing_get_event()` and querying with
  `atx_ticketing_get_events()`.
- Reading structured meta directly (`_atx_starts_at`, `_atx_venue_name`, …).
- Commented examples (bottom of the PHP file) for:
  - a shortcode built from a custom loop,
  - injecting markup on single event pages via `atx_ticketing_before_single_event`,
  - adding a derived field via the `atx_ticketing_event_payload` filter.

## Install / try it

1. Make sure **ATX Digital Ticketing Connect** is active and you have synced events.
2. Copy the `atx-custom-blocks-example/` folder into `wp-content/plugins/` and
   activate **ATX Custom Blocks (example)**. (Or drop it in `mu-plugins/`, or move
   the PHP + assets into a child theme and enqueue from `functions.php`.)
3. Edit a page → add the **“Featured event (parallax)”** block → pick an event or
   leave it on *Auto* → publish.

No build step: the editor uses `ServerSideRender`, the frontend is vanilla JS/CSS.

## The public API (quick reference)

```php
$event = atx_ticketing_get_event();                 // current post, or pass a post/ID
$q     = atx_ticketing_get_events([                 // returns a WP_Query
	'scope'   => 'upcoming',   // upcoming | past | all
	'category'=> '',           // category slug
	'limit'   => 6,
	'orderby' => 'date',       // date | title
	'order'   => 'ASC',        // ASC | DESC
]);
```

`$event` (the payload) contains: `post_id`, `id` (Laravel id), `title`, `status`,
`venue` (`name`/`address`/`lat`/`lng`), `image_url`, `gallery_urls`,
`occurrences`, `ticket_types`, `speakers`, `sponsors`,
`registration_questions`, `checkout_url`.

Hooks:

| Hook | Signature | Purpose |
|---|---|---|
| `atx_ticketing_event_payload` | filter `($payload, $post_id)` | add/tweak event data everywhere |
| `atx_ticketing_before_single_event` / `atx_ticketing_after_single_event` | action `($event, $post)` | wrap/inject on a single event |
| `atx_ticketing_before_events` / `atx_ticketing_after_events` | action `($query, $scope)` | wrap/inject around a list |

## Rules of the road

- **Don’t** edit the ticketing plugin’s files (updates overwrite them) or the
  synced event posts (syncs overwrite them). Keep customisations here or in your
  theme.
- Always **escape on output** (`esc_html`, `esc_url`, `esc_attr`) and call
  `wp_reset_postdata()` after a custom loop.
