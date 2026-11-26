# Release candidate manifest

Baseline is the clean GitHub Desktop checkout and GitHub main at `b737579640c26f013ab5e718c033cce65e08dd00`. The development checkout has an unborn main. The baseline contains the pre-9A admin, wallet and Phase 8 UI source, including its original landing screenshot. An authenticated Cloud revision was unavailable, so the exact deployed diff is open. The Git reference comparison from `76031f8` to `b737579` covers 73 changed paths, including those feature additions.

The candidate contains 427 source files, including this manifest and the existing LICENSE. The complete synchronization delta is 11 additions, 8 modifications and 0 deletions. Text SHA-256 hashes normalize CRLF to LF. Binary hashes use original bytes. This manifest omits its own hash.

| Change | Path                                                                                     | SHA-256                                                            |
| ------ | ---------------------------------------------------------------------------------------- | ------------------------------------------------------------------ |
| Add    | `database/migrations/2026_11_25_205525_protect_generation_settlements_from_mutation.php` | `43c066a5804a0ee43b763244be0076a3c149e1281d564e4bcd34bbbe0c2b33f3` |
| Add    | `docs/RELEASE_MANIFEST.md`                                                               | Self                                                               |
| Add    | `docs/RELEASE_RUNBOOK.md`                                                                | `e9768f746c6c1391125a3a8f058bc333b8554ec24ded95290015f49d65ced25d` |
| Add    | `docs/images/credit-wallet.png`                                                          | `3fce6a4c4212366f30b757264302495defe1579aba015db6cbdc1d8aeaddff65` |
| Add    | `docs/images/financial-reports.png`                                                      | `f1904f3c78a704a181d906319969ac59bc64ee701f7f125c0a72c16fa2e140b1` |
| Add    | `docs/images/generation-detail.png`                                                      | `55cbfead68ca1b0d253d2b782f61ac2d472a149f7d8fa9881a917996e8829b31` |
| Add    | `docs/images/studio-composer.png`                                                        | `86fb339b762a90073e2878f9a34c091e9eeb748f4028109b62f7e3a671f6eba6` |
| Add    | `tests/Postgres/bootstrap.php`                                                           | `9c0277a0835248e91f7e16e04cb7fb8e61794910e0e0a1f75309c4de0e05a4af` |
| Add    | `tests/Postgres/operations.php`                                                          | `484d259d30b77b2e77bc43a8be6ae1fd654a14c936726deed0478cad4a49e406` |
| Add    | `tests/Postgres/run.py`                                                                  | `a4fbacc182c9b1c5c1d59073d7b5ed011ba72c339d636d7cd53ccf36c24a9516` |
| Add    | `tests/Postgres/suite.php`                                                               | `aba0f338ead55099ff31ebe27e94e31935d2338a46ed01b64588565d97b4f34b` |
| Modify | `README.md`                                                                              | `aa726af47c15927741f996ce7d964adef2d4e5c9c49c83a8273a996c8bf3f715` |
| Modify | `app/Http/Controllers/Settings/ProfileController.php`                                    | `64dec888e15255baf8bee17e4f86b0c1380033948489f64768c6e86c97072596` |
| Modify | `docs/ARCHITECTURE.md`                                                                   | `de487ab8b9b75256d822f2adad8fdcb0f6ba6ad76e2a0cb67e9930e9329577ff` |
| Modify | `docs/MANUAL_TESTING.md`                                                                 | `88d895853b131c8d748aede286e28f8ecf099e7f33084471836edbd1ea5a3998` |
| Modify | `docs/VERIFICATION.md`                                                                   | `25b6623647d9d8d4244e2a329e4d428d5064888747ba1ffb473355c03ab948f4` |
| Modify | `phpunit.xml`                                                                            | `ff351cc96d9c904abd3f8f912fddbce71b6e08d4b19237d99fa3c6655390ec08` |
| Modify | `tests/Feature/CreditWalletTest.php`                                                     | `40dd44e6f64caa76da560fd6f0654b002d6cd65944b1ce4485c54ff990757844` |
| Modify | `tests/Feature/GenerationPersistenceTest.php`                                            | `60977110a743be991a745dcbf245209635cab62e1136e3de6c9ecf590c96c684` |

<details>
<summary>Feature paths already on GitHub since the documentation baseline</summary>

