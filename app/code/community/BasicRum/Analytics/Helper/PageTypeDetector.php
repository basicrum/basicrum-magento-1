<?php
declare(strict_types=1);

/**
 * BasicRum Analytics Page Type Detector Helper
 */
class BasicRum_Analytics_Helper_PageTypeDetector extends Mage_Core_Helper_Abstract
{
    /**
     * Get current page type based on layout handles
     *
     * @return string
     */
    public function getPageType(): string
    {
        $pageTypeMap = [
            'cms_index_noroute' => '404 Not Found',
            'cms_index_defaultnoroute' => '404 Not Found',
            'cms_index_index' => 'Home',
            'cms_page_view' => 'CMS Page',
            'catalog_category_view' => 'Category',
            'catalog_product_view' => 'Product',
            'catalogsearch_result_index' => 'Search',
            'catalogsearch_advanced_index' => 'Advanced Search',
            'checkout_cart_index' => 'Cart',
            'checkout_onepage_index' => 'Checkout',
            'checkout_onepage_success' => 'Checkout Success',
            'customer_account_login' => 'Login',
            'customer_account_create' => 'Register',
            'customer_account_index' => 'Account',
            'customer_account_logoutsuccess' => 'Logout Success',
            'contacts_index_index' => 'Contact',
            'sales_guest_form' => 'Orders and Returns',
            'customer_account_edit' => 'Customer Account Edit',
            'sales_order_view' => 'Order View',
            'sales_billing_agreement_index' => 'Billing Agreements',
            'sales_billing_agreement_view' => 'Billing Agreement View',
            'sales_guest_view' => 'Guest Order View',
            'customer_address_form' => 'Customer Address Edit',
            'customer_address_index' => 'Customer Address List',
            'wishlist_index_configure' => 'Wishlist Item Configure',
            'wishlist_index_index' => 'Wishlist Items List',
            'sales_order_history' => 'Order History',
            'customer_account_forgotpassword' => 'Forgot Password',
        ];

        $request = Mage::app()->getRequest();
        if ($request !== null) {
            $fullActionName = strtolower(
                $request->getRouteName() . '_' .
                $request->getControllerName() . '_' .
                $request->getActionName()
            );

            if (isset($pageTypeMap[$fullActionName])) {
                return $pageTypeMap[$fullActionName];
            }
        }

        $handles = $this->getLayoutHandles();
        $fallbackHandle = $this->getFallbackHandle($handles);
        if ($fallbackHandle !== null) {
            return 'unmapped_' . $fallbackHandle;
        }

        return 'unknown';
    }

    /**
     * @return string[]
     */
    private function getLayoutHandles(): array
    {
        $layout = Mage::app()->getLayout();
        if (!$layout) {
            return [];
        }

        $update = $layout->getUpdate();
        if (!$update) {
            return [];
        }

        $handles = $update->getHandles();
        return is_array($handles) ? $handles : [];
    }

    /**
     * @param string[] $handles
     * @return string|null
     */
    private function getFallbackHandle(array $handles)
    {
        $ignoredHandles = [
            'default' => true,
            'print' => true,
            'popup' => true,
        ];

        foreach ($handles as $handle) {
            if (isset($ignoredHandles[$handle])) {
                continue;
            }

            if (strncmp($handle, 'STORE_', 6) === 0 || strncmp($handle, 'THEME_', 6) === 0) {
                continue;
            }

            return $handle;
        }

        return null;
    }
}
