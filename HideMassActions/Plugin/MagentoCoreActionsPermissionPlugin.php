<?php
/**
 * @package   MagoArab_HideMassActions
 * @author    MagoArab
 * @copyright Copyright (c) 2025 MagoArab (https://www.magoarab.com)
 */

namespace MagoArab\HideMassActions\Plugin;

use Magento\Sales\Ui\Component\Control\MassAction;
use MagoArab\HideMassActions\Helper\Data as Helper;

class MagentoCoreActionsPermissionPlugin
{
    /**
     * @var Helper
     */
    private $helper;

    /**
     * @param Helper $helper
     */
    public function __construct(
        Helper $helper
    ) {
        $this->helper = $helper;
    }

    /**
     * Filter Magento core mass actions based on permissions
     *
     * @param MassAction $subject
     * @param array $result
     * @return array
     */
    public function afterPrepare(
        MassAction $subject,
        $result
    ) {
        $config = $subject->getData('config');
        
        if (isset($config['actions']) && is_array($config['actions'])) {
            $filteredActions = [];
            
            foreach ($config['actions'] as $actionId => $actionConfig) {
                // Check if action should be displayed based on our custom permissions
                if ($this->isActionAllowed($actionId)) {
                    $filteredActions[$actionId] = $actionConfig;
                }
            }
            
            // Update config with filtered actions
            $config['actions'] = $filteredActions;
            $subject->setData('config', $config);
        }
        
        return $result;
    }
    
    /**
     * Check if action is allowed based on custom ACL rules
     *
     * @param string $actionId
     * @return bool
     */
    private function isActionAllowed($actionId)
    {
        $actionMap = [
            'cancel' => 'MagoArab_HideMassActions::cancel',
            'hold_order' => 'MagoArab_HideMassActions::hold',
            'unhold_order' => 'MagoArab_HideMassActions::unhold',
            'print_shipping_label' => 'MagoArab_HideMassActions::print_shipping_labels',
            'print_invoice' => 'MagoArab_HideMassActions::print_invoices',
            'print_packing' => 'MagoArab_HideMassActions::print_packing_slips',
            'print_creditmemo' => 'MagoArab_HideMassActions::print_credit_memos',
            'pdfinvoices_order' => 'MagoArab_HideMassActions::print_invoices',
            'pdfshipments_order' => 'MagoArab_HideMassActions::print_packing_slips',
            'pdfcreditmemos_order' => 'MagoArab_HideMassActions::print_credit_memos',
            'pdfdocs_order' => 'MagoArab_HideMassActions::print_all'
        ];
        
        if (isset($actionMap[$actionId])) {
            return $this->helper->isActionAllowedByResource($actionMap[$actionId]);
        }
        
        // If we don't have a mapping for this action, allow it by default
        return true;
    }
}