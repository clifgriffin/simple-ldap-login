# Pro and Enterprise licenses

Free LDAP password login does not need a PaidCommunities account or license. The Pro ZIP contains the paid features for both annual plans. It replaces Free in the same plugin folder. A verified license determines which paid features can run.

## Products and plans

PaidCommunities uses a separate product for each feature tier. The **Simple LDAP Login** product family groups the two products. The family ID is `prgrp_9R8wuq4vA3Dtc9b8`.

| Plan | Annual price | Sites | Product ID | Plan ID |
| --- | --- | --- | --- | --- |
| Pro | USD 99 | 1 | `prd_9QyrKxQpvZGrcCW8` | `plan_9R8xkMlq1nmCekmG` |
| Enterprise | USD 499 | 1 | `prd_9R92jB1prxMfJR36` | `plan_9R937DcDDy4wMH4x` |

Pro is the default purchase choice, as the user requested. The previous USD 499 plan, `plan_9QysG8ogD3yHM8sO`, belongs to the Pro product and is retained for existing records. Its inactive status and the family's exact two-product membership were verified after reload. The Enterprise product remains Draft without a release. No release has been assigned as part of this setup. The pricing-page editor still needs a storage fix. See the [catalog setup](product-plan.md#paidcommunities-catalog-setup).

