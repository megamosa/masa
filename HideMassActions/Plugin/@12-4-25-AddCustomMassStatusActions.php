<?php
/**
 * @package   MagoArab_HideMassActions
 * @author    MagoArab
 * @copyright Copyright (c) 2025 MagoArab (https://www.magoarab.com)
 */

namespace MagoArab\HideMassActions\Plugin;

use Magento\Framework\AuthorizationInterface;
use Magento\Ui\Component\MassAction;
use MagoArab\HideMassActions\Helper\Data as Helper;

class AddCustomMassStatusActions
{
    /**
     * @var Helper
     */
    private $helper;

    /**
     * @var AuthorizationInterface
     */
    private $authorization;

    /**
     * @param Helper $helper
     * @param AuthorizationInterface $authorization
     */
    public function __construct(
        Helper $helper,
        AuthorizationInterface $authorization
    ) {
        $this->helper = $helper;
        $this->authorization = $authorization;
    }

    /**
     * Control visibility of custom mass actions based on permissions
     *
     * @param MassAction $subject
     * @param array $result
     * @return array
     */
    public function afterPrepare(MassAction $subject, $result)
    {
        // Check if we're on the sales order grid
        if ($subject->getContext()->getNamespace() !== 'sales_order_grid') {
            return $result;
        }
        
        $config = $subject->getData('config');
        
        if (isset($config['actions'])) {
            $permissionMap = [
                'status_preparingb' => 'MagoArab_HideMassActions::status_preparingb_action',
                'status_preparinga' => 'MagoArab_HideMassActions::status_preparinga_action',
                'status_deliveredtodayc' => 'MagoArab_HideMassActions::status_deliveredtodayc_action'
            ];

            foreach ($config['actions'] as $key => $action) {
                if (isset($action['type'], $permissionMap[$action['type']])) {
                    if (!$this->authorization->isAllowed($permissionMap[$action['type']])) {
                        unset($config['actions'][$key]);
                    }
                }
            }
            
            $subject->setData('config', $config);
        }

        return $result;
    }
}