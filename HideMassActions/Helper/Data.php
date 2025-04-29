<?php
/**
 * @package   MagoArab_HideMassActions
 * @author    MagoArab
 * @copyright Copyright (c) 2025 MagoArab (https://www.magoarab.com)
 */

namespace MagoArab\HideMassActions\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\AuthorizationInterface;

class Data extends AbstractHelper
{
    /**
     * @var AuthorizationInterface
     */
    protected $authorization;

    /**
     * @param Context $context
     * @param AuthorizationInterface $authorization
     */
    public function __construct(
        Context $context,
        AuthorizationInterface $authorization
    ) {
        $this->authorization = $authorization;
        parent::__construct($context);
    }

    /**
     * Check if action is allowed
     *
     * @param string $action
     * @return bool
     */
    public function isActionAllowed($action)
    {
        $resourceMap = [
            // Mageplaza actions
            'mp_status' => 'MagoArab_HideMassActions::change_status',
            'mp_create_invoice' => 'MagoArab_HideMassActions::create_invoice',
            'mp_create_shipment' => 'MagoArab_HideMassActions::create_shipment',
            'mp_invoice_shipment' => 'MagoArab_HideMassActions::invoice_shipment',
            'mp_order_comment' => 'MagoArab_HideMassActions::order_comment',
            'mp_send_tracking_information' => 'MagoArab_HideMassActions::send_tracking',
            
            // Magento core actions
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

        if (isset($resourceMap[$action])) {
            return $this->authorization->isAllowed($resourceMap[$action]);
        }

        return true;
    }
    
    /**
     * Check if action is allowed by specific resource
     *
     * @param string $resource
     * @return bool
     */
    public function isActionAllowedByResource($resource)
    {
        return $this->authorization->isAllowed($resource);
    }

    /**
     * Get all available order statuses with their permission mapping
     *
     * @return array
     */
/**
 * Get all available order statuses with their permission mapping
 *
 * @return array
 */
public function getOrderStatusPermissionMap()
{
    // Core status permission map
    $statusMap = [
        'pending' => 'MagoArab_HideMassActions::status_pending',
        'pending_payment' => 'MagoArab_HideMassActions::status_pending',
        'processing' => 'MagoArab_HideMassActions::status_processing',
        'complete' => 'MagoArab_HideMassActions::status_complete',
        'closed' => 'MagoArab_HideMassActions::status_closed',
        'canceled' => 'MagoArab_HideMassActions::status_canceled',
        'holded' => 'MagoArab_HideMassActions::status_holded',
        'payment_review' => 'MagoArab_HideMassActions::status_payment_review',
        'fraud' => 'MagoArab_HideMassActions::status_fraud',
        
        // Custom statuses
        'preparingb' => 'MagoArab_HideMassActions::status_preparingb_action',
        'preparinga' => 'MagoArab_HideMassActions::status_preparinga_action',
        'deliveredtodayc' => 'MagoArab_HideMassActions::status_deliveredtodayc_action',
        
        // Default for unknown statuses
        '0' => 'MagoArab_HideMassActions::nonexistent_permission'
    ];
    
    return $statusMap;
}
    
    /**
     * Check if an order status is assigned to any state
     *
     * @param string $statusCode
     * @return bool
     */
    public function isStatusAssigned($statusCode)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $status = $objectManager->create(\Magento\Sales\Model\Order\Status::class)->load($statusCode);
        $assignedStates = $status->getStates();
        
        return !empty($assignedStates);
    }
	/**
 * Get direct action url for specific status
 *
 * @param string $statusCode
 * @return string
 */
public function getDirectActionUrl($statusCode)
{
    return 'magoarab_mass/order/massCustomStatus/action/' . $statusCode . '_action';
}
/**
 * Get URL for an admin path
 *
 * @param string $path
 * @param array $params
 * @return string
 */
public function getUrl($path, $params = [])
{
    return $this->_urlBuilder->getUrl($path, $params);
}
}