<%-- Guest-only, success-only account-creation offer. v1 ships a link to the registration page (the storefront
     theme replaces this with the designed, prefilled one-field form). Never forced; the confirmation e-mail
     goes to the customer either way. No personal data is placed in the URL. --%>
<section class="account-offer">
    <h2><%t SilverShop\OrderConfirmation.AccountOfferHeading 'Create an account' %></h2>
    <p><%t SilverShop\OrderConfirmation.AccountOfferText 'Save your details to track this order and future ones. Your order confirmation still goes to your e-mail either way.' %></p>
    <ul class="account-offer__benefits">
        <li><%t SilverShop\OrderConfirmation.AccountBenefit1 'Track your orders' %></li>
        <li><%t SilverShop\OrderConfirmation.AccountBenefit2 'Faster checkout next time' %></li>
        <li><%t SilverShop\OrderConfirmation.AccountBenefit3 'Manage your saved addresses' %></li>
    </ul>
    <p><a class="btn account-offer__cta" href="{$BaseHref}register"><%t SilverShop\OrderConfirmation.CreateAccountCta 'Create an account' %></a></p>
</section>
