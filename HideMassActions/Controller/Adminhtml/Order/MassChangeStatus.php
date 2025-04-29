<?php
namespace MagoArab\HideMassActions\Controller\Adminhtml\Order;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Ui\Component\MassAction\Filter;
use Magento\Sales\Model\OrderRepository;
use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;
use Magento\CatalogInventory\Api\StockManagementInterface;
use Magento\Sales\Api\OrderManagementInterface;

class MassChangeStatus extends Action
{
    /**
     * @var Filter
     */
    protected $filter;

    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * @var OrderRepository
     */
    protected $orderRepository;

    /**
     * @var LoggerInterface
     */
    protected $logger;
    
    /**
     * @var StockManagementInterface
     */
    protected $stockManagement;
    
    /**
     * @var OrderManagementInterface
     */
    protected $orderManagement;

    /**
     * @param Context $context
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     * @param ResourceConnection $resourceConnection
     * @param OrderRepository $orderRepository
     * @param LoggerInterface $logger
     * @param StockManagementInterface $stockManagement
     * @param OrderManagementInterface $orderManagement
     */
    public function __construct(
        Context $context,
        Filter $filter,
        CollectionFactory $collectionFactory,
        ResourceConnection $resourceConnection = null,
        OrderRepository $orderRepository = null,
        LoggerInterface $logger = null,
        StockManagementInterface $stockManagement = null,
        OrderManagementInterface $orderManagement = null
    ) {
        parent::__construct($context);
        $this->filter = $filter;
        $this->collectionFactory = $collectionFactory;
        $this->resourceConnection = $resourceConnection ?: $context->getObjectManager()->get(ResourceConnection::class);
        $this->orderRepository = $orderRepository ?: $context->getObjectManager()->get(OrderRepository::class);
        $this->logger = $logger ?: $context->getObjectManager()->get(LoggerInterface::class);
        $this->stockManagement = $stockManagement ?: $context->getObjectManager()->get(StockManagementInterface::class);
        $this->orderManagement = $orderManagement ?: $context->getObjectManager()->get(OrderManagementInterface::class);
    }

    /**
     * Execute action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        try {
            // جمع الطلبات المختارة من واجهة المستخدم
            $collection = $this->filter->getCollection($this->collectionFactory->create());
            $collectionSize = $collection->getSize();
            
            // الحصول على حالة الطلب المطلوبة من المعلمات
            $status = $this->getRequest()->getParam('status');
            
            if (!$status) {
                throw new LocalizedException(__('No status specified.'));
            }

            $orderUpdated = 0;
            $orderErrors = 0;
            $orderIds = [];

            // استخدام المجموعات لتقليل الحمل على الخادم
            $batchSize = 20;
            $currentBatch = 0;
            $batchedCollection = [];

            // تقسيم المجموعة إلى مجموعات أصغر
            foreach ($collection as $order) {
                $batchedCollection[$currentBatch][] = $order;
                $orderIds[] = $order->getId();
                
                if (count($batchedCollection[$currentBatch]) >= $batchSize) {
                    $currentBatch++;
                }
            }

            // معالجة كل مجموعة على حدة
            foreach ($batchedCollection as $batch) {
                foreach ($batch as $order) {
                    try {
                        $oldStatus = $order->getStatus();
                        $oldState = $order->getState();
                        
                        // تحديد state مناسب بناءً على الحالة
                        $state = $this->getOrderState($status);
                        
                        // تغيير state إذا كان موجوداً
                        if ($state) {
                            $order->setState($state);
                        }
                        
                        // تغيير الحالة
                        $order->setStatus($status);
                        $order->addCommentToStatusHistory(
                            __('Status updated via Mass Action'),
                            false
                        );
                        
                        // معالجة المخزون عند تغيير الحالة إلى "complete" أو "canceled"
                        if ($status == 'canceled' && $oldStatus != 'canceled') {
                            // إرجاع المنتجات للمخزون عند الإلغاء
                            $this->handleCancellation($order);
                        } else if ($status == 'complete' && $oldStatus != 'complete') {
                            // التأكد من خصم المخزون عند الاكتمال إذا لم يكن قد تم بالفعل
                            $this->handleCompletion($order);
                        }
                        
                        $order->save();
                        $orderUpdated++;
                    } catch (\Exception $e) {
                        $this->logger->error('Error updating order #' . $order->getIncrementId() . ': ' . $e->getMessage());
                        $orderErrors++;
                    }
                }
            }
            
            // تحديث بيانات الجدول بطريقة ذكية
            $this->updateGridData($orderIds);
            
            if ($orderUpdated) {
                $this->messageManager->addSuccessMessage(
                    __('A total of %1 order(s) have been updated.', $orderUpdated)
                );
            }
            
            if ($orderErrors) {
                $this->messageManager->addErrorMessage(
                    __('A total of %1 order(s) cannot be updated.', $orderErrors)
                );
            }
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->logger->critical($e);
            $this->messageManager->addExceptionMessage(
                $e,
                __('Something went wrong while updating order status.')
            );
        }
        
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        return $resultRedirect->setPath('sales/order/index');
    }
    
    /**
     * معالجة إلغاء الطلب وإرجاع المنتجات للمخزون
     *
     * @param \Magento\Sales\Model\Order $order
     * @return void
     */
    private function handleCancellation($order)
    {
        try {
            // استخدام OrderManagement API لإلغاء الطلب بشكل صحيح يتضمن إرجاع المخزون
            if ($order->canCancel()) {
                $this->orderManagement->cancel($order->getId());
                $this->logger->info('Order #' . $order->getIncrementId() . ' was canceled and items returned to stock.');
            } else {
                // إذا لم يكن من الممكن إلغاء الطلب بالطريقة القياسية، نحاول إرجاع المخزون يدويًا
                $this->manuallyReturnItemsToStock($order);
            }
        } catch (\Exception $e) {
            $this->logger->error('Failed to cancel order #' . $order->getIncrementId() . ': ' . $e->getMessage());
            $this->manuallyReturnItemsToStock($order);
        }
    }
    
