<?php
namespace MagoArab\HideMassActions\Plugin;

use Magento\Framework\AuthorizationInterface;
use Magento\Ui\Component\MassAction;

class CustomStatusControlPlugin
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
     * Control visibility of custom mass actions based on permissions
     *
     * @param MassAction $subject
     * @param \Closure $proceed
     * @return void
     */
    public function aroundPrepare(MassAction $subject, \Closure $proceed)
    {
        $proceed();
        
        if ($subject->getContext()->getNamespace() !== 'sales_order_grid') {
            return;
        }

        $config = $subject->getConfig();
        
        if (isset($config['actions'])) {
            $actions = $config['actions'];
            
            // التحقق من الصلاحيات لكل إجراء على حدة
            $permissionMap = [
                'change_status_preparingb' => 'MagoArab_HideMassActions::status_preparingb_action',
                'change_status_preparinga' => 'MagoArab_HideMassActions::status_preparinga_action',
                'change_status_deliveredtodayc' => 'MagoArab_HideMassActions::status_deliveredtodayc_action'
            ];

            foreach ($actions as $key => $actionConfig) {
                if (isset($actionConfig['type'], $permissionMap[$actionConfig['type']]) && 
                    !$this->authorization->isAllowed($permissionMap[$actionConfig['type']])) {
                    unset($actions[$key]);
                }
            }
            
            $config['actions'] = array_values($actions);
            $subject->setConfig($config);
        }
    }
}