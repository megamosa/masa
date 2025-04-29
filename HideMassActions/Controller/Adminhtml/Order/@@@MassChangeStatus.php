<?php
namespace MagoArab\HideMassActions\Controller\Adminhtml\Order;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Ui\Component\MassAction\Filter;

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
     * @param Context $context
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        Context $context,
        Filter $filter,
        CollectionFactory $collectionFactory
    ) {
        parent::__construct($context);
        $this->filter = $filter;
        $this->collectionFactory = $collectionFactory;
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

            foreach ($collection as $order) {
                try {
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
                    $order->save();
                    $orderUpdated++;
                } catch (\Exception $e) {
                    $orderErrors++;
                }
            }
            
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
            'deliveredtodayc' => 'complete',  // تم الشحن اليوم
            // يمكنك إضافة المزيد من التخطيطات هنا
        ];
        
        return isset($statusStateMap[$status]) ? $statusStateMap[$status] : null;
    }
}