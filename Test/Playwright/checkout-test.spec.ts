import {Field, ShippingMethod} from '@loki-checkout/checkout-objects';
import {setupCheckout} from '@loki/setup-checkout';
import {test, expect} from '@loki/test';
import type {Page} from '@playwright/test';
import moduleConfig, {
    customerConfig,
    FIELD_ID,
    FIELD_LABEL,
    multiStepConfig,
    PREVIEW_ID,
    themes,
    withTheme,
} from './config/config';
import {fillAddress, placeOrder} from './helpers/order';

async function goToBillingStep(page: Page) {
    const forwardButton = page.locator('#loki-checkout-steps-shipping-forward-button');
    await expect(forwardButton).not.toHaveAttribute('aria-disabled', 'true');
    await forwardButton.click();
    await expect(page).toHaveURL(/#billing$/);
}

async function goToShippingStep(page: Page) {
    const backwardButton = page.locator('#loki-checkout-steps-billing-backward-button');
    await expect(backwardButton).toBeVisible();
    await backwardButton.click();
    await expect(page.locator('#loki-checkout-steps-shipping-forward-button')).toBeVisible();
}

test.describe('Quote address extended checkout test', () => {
    for (const theme of themes) {
        test.describe(theme.name, () => {
            test('should keep the value when switching between shipping and billing step', async ({page, context}) => {
                await setupCheckout(page, context, withTheme(multiStepConfig, theme));

                await fillAddress(page);
                await new ShippingMethod(page, 'flatrate_flatrate').select();

                const field = new Field(page, FIELD_ID);
                await field.fill('Step value');

                await goToBillingStep(page);
                await expect(page.getById(PREVIEW_ID)).toContainText('Step value');

                await page.reload();
                await expect(page).toHaveURL(/#billing$/);
                await expect(page.getById(PREVIEW_ID)).toContainText('Step value');

                await goToShippingStep(page);
                await field.expectValue('Step value');
            });

            test('should start a new checkout with an empty value after placing an order', async ({page, context}) => {
                test.setTimeout(120000);

                const config = withTheme(moduleConfig, theme);
                await setupCheckout(page, context, config);
                await placeOrder(page, 'First order value');

                await setupCheckout(page, context, config);

                const field = new Field(page, FIELD_ID);
                await expect(field.getLocator()).toBeVisible();
                await expect(field.getLocator()).toHaveValue('');
                await expect(page.getById(PREVIEW_ID)).not.toContainText(FIELD_LABEL);
            });

            test('should keep the value of a logged-in customer after reloading', async ({page, context}) => {
                await setupCheckout(page, context, withTheme(customerConfig, theme));
                await expect(page.locator('#loki-checkout-login')).toContainText('You are logged in as');

                const field = new Field(page, FIELD_ID);
                await field.fill('Customer value');

                await page.reload();
                await expect(page).toHaveTitle(/Checkout/);

                await field.expectValue('Customer value');
                await expect(page.getById(PREVIEW_ID)).toContainText('Customer value');
            });
        });
    }
});
