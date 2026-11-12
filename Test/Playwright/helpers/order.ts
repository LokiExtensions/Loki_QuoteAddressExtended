import {Field, PaymentMethod, PlaceOrderButton, ShippingMethod, SuccessPage} from '@loki-checkout/checkout-objects';
import {test, expect} from '@loki/test';
import type {Page} from '@playwright/test';
import {FIELD_ID, FIELD_LABEL} from '../config/config';

export const addressFields = {
    'customer.email': 'quote-address-extended@example.com',
    'shipping.firstname': 'Jane',
    'shipping.lastname': 'Doe',
    'shipping.street0': 'Amalialaan',
    'shipping.street1': '126',
    'shipping.postcode': '1234 AB',
    'shipping.city': 'Baarn',
    'shipping.telephone': '0123456789',
};

export const getAdminCredentials = () => ({
    username: process.env.MAGENTO_ADMIN_USER || process.env.ADMIN_USER || 'admin@loki-checkout.com',
    password: process.env.MAGENTO_ADMIN_PASSWORD || process.env.ADMIN_PASSWORD || 'P@ssw0rd1234',
});

export async function fillAddress(page: Page) {
    for (const [fieldName, fieldValue] of Object.entries(addressFields)) {
        await new Field(page, fieldName).fill(fieldValue);
    }
}

export async function placeOrder(page: Page, value: string | null): Promise<string> {
    await fillAddress(page);

    if (value !== null) {
        await new Field(page, FIELD_ID).fill(value);
    }

    await new ShippingMethod(page, 'flatrate_flatrate').select();
    await new PaymentMethod(page, 'checkmo').select();
    await new PlaceOrderButton(page).click();

    const successPage = new SuccessPage(page);
    await successPage.expectToBeLoaded();

    const bodyText = await page.locator('body').innerText();
    const match = bodyText.match(/order (?:#|number)\s*(?:is)?:?\s*([A-Za-z0-9-]*\d{6,})/i);
    expect(match, 'Order number on success page').not.toBeNull();

    return match[1];
}

export async function loginAdmin(page: Page) {
    const {username, password} = getAdminCredentials();

    await page.goto('/admin');
    await page.locator('#username').fill(username);
    await page.locator('#login').fill(password);
    await page.locator('.action-login').click();
    await page.waitForLoadState('domcontentloaded');

    test.skip(/\/tfa\//.test(page.url()), 'Two-factor authentication is enabled for the Magento Admin');
    await expect(page.locator('.page-title')).toBeVisible();
}

async function getAdminToken(page: Page): Promise<string> {
    const response = await page.request.post('/rest/V1/integration/admin/token', {
        data: getAdminCredentials(),
    });
    expect(response.ok(), 'Admin token: ' + await response.text()).toBeTruthy();

    return await response.json();
}

export async function getOrderId(page: Page, incrementId: string): Promise<number> {
    const token = await getAdminToken(page);
    const response = await page.request.get('/rest/V1/orders', {
        headers: {Authorization: 'Bearer ' + token},
        params: {
            'searchCriteria[filterGroups][0][filters][0][field]': 'increment_id',
            'searchCriteria[filterGroups][0][filters][0][value]': incrementId,
        },
    });
    expect(response.ok(), 'Orders: ' + await response.text()).toBeTruthy();

    const orders = await response.json();
    expect(orders.items).toHaveLength(1);

    return orders.items[0].entity_id;
}

export async function createInvoice(page: Page, orderId: number): Promise<number> {
    const token = await getAdminToken(page);
    const response = await page.request.post('/rest/V1/order/' + orderId + '/invoice', {
        headers: {Authorization: 'Bearer ' + token},
        data: {capture: false, notify: false},
    });
    expect(response.ok(), 'Invoice: ' + await response.text()).toBeTruthy();

    const invoiceId = parseInt(await response.json(), 10);
    expect(invoiceId).toBeGreaterThan(0);

    return invoiceId;
}

async function postOrderAction(page: Page, orderId: number, action: string, data: object): Promise<number> {
    const token = await getAdminToken(page);
    const response = await page.request.post('/rest/V1/order/' + orderId + '/' + action, {
        headers: {Authorization: 'Bearer ' + token},
        data,
    });
    expect(response.ok(), action + ': ' + await response.text()).toBeTruthy();

    const entityId = parseInt(await response.json(), 10);
    expect(entityId).toBeGreaterThan(0);

    return entityId;
}

export async function createShipment(page: Page, orderId: number): Promise<number> {
    return await postOrderAction(page, orderId, 'ship', {notify: false});
}

export async function createCreditmemo(page: Page, orderId: number): Promise<number> {
    return await postOrderAction(page, orderId, 'refund', {notify: false});
}

export async function openAdminOrder(page: Page, orderId: number, incrementId: string) {
    await page.goto('/admin/sales/order/view/order_id/' + orderId + '/');
    await expect(page.locator('.page-title')).toContainText(incrementId);
}

export async function openAdminInvoice(page: Page, invoiceId: number) {
    await page.goto('/admin/sales/order_invoice/view/invoice_id/' + invoiceId + '/');
    await expect(page.locator('.page-title')).toBeVisible();
}

export async function openAdminShipment(page: Page, shipmentId: number) {
    await page.goto('/admin/admin/order_shipment/view/shipment_id/' + shipmentId + '/');
    await expect(page.locator('.page-title')).toBeVisible();
}

export async function openAdminCreditmemo(page: Page, creditmemoId: number) {
    await page.goto('/admin/sales/order_creditmemo/view/creditmemo_id/' + creditmemoId + '/');
    await expect(page.locator('.page-title')).toBeVisible();
}

export function getAdminValueRow(page: Page) {
    return page.locator('tr', {has: page.locator('th', {hasText: FIELD_LABEL})});
}
