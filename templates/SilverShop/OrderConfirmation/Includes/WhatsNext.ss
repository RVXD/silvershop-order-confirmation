<%-- Post-payment next steps. Included within CheckoutPage_complete, so $IsPending / $IsSuccess are in scope.
     Only the timeframes the shop can stand behind — no invented tracking or ETAs. --%>
<section class="whats-next">
    <h2><%t SilverShop\OrderConfirmation.WhatsNextHeading 'What happens next' %></h2>
    <ol class="whats-next__list">
        <% if $IsPending %>
            <li class="whats-next__step whats-next__step--confirm"><%t SilverShop\OrderConfirmation.StepConfirmPayment 'We confirm your payment — usually within a few minutes.' %></li>
            <li class="whats-next__step whats-next__step--email"><%t SilverShop\OrderConfirmation.StepEmailPending 'We e-mail your confirmation the moment the payment clears.' %></li>
        <% else %>
            <li class="whats-next__step whats-next__step--email"><%t SilverShop\OrderConfirmation.StepEmail 'We e-mail your order confirmation.' %></li>
        <% end_if %>
        <li class="whats-next__step whats-next__step--packed"><%t SilverShop\OrderConfirmation.StepPacked 'We prepare and pack your order (1 to 2 working days).' %></li>
        <li class="whats-next__step whats-next__step--shipped"><%t SilverShop\OrderConfirmation.StepShipped 'Your order is on its way, or ready for pick-up.' %></li>
    </ol>
</section>
