# WordPress.org deployment

## Overview

- Plugin slug: `acme-coming-soon`
- Publication target: `https://plugins.svn.wordpress.org/acme-coming-soon/`
- Workflow: `.github/workflows/deploy.yml`
- Publishing environment: `wordpress-org`
- Manual dispatch: `dry_run` defaults to checked

Git is the development source. WordPress.org SVN is used only as a release
repository. The workflow never writes to a developer's local SVN checkout.

## Verified release requirements

| Declaration | Value |
| --- | --- |
| Plugin version | `1.0.7` |
| Minimum PHP | `8.3` |
| Minimum WordPress | `7.0` |
| Tested WordPress | `7.1` |

`Tested up to` is declared only in `readme.txt`.

## Release flow

1. The `package` job resolves one immutable Git commit. Real publishing requires
   an exact versioned tag whose version matches `package.json`.
2. It installs locked dependencies, runs PHP/JavaScript checks and tests, and
   builds the release package twice to prove reproducibility.
3. The reusable runtime workflow tests the exact commit on WordPress `7.0.9` and
   `7.1.5`. Every matrix job must match the package ZIP digest.
4. The validator checks metadata, changelogs, runtime files, exclusions,
   placeholders, text domains, and package contents.
5. The artifact name includes the version, commit, and ZIP digest prefix. A
   manifest records the full SHA-256 and provenance.
6. A credential-free SVN preview always runs before deployment.
7. The protected `deploy` job starts only after the preview and runtime matrix
   succeed. It downloads the same artifact and verifies its digest.
8. The SVN script updates `trunk/`, creates `tags/<version>/`, and commits once.
9. Remote trunk and tag exports are verified at the actual committed revision.
10. After WordPress.org processing, verify the Plugin Directory API and download.

## Manual dry run

1. Open **Actions → Deploy Acme Coming Soon to WordPress.org → Run workflow**.
2. Select the reviewed ref.
3. Leave `dry_run` checked.
4. Review the package artifact and `svn-preview-*` artifact.

Dry runs never receive SVN credentials.

## Manual real deployment

1. Create and push the exact annotated `v1.0.7` tag after release approval.
2. Dispatch the workflow with that exact tag as both the UI ref and `ref` input.
3. Set `dry_run` to unchecked.
4. Review the automatically generated SVN preview.
5. Approve the protected `wordpress-org` environment.

The workflow rejects branch names, missing tags, and version mismatches before a
real deployment. A stable published GitHub Release follows the same path.

## One-time WordPress.org setup

1. Confirm the WordPress.org account is listed as a committer:
   `https://wordpress.org/plugins/acme-coming-soon/advanced/`
2. Enable Two-Factor Authentication.
3. Generate an SVN-specific password:
   `https://profiles.wordpress.org/me/profile/edit/group/3/?screen=svn-password`
4. Store it securely. The username is case-sensitive.

## GitHub setup

These repository settings require separate administrator approval.

Create the `wordpress-org` environment:

1. Add required reviewers.
2. Restrict deployment to tags matching `v*`.
3. Disable administrator bypass.
4. Add environment secrets:

| Secret | Value |
| --- | --- |
| `SVN_USERNAME` | Exact WordPress.org committer username |
| `SVN_PASSWORD` | Generated SVN-specific password |

Protect `v*` tags with an active tag ruleset:

- Restrict creations
- Restrict updates
- Restrict deletions
- Block force pushes
- Limit bypass access to release maintainers

## Local validation

```bash
composer install --no-interaction --prefer-dist
composer validate --strict --no-check-publish
npm ci --no-audit --no-fund
npm run deploy
php .github/scripts/validate-release.php 1.0.7 deploy/acme-coming-soon
python3 .github/scripts/verify-artifact.py 1.0.7 deploy
composer lint
composer test
vendor/bin/phpcs --report=summary
```

Two complete `npm run deploy` runs on the same commit must produce identical ZIP
SHA-256 values.

## Plugin Check exceptions

Plugin Check runs without ignoring errors, and the only ignored result code is
`react_removed_api`. The published build bundles ATRC 1.0.37, which still
references `ReactDOM.findDOMNode`, `ReactDOM.unmountComponentAtNode`, and
`ReactDOM.unstable_renderSubtreeIntoContainer`. These calls live in the upstream
vendor bundle rather than in this plugin's source; drop the ignore once ATRC
ships a React 19-compatible build.

The plugin's `exclude` settings key is not a `WP_Query` argument, so the
`WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude` false positives
are suppressed inline with `phpcs:ignore` annotations at each settings definition.

## Runtime coverage

CI installs the built ZIP on disposable WordPress 7.0 and 7.1 environments and
checks activation, admin assets, REST permissions and partial updates, maintenance
redirects, access/exclusion rules, fail-closed behavior, public-content boundaries,
patterns, persistence, lifecycle, and PHP debug output. Playwright executes the
admin UI, including IP/slug repeater rows and saving settings.

## Release procedure

Only after separate publication approval:

1. Confirm all version fields are `1.0.7`.
2. Confirm CI, Plugin Check, runtime, browser, and package checks pass.
3. Create and push annotated tag `v1.0.7`.
4. Run the manual workflow with `dry_run` checked.
5. Review both artifacts.
6. Publish the stable GitHub Release or dispatch the exact tag for deployment.
7. Approve the `wordpress-org` environment.
8. Confirm immediate SVN verification passes.
9. Verify the public API and download after WordPress.org processing.

## Rollback

Never modify or reuse an existing SVN release tag. Prefer a fix-forward release.
