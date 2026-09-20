<?php
declare(strict_types=1);

/**
 * Plain-language consent-mode choices for the admin configuration.
 */
class BasicRum_Analytics_Model_System_Config_Source_ConsentMode
{
    /**
     * Preserve the existing stored values while explaining their consequences.
     *
     * @return array<int, array<string, string>>
     */
    public function toOptionArray(): array
    {
        $helper = Mage::helper('basicrum_analytics');

        return array(
            array(
                'value' => '0',
                'label' => $helper->__('Monitor without consent (immediate monitoring)'),
            ),
            array(
                'value' => '1',
                'label' => $helper->__('Require consent before monitoring (recommended)'),
            ),
        );
    }
}