```text
M	README.md
M	app/Actions/Fortify/CreateNewUser.php
M	app/Actions/Generations/ApplyGenerationSnapshot.php
M	app/Actions/Generations/CreateGeneration.php
A	app/Console/Commands/ManageAdmin.php
A	app/Domain/Billing/FinancialReport.php
A	app/Domain/Billing/FinancialTotals.php
A	app/Domain/Billing/ProviderReconciliation.php
A	app/Domain/Billing/ReportPeriod.php
A	app/Domain/Billing/WalletAccounting.php
A	app/Http/Controllers/Admin/FinancialReportController.php
A	app/Http/Controllers/Admin/GenerationFinancialController.php
A	app/Http/Controllers/Admin/RefreshReconciliationController.php
M	app/Http/Controllers/CreateGenerationController.php
M	app/Http/Controllers/GenerationShowController.php
M	app/Http/Controllers/GenerationStatusesController.php
M	app/Http/Controllers/QuoteGenerationController.php
M	app/Http/Controllers/Settings/ProfileController.php
M	app/Http/Controllers/StudioController.php
A	app/Http/Controllers/WalletController.php
M	app/Http/Middleware/HandleInertiaRequests.php
A	app/Http/Requests/BuyCreditsRequest.php
M	app/Http/Requests/CreateGenerationRequest.php
A	app/Http/Requests/FinancialReportRequest.php
M	app/Http/Resources/GenerationResource.php
M	app/Integrations/Pixelforge/PixelforgeClient.php
A	app/Integrations/Pixelforge/PixelforgeUsageData.php
M	app/Jobs/SubmitGeneration.php
A	app/Models/CreditReservation.php
A	app/Models/CreditWallet.php
M	app/Models/Generation.php
M	app/Models/User.php
M	app/Providers/AppServiceProvider.php
M	database/factories/UserFactory.php
A	database/migrations/2026_11_25_042923_add_is_admin_to_users_table.php
A	database/migrations/2026_11_25_052233_create_credit_wallet_tables.php
M	docs/ARCHITECTURE.md
M	docs/MANUAL_TESTING.md
M	docs/VERIFICATION.md
A	docs/images/studio-landing.png
A	public/art/registration-study.svg
M	resources/js/components/AppSidebar.vue
A	resources/js/components/RegistrationStudy.vue
A	resources/js/components/StudioGuide.vue
A	resources/js/components/generations/FinancialGenerationTable.vue
A	resources/js/components/generations/FinancialTable.vue
M	resources/js/components/generations/GenerationCard.vue
M	resources/js/components/generations/GenerationComposer.vue
M	resources/js/components/generations/GenerationImage.vue
M	resources/js/components/generations/GenerationOptions.vue
M	resources/js/components/generations/PriceEstimate.vue
A	resources/js/components/generations/ProviderReconciliationPanel.vue
A	resources/js/components/generations/ReportPagination.vue
M	resources/js/composables/useGenerationComposer.ts
A	resources/js/composables/useImageExpiry.ts
M	resources/js/pages/Welcome.vue
A	resources/js/pages/admin/financials/Index.vue
A	resources/js/pages/admin/financials/Show.vue
M	resources/js/pages/generations/Index.vue
M	resources/js/pages/generations/Show.vue
M	resources/js/pages/studio/Index.vue
A	resources/js/pages/wallet/Index.vue
A	resources/js/types/financial-report.ts
M	resources/js/types/generation.ts
M	resources/js/types/global.d.ts
M	routes/web.php
A	tests/Feature/AdminFinancialReportsTest.php
M	tests/Feature/CreateGenerationTest.php
A	tests/Feature/CreditWalletTest.php
M	tests/Feature/ExampleTest.php
M	tests/Feature/GenerationExperienceTest.php
A	tests/Feature/ProviderUsageTest.php
A	tests/Fixtures/Pixelforge/usage.json
```

</details>

## Prepare the GitHub commit locally

1. In GitHub Desktop, open the actual PixelForge checkout and verify its working tree is clean. Check that the branch and revision match the baseline above. If the baseline changed, compare its new contents before copying.
2. In GitHub Desktop create a local branch such as `release/pixelforge-final` from that baseline.
3. Copy the listed additions and modifications from the development folder into that branch checkout, preserving each relative path. Keep the existing LICENSE. Use the PowerShell block below if the paths match your setup.
4. Inspect the GitHub Desktop Changes view for all listed paths. Run `git diff --check` and review both tracked edits and additions. From the branch checkout follow the README setup and quality checks as needed. Make the commit on the local release branch and record its SHA.
5. Before any later publishing or merge, confirm the watched Cloud branch and push-to-deploy setting. Complete the [Cloud preflight](RELEASE_RUNBOOK.md) before triggering deployment.

```powershell
$source = Read-Host 'Development checkout path'
$target = Read-Host 'GitHub Desktop checkout path'
$baseline = git -C $target rev-parse HEAD
if ($baseline -ne 'b737579640c26f013ab5e718c033cce65e08dd00') { throw 'Unexpected Git baseline' }
if ((git -C $target status --porcelain) -or (git -C $target branch --show-current) -eq 'main') { throw 'Choose a clean release branch in GitHub Desktop' }
$paths = Select-String -LiteralPath (Join-Path $source 'docs/RELEASE_MANIFEST.md') -Pattern '^\|\s+(Add|Modify)\s+\|\s+`([^`]+)`' | ForEach-Object { $_.Matches[0].Groups[2].Value }
if (@($paths).Count -ne 19) { throw 'Unexpected manifest path count' }
foreach ($path in $paths) {
    if ($path -notmatch '^[a-zA-Z0-9._/-]+$' -or $path.StartsWith('/') -or $path.Split('/') -contains '..' -or $path.Split('/') -contains '.git') { throw 'Invalid manifest path' }
    $destination = Join-Path $target $path
    New-Item -ItemType Directory -Path (Split-Path -Parent $destination) -Force | Out-Null
    Copy-Item -LiteralPath (Join-Path $source $path) -Destination $destination
}
git -C $target status --short
git -C $target diff --check
```

Excluded files include workspace Git metadata, real environment files, dependency folders, generated route files, build output, logs, local databases and PostgreSQL or browser scratch directories. The example environment and empty runtime-directory placeholders are source files.
