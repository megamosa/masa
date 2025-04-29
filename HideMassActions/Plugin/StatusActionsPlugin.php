<?php
namespace MagoArab\HideMassActions\Plugin;

use Magento\Framework\AuthorizationInterface;
use Magento\Ui\Component\MassAction;

class StatusActionsPlugin
{
    /**
     * @var AuthorizationInterface
     */
    private $authorization;

    /**
     * @var \MagoArab\HideMassActions\Helper\Data
     */
    private $helper;

    /**
     * @param AuthorizationInterface $authorization
     * @param \MagoArab\HideMassActions\Helper\Data $helper
     */
    public function __construct(
        AuthorizationInterface $authorization,
        \MagoArab\HideMassActions\Helper\Data $helper
    ) {
        $this->authorization = $authorization;
        $this->helper = $helper;
    }

    /**
     * Filter out unauthorized status actions
     *
     * @param MassAction $subject
     * @param array $result
     * @return array
     */
    public function afterPrepare(MassAction $subject, $result)
    {
        if ($subject->getContext()->getNamespace() !== 'sales_order_grid') {
            return $result;
        }
        
        $config = $subject->getData('config');
        
        if (isset($config['actions'])) {
            // Get all status permissions from helper
            $statusPermissionMap = $this->helper->getOrderStatusPermissionMap();
            
            // Process direct actions
            foreach ($config['actions'] as $key => $action) {
                // Check if this is a status change action
                if (isset($action['type']) && strpos($action['type'], 'change_status_') === 0) {
                    // Extract status from action type
                    $status = str_replace('change_status_', '', $action['type']);
                    
                    // Map to permission resource
                    $resource = isset($statusPermissionMap[$status]) ? 
                        $statusPermissionMap[$status] : 
                        'MagoArab_HideMassActions::status_other';
                    
                    // Check permission
                    if (!$this->authorization->isAllowed($resource)) {
                        unset($config['actions'][$key]);
                    }
                }
                
                // For mp_status dropdown (from Mageplaza)
                if (isset($action['type']) && $action['type'] === 'mp_status' && isset($action['actions'])) {
                    $statusActions = $action['actions'];
                    $filteredStatusActions = [];
                    
                    foreach ($statusActions as $statusAction) {
                        if (isset($statusAction['status'])) {
                            $status = $statusAction['status'];
                            $resource = isset($statusPermissionMap[$status]) ? 
                                $statusPermissionMap[$status] : 
                                'MagoArab_HideMassActions::status_other';
                            
                            if (!$this->authorization->isAllowed($resource)) {
                                continue;
                            }
                        }
                        
                        $filteredStatusActions[] = $statusAction;
                    }
                    
                    $config['actions'][$key]['actions'] = $filteredStatusActions;
                }
            }
            
            // Clean up array indexes
            $config['actions'] = array_values($config['actions']);
            $subject->setData('config', $config);
        }
        
        return $result;
    }
}