# Pro and Enterprise licenses

Free LDAP login needs no license. Pro and Enterprise use separate ZIPs built from the same private `pro/` source. Each paid ZIP has one fixed PaidCommunities product ID. All ZIPs use the same plugin folder.

| Build | Annual price | Sites | Product ID | Plan ID |
| --- | --- | --- | --- | --- |
| Pro | USD 99 | 1 | `prd_9QyrKxQpvZGrcCW8` | `plan_9R8xkMlq1nmCekmG` |
| Enterprise | USD 499 | 1 | `prd_9R92jB1prxMfJR36` | `plan_9R937DcDDy4wMH4x` |

The product family ID is `prgrp_9R8wuq4vA3Dtc9b8`. The inactive old Enterprise plan on the Pro product, `plan_9QysG8ogD3yHM8sO`, is retained for existing records. See the [catalog setup](product-plan.md#paidcommunities-catalog-setup). A plan name, billing plan ID, price, or submitted product field cannot enable a tier.

## Native SDK integration

The Composer dependency is pinned to `paidcommunities/wordpress-sdk` `0.0.4`. Its upstream HEAD was verified as `4bfaef0967c01daf720fd5182d87fe91451ca20d` on 2026-11-02. The installed SDK and current upstream source match.

`Pro\PaidCommunities_Config` extends `PluginConfig`. It receives the main plugin file, fixed build product ID, version, and existing option name. The SDK registers its native activation/deactivation handlers, update controller, plugin information callback, assets, and response cache. The build adds `Update URI: https://paidcommunities.com` only to paid ZIPs. Free keeps WordPress.org updates. See the [SDK setup guide](https://paidcommunities.com/documentation/code-examples/wordpress-plugin/sdk-integration/) and [SDK reference](https://paidcommunities.com/documentation/sdks/wordpress-sdk/).

The React `LicenseActivation` component uses its default SDK API service. The PHP fallback also uses the SDK template and script. Both activation and deactivation go to the native `AdminAjaxController`; the plugin has no replacement activation API or HTTP client. Registration uses the product from PluginConfig. A submitted edition or product field is ignored.

`SimpleLdapLogin\License` extends the SDK License. Its read/save/delete overrides preserve the existing `sll_pro_license` option, encrypted secret, configured site binding, and site/network scope. These methods do not make service requests. The SDK supplies the status and redacted key. The original key is not stored. The SDK deletion behavior is retained, including deletion after a failed deactivation. A capability guard requires the appropriate site or network settings permission before the native handlers run.

Feature access delegates to the SDK's `isActive()` method. Pro features require an active license for the installed product. Enterprise features also require the Enterprise build. Settings must enable optional features. Free includes no paid feature code. See the [feature table](product-plan.md#free-pro-and-enterprise-features).

## Scheduled and manual checks

SDK 0.0.4 has no separate license scheduler or standalone validation service. It refreshes saved license status in `UpdateController::checkPluginUpdates()`, through WordPress's normal plugin-update checks. The plugin does not add an hourly license event. A small migration cleanup removes the old `sll_pro_license_check` event.

WordPress normally schedules `wp_update_plugins` twice daily and can also check during update administration. The SDK owns the update request, status write, and 60-second response cache. WordPress cron still needs site traffic or a system task. Delayed cron delays license status changes. Login and feature checks read saved state and do not contact PaidCommunities.

**Check license** clears the SDK's product/version cache entry and calls the same SDK update controller. It does not call an endpoint directly. Inactive licenses can be checked again, so a confirmed renewal restores features. A successful SDK save makes no second request. See the [SDK update controller](https://github.com/paidcommunities/paidcommunities-wordpress-sdk/blob/4bfaef0967c01daf720fd5182d87fe91451ca20d/src/UpdateController.php).

| SDK result | Feature behavior |
| --- | --- |
| Not activated or wrong build/product | Paid features stay off. |
| Active Pro | Backup servers, SSO, custom fields, group roles, and profile updates can run. |
| Active Enterprise | Pro features and scheduled jobs, multisite membership, and audit features can run. |
| Confirmed expiry/inactive status | Paid features stop. Saved settings, records, and existing suspensions remain. |
| Confirmed renewal | The SDK restores licensed features. |
| Failed update/status request | Keep the last saved status. An expired license stays expired. |

LDAP password login, account creation, login group restrictions, and local recovery stay available. Expiry does not end existing WordPress sessions. Enterprise controls are disabled without Enterprise access; their saved values remain.

## Compatibility guards

The plugin does not modify SDK vendor source. WordPress HTTP filters bound this product's SDK requests to ten seconds, block redirects, and limit response size. Invalid HTTP/JSON responses cannot overwrite saved status or put remote bodies in the SDK error log. Requests from other PaidCommunities plugins and the SDK download handler are left unchanged.

One SDK 0.0.4 response guard is required: the live service can return `code: invalid_product` together with `license.status: active`. The SDK reads the status alone. The guard changes that denied product's status to inactive before the SDK processes its existing response. It sends no additional request and never tries another product. Unknown active codes or a mismatched response slug are rejected.

The update callback delegates to the SDK and suppresses incomplete/no-download results and updates for inactive licenses. It still runs the SDK check for inactive licenses, so renewal works. Exceptions cannot break the admin page. The SDK's HTML view remains unmodified during dependency isolation; adding a PHP namespace after its leading HTML would break native rendering.

## Purchases and changing editions

Free lists both plans and links to their annual checkout URLs. Opening the plugin screen makes no purchase request. PaidCommunities receives the key, site domain, plugin version, product ID, and server IP during activation. Later SDK checks use the activation secret. LDAP credentials and directory records are not sent.

After purchase, install the ZIP for that plan at the existing plugin path and activate its matching key. Free does not install executable code. For Pro-to-Enterprise changes, deactivate the Pro key, install the Enterprise ZIP, and activate the Enterprise key. Checkout creates a new license; it does not change or cancel the Pro subscription. Manage subscriptions through PaidCommunities.

## Checks and release limits

`composer check:paid` runs the real SDK handlers and update controller with controlled WordPress HTTP responses. It checks both products and storage scopes, native nonce behavior, fixed product selection, one-request activation and status writes, update caching, expiry, renewal, malformed responses, copied sites, encryption, and local feature access. No custom license cron may be created. JavaScript tests verify that the activation component does not override the SDK API service.

ZIP tests exercise native PHP template rendering and isolated SDK classes. Docker tests use the native activation handler and update controller in real WordPress with controlled service responses. The prior real service probes below establish the API behavior; they do not substitute for purchase and published-release tests of the current implementation.

Before release, test completed purchases, live expiry/renewal, refunds, cancellation, site limits, staging, and a published release download through the SDK. No release is assigned by this task. The pricing-page feature storage issue also remains in the [catalog notes](product-plan.md#paidcommunities-catalog-setup).

## Product group and billing plan API test: 2026-11-02

Both service-issued test keys were used with the WordPress client API. These were manual test licenses, not keys from completed checkout purchases. No payment was made.

| Request | Result |
| --- | --- |
| Register with the key's assigned product ID | HTTP 200; domain ID and activation secret returned. |
| Register with `product_id` set to `prgrp_9R8wuq4vA3Dtc9b8` | HTTP 400; product group is not assigned to the license. |
| Register with only `product_group_id`, `group_id`, `plan_id`, or `billing_plan_id` | HTTP 400; `product_id` is required. |
| Register with `product_id` set to a billing plan ID | HTTP 400; product is not assigned to the license. |
| Authenticated update check with the group ID | HTTP 200 with no license data. It cannot establish access. |
| Update check with the correct product and a valid, invalid, or other tier's `plan_id` or `billing_plan_id` | Same active result. These fields do not select or verify a plan. |
| Authenticated domain detail or list | HTTP 404. |

The update response provides only license status and activation limit. Both annual plans cover one site, so the limit cannot identify a tier. Account license endpoints did not return usable license data with a domain activation secret. Public dashboard code stores product IDs on licenses; plans and groups use separate account endpoints.

No usable group or billing plan route was found in the exposed client API. Use three builds rather than trying product IDs in sequence. Pro uses `prd_9QyrKxQpvZGrcCW8`; Enterprise uses `prd_9R92jB1prxMfJR36`. All successful probe activations were removed. Do not put account API credentials in a distributed plugin. A published release and checkout tests remain required.
