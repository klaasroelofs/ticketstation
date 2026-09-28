# Releasing Ticketstation

These steps are for the maintainer. Users install and update through Joomla, see the [README](README.md).

1. Raise `<version>` (and `<creationDate>`) to the same new version in all three manifests: `pkg_ticketstation.xml`, `packages/com_ticketstation/ticketstation.xml` and `packages/mod_ticketstation_basket/mod_ticketstation_basket.xml`, also when only one extension changed. The build refuses to run when they differ.
2. Write the release notes in `release-notes/<version>.md`, for the people who use Ticketstation: start with `## What's new in <version>`, use `###` subheadings and `- ` list items, and add a `## Check before you update` section when admins need to check something. Leave out the install line; the workflow adds it to the GitHub release. The file ends up in the package, and after a successful install or update Joomla shows it on the installer result page (`pkg_script.php`). Only headings, list items, paragraphs, `**bold**`, `*italic*`, `` `code` `` and `[links](https://...)` are converted there. Commit it together with the version bump and push.
3. Tag the commit with that version and push the tag:

   ```
   git tag v<version>
   git push origin v<version>
   ```

The [release workflow](.github/workflows/release.yml) then builds the package and publishes a GitHub release with the zip and the update feed, using `release-notes/<version>.md` as its text. Without that file the release lists the commits since the previous release instead, and Joomla shows no notes after the install. Joomla sites read the feed from the latest release.

## Release candidates

Give the version a suffix, such as `2.4.5-rc1` (also `-beta1`, `-alpha1` or `-dev`), in all three manifests and tag it `v2.4.5-rc1`. The workflow publishes it as a GitHub pre-release and adds it, tagged with its stability, to the update feed of the latest stable release. Joomla only offers it to sites whose Minimum Extension Stability (Extensions: Update → Options) is set to that level or lower, so production on Stable does not see it. A newer pre-release replaces the previous one in that feed, and the next stable release starts with a clean feed.
