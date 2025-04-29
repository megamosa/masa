<?php
/**
 * @package   MagoArab_HideMassActions
 * @author    MagoArab
 * @copyright Copyright (c) 2025 MagoArab (https://www.magoarab.com)
 */

namespace MagoArab\HideMassActions\Plugin;

use Magento\Framework\AuthorizationInterface;
use Mageplaza\MassOrderActions\Model\Config\Source\System\Actions;
use Mageplaza\MassOrderActions\Plugin\Component\MassAction as MageplazaMassAction;

class MassOrderActionsPermissionPlugin
{
    /**
     * @var \MagoArab\HideMassActions\Helper\Data
     */
    private $helper;

    /**
     * @param \MagoArab\HideMassActions\Helper\Data $helper
     */
    public function __construct(
        \MagoArab\HideMassActions\Helper\Data $helper
    ) {
        $this->helper = $helper;
    }

    /**
     * Filter mass actions based on permissions
     *
     * @param MageplazaMassAction $subject
     * @param callable $proceed
     * @param \Magento\Ui\Component\MassAction $massAction
     * @return mixed
     */
    public function aroundAfterPrepare(
        MageplazaMassAction $subject,
        callable $proceed,
        $massAction
    ) {
        // Call the original method first
        $result = $proceed($massAction);
        
        // Get the current config
        $config = $massAction->getData('config');
        
        // If we have actions and we're on the sales order page
        if (isset($config['actions']) && $config['actions']) {
            $filteredActions = [];
            
            foreach ($config['actions'] as $action) {
                // Check permissions for each action type using helper
                $allowed = $this->helper->isActionAllowed($action['type']);
                
                // Only add actions the user has permission for
                if ($allowed) {
                    $filteredActions[] = $action;
                } elseif ($action['type'] === 'mp_status' && isset($action['actions'])) {
                    // For the mp_status type, we might need to remove the entire option with its dropdown
                    continue;
                }
            }
            
            // Update mass action config with filtered actions
            $config['actions'] = $filteredActions;
            $massAction->setData('config', $config);
        }
        
        return $result;
    }
}