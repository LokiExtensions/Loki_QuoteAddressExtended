import coreConfig from '@loki-checkout/config';
import merge from '@loki/util/merge';

export type Theme = {
    name: string;
    theme: string;
    isHyva: boolean;
};

export const themes: Theme[] = [
    {name: 'Luma', theme: 'Magento/luma', isHyva: false},
    {name: 'Hyvä', theme: 'Hyva/default-csp', isHyva: true},
];

export const withTheme = (config: any, theme: Theme) => merge(config, {theme: theme.theme});

export const FIELD_ID = 'loki-checkout-customer-example-address-field-field';
export const PREVIEW_ID = 'loki-checkout-sidebar-preview-quote-details';
export const FIELD_LABEL = 'Example address field';

const config = merge(coreConfig, {
    modules: [
        'Loki_QuoteAddressExtended',
        'Loki_QuoteAddressExtendedExample',
    ],
    config: {
        'carriers/flatrate/active': 1,
        'payment/checkmo/active': 1,
        'admin/security/use_form_key': 0,
    }
});

export const multiStepConfig = merge(config, {
    config: {
        'loki_checkout/general/theme': 'default',
    }
});

export const customerConfig = merge(config, {
    customer: {
        login: true,
    }
});

export default config;
