<?php

declare(strict_types=1);

namespace SilverShop\OrderConfirmation\Tests\Extension;

use SilverShop\Model\Order;
use SilverShop\Page\AccountPage;
use SilverShop\Page\CheckoutPage;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Omnipay\Model\Payment;
use SilverStripe\Security\Member;

class OrderConfirmationExtensionTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected function setUp(): void
    {
        parent::setUp();
        // Core's Order::Link() member branch calls AccountPage::find_link() (which user_errors without a page),
        // and the guest branch / our confirmation link use CheckoutPage — publish both so Link() resolves.
        $this->ensurePublishedPage(CheckoutPage::class, 'checkout', 'Checkout');
        $this->ensurePublishedPage(AccountPage::class, 'account', 'Account');
    }

    private function ensurePublishedPage(string $class, string $segment, string $title): void
    {
        $page = $class::get()->first();
        if (!$page) {
            $page = $class::create();
            $page->Title = $title;
            $page->URLSegment = $segment;
            $page->write();
        }
        $page->publishSingle();
    }

    public function testUpdateLinkUnifiesGuestAndMember(): void
    {
        $order = Order::create();
        $order->Status = 'Unpaid';
        $order->write();

        // Guest (logged out).
        $guestLink = $order->Link();

        // Same order, logged in as a member.
        $member = Member::create();
        $member->Email = 'member@example.com';
        $member->write();
        $this->logInAs($member);
        $memberLink = $order->Link();

        $this->assertSame(
            $guestLink,
            $memberLink,
            'Guest and member must land on the same unified confirmation URL'
        );
        $this->assertStringContainsString('complete/' . $order->ID, $guestLink);
        $this->assertStringContainsString('t=' . $order->GuestToken, $guestLink);
        $this->assertStringNotContainsString('account/order', $guestLink);
        $this->assertStringNotContainsString('checkout/order', $guestLink);
    }

    public function testGuestTokenGeneratedOnPlacementNotOnCart(): void
    {
        $cart = Order::create();
        $cart->Status = 'Cart';
        $cart->write();
        $this->assertEmpty($cart->GuestToken, 'A cart must not get a guest token');

        $placed = Order::create();
        $placed->Status = 'Unpaid';
        $placed->write();
        $this->assertNotEmpty($placed->GuestToken, 'A placed order must get a guest token');
        $this->assertSame(32, strlen((string) $placed->GuestToken));
    }

    public function testEnsureConfirmationAccessAddsTokenAndReferenceToCart(): void
    {
        $cart = Order::create();
        $cart->Status = 'Cart';
        $cart->write();

        $this->assertEmpty($cart->GuestToken);
        $this->assertEmpty($cart->getField('Reference'));

        // Payment initiation: a still-Cart order must come away with both a token and a reference, so the
        // failure page always has a reference to show.
        $cart->ensureConfirmationAccess();

        $this->assertNotEmpty($cart->GuestToken);
        $this->assertNotEmpty($cart->getField('Reference'));
    }

    public function testConfirmationStateSuccess(): void
    {
        $order = Order::create();
        $order->Status = 'Paid';
        $order->write();

        $this->assertSame('success', $order->getConfirmationState());
    }

    public function testConfirmationStatePending(): void
    {
        $order = Order::create();
        $order->Status = 'Unpaid';
        $order->write();

        $payment = Payment::create();
        $payment->Status = 'PendingPurchase';
        $payment->write();
        $order->Payments()->add($payment);

        $this->assertSame('pending', $order->getConfirmationState());
    }

    public function testConfirmationStateFailed(): void
    {
        $order = Order::create();
        $order->Status = 'Unpaid';
        $order->write();

        $this->assertSame('failed', $order->getConfirmationState());
    }
}
