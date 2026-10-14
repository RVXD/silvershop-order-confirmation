<?php

declare(strict_types=1);

namespace SilverShop\OrderConfirmation\Extension;

use SilverShop\Cart\ShoppingCart;
use SilverShop\Extension\OrderManipulationExtension;
use SilverShop\Forms\OrderActionsForm;
use SilverShop\Forms\PaymentForm;
use SilverShop\Model\Order;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Extension;
use SilverStripe\Omnipay\Model\Payment;
use SilverStripe\Omnipay\Service\ServiceResponse;
use SilverStripe\Security\Security;

/**
 * Adds the unified confirmation + failed-payment surfaces to the {@link \SilverShop\Page\CheckoutPageController}:
 *
 *  - `checkout/complete/{ID}?t={token}` — one status-aware thank-you page for guest AND member;
 *  - `checkout/failed/{ID}?t={token}`   — the failed / cancelled / error / already-paid page with retry;
 *  - `checkout/status/{ID}?t={token}`   — a tiny JSON poll the pending page uses to re-check for confirmation.
 *
 * Access is widened beyond the core session-only gate with an opaque order token, so the pages work
 * off-session and cross-device, without weakening the session/member paths.
 *
 * @extends Extension<\SilverShop\Page\CheckoutPageController>
 */
class ConfirmationControllerExtension extends Extension
{
    private static array $allowed_actions = [
        'complete',
        'failed',
        'status',
    ];

    /**
     * One status-aware thank-you page for every customer.
     *
     * @return array<string,mixed>|HTTPResponse
     */
    public function complete(HTTPRequest $request): array|HTTPResponse
    {
        $order = $this->resolveConfirmationOrder($request);
        if (!$order instanceof Order) {
            return $this->getOwner()->httpError(404, 'Order could not be found');
        }

        // Move the just-placed order from the cart session into the historical-orders session (as core's
        // `order` action does), so refreshes keep resolving it and the cart reads as empty.
        ShoppingCart::singleton()->archiveorderid((int) $order->ID);

        $state = $order->getConfirmationState();
        $statusParam = (string) $request->getVar('status');
        $overrides = [OrderConfirmationExtension::STATE_FAILED, OrderConfirmationExtension::STATE_CANCELLED];
        if (in_array($statusParam, $overrides, true)) {
            $state = $statusParam;
        }

        return [
            'Order' => $order,
            'ConfirmationState' => $state,
            'IsSuccess' => $state === OrderConfirmationExtension::STATE_SUCCESS,
            'IsPending' => $state === OrderConfirmationExtension::STATE_PENDING,
            'IsFailed' => $state === OrderConfirmationExtension::STATE_FAILED,
            'IsCancelled' => $state === OrderConfirmationExtension::STATE_CANCELLED,
            'PaymentErrorMessage' => $this->paymentErrorMessageFor($order),
            'Title' => $this->titleForState($state),
        ];
    }

    /**
     * The failed / cancelled / error / already-paid page, with in-place retry.
     *
     * @return array<string,mixed>|HTTPResponse
     */
    public function failed(HTTPRequest $request): array|HTTPResponse
    {
        $order = $this->resolveConfirmationOrder($request);
        if (!$order instanceof Order) {
            return $this->getOwner()->httpError(404, 'Order could not be found');
        }

        if ($order->IsPaid()) {
            // "You can't be charged twice" — a stale pay-link or back-button landing on a paid order.
            $state = 'paid';
        } else {
            $statusParam = (string) $request->getVar('status');
            $allowed = [
                OrderConfirmationExtension::STATE_FAILED,
                OrderConfirmationExtension::STATE_CANCELLED,
                'error',
            ];
            $state = in_array($statusParam, $allowed, true) ? $statusParam : OrderConfirmationExtension::STATE_FAILED;
        }

        return [
            'Order' => $order,
            'ConfirmationState' => $state,
            'IsFailed' => $state === OrderConfirmationExtension::STATE_FAILED,
            'IsCancelled' => $state === OrderConfirmationExtension::STATE_CANCELLED,
            'IsError' => $state === 'error',
            'IsAlreadyPaid' => $state === 'paid',
            'PaymentErrorMessage' => $this->paymentErrorMessageFor($order),
            'ActionsForm' => $this->confirmationActionsForm($order),
            'Title' => $this->titleForState($state),
        ];
    }

    /**
     * Lightweight JSON status endpoint the pending page polls (or a meta-refresh falls back to) to learn when
     * an asynchronous payment has been confirmed.
     */
    public function status(HTTPRequest $request): HTTPResponse
    {
        $response = HTTPResponse::create();
        $response->addHeader('Content-Type', 'application/json');

        $order = $this->resolveConfirmationOrder($request);
        if (!$order instanceof Order) {
            $response->setStatusCode(404);
            $response->setBody((string) json_encode(['error' => 'not-found']));
            return $response;
        }

        $state = $order->getConfirmationState();
        $response->setBody((string) json_encode([
            'state' => $state,
            'paid' => (bool) $order->IsPaid(),
            'redirect' => $state === OrderConfirmationExtension::STATE_SUCCESS ? $order->ConfirmationLink() : null,
        ]));

        return $response;
    }

