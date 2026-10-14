<%-- Guest account-creation upsell slot — DEFERRED.

     The register-from-order flow (a prefilled one-field form, seeded from the order) will be owned by the
     planned silvershop/auth module, so we do NOT ship a second registration flow here: no /register link and
     no live CTA in v1. The slot is kept present but hidden so the thank-you page shows no create-account
     prompt. Guests still reach their order via the GuestToken link — only the upsell is deferred.

     TODO(silvershop/auth): replace this placeholder with the prefilled register-from-order form, seeded with
     $Order.Name and $Order.LatestEmail, once that module exists. The storefront theme's styled override of this
     include will do the same. --%>
<div class="account-offer" data-account-offer-placeholder hidden aria-hidden="true"></div>
