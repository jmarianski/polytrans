# Release Process

This document describes how to create a new release of the PolyTrans plugin.

## Prerequisites

- Push access to the repository
- All changes merged to `main` branch
- All tests passing
- CHANGELOG.md updated

## Automated Release Process

The plugin uses GitHub Actions to automatically build and release when you push a version tag.

### Step 1: Update Version Numbers

Update version in these files:

1. **polytrans.php** (2 places):
   ```php
   * Version: 1.5.0
   define('POLYTRANS_VERSION', '1.5.0');
   ```

2. **CHANGELOG.md**:
   ```markdown
   ## [1.5.0] - 2025-12-10
   ```

3. **README.md**:
   ```markdown
   **Version**: 1.5.0
   ```

### Step 2: Commit Version Changes

```bash
git add -A
git commit -m "chore: Release version 1.5.0"
git push origin main
```

### Step 3: Create and Push Tag

```bash
# Create annotated tag
git tag -a v1.5.0 -m "Release version 1.5.0"

# Push tag to GitHub
git push origin v1.5.0
```

### Step 4: Automated Build

GitHub Actions will automatically:

1. ✅ Checkout code
2. ✅ Install PHP 7.4
3. ✅ Run `composer install --no-dev --optimize-autoloader`
4. ✅ Create clean release directory (excludes dev files)
5. ✅ Generate ZIP archive: `polytrans-1.5.0.zip`
6. ✅ Generate SHA256 checksum
7. ✅ Create GitHub Release with files attached
8. ✅ Upload artifact for 30 days

### Step 5: Verify Release

1. Go to: https://github.com/YOUR_ORG/polytrans/releases
2. Check that `polytrans-1.5.0.zip` is attached
3. Download and verify:
   ```bash
   sha256sum -c polytrans-1.5.0.zip.sha256
   ```

## What's Included in Release

### ✅ Included Files

- All plugin PHP files
- All assets (JS, CSS, images)
- Composer dependencies (`vendor/`)
- Documentation (`docs/`)
- Templates (`templates/`)
- Languages (`languages/`)
- README.md, CHANGELOG.md, LICENSE

### ❌ Excluded Files (Development Only)

- `.github/` - GitHub Actions workflows
- `tests/` - Unit tests
- `docs/planning/` - Planning documents
- `docs/development/` - Development docs
- `docs/testing/` - Testing docs
- `.gitignore`, `.gitattributes`
- `composer.json`, `composer.lock`
- `phpunit.xml`, `pest.php`
- `docker-compose.yml`, `Dockerfile`
- `.env.example`

## Manual Release (Fallback)

If GitHub Actions fails, you can build manually:

```bash
# Install production dependencies
composer install --no-dev --optimize-autoloader

# Create release directory
mkdir -p release
rsync -av --exclude='.git' \
          --exclude='.github' \
          --exclude='tests' \
          --exclude='docs/planning' \
          . release/polytrans/

# Create ZIP
cd release
zip -r polytrans-1.5.0.zip polytrans/

# Generate checksum
sha256sum polytrans-1.5.0.zip > polytrans-1.5.0.zip.sha256
```

Then manually upload to GitHub Releases.

## Versioning

We follow [Semantic Versioning](https://semver.org/):

- **MAJOR** (1.x.x): Breaking changes
- **MINOR** (x.1.x): New features, backward compatible
- **PATCH** (x.x.1): Bug fixes, backward compatible

### Examples

- `1.5.0` → `1.5.1`: Bug fix (patch)
- `1.5.0` → `1.6.0`: New feature (minor)
- `1.5.0` → `2.0.0`: Breaking change (major)

## Release Checklist

- [ ] All tests passing (`./vendor/bin/pest`)
- [ ] CHANGELOG.md updated with changes
- [ ] Version updated in `polytrans.php` (2 places)
- [ ] Version updated in `README.md`
- [ ] Version updated in `CHANGELOG.md`
- [ ] Changes committed to `main`
- [ ] Tag created and pushed (`git tag -a v1.5.0 -m "..."`)
- [ ] GitHub Actions build successful
- [ ] Release verified on GitHub
- [ ] ZIP downloaded and tested in WordPress

## Troubleshooting

### GitHub Actions Failed

1. Check workflow logs: https://github.com/YOUR_ORG/polytrans/actions
2. Common issues:
   - Composer dependencies failed → Check `composer.json`
   - ZIP creation failed → Check file permissions
   - Release creation failed → Check `GITHUB_TOKEN` permissions

### ZIP is Too Large

The release ZIP should be < 10 MB. If larger:

1. Check that `node_modules/` is excluded
2. Check that `tests/` is excluded
3. Run `composer install --no-dev` (not `--dev`)

### Missing Files in ZIP

1. Check `.gitattributes` export-ignore rules
2. Check `rsync` exclude patterns in workflow
3. Verify files exist in repository

## Support

For issues with the release process:

1. Check GitHub Actions logs
2. Review this document
3. Contact maintainers

---

**Last Updated**: 2025-12-10
**Current Version**: 1.5.0

