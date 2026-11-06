import {
    test, expect, addToCart, openCheckout, fillCheckoutAddress, chooseShipping, choosePayment, placeOrderButton,
    expectReadyToOrder, grandTotal, idle, uniqueEmail,
} from '@liquidlab-agency/e2e-kit';

/* EuPlatesc (payment code eppay) in the Hyvä checkout: the method shows its notice, placing an order sends the
   shopper to the gateway with a signed request for that order's total, coming back from the gateway restores the
   cart, and the IPN endpoint turns away a request it can't verify. The gateway is never reached: the browser's
   request to euplatesc.ro is answered by the test itself. Found by the payment code, form field names and the
   endpoints' plain-text answers, never by label: the store's language varies. */
const CODE = 'eppay';
const GATEWAY = /^https:\/\/([a-z0-9-]+\.)*euplatesc\.ro\//;

/** An amount as shown, in any format: "82,97 RON", "1.234,56 lei", "$54.00". */
function amount(shown) {
    const digits = shown.replace(/\s/g, '').match(/\d[\d.,]*/)?.[0] ?? '';
    const last = Math.max(digits.lastIndexOf(','), digits.lastIndexOf('.'));
    if (last >= 0 && digits.length - last - 1 === 2) {
        return Number(`${digits.slice(0, last).replace(/[.,]/g, '')}.${digits.slice(last + 1)}`);
    }
    return Number(digits.replace(/[.,]/g, ''));
}

const cents = (value) => Math.round(Number(value) * 100);

/** Fills the cart with the profile's product, takes the checkout to payment and picks EuPlatesc. */
async function toEuPlatesc(page, store, email) {
    await addToCart(page, store.product);
    await openCheckout(page);
    await fillCheckoutAddress(page, store.address, email);
    await chooseShipping(page, store.shipping[0]);
    test.skip(await page.locator(`#payment-method-${CODE}`).count() === 0, 'EuPlatesc is not offered in this checkout');
    await choosePayment(page, CODE);
}

/** The order's status as the guest order view shows it, in the store's language. */
async function guestOrderStatus(page, store, order, email) {
    await page.goto('sales/guest/form/');
    const form = page.locator('form[action*="sales/guest/view"]');
    await form.locator('[name="oar_order_id"]').fill(order);
    await form.locator('[name="oar_billing_lastname"]').fill(store.address.lastname);
    if (await form.locator('[name="oar_type"]').count()) await form.locator('[name="oar_type"]').selectOption('email');
    await form.locator('[name="oar_email"]').fill(email);
    await Promise.all([page.waitForURL(/sales\/guest\/view/), form.locator('button[type="submit"]').first().click()]);
    return (await page.locator('.order-status, [class*="order-status"]').first().innerText()).trim();
}

test('EuPlatesc shows its notice in the checkout and can be ordered with', async ({ page, store }) => {
    await toEuPlatesc(page, store, uniqueEmail('euplatesc'));
    const method = page.locator(`#payment-method-view-${CODE}, [wire\\:id*="${CODE}"]`).first();
    await expect(method, 'the EuPlatesc notice').toBeVisible();
    expect((await method.innerText()).trim(), 'the EuPlatesc notice (configured or default text)').not.toBe('');
    await expectReadyToOrder(page);
});

