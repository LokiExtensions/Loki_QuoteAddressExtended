import {Field} from '@loki-checkout/checkout-objects';
import {setupCheckout} from '@loki/setup-checkout';
import {test, expect} from '@loki/test';
import moduleConfig, {FIELD_ID, themes, withTheme} from './config/config';
import {
    createInvoice,
    getAdminValueRow,
    getOrderId,
    loginAdmin,
    openAdminInvoice,
    openAdminOrder,
    placeOrder,
} from './helpers/order';

test.describe('Quote address extended order test', () => {
    for (const theme of themes) {
        test.describe(theme.name, () => {
            test.beforeEach(async ({page, context}) => {
                test.setTimeout(120000);
                await setupCheckout(page, context, withTheme(moduleConfig, theme));
            });

            test('should show the last entered value on the admin order view', async ({page}) => {
                await new Field(page, FIELD_ID).fill('Old value ' + theme.name);

                const value = 'New value ' + theme.name;
                const incrementId = await placeOrder(page, value);
                const orderId = await getOrderId(page, incrementId);

                page.setCatchErrors(false);
                await loginAdmin(page);
                await openAdminOrder(page, orderId, incrementId);

                const row = getAdminValueRow(page);
                await expect(row).toHaveCount(1);
                await expect(row.locator('td')).toHaveText(value);
            });

            test('should show the value on the admin invoice view', async ({page}) => {
                const value = 'Invoice value ' + theme.name;
                const incrementId = await placeOrder(page, value);
                const orderId = await getOrderId(page, incrementId);
                const invoiceId = await createInvoice(page, orderId);

                page.setCatchErrors(false);
                await loginAdmin(page);
                await openAdminInvoice(page, invoiceId);

                const row = getAdminValueRow(page);
                await expect(row).toHaveCount(1);
                await expect(row.locator('td')).toHaveText(value);
            });

            test('should not show a value on the admin order view of an order without value', async ({page}) => {
                const incrementId = await placeOrder(page, null);
                const orderId = await getOrderId(page, incrementId);

                page.setCatchErrors(false);
                await loginAdmin(page);
                await openAdminOrder(page, orderId, incrementId);

                await expect(getAdminValueRow(page)).toHaveCount(0);
            });
        });
    }
});
