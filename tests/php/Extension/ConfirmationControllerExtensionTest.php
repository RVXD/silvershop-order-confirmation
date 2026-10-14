<?php

declare(strict_types=1);

namespace SilverShop\OrderConfirmation\Tests\Extension;

use SilverShop\Model\Order;
use SilverShop\Page\CheckoutPage;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Omnipay\Model\Payment;

/**
 * Exercises the token-authorised confirmation access end-to-end through the lightweight JSON `status`
 * endpoint (which returns an HTTPResponse, so no theme/template is required in the clean room).
 */
class ConfirmationControllerExtensionTest extends FunctionalTest
{
    protected $usesDatabase = true;

    protected function setUp(): void
    {
        parent::setUp();

        $checkout = CheckoutPage::get()->filter('URLSegment', 'checkout')->first();
        if (!$checkout) {
            $checkout = CheckoutPage::create();
            $checkout->Title = 'Checkout';
            $checkout->URLSegment = 'checkout';
            $checkout->write();
        }
        $checkout->publishSingle();
    }

    public function testStatusResolvesWithValidToken(): void
    {
        $order = $this->makeOrder('Paid');

        $response = $this->get('checkout/status/' . $order->ID . '?t=' . $order->GuestToken);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertSame('success', $data['state']);
        $this->assertTrue($data['paid']);
    }

    public function testStatusRejectsInvalidToken(): void
    {
        $order = $this->makeOrder('Paid');

        $response = $this->get('checkout/status/' . $order->ID . '?t=not-the-token');

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testStatusReportsPending(): void
    {
        $order = $this->makeOrder('Unpaid');
        $payment = Payment::create();
        $payment->Status = 'PendingPurchase';
        $payment->write();
        $order->Payments()->add($payment);

        $response = $this->get('checkout/status/' . $order->ID . '?t=' . $order->GuestToken);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertSame('pending', $data['state']);
        $this->assertFalse($data['paid']);
    }

    private function makeOrder(string $status): Order
    {
        $order = Order::create();
        $order->Status = $status;
        $order->GuestToken = bin2hex(random_bytes(16));
        $order->write();

        return $order;
    }
}