test('an EuPlatesc order goes to the gateway signed for its total, and coming back restores the cart @orders', async ({ page, store }) => {
    const email = uniqueEmail('euplatesc-order');
    await toEuPlatesc(page, store, email);
    await idle(page, 1200);
    const shown = amount(await grandTotal(page));
    const origin = new URL(page.url()).origin;

    /* The gateway gets nothing: the test answers the browser's request itself. */
    let gateway = null;
    await page.route(GATEWAY, (route) => {
        gateway ??= new URL(route.request().url());
        return route.fulfill({ status: 200, contentType: 'text/html', body: '<!doctype html><title>e2e gateway stub</title>' });
    });
    await expectReadyToOrder(page);
    await placeOrderButton(page).click();
    await expect.poll(() => gateway, { message: 'the redirect to EuPlatesc', timeout: 60_000 }).not.toBeNull();

    const param = (name) => gateway.searchParams.get(name);
    expect(gateway.protocol, 'the gateway over HTTPS').toBe('https:');
    expect(param('invoice_id'), 'invoice_id (the order number)').toMatch(/^\d{6,}$/);
    expect(cents(param('amount')), `amount ${param('amount')} against the checkout total ${shown}`).toBe(cents(shown));
    expect(param('curr'), 'curr').toMatch(/^[A-Z]{3}$/);
    expect(param('merch_id'), 'merch_id').toMatch(/\S/);
    /* The HMAC-MD5 signature over amount..nonce, upper-case hex (the protocol's own scheme). */
    expect(param('fp_hash'), 'fp_hash').toMatch(/^[0-9A-F]{32}$/);
    expect(param('nonce'), 'nonce').toMatch(/^[0-9a-f]{32}$/);
    const stamp = param('timestamp');
    expect(stamp, 'timestamp (UTC, YmdHis)').toMatch(/^\d{14}$/);
    const sent = Date.UTC(+stamp.slice(0, 4), +stamp.slice(4, 6) - 1, +stamp.slice(6, 8), +stamp.slice(8, 10), +stamp.slice(10, 12), +stamp.slice(12));
    expect(Math.abs(Date.now() - sent), 'timestamp within 15 minutes of now').toBeLessThan(15 * 60_000);
    expect(param('fname'), 'fname').toBe(store.address.firstname);
    expect(param('lname'), 'lname').toBe(store.address.lastname);
    expect(param('email'), 'email').toBe(email);

    /* Where the gateway sends the shopper and its IPN: this store, at the module's routes. */
    const back = {
        successurl: /\/checkout\/onepage\/success\/?$/,
        failedurl: /\/euplatesc\/cancel\/?$/,
        backtosite: /\/euplatesc\/cancel\/?$/,
        silenturl: /\/euplatesc\/index\/?$/,
    };
    for (const [name, path] of Object.entries(back)) {
        const url = new URL(param(`ExtraData[${name}]`));
        expect(url.origin, name).toBe(origin);
        expect(url.pathname, name).toMatch(path);
    }

    /* The shopper comes back without paying: the cart is theirs again, in the checkout. */
    await page.goto(new URL(param('ExtraData[backtosite]')).href);
    await expect(page, 'back in the checkout').toHaveURL(/\/checkout\/?(#.*)?$/);
    await expect(page.locator(`#payment-method-${CODE}`), 'the checkout with the restored cart').toBeVisible({ timeout: 30_000 });
});

test('the IPN endpoint answers a request it can\'t verify without touching an order @orders', async ({ page, store }) => {
    const email = uniqueEmail('euplatesc-ipn');
    await toEuPlatesc(page, store, email);
    let gateway = null;
    await page.route(GATEWAY, (route) => {
        gateway ??= new URL(route.request().url());
        return route.fulfill({ status: 200, contentType: 'text/html', body: '<!doctype html><title>e2e gateway stub</title>' });
    });
    await placeOrderButton(page).click();
    await expect.poll(() => gateway, { message: 'the redirect to EuPlatesc', timeout: 60_000 }).not.toBeNull();
    const order = gateway.searchParams.get('invoice_id');
    const before = await guestOrderStatus(page, store, order, email);

    /* An approved payment for this order, each IPN form the module reads, with a signature that isn't EuPlatesc's. */
    const forged = {
        amount: gateway.searchParams.get('amount'), curr: gateway.searchParams.get('curr'), invoice_id: order,
        ep_id: 'E2E', merch_id: gateway.searchParams.get('merch_id'), action: '0', message: 'Approved', approval: 'E2E',
        timestamp: gateway.searchParams.get('timestamp'), nonce: gateway.searchParams.get('nonce'), fp_hash: 'F'.repeat(32),
    };
    const ipn = new URL(gateway.searchParams.get('ExtraData[silenturl]')).href;
    for (const [form, extra] of [['plain', {}], ['sec_status', { sec_status: '8' }], ['cart_id', { cart_id: 'E2E', mid: forged.merch_id, sec_status: '8', type: 'E2E' }]]) {
        const response = await page.request.post(ipn, { form: { ...forged, ...extra } });
        expect(response.status(), `${form} IPN`).toBe(200);
        expect(response.headers()['content-type'], `${form} IPN content type`).toContain('text/plain');
        if (form !== 'cart_id') expect((await response.text()).trim(), `${form} IPN answer`).toBe('Invalid signature');
    }
    const empty = await page.request.post(ipn, { form: {} });
    expect(empty.status(), 'an empty IPN').toBe(200);
    expect((await empty.text()).trim(), 'an empty IPN answer').toBe('e0');

    expect(await guestOrderStatus(page, store, order, email), 'the order status after the forged IPNs').toBe(before);
});
