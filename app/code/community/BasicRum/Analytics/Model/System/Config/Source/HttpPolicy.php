<?php
declare(strict_types=1);

/**
 * Plain-language HTTP policy choices for Beacon Endpoints.
 */
class BasicRum_Analytics_Model_System_Config_Source_HttpPolicy
{
    /**
     * @return array<int, array<string, string>>
     */
    public function toOptionArray(): array
    {
        $helper = Mage::helper('basicrum_analytics');

        return array(
            array(
                'value' => '0',
                'label' => $helper->__('Require HTTPS Beacon Endpoints (recommended)'),
            ),
            array(
                'value' => '1',
                'label' => $helper->__('Allow HTTP Beacon Endpoints for local testing'),
            ),
        );
    }
}
