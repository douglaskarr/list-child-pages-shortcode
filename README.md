# List Child Pages Shortcode

WordPress shortcode `[listchildpages]` (alias `[dknm_list_child_pages]`) that lists child pages of a parent page, with optional featured images and excerpts.

- WordPress.org: https://wordpress.org/plugins/list-child-pages-shortcode/
- Support: https://wordpress.org/support/plugin/list-child-pages-shortcode/
- SVN (releases only): https://plugins.svn.wordpress.org/list-child-pages-shortcode/

This GitHub repository is the development copy. WordPress.org Subversion is the release channel. Do not use SVN for day-to-day commits.

## Local setup

```bash
composer install
bash bin/ci_check.sh
```

PHP 8.1+ is required for tests and PHPCS. The plugin itself still runs on PHP 7.4+. The plugin file is `dklcp-shortcode.php`.

Optional live WordPress environment (needs Docker + `@wordpress/env`):

```bash
npm install -g @wordpress/env
npx wp-env start
```

## Tests and checks

| Command | What it does |
| --- | --- |
| `composer test` | PHPUnit helper-function tests (no WordPress runtime) |
| `composer phpcs` | WordPress Coding Standards |
| `composer lint` | `php -l` on the plugin file |
| `bash bin/ci_check.sh` | Lint, version-header check, tests, phpcs |

GitHub Actions runs the same CI on PHP 8.1–8.3 and [Plugin Check](https://github.com/WordPress/plugin-check-action).

## Release workflow

1. Develop and test on a branch; merge to `main`.
2. Bump **both** the `Version` header in `dklcp-shortcode.php` and `Version` / `Stable tag` in `readme.txt` to the same number. Add a changelog entry.
3. Commit, then tag and publish a GitHub Release whose tag matches that version (`1.5.1`, not `v1.5.1`).
4. `.github/workflows/deploy.yml` copies the tag into WordPress.org `trunk` and `tags/<version>`.

Readme or banner/icon/screenshot-only changes on `main` can go out without a new plugin version via `.github/workflows/assets.yml`.

### GitHub secrets (required before the first deploy)

Add these under the repo **Settings → Secrets and variables → Actions**:

- `SVN_USERNAME` — WordPress.org username (`douglaskarr`)
- `SVN_PASSWORD` — [SVN password](https://profiles.wordpress.org/me/profile/edit/group/3/?screen=svn-password), not the account login password

Until `SVN_USERNAME` is set, the readme/assets workflow skips instead of failing. A GitHub Release will fail until both secrets exist — that is intentional so a tag cannot silently skip WordPress.org.

The `gh` token used to push this repo needs the `workflow` scope the first time `.github/workflows/*` is added (`gh auth refresh -s workflow`).

### Local SVN deploy (optional)

```bash
export SVN_USERNAME=douglaskarr
export SVN_PASSWORD='…'
bash bin/deploy.sh          # dry run
bash bin/deploy.sh --commit # publish
```

`.distignore` keeps GitHub/CI files out of the WordPress.org zip.

## Current WordPress.org state

WordPress.org serves **1.5.1**. Branch `release/1.6.0` prepares **1.6.0** (author DK New Media, `dknm_lcp_` prefix with `dklcp_` wrappers in `includes/legacy.php`, and the `orderby="menu_order"` fix). The slug, folder, main file `dklcp-shortcode.php`, and text domain stay the same so existing installs keep the plugin active on update.

Build a release zip with `bash bin/build.sh [output-dir]` (uses `.distignore`).

**Required before every WordPress.org release:** Plugin Check (PCP) must pass with no errors.

```bash
bash ../bin/run-plugin-check.sh .
```

CI also runs [wordpress/plugin-check-action](https://github.com/WordPress/plugin-check-action) on every push.

## Shortcode

```
[listchildpages ifempty="No child pages" orderby="publish_date" order="desc" displayimage="no" parent="current" size="thumbnail"]
<h3>Here are our child pages:</h3>
[/listchildpages]
```

Attributes: `ifempty`, `order`, `orderby`, `displayimage`, `align`, `ulclass`, `liclass`, `aclass`, `parent`, `size`.