    /**
     * Resolve + authorise the order for the confirmation surfaces. Any one of: a valid URL token
     * (constant-time compared), session membership, the current cart, or the owning member.
     */
    protected function resolveConfirmationOrder(HTTPRequest $request): ?Order
    {
        $id = (int) $request->param('ID');
        if ($id === 0) {
            return null;
        }

        $order = Order::get()->byID($id);
        if (!$order instanceof Order) {
            return null;
        }

        // 1) Opaque URL token — works off-session and cross-device.
        $token = (string) $request->getVar('t');
        $orderToken = (string) $order->getField('GuestToken');
        if ($token !== '' && $orderToken !== '' && hash_equals($orderToken, $token)) {
            return $order;
        }

        // 2) Session-stored order ids (the just-placed guest in the same browser).
        $sessionIds = OrderManipulationExtension::get_session_order_ids();
        if (is_array($sessionIds) && in_array($order->ID, $sessionIds)) {
            return $order;
        }

        // 3) The current cart (order placed this request, not yet archived to the session history).
        $cart = ShoppingCart::curr();
        if ($cart instanceof Order && (int) $cart->ID === (int) $order->ID) {
            return $order;
        }

        // 4) The owning member.
        $member = Security::getCurrentUser();
        if ($member && (int) $member->ID !== 0 && (int) $order->getField('MemberID') === (int) $member->ID) {
            return $order;
        }

        return null;
    }

    /**
     * The decline reason from the order's latest errored payment. Reads from a *resolved* order rather than
     * only the current cart (so it works for a token-accessed / off-session order).
     *
     * Note: omnipay 6.1 consolidated the per-type message classes into one {@link PaymentMessage} keyed by a
     * `Type` field (every error type ends in "Error", e.g. PurchaseError / CompletePurchaseError). Core's own
     * PaymentErrorMessage() still does `instanceof GatewayErrorMessage`, which matches nothing on 6.1 —
     * filtering on the Type is the version-correct way and actually surfaces the reason.
     */
    protected function paymentErrorMessageFor(Order $order): string
    {
        $lastPayment = $order->Payments()->sort(['Created' => 'DESC'])->first();
        if (!$lastPayment instanceof Payment) {
            return '';
        }

        $errorMessage = $lastPayment->Messages()
            ->exclude('Message', '')
            ->filter(['Type:EndsWith' => 'Error'])
            ->sort(['Created' => 'DESC'])
            ->first();

        return $errorMessage ? (string) $errorMessage->Message : '';
    }

    /**
     * Retry / cancel form for the failed page, bound to the resolved order. Returns null when there is nothing
     * to do (e.g. no non-manual gateway available — core's {@link OrderActionsForm} strips manual gateways).
     */
    protected function confirmationActionsForm(Order $order): ?OrderActionsForm
    {
        $form = OrderActionsForm::create($this->getOwner(), 'ActionsForm', $order);
        if (!$form->Actions()->exists()) {
            return null;
        }

        return $form;
    }

    /**
     * Single-page checkout ($OrderForm): point the payment's failure/cancel URL at our failed page.
     */
    public function updateOrderForm(PaymentForm $paymentForm): void
    {
        $this->applyFailureLink($paymentForm);
    }

    /**
     * Onsite payment step (/checkout/payment): point the payment's failure/cancel URL at our failed page.
     */
    public function updatePaymentForm(PaymentForm $paymentForm): void
    {
        $this->applyFailureLink($paymentForm);
    }

    protected function applyFailureLink(PaymentForm $paymentForm): void
    {
        $order = ShoppingCart::curr();
        if (!$order instanceof Order || !$order->Items()->exists()) {
            return;
        }

        // FailedLink() guarantees a reference + token on the (possibly still-Cart) order, so a gateway
        // cancel/decline on return lands on our failed page with a reference to show.
        $paymentForm->setFailureLink($order->FailedLink());
    }

    /**
     * Route the payment response. Success / off-site redirect keeps core's behaviour; an immediate error goes
     * to our failed page (with reference + token) instead of dumping the customer back on /checkout.
     *
     * Hooked by {@link PaymentForm::submitpayment()} when the controller defines this method.
     */
    public function processPaymentResponse($serviceResponse, $form): HTTPResponse
    {
        if ($serviceResponse instanceof ServiceResponse && !$serviceResponse->isError()) {
            return $serviceResponse->redirectOrRespond();
        }

        $order = ShoppingCart::curr();
        if ($order instanceof Order) {
            return $this->getOwner()->redirect($order->FailedLink());
        }

        // Fallback to core's default error behaviour when there is no resolvable cart (e.g. place_before_payment).
        if ($form instanceof PaymentForm) {
            $form->sessionMessage($form->getOrderProcessor()->getError(), 'bad');
        }

        return $this->getOwner()->redirectBack();
    }

    protected function titleForState(string $state): string
    {
        return match ($state) {
            OrderConfirmationExtension::STATE_SUCCESS => _t(self::class . '.TitleSuccess', 'Thank you for your order'),
            OrderConfirmationExtension::STATE_PENDING => _t(self::class . '.TitlePending', "We're confirming your payment"),
            OrderConfirmationExtension::STATE_CANCELLED => _t(self::class . '.TitleCancelled', 'Payment cancelled'),
            'paid' => _t(self::class . '.TitlePaid', 'This order is already paid'),
            'error' => _t(self::class . '.TitleError', "We couldn't confirm your payment"),
            default => _t(self::class . '.TitleFailed', 'Your payment did not go through'),
        };
    }
}
