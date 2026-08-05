## Summary

<!-- What does this change and why? -->

## Checklist

- [ ] `composer lint` passes (PHPCS)
- [ ] `composer analyze` passes (PHPStan)
- [ ] `composer test` passes (PHPUnit, unit)
- [ ] `composer test:integration` passes (PHPUnit, integration) — CI runs these too, they are not optional
- [ ] New behaviour has a test, and the test was counter-checked: it fails when the change is removed
- [ ] User-facing strings use the `living-handbook` text domain
- [ ] Output escaped, input sanitized, capabilities and nonces checked
- [ ] Changelog: the short entry in `readme.txt` (if user-facing), the reasoning in `CHANGELOG.md`
- [ ] English docs in `docs/` updated (if behaviour or API changed)
