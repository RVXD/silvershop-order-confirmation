<%-- Functional, unstyled default for the failed / cancelled / error / already-paid payment page with retry.
     The storefront theme overrides this with the designed version. --%>
<div class="payment-failed payment-failed--{$ConfirmationState}">
    <% if $IsAlreadyPaid %>
        <h1><%t SilverShop\OrderConfirmation.AlreadyPaidHeading 'This order is already paid' %></h1>
        <p><%t SilverShop\OrderConfirmation.AlreadyPaidText 'Good news — this order has already been paid, so there is nothing more to do. You will not be charged again.' %></p>
        <p><a class="btn" href="$Order.ConfirmationLink"><%t SilverShop\OrderConfirmation.ViewConfirmation 'View your order confirmation' %></a></p>
    <% else_if $IsCancelled %>
        <h1><%t SilverShop\OrderConfirmation.CancelledHeading 'You cancelled the payment' %></h1>
        <p><%t SilverShop\OrderConfirmation.CancelledFailedText 'Nothing has been charged and your order is saved exactly as you left it. You can complete your payment below.' %></p>
    <% else_if $IsError %>
        <h1><%t SilverShop\OrderConfirmation.ErrorHeading "We couldn't confirm your payment" %></h1>
        <p><%t SilverShop\OrderConfirmation.ErrorText 'If your bank shows the payment as completed, please do not pay again — contact us with your order reference and we will check.' %></p>
    <% else %>
        <h1><%t SilverShop\OrderConfirmation.FailedHeading 'Your payment did not go through' %></h1>
        <p><%t SilverShop\OrderConfirmation.FailedPageText 'Nothing has been charged. You can try again or choose another payment method below.' %></p>
    <% end_if %>

    <p class="payment-failed__meta">
        <%t SilverShop\OrderConfirmation.OrderReference 'Order reference' %>: <strong>$Order.Reference</strong>
        &middot; <%t SilverShop\OrderConfirmation.Total 'Total' %>: $Order.Total.Nice
    </p>

    <% if $PaymentErrorMessage %>
        <div class="message warning payment-failed__reason">$PaymentErrorMessage</div>
    <% end_if %>

    <section class="payment-failed__summary">
        <h2><%t SilverShop\OrderConfirmation.YourOrder 'Your order' %></h2>
        <% with $Order %><% include SilverShop\Model\Order_Content %><% end_with %>
    </section>

    <% if not $IsAlreadyPaid %>
        <section class="payment-failed__retry">
            <% if $ActionsForm %>
                <p class="payment-failed__no-double-charge"><%t SilverShop\OrderConfirmation.NoDoubleCharge "We check for an existing successful payment first — you can't be charged twice." %></p>
                $ActionsForm
            <% else %>
                <p class="payment-failed__no-retry"><%t SilverShop\OrderConfirmation.NoRetryAvailable 'To complete this payment, please contact us with your order reference.' %></p>
            <% end_if %>
        </section>
    <% end_if %>

    <p class="payment-failed__help"><%t SilverShop\OrderConfirmation.NeedHelp 'Need a hand? Contact us with your order reference and we can see the payment attempts on our side.' %></p>
    <p class="payment-failed__continue"><a href="$BaseHref"><%t SilverShop\OrderConfirmation.BackToShop 'Back to the shop' %></a></p>
</div>
