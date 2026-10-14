<?php

declare(strict_types=1);

namespace SilverShop\OrderConfirmation\Extension;

use SilverShop\Model\Order;
use SilverShop\Page\CheckoutPage;
use SilverStripe\Core\Extension;

/**
 * Adds the unified post-payment confirmation behaviour to {@link Order}:
 *
 *  - unifies the post-payment landing (guest AND member) onto one `checkout/complete` URL via the core
 *    `updateLink` hook, so Omnipay's success return URL is the same page for everyone;
 *  - adds an opaque {@link $db GuestToken} so the confirmation (and the receipt email) works without a session
 *    and across devices;
 *  - resolves the status-aware confirmation state (success / pending / failed) from existing order primitives.
 *
 * @extends Extension<Order>
 */
class OrderConfirmationExtension extends Extension
{
    public const STATE_SUCCESS = 'success';

    public const STATE_PENDING = 'pending';

    public const STATE_FAILED = 'failed';

    public const STATE_CANCELLED = 'cancelled';

    private static array $db = [
        'GuestToken' => 'Varchar(32)',
    ];

    private static array $indexes = [
        'GuestToken' => true,
    ];

    /**
     * Unify the post-payment landing. Core's {@link Order::Link()} returns two different URLs (guest ->
     * checkout/order/{ID}, member -> account/order/{ID}); it ends with `$this->extend('updateLink', $link)`,
     * which calls this. We rewrite it to one guest-capable `checkout/complete/{ID}?t={token}` route for
     * everyone, so the payment success URL (= Order::Link()) and the receipt-email link agree.
     *
     * The member history view at account/order/{ID} is left untouched and keeps working.
     */
    public function updateLink(string &$link): void
    {
        $order = $this->getOwner();
        if (!$order->ID) {
            return;
        }

        $link = $this->buildActionLink('complete');
    }

    /**
     * Give every placed order a guest token. Mirrors core's Reference generation timing (both happen as the
     * order leaves the `Cart` state), so a normally-placed order always has token access without a session.
     */
    public function onBeforeWrite(): void
    {
        $order = $this->getOwner();
        $status = (string) $order->getField('Status');
        if (!$order->getField('GuestToken') && $status !== '' && $status !== 'Cart') {
            $order->setField('GuestToken', $this->generateGuestToken());
        }
    }

    /**
     * Make sure the order has both a guest token and a reference, writing once if either was missing.
     *
     * This is called at payment *initiation* (when the checkout payment form is built). With the default
     * `place_before_payment = false`, a declined/cancelled attempt leaves the order a `Cart` with no
     * Reference — generating one here means the failure page and support always have a reference to show
     * (PLAN-post-payment §5.2 / design-rationale §29.5).
     */
    public function ensureConfirmationAccess(): void
    {
        $order = $this->getOwner();
        if (!$order->ID) {
            return;
        }

        $changed = false;
        if (!$order->getField('GuestToken')) {
            $order->setField('GuestToken', $this->generateGuestToken());
            $changed = true;
        }
        if (!$order->getField('Reference')) {
            $order->generateReference();
            $changed = true;
        }

        if ($changed) {
            $order->write();
        }
    }

    /**
     * Resolve the status-aware confirmation state from existing order primitives.
     *
     *  - paid                          -> success
     *  - unpaid + a pending payment    -> pending   (async iDEAL/Mollie: redirect is a hint, webhook is truth)
     *  - otherwise                     -> failed    (declined / cancelled / no successful attempt)
     *
     * The controller may override this to `cancelled` from an explicit `?status=` on the failure route.
     */
    public function getConfirmationState(): string
    {
        $order = $this->getOwner();
        if ($order->IsPaid()) {
            return self::STATE_SUCCESS;
        }
        if ($order->hasMethod('HasPendingPayments') && $order->HasPendingPayments()) {
            return self::STATE_PENDING;
        }

        return self::STATE_FAILED;
    }

    /**
     * The unified confirmation page link, guaranteed to carry a token (and reference). Server-side callers
     * (the failure-routing hooks, retry redirects) use this; templates can use `$Order.ConfirmationLink`.
     */
    public function ConfirmationLink(): string
    {
        $this->ensureConfirmationAccess();
        return $this->buildActionLink('complete');
    }

    /**
     * The failed-payment / retry page link, guaranteed to carry a token (and reference).
     */
    public function FailedLink(): string
    {
        $this->ensureConfirmationAccess();
        return $this->buildActionLink('failed');
    }

    /**
     * The lightweight status-poll endpoint link (used by the pending page to re-check for confirmation).
     */
    public function StatusLink(): string
    {
        return $this->buildActionLink('status');
    }

    protected function buildActionLink(string $action): string
    {
        $order = $this->getOwner();
        $link = CheckoutPage::find_link(false, $action, $order->ID);
        $token = (string) $order->getField('GuestToken');
        if ($token !== '') {
            $link .= '?t=' . $token;
        }

        return $link;
    }

    protected function generateGuestToken(): string
    {
        // 16 random bytes -> 32 hex chars, fits Varchar(32). Opaque, WooCommerce order-key style.
        return bin2hex(random_bytes(16));
    }
}
