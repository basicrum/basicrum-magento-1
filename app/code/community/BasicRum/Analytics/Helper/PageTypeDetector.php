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
        $handles = $this->getLayoutHandles();

        $pageTypeMap = [
            'cms_index_index' => 'Home',
            'cms_page' => 'CMS Page',
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
        ];

        foreach ($pageTypeMap as $handle => $pageType) {
            if (in_array($handle, $handles, true)) {
                return $pageType;
            }
        }

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
    private function getFallbackHandle(array $handles): null|string
    {
        $ignoredHandles = [
            'default',
            'print',
            'popup',
        ];

        foreach ($handles as $handle) {
            if (in_array($handle, $ignoredHandles, true)) {
                continue;
            }

            if (str_starts_with($handle, 'STORE_') || str_starts_with($handle, 'THEME_')) {
                continue;
            }

            return $handle;
        }

        return null;
    }
}
