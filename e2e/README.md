# hyva-payment-euplatesc end-to-end tests

Collected by a project's E2E run when the project installs this module (see `liquidlab-agency/e2e-kit` 1.2.0 or
later). The spec drives the Hyvä checkout as a guest with the store profile's `product`, `address` and first
`shipping` method, and picks EuPlatesc by its payment code, `eppay`. It never reaches EuPlatesc: the browser's request
to `euplatesc.ro` is answered by the test itself, so no payment is started. Endpoint answers are the module's own
plain-text strings; nothing is matched by label, so the spec runs on stores in any language.

`euplatesc.spec.mjs` (desktop, as a guest):
- EuPlatesc shows its notice (the configured text or the default one) and the order can be placed;
- `@orders`: placing the order sends the browser to the gateway with the order number as `invoice_id`, the checkout's
  grand total as `amount`, a currency, the merchant id, an upper-case 32-hex `fp_hash`, a fresh UTC `timestamp`, a
  32-hex `nonce` and the shopper's name and email. The success, failed, back-to-site and IPN (`silenturl`) URLs are on
  this store, at `checkout/onepage/success`, `euplatesc/cancel` and `euplatesc/index`. Going back to the store from
  the gateway lands in the checkout with the cart restored;
- `@orders`: the IPN endpoint (`euplatesc/index`) answers each IPN form the module reads (plain, `sec_status`,
  `cart_id`) with HTTP 200 and plain text when the signature isn't EuPlatesc's: `Invalid signature`, and `e0` for an
  empty request. The order's status, read through the guest order view, is the same afterwards.

Every test skips where the checkout doesn't offer EuPlatesc.

Not covered: the signature itself (it needs the merchant key, which the store doesn't expose), a paid order (it needs
EuPlatesc to sign the IPN), installments (`ExtraData[rate]`), and whether coming back cancels the unpaid order: that
happens only when the store's "New order status" is `pending_payment`, and the guest view shows the status label in the
store's language.
