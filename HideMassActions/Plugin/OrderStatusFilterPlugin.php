<?php
/**
 * @package   MagoArab_HideMassActions
 * @author    MagoArab
 * @copyright Copyright (c) 2025 MagoArab (https://www.magoarab.com)
 */

namespace MagoArab\HideMassActions\Plugin;

use Magento\Framework\AuthorizationInterface;
use Mageplaza\MassOrderActions\Plugin\Component\MassAction as MageplazaMassAction;
use Magento\Sales\Model\ResourceModel\Order\Status\Collection;
use Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory;
use Magento\Sales\Model\Order\Status;

class OrderStatusFilterPlugin
{
    /**
     * @var AuthorizationInterface
     */
    private $authorization;

    /**
     * @var CollectionFactory
     */
    private $statusCollectionFactory;

    /**
     * @var \MagoArab\HideMassActions\Helper\Data
     */
    private $helper;

    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @param AuthorizationInterface $authorization
     * @param CollectionFactory $statusCollectionFactory
     * @param \MagoArab\HideMassActions\Helper\Data $helper
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     */
    public function __construct(
        AuthorizationInterface $authorization,
        CollectionFactory $statusCollectionFactory,
        \MagoArab\HideMassActions\Helper\Data $helper,
        \Magento\Framework\ObjectManagerInterface $objectManager
    ) {
        $this->authorization = $authorization;
        $this->statusCollectionFactory = $statusCollectionFactory;
        $this->helper = $helper;
        $this->objectManager = $objectManager;
    }

    /**
     * Replace original status collection with our filtered collection
     *
     * @param MageplazaMassAction $subject
     * @param \Closure $proceed
     * @param mixed ...$args
     * @return mixed
     */
    public function aroundAddMassActions(
        MageplazaMassAction $subject,
        \Closure $proceed,
        ...$args
    ) {
        // Create patched collection factory that will filter out "0" statuses
        $mockFactory = $this->createFilteredCollectionFactory();
        
        // Replace original collection factory using reflection
        $refClass = new \ReflectionClass($subject);
        $refProp = $refClass->getProperty('_orderStatusColFact');
        $refProp->setAccessible(true);
        $originalFactory = $refProp->getValue($subject);
        $refProp->setValue($subject, $mockFactory);
        
        try {
            // Call original method with patched factory
            $result = call_user_func_array($proceed, $args);
            
            // Restore original factory
            $refProp->setValue($subject, $originalFactory);
            
            return $result;
        } catch (\Exception $e) {
            // Restore factory in case of exception
            $refProp->setValue($subject, $originalFactory);
            throw $e;
        }
    }
    
    /**
     * Create a factory that returns filtered collection without "0" statuses
     * and respecting ACL permissions
     *
     * @return object
     */
    private function createFilteredCollectionFactory()
    {
        $helper = $this->helper;
        $authorizationInterface = $this->authorization;
        $statusCollectionFactory = $this->statusCollectionFactory;
        $objectManager = $this->objectManager;
        
        return new class($helper, $authorizationInterface, $statusCollectionFactory, $objectManager) {
            private $helper;
            private $authorization;
            private $statusCollectionFactory;
            private $objectManager;
            
            public function __construct($helper, $authorization, $statusCollectionFactory, $objectManager) {
                $this->helper = $helper;
                $this->authorization = $authorization;
                $this->statusCollectionFactory = $statusCollectionFactory;
                $this->objectManager = $objectManager;
            }
            
            public function create()
            {
                $collection = $this->statusCollectionFactory->create()->joinStates();
                
                // Create a filtered clone of the collection
                $filteredCollection = $this->statusCollectionFactory->create()->joinStates();
                $filteredCollection->clear();
                
                // Get state-to-status relationship to check assigned statuses
                $statusCollection = $this->objectManager->create(\Magento\Sales\Model\ResourceModel\Order\Status\Collection::class);
                $stateStatusData = [];
                
                foreach ($statusCollection as $status) {
                    $statusCode = $status->getStatus();
                    $assignedStates = $status->getStates();
                    
                    if (!empty($assignedStates)) {
                        $stateStatusData[$statusCode] = $assignedStates;
                    }
                }
                
                // Only add valid statuses to the filtered collection
                foreach ($collection as $status) {
                    $statusCode = $status->getStatus();
                    $statusLabel = $status->getLabel();
                    
                    // Skip statuses with "0" as label or code
                    if ($statusLabel === '0' || $statusCode === '0') {
                        continue;
                    }
                    
                    // Skip statuses that aren't assigned to any state (these appear as "0")
                    if (!isset($stateStatusData[$statusCode])) {
                        continue;
                    }
                    
                    // Check if user has permission to use this status
                    if ($this->isStatusActionAllowed($statusCode)) {
                        $filteredCollection->addItem($status);
                    }
                }
                
                return $filteredCollection;
            }
            
            /**
             * Check if status is allowed based on ACL
             */
            private function isStatusActionAllowed($status)
            {
                $statusMap = $this->helper->getOrderStatusPermissionMap();
                $resource = isset($statusMap[$status]) ? $statusMap[$status] : 'MagoArab_HideMassActions::status_other';
                return $this->authorization->isAllowed($resource);
            }
        };
    }
}