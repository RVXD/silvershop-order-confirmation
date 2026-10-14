# silvershop/order-confirmation

One status-aware **post-payment confirmation page** for [SilverShop](https://github.com/silvershop/silvershop-core) —
the same thank-you page for guests *and* members — plus a dedicated **failed-payment page with retry**. No core
fork: everything is wired through existing extension points.

## Why

Out of the box, SilverShop sends guests and members to two different pages after paying
(`checkout/order/{ID}` vs `account/order/{ID}`), guest access to an order is session-only (so an emailed link or
a cross-device open silently 404s), a failed or cancelled payment just dumps the customer back on `/checkout`,
and there is no neutral "we're confirming your payment" state for asynchronous methods (iDEAL / Mollie). This
module fixes all of that.

## What it does

1. **Unifies the landing.** An `Order` extension implements the core `updateLink` hook, so `Order::Link()` —
   which is the payment success/return URL — is one guest-capable `checkout/complete/{ID}?t={token}` route for
   **everyone**. The member order-history view at `account/order/{ID}` keeps working unchanged.
2. **Token guest access.** Adds an opaque `GuestToken` to `Order` (32 hex chars, WooCommerce order-key style),
   generated as the order leaves the cart (and at payment initiation — see below). The confirmation and the
   receipt-email link then work **without a session** and **across devices**, alongside the existing
   session/member access.
3. **Status-aware rendering.** One page with **success / pending / failed / cancelled** variants, resolved from
   existing primitives: `Order::IsPaid()` → success, `Payable::HasPendingPayments()` → pending, otherwise
   failed/cancelled. **Pending never claims success** — asynchronous payments show a neutral "confirming" state
   (the redirect is a hint; the webhook is the truth) with a lightweight `checkout/status/{ID}` JSON poll (and a
   `<meta refresh>` fallback) to re-check.
4. **Failed-payment page with retry.** Gateway failure/cancel routes to `checkout/failed/{ID}?t={token}`, which
   re-exposes core's `OrderActionsForm` (pay again / choose another method) and guards against double-charging
   ("already paid"). The payment's reference + token are generated at **payment initiation**, so a declined
   attempt still has a reference to show.

## Installation

```bash
composer require silvershop/order-confirmation
```

Then run `dev/build?flush=all`.

Requires `silvershop/core ^6` (CMS 6, PHP 8.3 / 8.4). Optional: `silvershop/invoicing` (adds an invoice-PDF link
to the paid confirmation), `silvershop/storefront` (ships the styled templates).

## The one mandatory config (shipped for you)

SilverStripe 6 defaults `Session.cookie_samesite` to `Strict`, which **drops the session cookie on the top-level
redirect back from an off-site payment gateway**, breaking the cart and the guest order view. This module ships
the fix so every install gets it:

```yaml
SilverStripe\Control\Session:
  cookie_samesite: 'Lax'
```

(Applied except under the `test` environment, so functional tests keep their own session defaults.)

## Templates & theming

The module ships **functional, unstyled** default templates so it renders correctly headless:

- `templates/SilverShop/Page/Layout/CheckoutPage_complete.ss`
- `templates/SilverShop/Page/Layout/CheckoutPage_failed.ss`
- `templates/SilverShop/OrderConfirmation/Includes/WhatsNext.ss`
- `templates/SilverShop/OrderConfirmation/Includes/CreateAccountOffer.ss`

A theme overrides any of these by shipping the same path. The `silvershop/storefront` theme supplies the
designed, branded versions (status hero, progress stepper, `badge--status-pending`, the account-creation offer).

## How it hooks in (no core fork)

| Piece | Hook |
|---|---|
| Unified landing | `Order::updateLink()` (core calls `$this->extend('updateLink', $link)` in `Order::Link()`) |
| Guest token + reference-at-initiation | `Order::onBeforeWrite()` + `ensureConfirmationAccess()` |
| `complete` / `failed` / `status` actions | `ConfirmationControllerExtension` on `CheckoutPageController` |
| Failure/cancel URL | `updateOrderForm` / `updatePaymentForm` → `PaymentForm::setFailureLink()` |
| Immediate-error routing | `CheckoutPageController::processPaymentResponse()` (the documented `PaymentForm` hook) |

## Licence

BSD-3-Clause.