    /**
     * إرجاع المنتجات للمخزون يدويًا
     *
     * @param \Magento\Sales\Model\Order $order
     * @return void
     */
    private function manuallyReturnItemsToStock($order)
    {
        try {
            foreach ($order->getAllItems() as $item) {
                if ($item->getQtyOrdered() > $item->getQtyRefunded() + $item->getQtyCanceled()) {
                    $qtyToReturn = $item->getQtyOrdered() - $item->getQtyRefunded() - $item->getQtyCanceled();
                    
                    // استخدام StockManagement API لإرجاع الكمية للمخزون
                    $this->stockManagement->backItemQty(
                        $item->getProductId(),
                        $qtyToReturn,
                        $item->getStore()->getWebsiteId()
                    );
                    
                    // تحديث كمية الإلغاء في عنصر الطلب
                    $item->setQtyCanceled($item->getQtyCanceled() + $qtyToReturn);
                    $item->save();
                }
            }
            
            $this->logger->info('Manually returned items to stock for order #' . $order->getIncrementId());
        } catch (\Exception $e) {
            $this->logger->error('Failed to manually return items to stock for order #' . $order->getIncrementId() . ': ' . $e->getMessage());
        }
    }
    
    /**
     * معالجة اكتمال الطلب والتأكد من خصم المخزون
     *
     * @param \Magento\Sales\Model\Order $order
     * @return void
     */
    private function handleCompletion($order)
    {
        try {
            // التحقق مما إذا كان يجب إنشاء فاتورة وشحنة
            if ($order->canInvoice() || $order->canShip()) {
                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                
                // إنشاء فاتورة إذا لزم الأمر لضمان خصم المخزون
                if ($order->canInvoice()) {
                    $invoiceService = $objectManager->create('Magento\Sales\Model\Service\InvoiceService');
                    $invoice = $invoiceService->prepareInvoice($order);
                    $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE);
                    $invoice->register();
                    $invoice->save();
                    
                    // تحديث الطلب
                    $transactionSave = $objectManager->create('Magento\Framework\DB\Transaction')
                        ->addObject($invoice)
                        ->addObject($invoice->getOrder());
                    $transactionSave->save();
                    
                    $this->logger->info('Created invoice for order #' . $order->getIncrementId());
                }
                
                // إنشاء شحنة إذا لزم الأمر
                if ($order->canShip()) {
                    $shipmentFactory = $objectManager->create('Magento\Sales\Model\Order\ShipmentFactory');
                    $shipment = $shipmentFactory->create($order);
                    $shipment->register();
                    $shipment->save();
                    
                    // تحديث الطلب
                    $transactionSave = $objectManager->create('Magento\Framework\DB\Transaction')
                        ->addObject($shipment)
                        ->addObject($shipment->getOrder());
                    $transactionSave->save();
                    
                    $this->logger->info('Created shipment for order #' . $order->getIncrementId());
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('Failed to process completion for order #' . $order->getIncrementId() . ': ' . $e->getMessage());
        }
    }
    
    /**
     * تحديد state الطلب المناسب بناءً على الحالة
     *
     * @param string $status
     * @return string|null
     */
    private function getOrderState($status)
    {
        // خريطة الحالات إلى states
        $statusStateMap = [
            'preparingb' => 'processing',     // طباعة
            'preparinga' => 'processing',     // جاري الشحن
            'deliveredtodayc' => 'processing',  // تم الشحن اليوم
            'complete' => 'complete',  // مكتمل
            'pending' => 'pending',  // معلق
            'canceled' => 'canceled',  // الغاء
            // يمكنك إضافة المزيد من التخطيطات هنا
        ];
        
        return isset($statusStateMap[$status]) ? $statusStateMap[$status] : null;
    }

    /**
     * تحديث بيانات الجدول بطريقة ذكية لتجنب الحمل الزائد
     *
     * @param array $orderIds
     * @return void
     */
    private function updateGridData(array $orderIds)
    {
        if (empty($orderIds)) {
            return;
        }

        try {
            // الحصول على الاتصال بقاعدة البيانات
            $connection = $this->resourceConnection->getConnection();
            $salesOrderGridTable = $this->resourceConnection->getTableName('sales_order_grid');
            
            // للطلبات الكثيرة، نقوم بالتحديث على دفعات
            $batchSize = 20;
            $totalOrders = count($orderIds);
            $processedBatch = false;
            
            if ($totalOrders > $batchSize) {
                // معالجة الدفعة الأولى فقط
                $currentBatchIds = array_slice($orderIds, 0, $batchSize);
                $this->updateOrdersInGrid($connection, $salesOrderGridTable, $currentBatchIds);
                $processedBatch = true;
                
                // إضافة رسالة للمستخدم
                $this->messageManager->addNoticeMessage(
                    __('The first %1 orders have been updated in the grid. The remaining orders will be updated shortly.', $batchSize)
                );
            } else {
                // تحديث كل الطلبات فورياً
                $this->updateOrdersInGrid($connection, $salesOrderGridTable, $orderIds);
            }
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('Error updating grid data: ' . $e->getMessage());
            }
        }
    }
    
    /**
     * تحديث الطلبات في جدول Grid
     *
     * @param \Magento\Framework\DB\Adapter\AdapterInterface $connection
     * @param string $tableName
     * @param array $orderIds
     * @return void
     */
    private function updateOrdersInGrid($connection, $tableName, array $orderIds)
    {
        if (empty($orderIds) || !$connection) {
            return;
        }
        
        try {
            foreach ($orderIds as $orderId) {
                try {
                    // الحصول على الطلب
                    $order = $this->orderRepository->get($orderId);
                    
                    // تحديث بيانات الطلب في جدول Grid
                    $data = [
                        'status' => $order->getStatus(),
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                    
                    // تحديث الصف في جدول Grid
                    $connection->update(
                        $tableName,
                        $data,
                        ['entity_id = ?' => $orderId]
                    );
                } catch (\Exception $e) {
                    if ($this->logger) {
                        $this->logger->warning('Grid update error for order #' . $orderId . ': ' . $e->getMessage());
                    }
                }
            }
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('Batch grid update error: ' . $e->getMessage());
            }
        }
    }
}