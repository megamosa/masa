<?php
namespace MagoArab\HideMassActions\Plugin;

use Magento\Framework\AuthorizationInterface;
use Mageplaza\MassOrderActions\Model\Config\Source\Status as StatusSource;

class MageplazaStatusSourcePlugin
{
    /**
     * @var AuthorizationInterface
     */
    private $authorization;

    /**
     * @param AuthorizationInterface $authorization
     */
    public function __construct(
        AuthorizationInterface $authorization
    ) {
        $this->authorization = $authorization;
    }

    /**
     * Filter status options based on permissions
     *
     * @param StatusSource $subject
     * @param array $result
     * @return array
     */
    public function afterToOptionArray(StatusSource $subject, array $result)
    {
        $filteredOptions = [];
        
        foreach ($result as $option) {
            // Only process options with values
            if (!isset($option['value'])) {
                $filteredOptions[] = $option;
                continue;
            }
            
            $value = $option['value'];
            
            // Check permissions for all statuses
            $permissionMap = [
                // Custom statuses
                'preparingb' => 'MagoArab_HideMassActions::status_preparingb_action',
                'preparinga' => 'MagoArab_HideMassActions::status_preparinga_action',
                'deliveredtodayc' => 'MagoArab_HideMassActions::status_deliveredtodayc_action',
                
                // Standard Magento statuses
                'pending' => 'MagoArab_HideMassActions::status_pending',
                'processing' => 'MagoArab_HideMassActions::status_processing',
                'complete' => 'MagoArab_HideMassActions::status_complete',
                'closed' => 'MagoArab_HideMassActions::status_closed',
                'canceled' => 'MagoArab_HideMassActions::status_canceled',
                'holded' => 'MagoArab_HideMassActions::status_holded',
                'fraud' => 'MagoArab_HideMassActions::status_fraud',
                'payment_review' => 'MagoArab_HideMassActions::status_payment_review'
            ];
            
            if (isset($permissionMap[$value]) && 
                !$this->authorization->isAllowed($permissionMap[$value])) {
                continue;
            }
            
            $filteredOptions[] = $option;
        }
        
        return $filteredOptions;
    }
}