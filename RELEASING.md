# Releasing Ticketstation

These steps are for the maintainer. Users install and update through Joomla, see the [README](README.md).

1. Raise `<version>` (and `<creationDate>`) to the same new version in all three manifests: `pkg_ticketstation.xml`, `packages/com_ticketstation/ticketstation.xml` and `packages/mod_ticketstation_basket/mod_ticketstation_basket.xml`, also when only one extension changed. The build refuses to run when they differ. Commit and push.
2. Tag the commit with that version and push the tag:

   ```
   git tag v<version>
   git push origin v<version>
   ```

The [release workflow](.github/workflows/release.yml) then builds the package and publishes a GitHub release with the zip and the update feed. Joomla sites read the feed from the latest release. A tag with a suffix, such as `v2.4.0-rc1`, becomes a pre-release, which is not offered to sites as an update.