Pro includes backup LDAP servers, SSO, custom user fields, group role rules, and profile updates at login. Enterprise includes these features plus scheduled account import, scheduled access removal and session removal, multisite membership rules, and audit history with retention and CSV export. See the [complete feature table](product-plan.md#free-pro-and-enterprise-features).

The feature checks, purchase links, activation selector, license messages, and upgrade prompts are implemented. The plugin makes an authenticated status request for Enterprise first, then Pro if Enterprise returns `invalid_product`. It saves the verified product as `product_id` in local license state. Both plans use the same paid ZIP. Access does not depend on the price, a plan ID, or the administrator's activation choice.

| License state | Paid feature behavior |
| --- | --- |
| Not activated | All paid features are off. |
| Active Pro | Backup LDAP servers, SSO, custom fields, group role rules, and profile updates can run. Enterprise features are off. |
| Active Enterprise | All nine paid feature groups can run. |
| Active with a missing or unknown product | All paid features are off. Select **Check license** to verify the plan. |
| Expired or inactive | All paid features stop. LDAP uses only the first configured server. Saved settings, user data, audit records, and suspensions remain. |
| Renewed or changed product | A successful check applies the verified product entitlement. |
| Service error | Keep the last verified status and entitlement. An expired license stays expired. |

LDAP password login, account creation, login group checks, and local recovery accounts remain available in every state. Existing account suspensions and directory identity checks remain enforced. License expiry does not end existing WordPress sessions. Pro settings remain available. Enterprise controls are disabled without an Enterprise license and show a link to the License tab. Saving another tab keeps their saved values.

## Activation and checks

Install the Pro ZIP, then open its License tab. Choose **Pro** or **Enterprise** to match the purchase receipt, enter the key, and activate it. This choice selects the product for domain registration. The authenticated license response determines access. Invalid plan choices are rejected before a service request. **Check license** and deactivation do not need a plan choice.

Activation sends the key, configured site domain, plugin version, and selected product ID to PaidCommunities. The service also receives the server IP address. The original key is not stored. The site activation secret is encrypted with the same key source used for LDAP credentials. License data uses `sll_pro_license`, separate from the public LDAP settings API.

The shared Composer dependency is `paidcommunities/wordpress-sdk` at `0.0.4`. The build places the SDK under `SimpleLdapLoginVendor`. Only the Pro ZIP loads its local `LicenseActivation` React component. Neither package initializes `PluginConfig`, the SDK admin handlers, or its updater. The component calls the checked `wp_ajax_sll_license` handler through its `apiService` prop. A normal form remains available if JavaScript fails. The shared HTTP client checks HTTP status and JSON data, blocks redirects, and uses a ten-second timeout. Keys, secrets, remote error bodies, LDAP settings, and directory records are not logged.

The authenticated `plugins/update_check` endpoint checks the product ID in each request. The response's `slug` must match that ID. `code: invalid_product` denies access to that product even when `license.status` is `active`. `code: no_release` permits a license check before a release is assigned. A normal response without a code is also accepted. Unknown codes cannot grant active access. Missing license data and invalid statuses are errors. Only `active`, `inactive`, and `expired` statuses are accepted. A confirmed inactive or expired status stops paid features even with another result code. Enterprise takes priority; if both products are denied, paid features stop. These requests run during activation and background checks, never during login. See the [SDK source](https://github.com/paidcommunities/paidcommunities-wordpress-sdk/blob/4bfaef0967c01daf720fd5182d87fe91451ca20d/src/UpdateController.php).

The [general License object](https://paidcommunities.com/documentation/objects/license/) documents `product_id` and `product_ids`, but the live WordPress update response does not include those fields. Do not require them in this endpoint's response. Product access comes from the result of the authenticated request for that product.

Activation schedules `sll_pro_license_check` hourly through WP-Cron. `License::plan()` reads only saved local state. Login never calls PaidCommunities. Confirmed expiry takes effect after a successful check. WP-Cron needs site traffic or a server cron task. A delayed task delays expiry detection. Administrators can select **Check license** after renewal or a product change. Older activation records without a verified product retain their check schedule but cannot enable paid features until a check verifies the product.

License data uses site options for a site activation and network options for a network activation. Network checks run on the main site. A copy at a different configured site address needs its own activation. The service controls activation limits; live staging and multisite terms still need acceptance tests.

The admin handler requires POST, `manage_options` or `manage_network_options`, a valid nonce, and the correct settings scope. Failed permission checks do not read keys, call the service, or clear local activation data. Failed remote deactivation also keeps local data.

## Purchases and Enterprise access

Free's Go Pro tab lists both prices and the correct feature split. Each purchase button links directly to that product's annual plan. Free has no license form and loads no purchase service scripts. Opening Go Pro makes no purchase service request. A small notice at the bottom explains checkout data sharing. A retained active license hides another purchase offer for the same plan. A retained license without a verified product shows installation and license-check instructions.

`Commerce::checkout_url()` selects Pro by default. Passing `enterprise` selects the Enterprise product and annual plan. Both URLs are recorded in the [catalog setup](product-plan.md#paidcommunities-catalog-setup). Links use `rel="noreferrer"` and open in the same tab. PaidCommunities handles payment at `https://checkout.paidcommunities.com`.

After purchase, replace Free with the Pro ZIP from the receipt through WordPress. Activate the license in Pro. The replacement keeps LDAP settings and any existing license record. Free does not download or install executable code.

The paid License tab identifies the verified plan. Active Pro users see **Get Enterprise**. It opens checkout for a new license; it does not change or cancel a Pro subscription. Manage the Pro subscription separately through PaidCommunities. After purchase, deactivate the current key, choose Enterprise, and activate the new key. Deactivating a site key does not cancel its subscription. An active Enterprise license has no repeat purchase prompt.

## Checks and release limits

### Local service test: 2026-10-30

The dashboard was changed to **Test** mode. Its Stripe test connection was already configured. In the Pro product menu, **Preview Checkout** opened a page with a **TEST MODE** label and Stripe test inputs. The normal purchase link did not show that label. Use the dashboard preview for payment tests. Checkout needs a customer account password; no purchase was submitted.

A Pro license was created in Test mode, limited to `simple-ldap-demo.test` and one site. Its record is `lic_9R9J7uE84CBYPBXV`. Keep the key out of this repository. The local demo used the real SDK and service. Domain registration and the authenticated update check both returned HTTP 200. The response's `license` object contained only `limit` and `status`, without `product_id` or `product_ids`. The plugin rejected activation and saved no local activation record. The first diagnosis incorrectly treated the missing fields as a service defect. The test below found the supported product check.

A second registration failed, so the test domain could not be deactivated through that request. The license detail page also failed with `Cannot read properties of undefined (reading 'filter')`. Check and clear the remote activation when that page works. Opening **New Product** also created an unused blank draft, `prd_9R9Io1bCtkGSO939`; no plan or release was added to it.

The local license suite passed 177 checks after this service test.

### Product checks and correction: 2026-11-02

The documentation and both the installed and current upstream SDK were reviewed. The SDK sends `product_id` for registration and update checks; it does not require product IDs in the response's license object.

Two new service-issued test keys were limited to `.test` domains. With the Pro key, Enterprise registration returned HTTP 400. Pro registration succeeded. With its domain secret, the Pro status request returned `no_release`; the Enterprise request returned `invalid_product`. Both responses had HTTP 200 and `license.status: active`. An unknown product returned no license data. The test removed its domain activation.

The corrected plugin passed eight checks against the real service on the local demo: Enterprise rejection with a Pro key, Pro activation, no saved license key, Pro refresh, Pro deactivation, Enterprise activation with the same code, Enterprise refresh, and Enterprise deactivation. Both test activations were removed. No payment was submitted. The local license suite passes 173 checks with fixtures based on these real responses. Source checks, coding standards, and Free/Pro ZIP tests also pass. Payment, renewal, expiry, and keys from completed purchases still need live tests.

`php tests/licenses.php` tests the real SDK with controlled HTTP responses. It covers Pro and Enterprise products, activation choices, unknown and missing products, expiry, renewal, inactive licenses, timeouts, invalid JSON, HTTP errors, storage failure, scope, secret storage, and copied activations. Admin and JavaScript tests cover the feature lists, checkout links, plan selector, upgrade prompts, permissions, nonces, and form output. The feature tests check Pro denial and Enterprise access for jobs, membership changes, audit collection, retention, and export.

The tests do not use a live customer license. Before release, check a real purchase for each product, expiry, renewal, cancellation, refund, site limits, staging, and network activation with PaidCommunities. Test product changes and retained account suspensions. Complete the external updater separately. Verify both checkout URLs and current payment methods. Fix the pricing-page feature storage. Assign a tested release only after these checks pass.
