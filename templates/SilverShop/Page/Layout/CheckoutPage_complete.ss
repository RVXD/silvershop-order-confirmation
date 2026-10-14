<%-- Functional, unstyled default for the unified post-payment confirmation page. The storefront theme
     overrides this with the designed, status-aware version; everything here is data-driven from $Order so it
     renders correctly headless. State is one of success / pending / failed / cancelled. --%>
<div class="order-confirmation order-confirmation--{$ConfirmationState}">
    <% if $IsSuccess %>
        <h1><%t SilverShop\OrderConfirmation.ThankYouHeading 'Thank you for your order' %></h1>
        <p class="order-confirmation__lede"><%t SilverShop\OrderConfirmation.ThankYouLede 'Your order is confirmed. A confirmation has been e-mailed to {email}.' email=$Order.LatestEmail %></p>
    <% else_if $IsPending %>
        <h1><%t SilverShop\OrderConfirmation.PendingHeading "We're confirming your payment" %></h1>
        <p class="order-confirmation__lede"><%t SilverShop\OrderConfirmation.PendingLede "Your order is placed and we're waiting for your bank to confirm the payment. There is nothing you need to do — it is safe to close this page. We will e-mail {email} the moment it is confirmed." email=$Order.LatestEmail %></p>
    <% else_if $IsCancelled %>
        <h1><%t SilverShop\OrderConfirmation.CancelledHeading 'You cancelled the payment' %></h1>
        <p class="order-confirmation__lede"><%t SilverShop\OrderConfirmation.CancelledLede 'Nothing has been charged and your order is saved.' %></p>
    <% else %>
        <h1><%t SilverShop\OrderConfirmation.FailedHeading 'Your payment did not go through' %></h1>
        <p class="order-confirmation__lede"><%t SilverShop\OrderConfirmation.FailedLede 'Nothing has been charged. Your order is saved so you can try again.' %></p>
    <% end_if %>

    <p class="order-confirmation__meta">
        <%t SilverShop\OrderConfirmation.OrderReference 'Order reference' %>: <strong>$Order.Reference</strong>
        <% if $Order.Placed %>&middot; <%t SilverShop\OrderConfirmation.Placed 'Placed' %>: $Order.Placed.Nice<% end_if %>
        &middot; <%t SilverShop\OrderConfirmation.Total 'Total' %>: $Order.Total.Nice
        &middot; <%t SilverShop\OrderConfirmation.Status 'Status' %>:
        <span class="badge badge--status-$Order.DisplayStatus">$Order.StatusI18N</span>
    </p>

    <% if $IsPending %>
        <div class="order-confirmation__status" role="status" aria-live="polite"
             data-oc-poll="$Order.StatusLink"
             data-oc-success="$Order.ConfirmationLink"
             data-oc-failed="$Order.FailedLink"
             data-oc-timeout-text="<%t SilverShop\OrderConfirmation.PendingTimeout 'This is taking longer than expected. You can safely close this page — we will e-mail you as soon as your payment is confirmed.' %>">
            <p data-oc-status-text><%t SilverShop\OrderConfirmation.PendingStatus 'Waiting for confirmation. This page re-checks automatically.' %></p>
        </div>
        <%-- No-JS fallback: refresh the page periodically. JS clients use the capped poller below instead. --%>
        <noscript><meta http-equiv="refresh" content="15"></noscript>
        <script>
        (function () {
            var box = document.querySelector('[data-oc-poll]');
            if (!box || !window.fetch) { return; }
            var pollUrl = box.getAttribute('data-oc-poll');
            var successUrl = box.getAttribute('data-oc-success');
            var failedUrl = box.getAttribute('data-oc-failed');
            var textEl = box.querySelector('[data-oc-status-text]') || box;
            var timeoutText = box.getAttribute('data-oc-timeout-text');
            var tries = 0, max = 40, every = 6000; // cap at ~4 minutes, then stop and reassure
            function tick() {
                tries++;
                fetch(pollUrl, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(function (r) { return r.json(); })
                    .then(function (d) {
                        if (d && (d.paid || d.state === 'success')) { window.location = d.redirect || successUrl; return; }
                        if (d && (d.state === 'failed' || d.state === 'cancelled')) { window.location = failedUrl; return; }
                        if (tries >= max) { if (textEl) { textEl.textContent = timeoutText; } return; }
                        setTimeout(tick, every);
                    })
                    .catch(function () { if (tries < max) { setTimeout(tick, every); } });
            }
            setTimeout(tick, every);
        })();
        </script>
    <% end_if %>

    <% if $IsSuccess && $PurchaseComplete %>
        <div class="order-confirmation__cms">$PurchaseComplete</div>
    <% end_if %>

    <% if $IsFailed || $IsCancelled %>
        <% if $PaymentErrorMessage %><div class="message bad order-confirmation__reason">$PaymentErrorMessage</div><% end_if %>
        <p><a class="btn" href="$Order.FailedLink"><%t SilverShop\OrderConfirmation.TryAgain 'Try again or choose another method' %></a></p>
    <% end_if %>

    <section class="order-confirmation__summary">
        <h2><%t SilverShop\OrderConfirmation.YourOrder 'Your order' %></h2>
        <% with $Order %><% include SilverShop\Model\Order_Content %><% end_with %>
    </section>

    <% if $IsSuccess || $IsPending %>
        <section class="order-confirmation__delivery">
            <h2><%t SilverShop\OrderConfirmation.Delivery 'Delivery' %></h2>
            <% with $Order %><% include SilverShop\Model\Order_Address %><% end_with %>
        </section>
    <% end_if %>

    <section class="order-confirmation__payment">
        <h2><%t SilverShop\OrderConfirmation.Payment 'Payment' %></h2>
        <% with $Order %><% include SilverShop\Model\Order_Payments %><% end_with %>
    </section>

    <% if $IsSuccess %>
        <% if $Order.hasMethod('CustomerInvoices') %>
            <% loop $Order.CustomerInvoices %>
                <p class="order-confirmation__invoice"><a href="{$DocumentURL}/pdf"><%t SilverShop\OrderConfirmation.DownloadInvoice 'Download invoice (PDF)' %></a></p>
            <% end_loop %>
        <% end_if %>
        <% include SilverShop\OrderConfirmation\Includes\WhatsNext %>
    <% else_if $IsPending %>
        <% include SilverShop\OrderConfirmation\Includes\WhatsNext %>
    <% end_if %>

    <% if $IsSuccess && not $CurrentMember %>
        <% include SilverShop\OrderConfirmation\Includes\CreateAccountOffer %>
    <% end_if %>

    <p class="order-confirmation__continue"><a href="$BaseHref"><%t SilverShop\OrderConfirmation.ContinueShopping 'Continue shopping' %></a></p>
</div>
