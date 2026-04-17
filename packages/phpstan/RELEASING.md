# Releasing `blockstudio/phpstan`

The source of truth for the package lives in the Blockstudio monorepo under
`packages/phpstan`.

Public Composer releases are published from the split repository:

- `inline0/blockstudio-phpstan`

## Branch sync

The monorepo contains a workflow that can split `packages/phpstan` into the
package repository.

- pushes to `main` sync automatically to the package repo `main` branch
- manual dispatch can sync any source ref to any target branch
- manual dispatch can also create a tag in the package repo

## First release

1. Merge the desired package state into the monorepo branch you want to release from.
2. Run the `PHPStan Package Split` workflow with:
   - `source_ref`: the source branch, tag, or SHA in the monorepo
   - `target_branch`: `main`
   - `version_tag`: the package version tag, for example `v0.1.0`
3. Register `inline0/blockstudio-phpstan` on Packagist.
4. Trigger a Packagist update after each new tag.

## Versioning

Use normal Composer/Packagist tags in the split repo:

- `v0.1.0`
- `v0.1.1`
- `v1.0.0`

The monorepo does not need matching root tags for the package.
