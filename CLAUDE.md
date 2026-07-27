# sumnermission.org — "Another Side of Heaven"

Craft CMS 5 blog where John & Kristi Sumner (non-technical authors) post mission
stories from the Caribbean. Matt maintains it. Anything touching the control
panel should stay simple and self-explanatory for the authors.

## Deployment

- Push to `main` → GitHub Actions (`.github/workflows/deploy.yml`) SSHes to the
  server and runs `git pull`, `composer install`, `migrate/all`,
  `project-config/apply --force`, `clear-caches/all`.
- Production lives at `/var/www/sumnermission.org` on the `mlj.one` server
  (ssh alias `mlj.one`; foodnotfrenzy.com and other personal sites live on the
  same box with the same setup).
- The deploy runs as user `matt`. **All project files must stay owned
  `matt:www-data`** or the pull fails; `storage/` and `web/images/` are
  runtime dirs PHP writes to — leave their ownership alone.
- `web/images/` holds all CMS asset uploads. It is untracked but **not**
  gitignored — never `git clean` the whole tree on the server.
- Run craft commands on the server as www-data: `sudo -u www-data php craft …`
- The GitHub repo was renamed to `mattbloomfield/www.sumnermission.org`
  (old remotes still work via redirect).

## Project config changes

Edit the YAML under `config/project/`, bump `dateModified` in
`config/project/project.yaml` (`date +%s`), commit, push — the deploy applies
it. Field-layout facts: `searchable` is set at the field level
(`config/project/fields/*.yaml`); per-instance layout settings like
`includeInCards`, `providesThumbs`, `instructions`, and handle overrides live
in the entry type / volume / global set YAML.

## Subscriber email notifications

Custom module (`modules/site/`) emails all subscribers when a blog entry is
saved in the *live* state for the first time (`Site.php` after-save handler).

- Transport: Brevo plugin, key in `.env` `BREVO_API_KEY`. A 401 "API Key is
  not enabled" means the key/account is disabled in the Brevo dashboard.
- Every send attempt is recorded in the `blog_notifications` DB table
  (created on demand, backfilled so pre-existing posts never re-notify) and
  logged to `storage/logs/blog-notifications-*.log` (info level survives
  production because the module registers its own Monolog target).
- Console commands (run on the server):
  - `php craft site/notify/status` — send log
  - `php craft site/notify/test --to=x@y.com` — one test email
  - `php craft site/notify/send --entry-id=N [--force]` — notify subscribers
- The CP entry sidebar for blog posts has a send/re-send button with a
  confirmation (web `NotifyController`, template `site/_cp/notify-sidebar`).
- Subscribers are entries in the `subscribers` section; unsubscribed = disabled.

## SEO / meta

SEOMate renders everything via `{% hook 'seomateMeta' %}` in
`templates/_layouts/generic-page-layout.twig` (including the `<title>` — don't
add another). Config in `config/seomate.php`. Per-template overrides use
`{% set seomate = { meta: { … } } %}` (see blog post/home templates).
Sitewide fallback description/image come from the SEO global set in the CP.

## Content model notes

- The shared `image` assets field is capped at **one** asset (it's both the
  post Featured Image and the Image block's field).
- Image blocks show the asset's own caption; the optional "Caption Override"
  field (instance handle `caption`, Overrides tab) wins when set — see
  `templates/_pageBuilders/articleBody.twig`.
- Asset captions on the Images volume use the shared `text` field with
  instance handle `caption`.

## Database access

`mysql` on the server with creds from `.env` (`CRAFT_DB_USER`,
`CRAFT_DB_PASSWORD`, `CRAFT_DB_DATABASE`); strip quotes from values.
